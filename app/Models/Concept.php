<?php

namespace App\Models;

use App\Enums\ConceptType;
use App\Enums\Periodicity;
use App\Models\Concerns\Auditable;
use Database\Factories\ConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Concepto de cobro (anualidad, boleto, etc.).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property ConceptType $type
 * @property numeric-string $amount
 * @property Periodicity $periodicity
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'type', 'amount', 'periodicity', 'is_active'])]
class Concept extends Model
{
    /** @use HasFactory<ConceptFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public static function auditModule(): string
    {
        return 'Conceptos';
    }

    protected function casts(): array
    {
        return [
            'type' => ConceptType::class,
            'periodicity' => Periodicity::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, ConceptType $type): void
    {
        $query->where('type', $type->value);
    }
}
