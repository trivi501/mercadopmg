<?php

namespace App\Models;

use App\Enums\AnnualityStatus;
use App\Enums\CommerceType;
use App\Enums\MerchantStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Comerciante del padrón.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property string $taxpayer_name
 * @property string|null $trade_name
 * @property int $business_type_id
 * @property string|null $business_description
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $neighborhood
 * @property string|null $municipality
 * @property string|null $state
 * @property string|null $location
 * @property string|null $location_street
 * @property string|null $location_number
 * @property string|null $location_neighborhood
 * @property string|null $location_reference
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $route_id
 * @property int|null $tianguis_id
 * @property numeric-string $meters
 * @property CommerceType $commerce_type
 * @property MerchantStatus $status
 * @property Carbon $registered_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BusinessType $businessType
 * @property-read Tianguis|null $tianguis
 * @property-read MarketRoute|null $route
 */
#[Fillable([
    'uuid', 'folio', 'taxpayer_name', 'trade_name', 'business_type_id', 'business_description', 'phone',
    'address', 'neighborhood', 'municipality', 'state',
    'location', 'location_street', 'location_number', 'location_neighborhood', 'location_reference', 'latitude', 'longitude',
    'route_id', 'tianguis_id', 'meters', 'commerce_type', 'status', 'registered_at', 'notes', 'created_by', 'updated_by',
])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public static function auditModule(): string
    {
        return 'Padrón';
    }

    protected function casts(): array
    {
        return [
            'commerce_type' => CommerceType::class,
            'status' => MerchantStatus::class,
            'registered_at' => 'date',
            'meters' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Tianguis, $this>
     */
    public function tianguis(): BelongsTo
    {
        return $this->belongsTo(Tianguis::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MarketRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(MarketRoute::class, 'route_id')->withTrashed();
    }

    /**
     * @return HasMany<MerchantMeasurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(MerchantMeasurement::class)->latest('start_date')->latest('id');
    }

    /**
     * @return HasOne<MerchantMeasurement, $this>
     */
    public function currentMeasurement(): HasOne
    {
        return $this->hasOne(MerchantMeasurement::class)->whereNull('end_date')->latestOfMany();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * @return HasMany<Annuality, $this>
     */
    public function annualities(): HasMany
    {
        return $this->hasMany(Annuality::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Búsqueda libre por folio, nombre, nombre comercial o teléfono.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $term = trim($term);

        $query->where(function (Builder $q) use ($term) {
            $q->where('folio', $term)
                ->orWhere('folio', 'like', "%{$term}%")
                ->orWhere('taxpayer_name', 'like', "%{$term}%")
                ->orWhere('trade_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    /**
     * Comerciantes dentro de un radio (km) alrededor de un punto.
     * Filtra con un rectángulo de acotamiento (usa el índice lat/lng);
     * MerchantService::nearby() aplica después la distancia exacta (haversine).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function nearby(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($lat)), 0.00001));

        $query->whereNotNull('latitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withPendingAnnualities(Builder $query): void
    {
        $query->whereHas('annualities', fn (Builder $q) => $q->where('status', AnnualityStatus::Pending->value));
    }

    /**
     * Contenido del QR del comerciante: solo el folio, sin datos sensibles.
     */
    public function qrPayload(): string
    {
        return $this->folio;
    }
}
