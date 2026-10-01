<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_permissions(): void
    {
        $user = $this->userWithRole(Role::Collector, ['username' => 'recaudador1']);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'recaudador1',
            'password' => 'password',
            'device_name' => 'Terminal 01',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'collector')
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['permissions']]]);

        $this->assertContains('payments.create', $response->json('data.user.permissions'));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_rejects_bad_credentials_and_inactive_users(): void
    {
        $this->userWithRole(Role::Collector, ['username' => 'activo']);
        $this->userWithRole(Role::Collector, ['username' => 'inactivo', 'is_active' => false]);

        $this->postJson('/api/v1/login', ['username' => 'activo', 'password' => 'mal', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonPath('code', 'validation_error')->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/login', ['username' => 'inactivo', 'password' => 'password', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->userWithRole(Role::Collector, ['username' => 'limite']);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/login', ['username' => 'limite', 'password' => 'mal', 'device_name' => 'x']);
        }

        $this->postJson('/api/v1/login', ['username' => 'limite', 'password' => 'mal', 'device_name' => 'x'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->userWithRole(Role::Collector);
        $token = $user->createToken('tel')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deactivated_user_loses_api_access(): void
    {
        $user = $this->userWithRole(Role::Collector);
        $token = $user->createToken('tel')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'user_inactive');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unauthenticated_requests_get_standard_error(): void
    {
        $this->getJson('/api/v1/merchants')->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
    }

    public function test_me_and_catalogs(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Inspector));

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.role', 'inspector');
        $this->getJson('/api/v1/giros')->assertOk()->assertJsonCount(11, 'data');
        $this->getJson('/api/v1/tianguis')->assertOk()->assertJsonPath('data.0.name', 'Tianguis Municipal');
        $this->getJson('/api/v1/routes')->assertOk()->assertJsonPath('data.0.name', 'Ruta 01');
        $this->getJson('/api/v1/catalogs')->assertOk()
            ->assertJsonPath('data.concepts.0.code', 'BOL-001')
            ->assertJsonPath('data.concepts.0.amount', '40.00')
            ->assertJsonPath('data.concepts.1.amount', '155.00');
    }

    public function test_merchant_search_and_qr_lookup(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Inspector));
        $merchant = Merchant::factory()->create(['taxpayer_name' => 'Juan Pérez López']);
        Merchant::factory()->count(3)->create();

        $this->getJson('/api/v1/merchants?search=Juan%20P')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.folio', $merchant->folio);

        $this->getJson("/api/v1/merchants/folio/{$merchant->folio}")->assertOk()
            ->assertJsonPath('data.taxpayer_name', 'Juan Pérez López')
            ->assertJsonPath('data.qr', $merchant->folio);

        $this->getJson('/api/v1/merchants/folio/COM-999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_create_merchant_is_idempotent_by_uuid(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::DataEntry));
        $payload = [
            'uuid' => (string) Str::uuid(),
            'taxpayer_name' => 'María López',
            'business_type_id' => BusinessType::query()->value('id'),
            'commerce_type' => 'public_road',
            'meters' => 2,
            'latitude' => 19.43,
            'longitude' => -99.13,
        ];

        $this->postJson('/api/v1/merchants', $payload)->assertCreated()->assertJsonPath('data.folio', 'COM-000001');
        $this->postJson('/api/v1/merchants', $payload)->assertOk()->assertJsonPath('data.folio', 'COM-000001');

        $this->assertSame(1, Merchant::query()->count());
    }

    public function test_inspector_can_not_create_merchants_or_charge(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Inspector));
        $merchant = Merchant::factory()->create();

        $this->postJson('/api/v1/merchants', ['taxpayer_name' => 'X'])->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id, 'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_payment_returns_ticket_and_duplicate_uuid_does_not_charge_twice(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $merchant = Merchant::factory()->create();
        $payload = [
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'payment_method' => 'cash',
            'items' => [
                ['concept_id' => $this->ticketConcept()->id, 'quantity' => 5],
                ['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026],
            ],
        ];

        $this->postJson('/api/v1/payments', $payload, ['X-Device-Name' => 'Terminal 01'])
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.receipt.total', '355.00')
            ->assertJsonPath('data.receipt.source', 'android')
            ->assertJsonPath('data.ticket.header.title', 'RECIBO DE PAGO')
            ->assertJsonPath('data.ticket.merchant_folio', $merchant->folio)
            ->assertJsonPath('data.ticket.paper_width', 58)
            ->assertJsonStructure(['data' => ['ticket' => ['lines', 'qr_content', 'items']]]);

        $this->postJson('/api/v1/payments', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('data.receipt.folio', 'REC-00000001');

        $this->assertSame(1, Receipt::query()->count());

        $receiptId = Receipt::query()->value('id');
        $this->get("/api/v1/receipts/{$receiptId}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->getJson("/api/v1/receipts/{$receiptId}")->assertOk()->assertJsonPath('data.receipt.reprint_count', 0);
    }

    public function test_business_rule_errors_have_codes(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $merchant = Merchant::factory()->create();
        $charge = fn () => $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id, 'payment_method' => 'cash',
            'items' => [['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]],
        ]);

        $charge()->assertCreated();
        $charge()->assertStatus(422)->assertJsonPath('code', 'annuality_already_paid');
    }

    public function test_print_once_then_reprint_with_reason(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $merchant = Merchant::factory()->create();
        $receiptId = $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id, 'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 2]],
        ])->json('data.receipt.id');

        $this->postJson("/api/v1/receipts/{$receiptId}/print", ['paper_width' => 80])
            ->assertOk()->assertJsonPath('data.ticket.paper_width', 80);
        $this->postJson("/api/v1/receipts/{$receiptId}/print")
            ->assertStatus(422)->assertJsonPath('code', 'already_printed');

        $response = $this->postJson("/api/v1/receipts/{$receiptId}/reprint", ['reason' => 'Ticket dañado'])
            ->assertCreated()
            ->assertJsonPath('data.ticket.reprint.number', 1)
            ->assertJsonPath('data.receipt.status', 'reprinted');

        $texts = collect($response->json('data.ticket.lines'))->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'REIMPRESIÓN')));
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_account_payments_and_receipts_endpoints(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $merchant = Merchant::factory()->create();
        $this->postJson('/api/v1/payments', [
            'merchant_id' => $merchant->id, 'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 10], ['concept_id' => $this->annualityConcept()->id]],
        ])->assertCreated();

        $this->getJson("/api/v1/merchants/{$merchant->id}/account")->assertOk()
            ->assertJsonPath('data.annuality.status_label', 'PAGADO')
            ->assertJsonPath('data.tickets.total', '400.00')
            ->assertJsonPath('data.total_paid', '555.00');

        $this->getJson("/api/v1/merchants/{$merchant->id}/payments")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/merchants/{$merchant->id}/receipts")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/dashboard?mine=1')->assertOk()->assertJsonPath('data.tickets_today', 10);
    }

    public function test_collector_history_only_lists_own_receipts(): void
    {
        $merchant = Merchant::factory()->create();
        $other = $this->userWithRole(Role::Collector);
        $mine = $this->userWithRole(Role::Collector);
        $payload = ['merchant_id' => $merchant->id, 'payment_method' => 'cash', 'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]]];

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/payments', $payload)->assertCreated();
        Sanctum::actingAs($mine);
        $this->postJson('/api/v1/payments', $payload)->assertCreated();

        $this->getJson('/api/v1/receipts?mine=1&date_from='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.folio', 'REC-00000002');
    }

    public function test_reports_endpoint_requires_permission(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $this->getJson('/api/v1/reports?type=revenue')->assertForbidden();

        Sanctum::actingAs($this->userWithRole(Role::Admin));
        $this->getJson('/api/v1/reports?type=revenue')->assertOk()->assertJsonStructure(['title', 'columns', 'summary', 'data', 'meta']);
    }
}
