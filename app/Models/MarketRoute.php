<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\MarketRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ruta de comercio (tabla `routes`). Se nombra MarketRoute para no
 * confundirla con el facade Route de Laravel.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $zone
 * @property string|null $itinerary
 * @property bool $is_active
 */
#[Table('routes')]
#[Fillable(['name', 'description', 'zone', 'itinerary', 'is_active'])]
class MarketRoute extends Model
{
    /** @use HasFactory<MarketRouteFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public static function auditModule(): string
    {
        return 'Rutas';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Merchant, $this>
     */
    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'route_id');
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
