<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pago aplicado a un concepto. Un recibo agrupa uno o varios pagos.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $merchant_id
 * @property int $concept_id
 * @property int $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $subtotal
 * @property numeric-string $discount
 * @property numeric-string $surcharge
 * @property numeric-string $total
 * @property PaymentMethod $payment_method
 * @property Carbon $paid_at
 * @property int $user_id
 * @property int|null $tianguis_id
 * @property Carbon|null $service_date
 * @property int|null $fiscal_year
 * @property PaymentStatus $status
 * @property string|null $notes
 * @property-read Receipt $receipt
 * @property-read Concept $concept
 * @property-read Merchant $merchant
 * @property-read User $user
 */
#[Fillable([
    'receipt_id', 'merchant_id', 'concept_id', 'quantity', 'unit_price', 'subtotal', 'discount', 'surcharge', 'total',
    'payment_method', 'paid_at', 'user_id', 'tianguis_id', 'service_date', 'fiscal_year', 'status', 'notes',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'service_date' => 'date',
            'fiscal_year' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Tianguis, $this>
     */
    public function tianguis(): BelongsTo
    {
        return $this->belongsTo(Tianguis::class)->withTrashed();
    }
}
