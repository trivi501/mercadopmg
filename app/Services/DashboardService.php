<?php

namespace App\Services;

use App\Enums\AnnualityStatus;
use App\Enums\CommerceType;
use App\Enums\ConceptType;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Models\Annuality;
use App\Models\BusinessType;
use App\Models\MarketRoute;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Models\Tianguis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del dashboard (web y Android).
 */
class DashboardService
{
    private const array MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * Indicadores resumidos.
     *
     * @return array<string, mixed>
     */
    public function indicators(?int $userId = null): array
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $year = (int) $today->year;

        $receipts = fn () => Receipt::query()->valid()->when($userId, fn ($q) => $q->where('user_id', $userId));
        $payments = fn () => Payment::query()->where('payments.status', PaymentStatus::Applied->value)
            ->when($userId, fn ($q) => $q->where('payments.user_id', $userId));
        $ofType = fn ($q, ConceptType $type) => $q->whereHas('concept', fn ($c) => $c->where('type', $type->value));

        $merchantCounts = Merchant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        // Adeudo = anualidades pendientes de ejercicios ya iniciados (no las del año próximo).
        $pendingAnnualities = Annuality::query()->where('status', AnnualityStatus::Pending->value)
            ->where('fiscal_year', '<=', $year)
            ->whereHas('merchant', fn ($q) => $q->where('status', MerchantStatus::Active->value));

        return [
            'merchants_total' => (int) $merchantCounts->sum(),
            'merchants_active' => (int) ($merchantCounts[MerchantStatus::Active->value] ?? 0),
            'merchants_inactive' => (int) $merchantCounts->except(MerchantStatus::Active->value)->sum(),
            'tianguistas' => Merchant::query()->where('commerce_type', CommerceType::Tianguis->value)->count(),
            'revenue_today' => $this->money($receipts()->whereDate('issued_at', $today)->sum('total')),
            'revenue_month' => $this->money($receipts()->where('issued_at', '>=', $monthStart)->sum('total')),
            'charges_today' => $receipts()->whereDate('issued_at', $today)->count(),
            'tickets_today' => (int) $ofType($payments(), ConceptType::Ticket)->whereDate('paid_at', $today)->sum('quantity'),
            'tickets_month' => (int) $ofType($payments(), ConceptType::Ticket)->where('paid_at', '>=', $monthStart)->sum('quantity'),
            'annualities_paid' => $ofType($payments(), ConceptType::Annuality)->where('fiscal_year', $year)->count(),
            'annualities_paid_today' => $ofType($payments(), ConceptType::Annuality)->whereDate('paid_at', $today)->count(),
            'annualities_amount' => $this->money($ofType($payments(), ConceptType::Annuality)->where('fiscal_year', $year)->sum('total')),
            'debts_count' => (clone $pendingAnnualities)->count(),
            'debts_amount' => $this->money((clone $pendingAnnualities)->sum('amount')),
            'receipts_month' => Receipt::query()->where('issued_at', '>=', $monthStart)->when($userId, fn ($q) => $q->where('user_id', $userId))->count(),
            'reprints_month' => ReceiptReprint::query()->where('created_at', '>=', $monthStart)->when($userId, fn ($q) => $q->where('user_id', $userId))->count(),
            'fiscal_year' => $year,
        ];
    }

    /**
     * Series para las gráficas.
     *
     * @return array<string, list<array{label: string, value: float|int}>>
     */
    public function charts(): array
    {
        $dayStart = today()->subDays(29);
        $monthStart = today()->startOfMonth()->subMonths(11);

        $byDay = Receipt::query()->valid()->where('issued_at', '>=', $dayStart)
            ->selectRaw('DATE(issued_at) as d, SUM(total) as total')->groupBy('d')->pluck('total', 'd');

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', issued_at)"
            : "DATE_FORMAT(issued_at, '%Y-%m')";
        $byMonth = Receipt::query()->valid()->where('issued_at', '>=', $monthStart)
            ->selectRaw("{$monthExpr} as m, SUM(total) as total")->groupBy('m')->pluck('total', 'm');

        $days = collect(range(0, 29))->map(function (int $i) use ($dayStart, $byDay) {
            $date = $dayStart->copy()->addDays($i);

            return ['label' => $date->format('d/m'), 'value' => (float) ($byDay[$date->toDateString()] ?? 0)];
        });

        $months = collect(range(0, 11))->map(function (int $i) use ($monthStart, $byMonth) {
            $date = $monthStart->copy()->addMonths($i);

            return ['label' => self::MONTHS[$date->month - 1].' '.$date->format('y'), 'value' => (float) ($byMonth[$date->format('Y-m')] ?? 0)];
        });

        return [
            'revenue_by_day' => array_values($days->all()),
            'revenue_by_month' => array_values($months->all()),
            'merchants_by_business_type' => $this->countSeries(BusinessType::query()),
            'merchants_by_tianguis' => $this->countSeries(Tianguis::query()),
            'merchants_by_route' => $this->countSeries(MarketRoute::query()),
        ];
    }

    /**
     * Comerciantes por catálogo (giro, tianguis o ruta), de mayor a menor.
     *
     * @param  Builder<BusinessType>|Builder<Tianguis>|Builder<MarketRoute>  $query
     * @return list<array{label: string, value: int}>
     */
    private function countSeries(Builder $query): array
    {
        return array_values($query->withCount('merchants')
            ->get()
            ->filter(fn ($row) => $row->merchants_count > 0)
            ->sortByDesc('merchants_count')
            ->map(fn ($row) => ['label' => $row->name, 'value' => (int) $row->merchants_count])
            ->all());
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
