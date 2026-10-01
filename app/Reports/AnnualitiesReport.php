<?php

namespace App\Reports;

use App\Enums\AnnualityStatus;
use App\Models\Annuality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AnnualitiesReport extends Report
{
    public function key(): string
    {
        return 'annualities';
    }

    public function title(): string
    {
        return 'Reporte de anualidades '.$this->year();
    }

    public function columns(): array
    {
        return [
            'merchant_folio' => 'Folio contribuyente',
            'merchant' => 'Contribuyente',
            'tianguis' => 'Tianguis',
            'route' => 'Ruta',
            'concept' => 'Concepto',
            'fiscal_year' => 'Ejercicio',
            'amount' => 'Importe',
            'status' => 'Estado',
            'receipt' => 'Recibo',
            'paid_at' => 'Fecha de pago',
        ];
    }

    private function year(): int
    {
        return (int) ($this->filter('fiscal_year') ?: now()->year);
    }

    /**
     * @return Builder<Annuality>
     */
    protected function baseQuery(): Builder
    {
        return Annuality::query()
            ->where('fiscal_year', $this->year())
            ->when($this->filter('status'), fn ($q, $v) => $q->where('annualities.status', $v))
            ->when($this->filter('tianguis_id'), fn ($q, $v) => $q->whereHas('merchant', fn ($m) => $m->where('tianguis_id', $v)))
            ->when($this->filter('route_id'), fn ($q, $v) => $q->whereHas('merchant', fn ($m) => $m->where('route_id', $v)));
    }

    /**
     * @return Builder<Annuality>
     */
    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['merchant:id,folio,taxpayer_name,tianguis_id,route_id', 'merchant.tianguis:id,name', 'merchant.route:id,name', 'concept:id,name', 'payment.receipt:id,folio'])
            ->orderBy('status')
            ->orderBy('merchant_id');
    }

    public function row(Model $model): array
    {
        /** @var Annuality $model */
        return [
            'merchant_folio' => $model->merchant->folio,
            'merchant' => $model->merchant->taxpayer_name,
            'tianguis' => $model->merchant->tianguis?->name,
            'route' => $model->merchant->route?->name,
            'concept' => $model->concept->name,
            'fiscal_year' => $model->fiscal_year,
            'amount' => self::money($model->amount),
            'status' => $model->status === AnnualityStatus::Paid ? 'PAGADA' : 'PENDIENTE',
            'receipt' => $model->payment?->receipt->folio,
            'paid_at' => $model->paid_at?->format('d/m/Y'),
        ];
    }

    public function summary(): array
    {
        $paid = (clone $this->baseQuery())->where('annualities.status', AnnualityStatus::Paid->value);
        $pending = (clone $this->baseQuery())->where('annualities.status', AnnualityStatus::Pending->value);

        return [
            'Pagadas' => (clone $paid)->count(),
            'Pendientes' => (clone $pending)->count(),
            'Total recaudado' => self::money((clone $paid)->sum('amount')),
            'Por recaudar' => self::money((clone $pending)->sum('amount')),
        ];
    }
}
