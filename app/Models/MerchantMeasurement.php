<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Histórico de metros asignados a un comerciante.
 *
 * @property int $id
 * @property int $merchant_id
 * @property numeric-string $meters
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int|null $user_id
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
#[Fillable(['merchant_id', 'meters', 'start_date', 'end_date', 'user_id', 'reason'])]
class MerchantMeasurement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'meters' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
