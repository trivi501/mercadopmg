<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    use HasLabels;

    case Issued = 'issued';
    case Cancelled = 'cancelled';
    case Reprinted = 'reprinted';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitido',
            self::Cancelled => 'Cancelado',
            self::Reprinted => 'Reimpreso',
        };
    }
}
