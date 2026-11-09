<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreAttributeRequest;
use App\Http\Requests\Catalog\UpdateAttributeRequest;
use App\Http\Resources\Catalog\AttributeResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeController extends Controller
{
    /**
     * Retrieve visible attributes.
     *
     * Supports pagination and optional category/type filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],

            'type' => [
                'sometimes',
                'nullable',
                'string',
                'in:select,color',
            ],
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
     * Hidden attributes are treated as nonexistent.
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

    /**
     * Create an attribute.
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $attribute = Attribute::query()->create(
            $request->validated(),
        );

        // Reload database defaults, including type, sort_order and status.
        $attribute->refresh();

        return response()->json([
            'message' => 'Attribute created successfully.',
            'data' => [
                'attribute' => AttributeResource::make($attribute)
                    ->resolve($request),
            ],
        ], 201);
    }

    /**
     * Partially update an existing attribute.
     *
     * Hidden attributes can still be managed by ID so administrators
     * can update or reactivate them.
     */
    public function update(
        UpdateAttributeRequest $request,
        int $id,
    ): JsonResponse {
        $attribute = Attribute::query()->findOrFail($id);

        $attribute->fill($request->validated());
        $attribute->save();

        return response()->json([
            'message' => 'Attribute updated successfully.',
            'data' => [
                'attribute' => AttributeResource::make(
                    $attribute->refresh(),
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Delete an attribute only when deletion is safe.
     *
     * Its AttributeValues and category assignments are removed through
     * the existing database cascade constraints.
     *
     * ProductVariant references must never be silently removed.
     */
    public function destroy(int $id): JsonResponse
    {
        $attributeModel = new Attribute;
        $connection = $attributeModel->getConnection();
        $schema = $connection->getSchemaBuilder();

        $attributeValuesTable = (new AttributeValue)->getTable();

        $variantPivotTable = $schema->hasTable('variant_attribute_values')
            ? 'variant_attribute_values'
            : (new ProductVariant)->attributeValues()->getTable();

        try {
            $conflictMessage = $connection->transaction(
                function () use (
                    $id,
                    $connection,
                    $schema,
                    $attributeValuesTable,
                    $variantPivotTable,
                ): ?string {
                    $attribute = Attribute::query()
                        ->lockForUpdate()
                        ->findOrFail($id);

                    /*
                     * ProductVariant owns its integration schema.
                     *
                     * When the pivot table does not exist, there are no
                     * Laravel pivot records to reference these values.
                     *
                     * Never create a placeholder pivot table here.
                     */
                    if ($schema->hasTable($variantPivotTable)) {
                        if (! $schema->hasColumn(
                            $variantPivotTable,
                            'attribute_value_id',
                        )) {
                            return 'Attribute cannot be deleted safely because the variant_attribute_values.attribute_value_id reference is unavailable. Set status to false to hide the attribute instead.';
                        }

                        /*
                         * Require a restrictive foreign key.
                         *
                         * An existence check alone cannot prevent a
                         * concurrent ProductVariant from referencing a
                         * value while deletion is in progress.
                         */
                        $hasRestrictiveForeignKey = false;

                        foreach (
                            $schema->getForeignKeys($variantPivotTable) as $foreignKey
                        ) {
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
                                $columns !== ['attribute_value_id']
                                || $foreignTable !== strtolower($attributeValuesTable)
                                || $foreignColumns !== ['id']
                            ) {
                                continue;
                            }

                            $onDelete = strtolower(
                                trim((string) ($foreignKey['on_delete'] ?? '')),
                            );

                            $hasRestrictiveForeignKey = in_array(
                                $onDelete,
                                [
                                    'restrict',
                                    'no action',
                                ],
                                true,
                            );

                            break;
                        }

                        if (! $hasRestrictiveForeignKey) {
                            return 'Attribute cannot be deleted safely because variant_attribute_values.attribute_value_id must reference attribute_values.id with ON DELETE RESTRICT or NO ACTION. Configure the restrictive foreign key in the ProductVariant schema, or set status to false to hide the attribute instead.';
                        }

                        /*
                         * Check whether any value owned by this attribute
                         * is currently assigned to a ProductVariant.
                         */
                        $valueIds = $attribute->values()
                            ->pluck('id');

                        $hasVariantReferences = $valueIds->isNotEmpty()
                            && $connection
                                ->table($variantPivotTable)
                                ->whereIn('attribute_value_id', $valueIds)
                                ->exists();

                        if ($hasVariantReferences) {
                            return 'Attribute cannot be deleted while one or more attribute values are used by ProductVariants. Set status to false to hide the attribute instead.';
                        }
                    }

                    $attribute->delete();

                    return null;
                },
            );
        } catch (QueryException $exception) {
            if (! $this->isForeignKeyConstraintViolation($exception)) {
                throw $exception;
            }

            return response()->json([
                'message' => 'Attribute cannot be deleted because another record references one or more of its values. Set status to false to hide the attribute instead.',
            ], 409);
        }

        if ($conflictMessage !== null) {
            return response()->json([
                'message' => $conflictMessage,
            ], 409);
        }

        return response()->json([
            'message' => 'Attribute deleted successfully.',
        ]);
    }

    /**
     * Determine whether a database exception represents a foreign-key
     * constraint violation.
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
