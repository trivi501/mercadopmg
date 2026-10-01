<?php

namespace App\Reports;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Recaudación por renglón de pago (concepto), excluyendo recibos cancelados.
 */
class RevenueReport extends Report
{
    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Reporte de recaudación';
    }

    public function columns(): array
    {
        return [
            'receipt' => 'Recibo',
            'date' => 'Fecha',
            'merchant_folio' => 'Folio contribuyente',
            'merchant' => 'Contribuyente',
            'concept' => 'Concepto',
            'quantity' => 'Cantidad',
            'unit_price' => 'Precio unitario',
            'total' => 'Importe',
            'payment_method' => 'Forma de pago',
            'tianguis' => 'Tianguis',
            'user' => 'Usuario',
        ];
    }

    /**
     * @return Builder<Payment>
     */
    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['receipt:id,folio,route_id', 'merchant:id,folio,taxpayer_name', 'concept:id,name,code,type', 'user:id,name', 'tianguis:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * @return Builder<Payment>
     */
    protected function baseQuery(): Builder
    {
        return Payment::query()
            ->where('payments.status', PaymentStatus::Applied->value)
            ->when($this->filter('date_from'), fn ($q, $v) => $q->where('paid_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($this->filter('date_to'), fn ($q, $v) => $q->where('paid_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($this->filter('user_id'), fn ($q, $v) => $q->where('payments.user_id', $v))
            ->when($this->filter('tianguis_id'), fn ($q, $v) => $q->where('payments.tianguis_id', $v))
            ->when($this->filter('route_id'), fn ($q, $v) => $q->whereHas('receipt', fn ($r) => $r->where('route_id', $v)))
            ->when($this->filter('concept_id'), fn ($q, $v) => $q->where('concept_id', $v));
    }

    public function row(Model $model): array
    {
        /** @var Payment $model */
        return [
            'receipt' => $model->receipt->folio,
            'date' => $model->paid_at->format('d/m/Y H:i'),
            'merchant_folio' => $model->merchant->folio,
            'merchant' => $model->merchant->taxpayer_name,
            'concept' => $model->concept->name.($model->fiscal_year ? " {$model->fiscal_year}" : ''),
            'quantity' => $model->quantity,
            'unit_price' => self::money($model->unit_price),
            'total' => self::money($model->total),
            'payment_method' => $model->payment_method->label(),
            'tianguis' => $model->tianguis?->name,
            'user' => $model->user->name,
        ];
    }

    public function summary(): array
    {
        $base = $this->baseQuery();

        return [
            'Movimientos' => (clone $base)->count(),
            'Recibos' => (clone $base)->distinct()->count('receipt_id'),
            'Total recaudado' => self::money((clone $base)->sum('total')),
        ];
    }
}
