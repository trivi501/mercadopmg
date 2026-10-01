<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
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
            'taxpayer_name' => $this->taxpayer_name,
            'trade_name' => $this->trade_name,
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->whenLoaded('businessType', fn () => $this->businessType->name),
            'business_description' => $this->business_description,
            'phone' => $this->phone,
            'address' => $this->address,
            'neighborhood' => $this->neighborhood,
            'municipality' => $this->municipality,
            'state' => $this->state,
            'location' => $this->location,
            'location_street' => $this->location_street,
            'location_number' => $this->location_number,
            'location_neighborhood' => $this->location_neighborhood,
            'location_reference' => $this->location_reference,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'route_id' => $this->route_id,
            'route' => $this->whenLoaded('route', fn () => $this->route?->name),
            'tianguis_id' => $this->tianguis_id,
            'tianguis' => $this->whenLoaded('tianguis', fn () => $this->tianguis?->name),
            'meters' => $this->meters,
            'commerce_type' => $this->commerce_type->value,
            'commerce_type_label' => $this->commerce_type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'registered_at' => $this->registered_at->toDateString(),
            'notes' => $this->notes,
            'qr' => $this->qrPayload(),
            'distance_km' => $this->when($this->resource->getAttribute('distance_km') !== null, fn () => $this->resource->getAttribute('distance_km')),
            'measurements' => $this->whenLoaded('measurements', fn () => $this->measurements->map(fn ($m) => [
                'id' => $m->id,
                'meters' => $m->meters,
                'start_date' => $m->start_date->toDateString(),
                'end_date' => $m->end_date?->toDateString(),
                'reason' => $m->reason,
                'user' => $m->user?->name,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
