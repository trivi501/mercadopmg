<?php

namespace App\Http\Resources;

use App\Models\BusinessType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessType */
class BusinessTypeResource extends JsonResource
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
            'is_active' => $this->is_active,
            'merchants_count' => $this->whenCounted('merchants'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
