<?php

namespace App\Enums;

enum CommerceType: string
{
    use HasLabels;

    case Tianguis = 'tianguis';
    case Route = 'route';
    case Plaza = 'plaza';
    case PublicRoad = 'public_road';

    public function label(): string
    {
        return match ($this) {
            self::Tianguis => 'Tianguis',
            self::Route => 'Ruta',
            self::Plaza => 'Plaza',
            self::PublicRoad => 'Vía pública',
        };
    }
}
