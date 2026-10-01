<?php

namespace App\Http\Controllers\Catalogs;

use App\Enums\ConceptType;
use App\Enums\Periodicity;
use App\Http\Requests\Catalogs\ConceptRequest;
use App\Http\Resources\ConceptResource;
use App\Models\Concept;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class ConceptController extends CatalogController
{
    protected function model(): string
    {
        return Concept::class;
    }

    protected function resource(): string
    {
        return ConceptResource::class;
    }

    protected function page(): string
    {
        return 'catalogs/concepts';
    }

    protected function label(): string
    {
        return 'el concepto';
    }

    protected function searchable(): array
    {
        return ['name', 'code'];
    }

    protected function indexQuery(Builder $query): Builder
    {
        return $query->orderBy('code');
    }

    protected function extraProps(): array
    {
        return ['types' => ConceptType::options(), 'periodicities' => Periodicity::options()];
    }

    protected function inUse(Model $model): bool
    {
        return Payment::query()->where('concept_id', $model->getKey())->exists();
    }

    public function store(ConceptRequest $request): RedirectResponse
    {
        Concept::create($request->validated());

        return $this->saved('Concepto registrado.');
    }

    /**
     * Cambiar el importe no afecta recibos ya emitidos (guardan su propio precio).
     */
    public function update(ConceptRequest $request, Concept $concept): RedirectResponse
    {
        $concept->update($request->validated());

        return $this->saved('Concepto actualizado.');
    }

    public function destroy(Concept $concept): RedirectResponse
    {
        return $this->remove($concept);
    }
}
