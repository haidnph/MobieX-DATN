<?php

namespace App\Http\Resources\Catalog;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
{
    /**
     * Transform an attribute into an API response.
     *
     * Values are included only when the controller explicitly loads them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'name' => $this->name,

            'code' => $this->code,

            'type' => $this->type,

            'sort_order' => (int) $this->sort_order,

            'status' => (bool) $this->status,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),

            'values' => AttributeValueResource::collection(
                $this->whenLoaded('values'),
            ),
        ];
    }
}
