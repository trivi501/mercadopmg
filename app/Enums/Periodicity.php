<?php

namespace App\Enums;

enum Periodicity: string
{
    use HasLabels;

    case Unique = 'unique';
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Annual = 'annual';
    case PerEvent = 'per_event';

    public function label(): string
    {
        return match ($this) {
            self::Unique => 'Única',
            self::Daily => 'Diaria',
            self::Monthly => 'Mensual',
            self::Annual => 'Anual',
            self::PerEvent => 'Por evento',
        };
    }
}
