<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'folio' => $this->folio,
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->whenLoaded('merchant', fn () => [
                'id' => $this->merchant->id,
                'folio' => $this->merchant->folio,
                'taxpayer_name' => $this->merchant->taxpayer_name,
            ]),
            'issued_at' => $this->issued_at->toIso8601String(),
            'date' => $this->issued_at->format('d/m/Y'),
            'time' => $this->issued_at->format('H:i'),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'surcharge' => $this->surcharge,
            'total' => $this->total,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'printed_at' => $this->printed_at?->toIso8601String(),
            'reprint_count' => $this->reprint_count,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'canceller' => $this->whenLoaded('canceller', fn () => $this->canceller?->name),
            'user' => $this->whenLoaded('user', fn () => $this->user->name),
            'tianguis' => $this->whenLoaded('tianguis', fn () => $this->tianguis?->name),
            'route' => $this->whenLoaded('route', fn () => $this->route?->name),
            'source' => $this->source,
            'concepts' => $this->whenLoaded('items', fn () => $this->items->pluck('description')->implode(', ')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'concept_id' => $item->concept_id,
                'concept_code' => $item->concept_code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ])),
            'reprints' => ReceiptReprintResource::collection($this->whenLoaded('reprints')),
        ];
    }
}
