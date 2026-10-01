<?php

namespace App\Enums;

enum Role: string
{
    use HasLabels;

    case Admin = 'admin';
    case Collector = 'collector';
    case Inspector = 'inspector';
    case DataEntry = 'data_entry';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Collector => 'Recaudador',
            self::Inspector => 'Inspector',
            self::DataEntry => 'Capturista',
        };
    }
}
