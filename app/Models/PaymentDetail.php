<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desglose de la forma de pago de un recibo (base para el futuro corte de caja).
 *
 * @property int $id
 * @property int $receipt_id
 * @property PaymentMethod $payment_method
 * @property numeric-string $amount
 * @property string|null $reference
 */
#[Fillable(['receipt_id', 'payment_method', 'amount', 'reference'])]
class PaymentDetail extends Model
{
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
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
}
