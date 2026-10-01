<?php

namespace Tests\Feature\Padron;

use App\Enums\AnnualityStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\Annuality;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $collector;

    private Receipt $receipt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = $this->userWithRole(Role::Collector);
        $this->receipt = app(PaymentService::class)->charge([
            'merchant_id' => Merchant::factory()->create()->id,
            'payment_method' => 'cash',
            'items' => [
                ['concept_id' => $this->ticketConcept()->id, 'quantity' => 5],
                ['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026],
            ],
        ], $this->collector);
    }

    public function test_original_print_is_allowed_once(): void
    {
        $this->actingAs($this->collector)->post(route('receipts.print', $this->receipt))->assertRedirect();
        $this->assertNotNull($this->receipt->refresh()->printed_at);

        $this->actingAs($this->collector)->post(route('receipts.print', $this->receipt))->assertSessionHasErrors('general');
    }

    public function test_reprint_is_recorded_without_new_charge(): void
    {
        $service = app(ReceiptService::class);

        $first = $service->reprint($this->receipt, $this->collector, 'Ticket dañado');
        $second = $service->reprint($this->receipt, $this->collector, 'Cliente lo extravió');

        $this->assertSame(1, $first->reprint_number);
        $this->assertSame(2, $second->reprint_number);

        $receipt = $this->receipt->refresh();
        $this->assertSame(2, $receipt->reprint_count);
        $this->assertSame(ReceiptStatus::Reprinted, $receipt->status);
        $this->assertSame('REC-00000001', $receipt->folio);
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(2, AuditLog::query()->where('action', 'reprint')->count());
    }

    public function test_reprint_requires_a_reason(): void
    {
        $this->actingAs($this->collector)
            ->post(route('receipts.reprint', $this->receipt), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, ReceiptReprint::query()->count());
    }

    public function test_reprinted_ticket_shows_the_legend(): void
    {
        $reprint = app(ReceiptService::class)->reprint($this->receipt, $this->collector, 'Ticket dañado');

        $this->actingAs($this->collector)
            ->get(route('receipts.thermal', [$this->receipt, 'reprint' => $reprint->id]))
            ->assertOk()
            ->assertSee('REIMPRESIÓN No. 1')
            ->assertSee('Ticket dañado')
            ->assertSee('REC-00000001');
    }

    public function test_reprint_records_can_not_be_modified_or_deleted(): void
    {
        $reprint = app(ReceiptService::class)->reprint($this->receipt, $this->collector, 'Ticket dañado');

        $this->expectException(LogicException::class);
        $reprint->delete();
    }

    public function test_reprint_records_can_not_be_updated(): void
    {
        $reprint = app(ReceiptService::class)->reprint($this->receipt, $this->collector, 'Ticket dañado');

        $this->expectException(LogicException::class);
        $reprint->update(['reason' => 'otro']);
    }

    public function test_inspector_can_not_reprint(): void
    {
        $this->actingAs($this->userWithRole(Role::Inspector))
            ->post(route('receipts.reprint', $this->receipt), ['reason' => 'Ticket dañado'])
            ->assertForbidden();
    }

    public function test_cancellation_keeps_the_receipt_and_releases_the_annuality(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->post(route('receipts.cancel', $this->receipt), ['reason' => 'Cobro capturado por error'])
            ->assertRedirect();

        $receipt = $this->receipt->refresh();
        $this->assertSame(ReceiptStatus::Cancelled, $receipt->status);
        $this->assertSame($admin->id, $receipt->cancelled_by);
        $this->assertSame('Cobro capturado por error', $receipt->cancellation_reason);
        $this->assertNotNull($receipt->cancelled_at);
        $this->assertSame(2, $receipt->payments()->where('status', PaymentStatus::Cancelled->value)->count());

        $annuality = Annuality::query()->where('fiscal_year', 2026)->firstOrFail();
        $this->assertSame(AnnualityStatus::Pending, $annuality->status);
        $this->assertNull($annuality->payment_id);
    }

    public function test_cancelled_folio_is_not_reused_and_annuality_can_be_charged_again(): void
    {
        app(ReceiptService::class)->cancel($this->receipt, $this->collector, 'Cobro capturado por error');

        $new = app(PaymentService::class)->charge([
            'merchant_id' => $this->receipt->merchant_id,
            'payment_method' => 'cash',
            'items' => [['concept_id' => $this->annualityConcept()->id, 'fiscal_year' => 2026]],
        ], $this->collector);

        $this->assertSame('REC-00000002', $new->folio);
        $this->assertSame(2, Receipt::query()->count());
    }

    public function test_cancelled_receipts_can_not_be_reprinted_or_cancelled_again(): void
    {
        $service = app(ReceiptService::class);
        $service->cancel($this->receipt, $this->collector, 'Cobro capturado por error');

        $this->expectException(BusinessRuleException::class);
        $service->reprint($this->receipt, $this->collector, 'Ticket dañado');
    }

    public function test_collector_can_not_cancel(): void
    {
        $this->actingAs($this->collector)
            ->post(route('receipts.cancel', $this->receipt), ['reason' => 'Cobro capturado por error'])
            ->assertForbidden();
    }

    public function test_receipts_can_never_be_deleted(): void
    {
        $this->expectException(LogicException::class);
        $this->receipt->delete();
    }

    public function test_pdf_and_history(): void
    {
        $this->actingAs($this->collector)->get(route('receipts.pdf', $this->receipt))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->collector)->get(route('receipts.index', ['status' => 'issued']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('receipts/index')->has('receipts.data', 1)->where('totals.amount', '355.00'));
    }

    public function test_public_verification_page(): void
    {
        $this->get(route('receipts.verify', $this->receipt->uuid))
            ->assertOk()->assertSee('REC-00000001')->assertSee('VÁLIDO')
            ->assertDontSee($this->receipt->merchant->taxpayer_name);
    }

    public function test_audit_logs_are_read_only(): void
    {
        $log = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['description' => 'alterado']);
    }
}
