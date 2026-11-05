<?php

namespace App\Http\Resources;

use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CashRegisterSession */
class CashSessionResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_open' => $this->isOpen(),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'opened_at' => $this->opened_at->toIso8601String(),
            'opened_at_label' => $this->opened_at->format('d/m/Y H:i'),
            'opening_amount' => $this->opening_amount,
            'opening_notes' => $this->opening_notes,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_at_label' => $this->closed_at?->format('d/m/Y H:i'),
            'closed_by' => $this->whenLoaded('closer', fn () => $this->closer ? ['id' => $this->closer->id, 'name' => $this->closer->name] : null),
            'total_collected' => $this->total_collected,
            'expected_cash' => $this->expected_cash,
            'counted_cash' => $this->counted_cash,
            'difference' => $this->difference,
            'closing_notes' => $this->closing_notes,
            'source' => $this->source,
        ];
    }
}
