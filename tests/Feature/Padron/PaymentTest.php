<?php

namespace Tests\Feature\Padron;

use App\Enums\AnnualityStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\Annuality;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
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

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function charge(array $items, array $extra = []): Receipt
    {
        return app(PaymentService::class)->charge([
            'merchant_id' => $this->merchant->id,
            'payment_method' => 'cash',
            'items' => $items,
            ...$extra,
        ], $this->collector);
    }

    public function test_five_tickets_cost_two_hundred(): void
    {
        $receipt = $this->charge([['concept_id' => $this->ticketConcept()->id, 'quantity' => 5]]);

        $this->assertSame('200.00', $receipt->total);
        $this->assertSame('REC-00000001', $receipt->folio);

        $payment = $receipt->payments()->firstOrFail();
        $this->assertSame(5, $payment->quantity);
        $this->assertSame('40.00', $payment->unit_price);
        $this->assertSame('200.00', $payment->total);
        $this->assertSame($this->collector->id, $payment->user_id);
    }

    public function test_annuality_costs_one_hundred_fifty_five_and_is_marked_paid(): void
    {
        $receipt = $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]);

        $this->assertSame('155.00', $receipt->total);

        $annuality = Annuality::query()->where(['merchant_id' => $this->merchant->id, 'fiscal_year' => 2026])->firstOrFail();
        $this->assertSame(AnnualityStatus::Paid, $annuality->status);
        $this->assertSame($receipt->payments()->value('id'), $annuality->payment_id);
    }

    public function test_tickets_plus_annuality_in_one_receipt(): void
    {
        $receipt = $this->charge([
            ['concept_id' => $this->ticketConcept()->id, 'quantity' => 5],
            ['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026],
        ]);

        $this->assertSame('355.00', $receipt->total);
        $this->assertCount(2, $receipt->items);
        $this->assertCount(2, $receipt->payments);
        $this->assertSame('355.00', $receipt->paymentDetails()->value('amount'));
    }

    public function test_annuality_can_not_be_charged_twice_for_the_same_year(): void
    {
        $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('ya fue pagada');

        try {
            $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]);
        } finally {
            $this->assertSame(1, Receipt::query()->count());
            $this->assertSame(1, Payment::query()->count());
        }
    }

    public function test_annuality_for_another_year_can_be_charged(): void
    {
        $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2025]]);
        $receipt = $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]);

        $this->assertSame('155.00', $receipt->total);
        $this->assertSame(2, Annuality::query()->where('status', 'paid')->count());
    }

    public function test_duplicated_annuality_inside_the_same_charge_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->charge([
            ['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026],
            ['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026],
        ]);
    }

    public function test_same_uuid_does_not_charge_twice(): void
    {
        $uuid = (string) Str::uuid();
        $items = [['concept_id' => $this->ticketConcept()->id, 'quantity' => 3]];

        $first = $this->charge($items, ['uuid' => $uuid]);
        $second = $this->charge($items, ['uuid' => $uuid]);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_inactive_merchants_can_not_be_charged(): void
    {
        $this->merchant->update(['status' => 'inactive']);

        $this->expectException(BusinessRuleException::class);
        $this->charge([['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]]);
    }

    public function test_receipt_keeps_the_price_even_if_the_concept_changes(): void
    {
        $receipt = $this->charge([['concept_id' => $this->ticketConcept()->id, 'quantity' => 2]]);
        $this->ticketConcept()->update(['amount' => '50.00']);

        $this->assertSame('40.00', $receipt->items()->value('unit_price'));
        $this->assertSame('80.00', $receipt->refresh()->total);
    }

    public function test_charge_through_the_web_form(): void
    {
        $this->actingAs($this->collector)
            ->post(route('charges.store'), [
                'merchant_id' => $this->merchant->id,
                'payment_method' => 'cash',
                'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 5]],
            ])
            ->assertRedirect(route('receipts.show', Receipt::query()->firstOrFail()));

        $this->assertDatabaseHas('receipts', ['total' => '200.00', 'source' => 'web', 'user_id' => $this->collector->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'charge', 'user_id' => $this->collector->id]);
    }

    public function test_web_charge_shows_business_rule_errors(): void
    {
        $this->charge([['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]]);

        $this->actingAs($this->collector)
            ->post(route('charges.store'), [
                'merchant_id' => $this->merchant->id,
                'payment_method' => 'cash',
                'items' => [['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]],
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_inspectors_and_data_entry_can_not_charge(): void
    {
        foreach ([Role::Inspector, Role::DataEntry] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post(route('charges.store'), [
                    'merchant_id' => $this->merchant->id,
                    'payment_method' => 'cash',
                    'items' => [['concept_id' => $this->ticketConcept()->id, 'quantity' => 1]],
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_account_statement(): void
    {
        $this->charge([['concept_id' => $this->ticketConcept()->id, 'quantity' => 10]]);
        $this->charge([['concept_id' => $this->annualityConcept()->id]]);

        $this->actingAs($this->collector)
            ->get(route('merchants.show', $this->merchant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('merchants/show')
                ->where('account.annuality.status_label', 'PAGADO')
                ->where('account.annuality.amount', '155.00')
                ->where('account.tickets.quantity', 10)
                ->where('account.tickets.total', '400.00')
                ->where('account.total_paid', '555.00')
                ->where('account.debt.count', 0));
    }
}
