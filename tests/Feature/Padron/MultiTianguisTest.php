<?php

namespace Tests\Feature\Padron;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Tianguis;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiTianguisTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_operate_in_several_tianguis(): void
    {
        [$a, $b] = Tianguis::factory()->count(2)->create();

        $this->actingAs($this->userWithRole(Role::DataEntry))->post(route('merchants.store'), [
            'taxpayer_name' => 'Roberto Delgado Esparza',
            'business_type_id' => BusinessType::query()->value('id'),
            'commerce_type' => 'tianguis',
            'tianguis_ids' => [$a->id, $b->id],
            'meters' => 6,
        ])->assertSessionHasNoErrors();

        $merchant = Merchant::query()->firstOrFail();
        $this->assertSame($a->id, $merchant->tianguis_id, 'el primero es el principal');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $merchant->allTianguis->pluck('id')->all());

        // Aparece al filtrar por cualquiera de sus tianguis.
        $this->assertSame(1, Merchant::query()->inTianguis($b->id)->count());
    }

    public function test_tickets_are_charged_in_the_chosen_tianguis(): void
    {
        $merchant = Merchant::factory()->create();
        $second = Tianguis::factory()->create();
        $merchant->allTianguis()->attach($second->id);
        $collector = $this->userWithRole(Role::Collector);
        $this->openCashSession($collector);

        $receipt = app(PaymentService::class)->charge([
            'merchant_id' => $merchant->id,
            'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 2, 'tianguis_id' => $second->id]],
        ], $collector);

        $this->assertSame($second->id, $receipt->tianguis_id);
        $this->assertSame($second->id, $receipt->payments()->value('tianguis_id'));
    }

    public function test_tickets_can_not_be_charged_in_a_tianguis_not_assigned(): void
    {
        $merchant = Merchant::factory()->create();
        $other = Tianguis::factory()->create();
        $collector = $this->userWithRole(Role::Collector);
        $this->openCashSession($collector);

        $this->expectException(BusinessRuleException::class);

        app(PaymentService::class)->charge([
            'merchant_id' => $merchant->id,
            'payment_method' => 'cash',
            'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1, 'tianguis_id' => $other->id]],
        ], $collector);
    }

    public function test_api_exposes_all_tianguis_of_the_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $second = Tianguis::factory()->create(['name' => 'Tianguis Las Américas']);
        $merchant->allTianguis()->attach($second->id);

        Sanctum::actingAs($this->userWithRole(Role::Collector));

        $this->getJson("/api/v1/merchants/{$merchant->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.tianguis_list');
    }
}
