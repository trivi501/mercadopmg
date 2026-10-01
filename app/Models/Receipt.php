<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Recibo de pago. Nunca se elimina físicamente: solo se cancela.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $merchant_id
 * @property int $user_id
 * @property int|null $tianguis_id
 * @property int|null $route_id
 * @property Carbon $issued_at
 * @property numeric-string $subtotal
 * @property numeric-string $discount
 * @property numeric-string $surcharge
 * @property numeric-string $total
 * @property PaymentMethod $payment_method
 * @property ReceiptStatus $status
 * @property Carbon|null $printed_at
 * @property int $reprint_count
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property int|null $cash_register_session_id
 * @property string $source
 * @property string|null $device
 * @property string|null $notes
 * @property-read Merchant $merchant
 * @property-read User $user
 * @property-read Tianguis|null $tianguis
 * @property-read MarketRoute|null $route
 * @property-read User|null $canceller
 */
#[Fillable([
    'uuid', 'folio', 'merchant_id', 'user_id', 'tianguis_id', 'route_id', 'issued_at',
    'subtotal', 'discount', 'surcharge', 'total', 'payment_method', 'status', 'printed_at', 'reprint_count',
    'cancelled_at', 'cancelled_by', 'cancellation_reason', 'cash_register_session_id', 'source', 'device', 'notes',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new LogicException('Los recibos no pueden eliminarse; deben cancelarse.');
        });
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'printed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => ReceiptStatus::class,
            'reprint_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
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
     * @return HasMany<ReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentDetail, $this>
     */
    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }

    /**
     * @return HasMany<ReceiptReprint, $this>
     */
    public function reprints(): HasMany
    {
        return $this->hasMany(ReceiptReprint::class)->orderBy('reprint_number');
    }

    public function isCancelled(): bool
    {
        return $this->status === ReceiptStatus::Cancelled;
    }

    /**
     * Recibos vigentes (no cancelados).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function valid(Builder $query): void
    {
        $query->where('status', '!=', ReceiptStatus::Cancelled->value);
    }

    /**
     * Filtros comunes del historial de recibos y reportes.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->where('issued_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->where('issued_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($filters['merchant_id'] ?? null, fn (Builder $q, $v) => $q->where('merchant_id', $v))
            ->when($filters['folio'] ?? null, fn (Builder $q, $v) => $q->where('folio', 'like', "%{$v}%"))
            ->when($filters['tianguis_id'] ?? null, fn (Builder $q, $v) => $q->where('tianguis_id', $v))
            ->when($filters['route_id'] ?? null, fn (Builder $q, $v) => $q->where('route_id', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['concept_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('items', fn (Builder $i) => $i->where('concept_id', $v)))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $s) use ($v) {
                $s->where('folio', 'like', "%{$v}%")
                    ->orWhereHas('merchant', fn (Builder $m) => $m->where('folio', 'like', "%{$v}%")->orWhere('taxpayer_name', 'like', "%{$v}%"));
            }));
    }
}
