<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SyncCategoryAttributesRequest;
use App\Http\Resources\Catalog\AttributeResource;
use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryAttributeController extends Controller
{
    /**
     * Retrieve active attributes assigned directly to a visible category.
     *
     * Parent category assignments are intentionally not inherited.
     */
    public function index(
        Request $request,
        int $categoryId,
    ): JsonResponse {
        $category = Category::query()
            ->where('status', true)
            ->findOrFail($categoryId);

        $attributes = $category->attributes()
            ->where('attributes.status', true)
            ->orderBy('attributes.sort_order')
            ->orderBy('attributes.id')
            ->get();

        return response()->json([
            'message' => 'Category attributes retrieved successfully.',
            'data' => [
                'attributes' => AttributeResource::collection($attributes)
                    ->resolve($request),
            ],
        ]);
    }

    /**
     * Replace all direct attribute assignments of a category.
     *
     * The operation is atomic. Selected attributes are locked in ascending
     * ID order before the category is locked and its pivot is synchronized.
     *
     * Empty attribute_ids intentionally removes every existing assignment.
     */
    public function sync(
        SyncCategoryAttributesRequest $request,
        int $categoryId,
    ): JsonResponse {
        $attributeIds = array_values(
            array_map(
                static fn (mixed $id): int => (int) $id,
                $request->validated('attribute_ids'),
            ),
        );

        $attributes = DB::transaction(
            function () use ($categoryId, $attributeIds) {
                /*
                 * Lock selected attributes in a deterministic order.
                 *
                 * This prevents an attribute from being deleted between
                 * validation and assignment. It also gives concurrent
                 * assignment requests a consistent lock acquisition order.
                 */
                if ($attributeIds !== []) {
                    $lockedAttributeIds = Attribute::query()
                        ->whereIn('id', $attributeIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->map(
                            static fn ($id): int => (int) $id,
                        )
                        ->all();

                    $requestedIds = $attributeIds;

                    sort($requestedIds, SORT_NUMERIC);

                    if ($lockedAttributeIds !== $requestedIds) {
                        throw ValidationException::withMessages([
                            'attribute_ids' => 'One or more selected attributes no longer exist. Refresh the attribute list and try again.',
                        ]);
                    }
                }

                /*
                 * Lock the category so concurrent replacement operations
                 * for the same category are serialized on databases that
                 * support row-level locking.
                 */
                $category = Category::query()
                    ->lockForUpdate()
                    ->findOrFail($categoryId);

                /*
                 * sync() is the source of truth for replacement semantics:
                 * - Attach new assignments.
                 * - Keep existing requested assignments.
                 * - Detach assignments omitted from the payload.
                 *
                 * The operation remains inside the transaction.
                 */
                $category->attributes()->sync($attributeIds);

                /*
                 * Return the final persisted assignment state.
                 *
                 * Hidden attributes are included in this administrative
                 * response because the PUT contract accepts any existing ID.
                 * Public GET filters hidden attributes separately.
                 */
                return $category->attributes()
                    ->orderBy('attributes.sort_order')
                    ->orderBy('attributes.id')
                    ->get();
            },
        );

        return response()->json([
            'message' => 'Category attributes updated successfully.',
            'data' => [
                'attributes' => AttributeResource::collection($attributes)
                    ->resolve($request),
            ],
        ]);
    }
}
