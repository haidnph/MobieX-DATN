<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\AttributeResource;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    /**
     * Retrieve visible attributes with pagination and optional filters.
     *
     * Public responses never expose hidden attributes. Category assignments
     * are direct relationships; child categories do not inherit attributes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(['select', 'color'])],
        ]);

        $query = Attribute::query()
            ->where('status', true);

        if (isset($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];

            $query->whereHas(
                'categories',
                static function (Builder $categoryQuery) use ($categoryId): void {
                    $categoryQuery->whereKey($categoryId);
                },
            );
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $attributes = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return AttributeResource::collection($attributes)
            ->additional([
                'message' => 'Attributes retrieved successfully.',
            ]);
    }

    /**
     * Retrieve a visible attribute and its visible values by ID.
     *
     * Hidden attributes are treated as nonexistent. Hidden values are omitted
     * from the public detail response.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $attribute = Attribute::query()
            ->where('status', true)
            ->with([
                'values' => static function ($valueQuery): void {
                    $valueQuery
                        ->where('status', true)
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Attribute retrieved successfully.',
            'data' => [
                'attribute' => AttributeResource::make($attribute)
                    ->resolve($request),
            ],
        ]);
    }
}
