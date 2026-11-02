<?php

namespace App\Http\Resources\Catalog;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform a category into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'parent_id' => $this->parent_id === null
                ? null
                : (int) $this->parent_id,

            'name' => $this->name,

            'slug' => $this->slug,

            'description' => $this->description,

            'image' => $this->image,

            'sort_order' => (int) $this->sort_order,

            'status' => (bool) $this->status,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),

            // Include children only when the controller has loaded
            // or assembled the relationship.
            'children' => $this->whenLoaded(
                'children',
                fn () => self::collection($this->children),
            ),
        ];
    }
}
