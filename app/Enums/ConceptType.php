<?php

namespace App\Enums;

enum ConceptType: string
{
    use HasLabels;

    case Annuality = 'annuality';
    case Ticket = 'ticket';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Annuality => 'Anualidad',
            self::Ticket => 'Boleto',
            self::Other => 'Otro',
        };
    }
}
