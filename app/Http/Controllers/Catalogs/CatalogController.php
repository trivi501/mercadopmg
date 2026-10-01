<?php

namespace App\Http\Controllers\Catalogs;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base para los catálogos administrables (giros, tianguis, rutas, conceptos).
 */
abstract class CatalogController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** @return class-string<JsonResource> */
    abstract protected function resource(): string;

    abstract protected function page(): string;

    abstract protected function label(): string;

    /**
     * @return list<string>
     */
    protected function searchable(): array
    {
        return ['name'];
    }

    /**
     * ¿El registro está en uso? Si lo está no se elimina; se debe desactivar.
     */
    abstract protected function inUse(Model $model): bool;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function indexQuery(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [];
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('catalogs.manage'), 403);

        $search = $request->string('search')->trim()->toString();
        $query = $this->indexQuery($this->model()::query())
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($search) {
                foreach ($this->searchable() as $column) {
                    $w->orWhere($column, 'like', "%{$search}%");
                }
            }))
            ->when($request->filled('active'), fn (Builder $q) => $q->where('is_active', $request->boolean('active')));

        return Inertia::render($this->page(), [
            'items' => $this->resource()::collection($query->paginate(20)->withQueryString()),
            'filters' => $request->only(['search', 'active']),
            ...$this->extraProps(),
        ]);
    }

    protected function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    protected function remove(Model $model): RedirectResponse
    {
        abort_unless(request()->user()?->can('catalogs.manage'), 403);

        if ($this->inUse($model)) {
            throw new BusinessRuleException("No se puede eliminar: {$this->label()} está en uso. Desactívelo en su lugar.", 'in_use');
        }

        $model->delete();

        return $this->saved(ucfirst($this->label()).' eliminado.');
    }
}
