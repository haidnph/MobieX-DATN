<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\Catalog\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     */
    public function show(Request $request, int $id): JsonResponse
    {
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
     * Create a category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create(
            $request->validated(),
        );

        // Reload database-generated default values.
        $category->refresh();

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => [
                'category' => CategoryResource::make($category)
                    ->resolve($request),
            ],
        ], 201);
    }

    /**
     * Partially update an existing category.
     */
    public function update(
        UpdateCategoryRequest $request,
        int $id,
    ): JsonResponse {
        $category = Category::query()->findOrFail($id);

        $category->fill($request->validated());
        $category->save();

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => [
                'category' => CategoryResource::make(
                    $category->refresh(),
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Delete a category only when it has no dependencies.
     *
     * Deletion is performed inside a transaction.
     *
     * A category cannot be deleted when:
     * - It has child categories.
     * - Products reference it through category_id.
     *
     * The products reference is checked only when the products
     * table and its category_id column exist.
     */
    public function destroy(int $id): JsonResponse
    {
        $conflictMessage = DB::transaction(
            function () use ($id): ?string {
                $category = Category::query()
                    ->lockForUpdate()
                    ->findOrFail($id);

                // Hidden children must also prevent parent deletion.
                if ($category->children()->exists()) {
                    return 'Category cannot be deleted while it has child categories. Set status to false to hide it instead.';
                }

                $productsTable = (new Product)->getTable();

                /*
                 * D-15:
                 * Do not query products when its schema has not
                 * been integrated yet.
                 *
                 * Never create a placeholder products table here.
                 */
                if (Schema::hasTable($productsTable)) {
                    // Fail safely if the expected reference column
                    // has not been integrated into the product schema.
                    if (! Schema::hasColumn($productsTable, 'category_id')) {
                        return 'Category cannot be deleted safely because the products.category_id reference is unavailable.';
                    }

                    if ($category->products()->exists()) {
                        return 'Category cannot be deleted while products reference it. Set status to false to hide it instead.';
                    }
                }

                $category->delete();

                return null;
            },
        );

        if ($conflictMessage !== null) {
            return response()->json([
                'message' => $conflictMessage,
            ], 409);
        }

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    /**
     * Build a complete hierarchy from all visible categories.
     *
     * Categories are retrieved in one database query.
     * Child relationships are assembled in memory to avoid N+1 queries.
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
            static fn (Category $category): int => (int) $category->getKey(),
        );

        foreach ($categories as $category) {
            $category->setRelation(
                'children',
                new EloquentCollection,
            );
        }

        $roots = new EloquentCollection;

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $roots->push($category);

                continue;
            }

            $parent = $categoriesById->get(
                (int) $category->parent_id,
            );

            if ($parent instanceof Category) {
                $parent->getRelation('children')->push($category);
            }
        }

        return $roots;
    }
}
