<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_id' => $this->receipt_id,
            'receipt_folio' => $this->whenLoaded('receipt', fn () => $this->receipt->folio),
            'merchant_id' => $this->merchant_id,
            'concept_id' => $this->concept_id,
            'concept' => $this->whenLoaded('concept', fn () => $this->concept->name),
            'concept_type' => $this->whenLoaded('concept', fn () => $this->concept->type->value),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'surcharge' => $this->surcharge,
            'total' => $this->total,
            'payment_method' => $this->payment_method->value,
            'paid_at' => $this->paid_at->toIso8601String(),
            'fiscal_year' => $this->fiscal_year,
            'service_date' => $this->service_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'user' => $this->whenLoaded('user', fn () => $this->user->name),
        ];
    }
}
