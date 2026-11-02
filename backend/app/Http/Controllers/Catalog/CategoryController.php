<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Retrieve visible categories.
     *
     * Default behavior returns a paginated flat list.
     * Passing tree=true returns the complete category hierarchy.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if ($request->boolean('tree')) {
            return CategoryResource::collection(
                $this->buildVisibleTree(),
            )->additional([
                'message' => 'Categories retrieved successfully.',
            ]);
        }

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15);

        return CategoryResource::collection($categories)
            ->additional([
                'message' => 'Categories retrieved successfully.',
            ]);
    }

    /**
     * Retrieve a visible category by its ID.
     *
     * Hidden categories are treated as not found.
     */
    public function show(
        Request $request,
        int $id,
    ): JsonResponse {
        $category = Category::query()
            ->where('status', true)
            ->findOrFail($id);

        return response()->json([
            'message' => 'Category retrieved successfully.',
            'data' => [
                'category' => CategoryResource::make($category)
                    ->resolve($request),
            ],
        ]);
    }

    /**
     * Build a complete hierarchy from all visible categories.
     *
     * Categories are retrieved in one database query.
     * Child relationships are assembled in memory to avoid N+1 queries
     * and to support arbitrary hierarchy depth.
     *
     * A category is included in the tree only when it can be reached
     * from a visible root through visible parent categories.
     *
     * @return EloquentCollection<int, Category>
     */
    private function buildVisibleTree(): EloquentCollection
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $categoriesById = $categories->keyBy(
            static fn(Category $category): int => (int) $category->getKey(),
        );

        // Initialize an empty children collection for every category.
        foreach ($categories as $category) {
            $category->setRelation(
                'children',
                new EloquentCollection,
            );
        }

        $roots = new EloquentCollection;

        // Assemble the hierarchy without issuing additional queries.
        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $roots->push($category);

                continue;
            }

            $parent = $categoriesById->get(
                (int) $category->parent_id,
            );

            // Do not expose orphaned categories whose parent is hidden.
            if ($parent instanceof Category) {
                $parent->getRelation('children')->push($category);
            }
        }

        return $roots;
    }
}
