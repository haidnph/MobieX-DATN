<?php

namespace App\Http\Resources\Catalog;

use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeValue
 */
class AttributeValueResource extends JsonResource
{
    /**
     * Transform an attribute value into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'attribute_id' => (int) $this->attribute_id,

            'value' => $this->value,

            'color_code' => $this->color_code,

            'sort_order' => (int) $this->sort_order,

            'status' => (bool) $this->status,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
