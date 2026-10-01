<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Requests\Catalogs\BusinessTypeRequest;
use App\Http\Resources\BusinessTypeResource;
use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class BusinessTypeController extends CatalogController
{
    protected function model(): string
    {
        return BusinessType::class;
    }

    protected function resource(): string
    {
        return BusinessTypeResource::class;
    }

    protected function page(): string
    {
        return 'catalogs/business-types';
    }

    protected function label(): string
    {
        return 'el giro';
    }

    protected function indexQuery(Builder $query): Builder
    {
        return $query->withCount('merchants')->orderBy('name');
    }

    protected function inUse(Model $model): bool
    {
        /** @var BusinessType $model */
        return $model->merchants()->withTrashed()->exists();
    }

    public function store(BusinessTypeRequest $request): RedirectResponse
    {
        BusinessType::create($request->validated());

        return $this->saved('Giro registrado.');
    }

    public function update(BusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $businessType->update($request->validated());

        return $this->saved('Giro actualizado.');
    }

    public function destroy(BusinessType $businessType): RedirectResponse
    {
        return $this->remove($businessType);
    }
}
