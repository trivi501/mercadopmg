<?php

namespace App\Enums;

/**
 * Permisos del sistema (Spatie). Los roles se arman en RolesAndPermissionsSeeder.
 */
enum Permission: string
{
    use HasLabels;

    case DashboardView = 'dashboard.view';
    case MerchantsView = 'merchants.view';
    case MerchantsCreate = 'merchants.create';
    case MerchantsUpdate = 'merchants.update';
    case MerchantsDelete = 'merchants.delete';
    case CatalogsManage = 'catalogs.manage';
    case PaymentsCreate = 'payments.create';
    case PaymentsView = 'payments.view';
    case ReceiptsView = 'receipts.view';
    case ReceiptsPrint = 'receipts.print';
    case ReceiptsReprint = 'receipts.reprint';
    case ReceiptsCancel = 'receipts.cancel';
    case ReprintsView = 'reprints.view';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Ver dashboard',
            self::MerchantsView => 'Consultar padrón',
            self::MerchantsCreate => 'Registrar comerciantes',
            self::MerchantsUpdate => 'Modificar comerciantes',
            self::MerchantsDelete => 'Dar de baja comerciantes',
            self::CatalogsManage => 'Administrar catálogos',
            self::PaymentsCreate => 'Registrar cobros',
            self::PaymentsView => 'Consultar pagos',
            self::ReceiptsView => 'Consultar recibos',
            self::ReceiptsPrint => 'Imprimir recibos',
            self::ReceiptsReprint => 'Reimprimir recibos',
            self::ReceiptsCancel => 'Cancelar recibos',
            self::ReprintsView => 'Consultar bitácora de reimpresiones',
            self::ReportsView => 'Consultar reportes',
            self::ReportsExport => 'Exportar reportes',
            self::UsersManage => 'Administrar usuarios',
            self::AuditView => 'Consultar auditoría',
            self::SettingsManage => 'Administrar configuración',
        };
    }

    /**
     * Permisos por rol. El administrador obtiene todos mediante Gate::before.
     *
     * @return array<string, list<self>>
     */
    public static function byRole(): array
    {
        return [
            Role::Admin->value => self::cases(),
            Role::Collector->value => [
                self::DashboardView, self::MerchantsView, self::PaymentsCreate, self::PaymentsView,
                self::ReceiptsView, self::ReceiptsPrint, self::ReceiptsReprint,
            ],
            Role::Inspector->value => [
                self::DashboardView, self::MerchantsView, self::PaymentsView, self::ReceiptsView,
            ],
            Role::DataEntry->value => [
                self::DashboardView, self::MerchantsView, self::MerchantsCreate, self::MerchantsUpdate,
            ],
        ];
    }
}
