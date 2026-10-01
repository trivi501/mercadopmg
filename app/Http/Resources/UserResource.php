<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'role' => $this->getRoleNames()->first(),
            'role_label' => $this->roleLabel(),
            'permissions' => $this->when($request->routeIs('api.v1.me', 'api.v1.login'), fn () => $this->getAllPermissions()->pluck('name')->values()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'tokens_count' => $this->whenCounted('tokens'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
