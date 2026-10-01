<?php

namespace App\Reports;

use App\Enums\ConceptType;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class TicketsReport extends RevenueReport
{
    public function key(): string
    {
        return 'tickets';
    }

    public function title(): string
    {
        return 'Reporte de boletos';
    }

    /**
     * @return Builder<Payment>
     */
    protected function baseQuery(): Builder
    {
        return parent::baseQuery()->whereHas('concept', fn ($q) => $q->where('type', ConceptType::Ticket->value));
    }

    public function summary(): array
    {
        $base = $this->baseQuery();
        $quantity = (int) (clone $base)->sum('quantity');
        $total = (float) (clone $base)->sum('total');

        return [
            'Boletos vendidos' => $quantity,
            'Precio promedio' => self::money($quantity > 0 ? $total / $quantity : 0),
            'Total' => self::money($total),
        ];
    }
}
