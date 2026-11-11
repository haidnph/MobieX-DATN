<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\Catalog\BrandResource;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Retrieve visible brands with pagination.
     *
     * Records are ordered by name and then ID
     * to ensure stable pagination.
     */
    public function index(): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return BrandResource::collection($brands)
            ->additional([
                'message' => 'Brands retrieved successfully.',
            ]);
    }

    /**
     * Retrieve a visible brand by its ID.
     *
     * Hidden brands are treated as nonexistent.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $brand = Brand::query()
            ->where('status', true)
            ->findOrFail($id);

        return response()->json([
            'message' => 'Brand retrieved successfully.',
            'data' => [
                'brand' => BrandResource::make($brand)
                    ->resolve($request),
            ],
        ]);
    }

    /**
     * Create a brand.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::query()->create(
            $request->validated(),
        );

        // Reload database defaults, including status=true when omitted.
        $brand->refresh();

        return response()->json([
            'message' => 'Brand created successfully.',
            'data' => [
                'brand' => BrandResource::make($brand)
                    ->resolve($request),
            ],
        ], 201);
    }

    /**
     * Partially update an existing brand.
     *
     * Hidden brands can still be managed by ID so administrators
     * can update or reactivate them.
     */
    public function update(
        UpdateBrandRequest $request,
        int $id,
    ): JsonResponse {
        $brand = Brand::query()->findOrFail($id);

        $brand->fill($request->validated());
        $brand->save();

        return response()->json([
            'message' => 'Brand updated successfully.',
            'data' => [
                'brand' => BrandResource::make(
                    $brand->refresh(),
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Delete a brand only when deletion is safe.
     *
     * Safety rules:
     * - Never fabricate or create the products table.
     * - If products is absent, there are no records in that table
     *   that can reference this brand.
     * - If products exists, brand_id must exist.
     * - products.brand_id must reference brands.id.
     * - The foreign key must use RESTRICT or NO ACTION.
     * - A brand with referencing products cannot be deleted.
     *
     * The brand row is locked during the transaction.
     * The database foreign key provides the final protection
     * against concurrent product creation.
     */
    public function destroy(int $id): JsonResponse
    {
        $brandModel = new Brand;
        $connection = $brandModel->getConnection();
        $schema = $connection->getSchemaBuilder();

        $productsTable = (new Product)->getTable();
        $brandsTable = $brandModel->getTable();

        try {
            $conflictMessage = $connection->transaction(
                function () use (
                    $id,
                    $connection,
                    $schema,
                    $productsTable,
                    $brandsTable,
                ): ?string {
                    $brand = Brand::on($connection->getName())
                        ->lockForUpdate()
                        ->findOrFail($id);

                    /*
                     * D-15:
                     * Product/Stock owns the products table.
                     * Do not create a placeholder table.
                     */
                    if ($schema->hasTable($productsTable)) {
                        if (! $schema->hasColumn($productsTable, 'brand_id')) {
                            return 'Brand cannot be deleted safely because the products.brand_id reference is unavailable. Set status to false to hide the brand instead.';
                        }

                        /*
                         * A reference existence query alone is not sufficient
                         * to protect against concurrent product creation.
                         *
                         * Require a real database foreign key so the database
                         * enforces referential integrity during concurrent
                         * transactions as well.
                         */
                        $hasRestrictiveBrandForeignKey = false;

                        foreach ($schema->getForeignKeys($productsTable) as $foreignKey) {
                            $columns = array_map(
                                static fn (string $column): string => strtolower($column),
                                $foreignKey['columns'] ?? [],
                            );

                            $foreignTable = strtolower(
                                (string) ($foreignKey['foreign_table'] ?? ''),
                            );

                            $foreignColumns = array_map(
                                static fn (string $column): string => strtolower($column),
                                $foreignKey['foreign_columns'] ?? [],
                            );

                            if (
                                $columns !== ['brand_id']
                                || $foreignTable !== strtolower($brandsTable)
                                || $foreignColumns !== ['id']
                            ) {
                                continue;
                            }

                            $onDelete = strtolower(
                                trim((string) ($foreignKey['on_delete'] ?? '')),
                            );

                            $hasRestrictiveBrandForeignKey = in_array(
                                $onDelete,
                                ['restrict', 'no action'],
                                true,
                            );

                            break;
                        }

                        if (! $hasRestrictiveBrandForeignKey) {
                            return 'Brand cannot be deleted safely because products.brand_id must reference brands.id with ON DELETE RESTRICT or NO ACTION. Configure the foreign key in the Product schema, or set status to false to hide the brand instead.';
                        }

                        /*
                         * Check whether any products reference this brand.
                         */
                        $hasProducts = $connection
                            ->table($productsTable)
                            ->where('brand_id', $brand->getKey())
                            ->exists();

                        if ($hasProducts) {
                            return 'Brand cannot be deleted while products reference it. Set status to false to hide the brand instead.';
                        }
                    }

                    /*
                     * Check if any other table has foreign key references to this brand.
                     */
                    try {
                        foreach ($schema->getTables() as $tableInfo) {
                            $tableName = is_array($tableInfo) ? ($tableInfo['name'] ?? null) : ($tableInfo->name ?? null);
                            if (! $tableName || $tableName === $brandsTable || $tableName === $productsTable) {
                                continue;
                            }
                            $foreignKeys = $schema->getForeignKeys($tableName);
                            foreach ($foreignKeys as $foreignKey) {
                                $foreignTable = strtolower((string) ($foreignKey['foreign_table'] ?? ''));
                                if ($foreignTable === strtolower($brandsTable)) {
                                    $columns = $foreignKey['columns'] ?? [];
                                    foreach ($columns as $column) {
                                        if ($connection->table($tableName)->where($column, $brand->getKey())->exists()) {
                                            return 'Brand cannot be deleted because another record references it. Set status to false to hide the brand instead.';
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // Safe fallback if table listing is not supported
                    }

                    /*
                     * The database remains the final authority.
                     * Other tables may also have foreign keys referencing
                     * brands, and those constraints must never be bypassed.
                     */
                    $brand->delete();

                    return null;
                },
            );
        } catch (QueryException $exception) {
            if (! $this->isForeignKeyConstraintViolation($exception)) {
                throw $exception;
            }

            return response()->json([
                'message' => 'Brand cannot be deleted because another record references it. Set status to false to hide the brand instead.',
            ], 409);
        }

        if ($conflictMessage !== null) {
            return response()->json([
                'message' => $conflictMessage,
            ], 409);
        }

        return response()->json([
            'message' => 'Brand deleted successfully.',
        ]);
    }

    /**
     * Determine whether a database exception represents a foreign-key
     * constraint violation.
     *
     * Supports common messages from MySQL, PostgreSQL and SQLite.
     */
    private function isForeignKeyConstraintViolation(
        QueryException $exception,
    ): bool {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'foreign key constraint')
            || str_contains($message, 'violates foreign key')
            || str_contains($message, 'reference constraint');
    }
}
