<?php

namespace App\Enums;

enum AnnualityStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagado',
        };
    }
}
