<?php

namespace App\Http\Resources;

use App\Models\Tianguis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tianguis */
class TianguisResource extends JsonResource
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
            'location' => $this->location,
            'neighborhood' => $this->neighborhood,
            'municipality' => $this->municipality,
            'operating_day' => $this->operating_day,
            'schedule' => $this->schedule,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'merchants_count' => $this->whenCounted('merchants'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
