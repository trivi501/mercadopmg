<?php

namespace App\Services;

use App\Enums\AnnualityStatus;
use App\Enums\ConceptType;
use App\Enums\PaymentStatus;
use App\Models\Concept;
use App\Models\Merchant;
use App\Models\Payment;

/**
 * Cuenta / estado de cuenta del contribuyente.
 */
class AccountStatementService
{
    public function __construct(private readonly AnnualityService $annualities) {}

    /**
     * @return array<string, mixed>
     */
    public function forMerchant(Merchant $merchant, ?int $fiscalYear = null): array
    {
        $fiscalYear ??= (int) now()->year;

        if ($merchant->status->value === 'active') {
            $this->annualities->ensureForMerchant($merchant, $fiscalYear);
        }

        $merchant->loadMissing(['businessType', 'tianguis', 'route']);

        $annualities = $merchant->annualities()
            ->with(['concept', 'payment.receipt:id,folio'])
            ->orderByDesc('fiscal_year')
            ->get();

        $yearPayments = Payment::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', PaymentStatus::Applied->value)
            ->whereYear('paid_at', $fiscalYear)
            ->with('concept')
            ->get();

        $tickets = $yearPayments->filter(fn (Payment $p) => $p->concept->type === ConceptType::Ticket);
        $others = $yearPayments->filter(fn (Payment $p) => $p->concept->type === ConceptType::Other);
        $sum = fn ($payments) => number_format((float) $payments->sum('total'), 2, '.', '');

        $pending = $annualities->where('status', AnnualityStatus::Pending)->where('fiscal_year', '<=', (int) now()->year);
        $currentAnnuality = $annualities->firstWhere('fiscal_year', $fiscalYear);

        return [
            'fiscal_year' => $fiscalYear,
            'merchant' => [
                'id' => $merchant->id,
                'folio' => $merchant->folio,
                'taxpayer_name' => $merchant->taxpayer_name,
                'trade_name' => $merchant->trade_name,
                'business_type' => $merchant->businessType->name,
                'business_description' => $merchant->business_description,
                'location' => $merchant->tianguis->name ?? $merchant->location,
                'tianguis' => $merchant->tianguis?->name,
                'route' => $merchant->route?->name,
                'meters' => $merchant->meters,
                'status' => $merchant->status->value,
            ],
            'annuality' => $currentAnnuality ? [
                'concept' => $currentAnnuality->concept->name,
                'amount' => $currentAnnuality->amount,
                'fiscal_year' => $currentAnnuality->fiscal_year,
                'status' => $currentAnnuality->status->value,
                'status_label' => $currentAnnuality->status === AnnualityStatus::Paid ? 'PAGADO' : 'PENDIENTE',
                'receipt_folio' => $currentAnnuality->payment?->receipt->folio,
                'paid_at' => $currentAnnuality->paid_at?->toIso8601String(),
            ] : null,
            'annualities' => $annualities->map(fn ($a) => [
                'id' => $a->id,
                'concept' => $a->concept->name,
                'fiscal_year' => $a->fiscal_year,
                'amount' => $a->amount,
                'status' => $a->status->value,
                'receipt_folio' => $a->payment?->receipt->folio,
                'paid_at' => $a->paid_at?->toIso8601String(),
            ])->values()->all(),
            'tickets' => [
                'quantity' => (int) $tickets->sum('quantity'),
                'unit_price' => $tickets->first()->unit_price ?? Concept::query()->ofType(ConceptType::Ticket)->value('amount'),
                'total' => $sum($tickets),
            ],
            'other_total' => $sum($others),
            'total_paid' => $sum($yearPayments),
            'debt' => [
                'count' => $pending->count(),
                'total' => number_format((float) $pending->sum('amount'), 2, '.', ''),
                'years' => $pending->pluck('fiscal_year')->values()->all(),
            ],
        ];
    }
}
