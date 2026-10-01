<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\TianguisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $location
 * @property string|null $neighborhood
 * @property string|null $municipality
 * @property string|null $operating_day
 * @property string|null $schedule
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_active
 */
#[Table('tianguis')]
#[Fillable(['name', 'description', 'location', 'neighborhood', 'municipality', 'operating_day', 'schedule', 'latitude', 'longitude', 'is_active'])]
class Tianguis extends Model
{
    /** @use HasFactory<TianguisFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const array DAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    public static function auditModule(): string
    {
        return 'Tianguis';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return HasMany<Merchant, $this>
     */
    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
