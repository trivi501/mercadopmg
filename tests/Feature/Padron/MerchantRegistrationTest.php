<?php

namespace Tests\Feature\Padron;

use App\Enums\AnnualityStatus;
use App\Enums\Role;
use App\Models\Annuality;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Tianguis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'taxpayer_name' => 'Juan Pérez López',
            'business_type_id' => BusinessType::query()->where('name', 'Ropa')->value('id'),
            'business_description' => 'Venta de ropa',
            'tianguis_id' => Tianguis::query()->value('id'),
            'commerce_type' => 'tianguis',
            'meters' => 3,
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            ...$overrides,
        ];
    }

    public function test_data_entry_user_can_register_a_merchant_with_consecutive_folio(): void
    {
        $user = $this->userWithRole(Role::DataEntry);

        $this->actingAs($user)->post(route('merchants.store'), $this->payload())->assertRedirect();
        $this->actingAs($user)->post(route('merchants.store'), $this->payload(['taxpayer_name' => 'María López']))->assertRedirect();

        $this->assertSame(['COM-000001', 'COM-000002'], Merchant::query()->orderBy('id')->pluck('folio')->all());

        $merchant = Merchant::query()->where('folio', 'COM-000001')->firstOrFail();
        $this->assertSame('Juan Pérez López', $merchant->taxpayer_name);
        $this->assertSame($user->id, $merchant->created_by);
        $this->assertNotEmpty($merchant->uuid);
    }

    public function test_registration_creates_initial_measurement_and_current_annuality(): void
    {
        $this->actingAs($this->userWithRole(Role::DataEntry))->post(route('merchants.store'), $this->payload());

        $merchant = Merchant::query()->firstOrFail();

        $this->assertCount(1, $merchant->measurements);
        $this->assertSame('3.00', $merchant->measurements->first()->meters);
        $this->assertNull($merchant->measurements->first()->end_date);

        $annuality = $merchant->annualities()->firstOrFail();
        $this->assertSame((int) now()->year, $annuality->fiscal_year);
        $this->assertSame('155.00', $annuality->amount);
        $this->assertSame(AnnualityStatus::Pending, $annuality->status);
    }

    public function test_changing_meters_keeps_history(): void
    {
        $user = $this->userWithRole(Role::DataEntry);
        $this->actingAs($user)->post(route('merchants.store'), $this->payload());
        $merchant = Merchant::query()->firstOrFail();

        $this->actingAs($user)->put(route('merchants.update', $merchant), [
            'meters' => 5,
            'meters_reason' => 'Ampliación autorizada',
        ])->assertRedirect(route('merchants.show', $merchant));

        $history = $merchant->measurements()->reorder('id')->get();

        $this->assertCount(2, $history);
        $this->assertSame('3.00', $history[0]->meters);
        $this->assertNotNull($history[0]->end_date);
        $this->assertSame('5.00', $history[1]->meters);
        $this->assertNull($history[1]->end_date);
        $this->assertSame('Ampliación autorizada', $history[1]->reason);
        $this->assertSame('5.00', $merchant->refresh()->meters);
    }

    public function test_folio_can_not_be_changed_on_update(): void
    {
        $user = $this->userWithRole(Role::Admin);
        $this->actingAs($user)->post(route('merchants.store'), $this->payload());
        $merchant = Merchant::query()->firstOrFail();

        $this->actingAs($user)->put(route('merchants.update', $merchant), ['folio' => 'COM-999999', 'taxpayer_name' => 'Otro']);

        $this->assertSame('COM-000001', $merchant->refresh()->folio);
        $this->assertSame('Otro', $merchant->taxpayer_name);
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->userWithRole(Role::DataEntry))
            ->post(route('merchants.store'), $this->payload(['taxpayer_name' => '', 'meters' => -1, 'tianguis_id' => null]))
            ->assertSessionHasErrors(['taxpayer_name', 'meters', 'tianguis_id']);

        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_inspector_and_collector_can_not_register_merchants(): void
    {
        foreach ([Role::Inspector, Role::Collector] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post(route('merchants.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_registration_is_audited(): void
    {
        $user = $this->userWithRole(Role::DataEntry);
        $this->actingAs($user)->post(route('merchants.store'), $this->payload());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'create',
            'module' => 'Padrón',
            'auditable_id' => Merchant::query()->value('id'),
        ]);
    }

    public function test_nearby_search_returns_merchants_within_radius(): void
    {
        $near = Merchant::factory()->create(['latitude' => 19.4326, 'longitude' => -99.1332]);
        Merchant::factory()->create(['latitude' => 19.5000, 'longitude' => -99.2000]);

        $this->actingAs($this->userWithRole(Role::Inspector))
            ->get(route('merchants.map', ['lat' => 19.4330, 'lng' => -99.1330, 'radius' => 0.5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('merchants/map')
                ->has('merchants', 1)
                ->where('merchants.0.folio', $near->folio));
    }

    public function test_annual_obligations_command_is_idempotent(): void
    {
        Merchant::factory()->count(3)->create();
        Merchant::factory()->inactive()->create();

        $this->artisan('padron:anualidades', ['year' => 2027])->assertSuccessful();
        $this->artisan('padron:anualidades', ['year' => 2027])->assertSuccessful();

        $this->assertSame(3, Annuality::query()->where('fiscal_year', 2027)->count());
    }
}
