<?php

namespace Tests\Feature\Padron;

use App\Enums\CashSessionStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegisterSession;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $collector;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = $this->userWithRole(Role::Collector);
        $this->merchant = Merchant::factory()->create();
    }

    private function charge(int $tickets, string $method = 'cash', ?User $user = null): Receipt
    {
        return app(PaymentService::class)->charge([
            'merchant_id' => $this->merchant->id,
            'payment_method' => $method,
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => $tickets]],
        ], $user ?? $this->collector);
    }

    public function test_charging_requires_an_open_cash_session(): void
    {
        try {
            $this->charge(1);
            $this->fail('Se esperaba BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertSame('cash_session_required', $e->errorCode);
        }

        Sanctum::actingAs($this->collector);
        $this->postJson('/api/v1/payments', [
            'merchant_id' => $this->merchant->id, 'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('code', 'cash_session_required');

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_open_assigns_folio_and_only_one_can_be_open(): void
    {
        $session = $this->openCashSession($this->collector, '500');

        $this->assertSame('COR-000001', $session->folio);
        $this->assertSame('500.00', $session->opening_amount);
        $this->assertSame(CashSessionStatus::Open, $session->status);

        $this->expectException(BusinessRuleException::class);
        $this->openCashSession($this->collector);
    }

    public function test_reopening_with_the_same_uuid_returns_the_same_session(): void
    {
        Sanctum::actingAs($this->collector);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/cash-sessions', ['uuid' => $uuid, 'opening_amount' => 200])->assertCreated();
        $this->postJson('/api/v1/cash-sessions', ['uuid' => $uuid, 'opening_amount' => 200])->assertOk();

        $this->assertSame(1, CashRegisterSession::query()->count());
    }

    public function test_summary_groups_by_concept_and_method_and_ignores_cancelled_receipts(): void
    {
        $session = $this->openCashSession($this->collector, '500');
        $cash = $this->charge(3);
        $this->charge(1, 'transfer');
        $cancelled = $this->charge(2);
        app(ReceiptService::class)->cancel($cancelled, $this->userWithRole(Role::Admin), 'Error de captura');

        $this->assertSame($session->id, $cash->cash_register_session_id);

        $summary = app(CashRegisterService::class)->summary($session);

        $this->assertSame(2, $summary['receipts']);
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame('80.00', $summary['cancelled_amount']);
        $this->assertSame('160.00', $summary['total_collected']);
        $this->assertSame('120.00', $summary['cash_collected']);
        $this->assertSame('620.00', $summary['expected_cash']);
        $this->assertSame([['code' => 'BOL-001', 'name' => 'Boleto Tianguista', 'quantity' => 4, 'amount' => '160.00']], $summary['by_concept']);
        $this->assertSame(['cash', 'transfer'], array_column($summary['by_method'], 'method'));
    }

    public function test_close_records_the_count_and_freezes_the_totals(): void
    {
        $session = $this->openCashSession($this->collector, '500');
        $this->charge(3);

        $closed = app(CashRegisterService::class)->close($session, $this->collector, '600', 'Faltó cambio');

        $this->assertSame(CashSessionStatus::Closed, $closed->status);
        $this->assertSame('120.00', $closed->total_collected);
        $this->assertSame('620.00', $closed->expected_cash);
        $this->assertSame('600.00', $closed->counted_cash);
        $this->assertSame('-20.00', $closed->difference);

        // Un cobro posterior exige iniciar otro corte y no altera el cerrado.
        $this->expectException(BusinessRuleException::class);
        $this->charge(1);
    }

    public function test_closed_sessions_can_not_be_closed_again_modified_or_deleted(): void
    {
        $session = $this->openCashSession($this->collector);
        $service = app(CashRegisterService::class);
        $service->close($session, $this->collector, '0');

        try {
            $service->close($session, $this->collector, '10');
            $this->fail('Se esperaba BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertSame('cash_session_closed', $e->errorCode);
        }

        $fresh = $session->fresh();
        $this->assertNotNull($fresh);

        try {
            $fresh->update(['counted_cash' => '999']);
            $this->fail('Se esperaba LogicException');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $fresh->delete();
    }

    public function test_offline_charges_are_accepted_without_an_open_session(): void
    {
        Sanctum::actingAs($this->collector);

        $this->postJson('/api/v1/sync', ['operations' => [[
            'uuid' => (string) Str::uuid(),
            'type' => 'payment.create',
            'payload' => [
                'merchant_id' => $this->merchant->id, 'payment_method' => 'cash',
                'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]],
            ],
        ]]])->assertOk()->assertJsonPath('data.0.status', 'created');

        $this->assertNull(Receipt::query()->value('cash_register_session_id'));
    }

    public function test_collectors_only_see_their_own_sessions_and_admin_can_close_any(): void
    {
        $session = $this->openCashSession($this->collector);
        $other = $this->userWithRole(Role::Collector);

        $this->actingAs($other)->get(route('cash-sessions.show', $session))->assertForbidden();
        $this->actingAs($other)->post(route('cash-sessions.close', $session), ['counted_cash' => 0])->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Inspector))->get(route('cash-sessions.index'))->assertForbidden();

        $admin = $this->userWithRole(Role::Admin);
        $this->actingAs($admin)->post(route('cash-sessions.close', $session), ['counted_cash' => 0])
            ->assertRedirect(route('cash-sessions.show', $session));

        $this->assertSame($admin->id, $session->fresh()?->closed_by);
    }

    public function test_web_flow_open_charge_close_and_print(): void
    {
        $this->actingAs($this->collector)
            ->post(route('cash-sessions.store'), ['opening_amount' => '300'])
            ->assertRedirect();

        $session = CashRegisterSession::query()->firstOrFail();
        $this->charge(2);

        $this->actingAs($this->collector)->get(route('cash-sessions.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('cash-sessions/index')
                ->where('current.summary.total_collected', '80.00')
                ->where('current.summary.expected_cash', '380.00'));

        $this->actingAs($this->collector)->get(route('charges.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('cashSession.folio', 'COR-000001'));

        $this->actingAs($this->collector)
            ->post(route('cash-sessions.close', $session), ['counted_cash' => '390', 'closing_notes' => 'Sobró'])
            ->assertRedirect(route('cash-sessions.show', $session));

        $this->actingAs($this->collector)->get(route('cash-sessions.show', $session))->assertOk()
            ->assertInertia(fn ($page) => $page->component('cash-sessions/show')
                ->where('session.data.difference', '10.00')
                ->where('can.close', false)
                ->has('receipts.data', 1));

        $this->actingAs($this->collector)->get(route('cash-sessions.thermal', [$session, 'width' => 80]))
            ->assertOk()->assertSee('CORTE DE CAJA')->assertSee('SOBRANTE:');
        $this->actingAs($this->collector)->get(route('cash-sessions.pdf', $session))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_api_current_and_close_return_printable_lines(): void
    {
        Sanctum::actingAs($this->collector);

        $this->getJson('/api/v1/cash-sessions/current')->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.last_closed', null);

        $id = $this->postJson('/api/v1/cash-sessions', ['opening_amount' => 100])
            ->assertCreated()->json('data.session.id');
        $this->charge(1);

        $this->getJson('/api/v1/cash-sessions/current?paper_width=80')->assertOk()
            ->assertJsonPath('data.current.session.folio', 'COR-000001')
            ->assertJsonPath('data.current.summary.total_collected', '40.00');

        $response = $this->postJson("/api/v1/cash-sessions/{$id}/close", ['counted_cash' => 140])
            ->assertOk()
            ->assertJsonPath('data.session.status', 'closed')
            ->assertJsonPath('data.session.difference', '0.00');

        $lines = collect($response->json('data.lines'));
        $this->assertTrue($lines->every(fn ($l) => mb_strlen($l['text']) <= 32));
        $this->assertTrue($lines->pluck('text')->contains(fn ($t) => str_starts_with($t, 'TOTAL COBRADO:')));

        $this->getJson('/api/v1/cash-sessions/current')->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.last_closed.folio', 'COR-000001');
    }
}
