<?php

namespace App\Enums;

enum AuditAction: string
{
    use HasLabels;

    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Charge = 'charge';
    case Cancel = 'cancel';
    case Reprint = 'reprint';
    case Print = 'print';
    case View = 'view';
    case Export = 'export';
    case Login = 'login';
    case Logout = 'logout';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Crear',
            self::Update => 'Editar',
            self::Delete => 'Eliminar',
            self::Charge => 'Cobrar',
            self::Cancel => 'Cancelar',
            self::Reprint => 'Reimprimir',
            self::Print => 'Imprimir',
            self::View => 'Consultar',
            self::Export => 'Exportar',
            self::Login => 'Inicio de sesión',
            self::Logout => 'Cierre de sesión',
        };
    }
}
