<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Requests\Catalogs\RouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\MarketRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class RouteController extends CatalogController
{
    protected function model(): string
    {
        return MarketRoute::class;
    }

    protected function resource(): string
    {
        return RouteResource::class;
    }

    protected function page(): string
    {
        return 'catalogs/routes';
    }

    protected function label(): string
    {
        return 'la ruta';
    }

    protected function searchable(): array
    {
        return ['name', 'zone'];
    }

    protected function indexQuery(Builder $query): Builder
    {
        return $query->withCount('merchants')->orderBy('name');
    }

    protected function inUse(Model $model): bool
    {
        /** @var MarketRoute $model */
        return $model->merchants()->withTrashed()->exists();
    }

    public function store(RouteRequest $request): RedirectResponse
    {
        MarketRoute::create($request->validated());

        return $this->saved('Ruta registrada.');
    }

    public function update(RouteRequest $request, MarketRoute $route): RedirectResponse
    {
        $route->update($request->validated());

        return $this->saved('Ruta actualizada.');
    }

    public function destroy(MarketRoute $route): RedirectResponse
    {
        return $this->remove($route);
    }
}
