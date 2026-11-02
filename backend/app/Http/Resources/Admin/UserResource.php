<?php

namespace App\Http\Resources\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,

            'roles' => $this->whenLoaded(
                'roles',
                fn (): array => $this->roles
                    ->map(static fn (Role $role): array => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
