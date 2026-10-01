<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use HasLabels;

    case Applied = 'applied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Aplicado',
            self::Cancelled => 'Cancelado',
        };
    }
}
