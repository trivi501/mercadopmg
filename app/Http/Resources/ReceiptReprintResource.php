<?php

namespace App\Http\Resources;

use App\Models\ReceiptReprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReceiptReprint */
class ReceiptReprintResource extends JsonResource
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
            'reprint_number' => $this->reprint_number,
            'reason' => $this->reason,
            'date' => $this->reprint_date->format('d/m/Y'),
            'time' => substr($this->reprint_time, 0, 5),
            'user' => $this->whenLoaded('user', fn () => $this->user->name),
        ];
    }
}
