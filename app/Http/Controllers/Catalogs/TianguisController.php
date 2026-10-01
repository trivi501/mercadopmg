<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Requests\Catalogs\TianguisRequest;
use App\Http\Resources\TianguisResource;
use App\Models\Tianguis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class TianguisController extends CatalogController
{
    protected function model(): string
    {
        return Tianguis::class;
    }

    protected function resource(): string
    {
        return TianguisResource::class;
    }

    protected function page(): string
    {
        return 'catalogs/tianguis';
    }

    protected function label(): string
    {
        return 'el tianguis';
    }

    protected function searchable(): array
    {
        return ['name', 'neighborhood', 'location'];
    }

    protected function indexQuery(Builder $query): Builder
    {
        return $query->withCount('merchants')->orderBy('name');
    }

    protected function extraProps(): array
    {
        return ['days' => Tianguis::DAYS];
    }

    protected function inUse(Model $model): bool
    {
        /** @var Tianguis $model */
        return $model->merchants()->withTrashed()->exists();
    }

    public function store(TianguisRequest $request): RedirectResponse
    {
        Tianguis::create($request->validated());

        return $this->saved('Tianguis registrado.');
    }

    public function update(TianguisRequest $request, Tianguis $tianguis): RedirectResponse
    {
        $tianguis->update($request->validated());

        return $this->saved('Tianguis actualizado.');
    }

    public function destroy(Tianguis $tianguis): RedirectResponse
    {
        return $this->remove($tianguis);
    }
}
