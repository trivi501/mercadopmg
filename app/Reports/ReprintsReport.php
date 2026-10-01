<?php

namespace App\Reports;

use App\Models\ReceiptReprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ReprintsReport extends Report
{
    public function key(): string
    {
        return 'reprints';
    }

    public function title(): string
    {
        return 'Reporte de reimpresiones';
    }

    public function columns(): array
    {
        return [
            'receipt' => 'Recibo',
            'merchant' => 'Contribuyente',
            'number' => 'No. reimpresión',
            'date' => 'Fecha',
            'time' => 'Hora',
            'user' => 'Usuario',
            'reason' => 'Motivo',
        ];
    }

    /**
     * @return Builder<ReceiptReprint>
     */
    public function query(): Builder
    {
        return ReceiptReprint::query()
            ->with(['receipt:id,folio,merchant_id', 'receipt.merchant:id,folio,taxpayer_name', 'user:id,name'])
            ->when($this->filter('date_from'), fn ($q, $v) => $q->where('reprint_date', '>=', Carbon::parse($v)->toDateString()))
            ->when($this->filter('date_to'), fn ($q, $v) => $q->where('reprint_date', '<=', Carbon::parse($v)->toDateString()))
            ->when($this->filter('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($this->filter('search'), fn ($q, $v) => $q->whereHas('receipt', fn ($r) => $r->where('folio', 'like', "%{$v}%")))
            ->orderByDesc('id');
    }

    public function row(Model $model): array
    {
        /** @var ReceiptReprint $model */
        return [
            'receipt' => $model->receipt->folio,
            'merchant' => $model->receipt->merchant->folio.' '.$model->receipt->merchant->taxpayer_name,
            'number' => $model->reprint_number,
            'date' => $model->reprint_date->format('d/m/Y'),
            'time' => substr($model->reprint_time, 0, 5),
            'user' => $model->user->name,
            'reason' => $model->reason,
        ];
    }

    public function summary(): array
    {
        return ['Reimpresiones' => $this->query()->count()];
    }
}
