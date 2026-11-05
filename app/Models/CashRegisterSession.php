<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Corte de caja de un recaudador: del inicio de cobro al corte final.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $user_id
 * @property CashSessionStatus $status
 * @property Carbon $opened_at
 * @property string $opening_amount
 * @property string|null $opening_notes
 * @property string $source
 * @property string|null $device
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $total_collected
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $difference
 * @property array<string, mixed>|null $summary
 * @property string|null $closing_notes
 * @property-read User $user
 * @property-read User|null $closer
 */
#[Fillable([
    'uuid', 'folio', 'user_id', 'status', 'opened_at', 'opening_amount', 'opening_notes', 'source', 'device',
    'closed_at', 'closed_by', 'total_collected', 'expected_cash', 'counted_cash', 'difference', 'summary', 'closing_notes',
])]
class CashRegisterSession extends Model
{
    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Los cortes de caja no pueden eliminarse.'));

        // Un corte cerrado queda congelado.
        static::updating(function (self $session) {
            if ($session->getOriginal('status') === CashSessionStatus::Closed) {
                throw new LogicException('El corte ya está cerrado y no puede modificarse.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'summary' => 'array',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', CashSessionStatus::Open);
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
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
