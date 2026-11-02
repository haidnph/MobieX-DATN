<?php

namespace App\Http\Resources\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the role into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,

            'permissions' => $this->whenLoaded(
                'permissions',
                fn (): array => $this->permissions
                    ->pluck('slug')
                    ->values()
                    ->all(),
            ),
        ];
    }
}
