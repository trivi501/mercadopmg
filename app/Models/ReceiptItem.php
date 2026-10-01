<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón impreso en el recibo (copia inmutable del concepto cobrado).
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $payment_id
 * @property int $concept_id
 * @property string $concept_code
 * @property string $description
 * @property int $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $amount
 */
#[Fillable(['receipt_id', 'payment_id', 'concept_id', 'concept_code', 'description', 'quantity', 'unit_price', 'amount'])]
class ReceiptItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
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
