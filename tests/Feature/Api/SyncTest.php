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

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_queue_is_applied_and_resending_is_safe(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Admin));

        $merchantUuid = (string) Str::uuid();
        $operations = [
            [
                'uuid' => $merchantUuid,
                'type' => 'merchant.create',
                'payload' => [
                    'taxpayer_name' => 'Comerciante sin conexión',
                    'business_type_id' => BusinessType::query()->value('id'),
                    'commerce_type' => 'public_road',
                    'meters' => 2,
                ],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'type' => 'payment.create',
                'payload' => [
                    'merchant_uuid' => $merchantUuid,
                    'payment_method' => 'cash',
                    'issued_at' => now()->subHour()->toDateTimeString(),
                    'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 5]],
                ],
            ],
        ];

        $this->postJson('/api/v1/sync', ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'created')
            ->assertJsonPath('data.0.folio', 'COM-000001')
            ->assertJsonPath('data.1.status', 'created')
            ->assertJsonPath('data.1.total', '200.00');

        // Reenvío del mismo lote (p. ej. se perdió la respuesta): no duplica nada.
        $this->postJson('/api/v1/sync', ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'duplicate')
            ->assertJsonPath('data.1.status', 'duplicate');

        $this->assertSame(1, Merchant::query()->count());
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame('android', Receipt::query()->value('source'));
    }

    public function test_rejected_operations_do_not_stop_the_batch(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));
        $merchant = Merchant::factory()->create();

        $this->postJson('/api/v1/sync', ['operations' => [
            [
                'uuid' => (string) Str::uuid(),
                'type' => 'payment.create',
                'payload' => ['merchant_id' => $merchant->id, 'payment_method' => 'cash', 'items' => [['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'type' => 'payment.create',
                'payload' => ['merchant_id' => $merchant->id, 'payment_method' => 'cash', 'items' => [['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'type' => 'payment.create',
                'payload' => ['merchant_id' => $merchant->id, 'payment_method' => 'bitcoin', 'items' => []],
            ],
        ]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'created')
            ->assertJsonPath('data.1.status', 'rejected')
            ->assertJsonPath('data.1.code', 'annuality_already_paid')
            ->assertJsonPath('data.2.status', 'rejected')
            ->assertJsonPath('data.2.code', 'validation_error');

        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_operations_require_uuid(): void
    {
        Sanctum::actingAs($this->userWithRole(Role::Collector));

        $this->postJson('/api/v1/sync', ['operations' => [['type' => 'payment.create', 'payload' => []]]])
            ->assertStatus(422)->assertJsonValidationErrors('operations.0.uuid');
    }
}
