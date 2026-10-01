<?php

namespace App\Models;

use App\Enums\AnnualityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Obligación anual de un comerciante por ejercicio fiscal.
 * La restricción única (merchant_id, concept_id, fiscal_year) impide duplicarla.
 *
 * @property int $id
 * @property int $merchant_id
 * @property int $concept_id
 * @property int $fiscal_year
 * @property numeric-string $amount
 * @property AnnualityStatus $status
 * @property int|null $payment_id
 * @property Carbon|null $paid_at
 * @property-read Merchant $merchant
 * @property-read Concept $concept
 * @property-read Payment|null $payment
 */
#[Fillable(['merchant_id', 'concept_id', 'fiscal_year', 'amount', 'status', 'payment_id', 'paid_at'])]
class Annuality extends Model
{
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'amount' => 'decimal:2',
            'status' => AnnualityStatus::class,
            'paid_at' => 'datetime',
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
     * @return BelongsTo<Concept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
