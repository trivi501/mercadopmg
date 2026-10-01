<?php

namespace App\Http\Resources;

use App\Models\MarketRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MarketRoute */
class RouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'zone' => $this->zone,
            'itinerary' => $this->itinerary,
            'is_active' => $this->is_active,
            'merchants_count' => $this->whenCounted('merchants'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
