<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Bitácora de reimpresiones. Los registros son inmutables.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $user_id
 * @property string $reason
 * @property int $reprint_number
 * @property Carbon $reprint_date
 * @property string $reprint_time
 * @property Carbon|null $created_at
 * @property-read Receipt $receipt
 * @property-read User $user
 */
#[Fillable(['receipt_id', 'user_id', 'reason', 'reprint_number', 'reprint_date', 'reprint_time'])]
class ReceiptReprint extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las reimpresiones no pueden modificarse.'));
        static::deleting(fn () => throw new LogicException('Las reimpresiones no pueden eliminarse.'));
    }

    protected function casts(): array
    {
        return [
            'reprint_number' => 'integer',
            'reprint_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
