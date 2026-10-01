<?php

namespace App\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Definición de un reporte: consulta filtrada, columnas, renglones y totales.
 * La misma definición alimenta la vista web paginada y las exportaciones.
 */
abstract class Report
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected array $filters = []) {}

    abstract public function key(): string;

    abstract public function title(): string;

    /**
     * @return array<string, string> clave => encabezado
     */
    abstract public function columns(): array;

    /**
     * @return Builder<covariant Model>
     */
    abstract public function query(): Builder;

    /**
     * @return array<string, scalar|null>
     */
    abstract public function row(Model $model): array;

    /**
     * Totales del reporte (etiqueta => valor ya formateado).
     *
     * @return array<string, string|int>
     */
    public function summary(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    protected function filter(string $key): mixed
    {
        $value = $this->filters[$key] ?? null;

        return $value === '' ? null : $value;
    }

    protected static function money(mixed $value): string
    {
        return '$'.number_format((float) $value, 2);
    }
}
