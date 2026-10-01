<?php

namespace App\Reports;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchantsReport extends Report
{
    public function key(): string
    {
        return 'merchants';
    }

    public function title(): string
    {
        return 'Reporte de comerciantes';
    }

    public function columns(): array
    {
        return [
            'folio' => 'Folio',
            'taxpayer_name' => 'Contribuyente',
            'trade_name' => 'Nombre comercial',
            'business_type' => 'Giro',
            'commerce_type' => 'Tipo',
            'tianguis' => 'Tianguis',
            'route' => 'Ruta',
            'meters' => 'Metros',
            'phone' => 'Teléfono',
            'location' => 'Ubicación',
            'status' => 'Estado',
            'registered_at' => 'Fecha alta',
        ];
    }

    /**
     * @return Builder<Merchant>
     */
    public function query(): Builder
    {
        return Merchant::query()
            ->with(['businessType:id,name', 'tianguis:id,name', 'route:id,name'])
            ->search($this->filter('search'))
            ->when($this->filter('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($this->filter('business_type_id'), fn ($q, $v) => $q->where('business_type_id', $v))
            ->when($this->filter('tianguis_id'), fn ($q, $v) => $q->where('tianguis_id', $v))
            ->when($this->filter('route_id'), fn ($q, $v) => $q->where('route_id', $v))
            ->when($this->filter('commerce_type'), fn ($q, $v) => $q->where('commerce_type', $v))
            ->orderBy('folio');
    }

    public function row(Model $model): array
    {
        /** @var Merchant $model */
        return [
            'folio' => $model->folio,
            'taxpayer_name' => $model->taxpayer_name,
            'trade_name' => $model->trade_name,
            'business_type' => $model->businessType->name,
            'commerce_type' => $model->commerce_type->label(),
            'tianguis' => $model->tianguis?->name,
            'route' => $model->route?->name,
            'meters' => $model->meters,
            'phone' => $model->phone,
            'location' => $model->location,
            'status' => $model->status->label(),
            'registered_at' => $model->registered_at->format('d/m/Y'),
        ];
    }

    public function summary(): array
    {
        return ['Total de comerciantes' => $this->query()->count()];
    }
}
