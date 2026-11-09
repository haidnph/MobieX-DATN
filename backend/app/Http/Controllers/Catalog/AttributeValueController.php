<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreAttributeValueRequest;
use App\Http\Requests\Catalog\UpdateAttributeValueRequest;
use App\Http\Resources\Catalog\AttributeValueResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeValueController extends Controller
{
    /**
     * Retrieve visible values belonging to a visible attribute.
     *
     * Values are ordered by sort_order and then ID for deterministic output.
     */
    public function index(Attribute $attribute): AnonymousResourceCollection
    {
        abort_unless($attribute->status, 404);

        $values = $attribute->values()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return AttributeValueResource::collection($values)
            ->additional([
                'message' => 'Attribute values retrieved successfully.',
            ]);
    }

    /**
     * Create a value under the attribute identified by the URL.
     */
    public function store(
        StoreAttributeValueRequest $request,
        Attribute $attribute,
    ): JsonResponse {
        $value = $attribute->values()->create(
            $request->validated(),
        );

        // Reload database defaults when optional fields were omitted.
        $value->refresh();

        return response()->json([
            'message' => 'Attribute value created successfully.',
            'data' => [
                'attribute_value' => AttributeValueResource::make($value)
                    ->resolve($request),
            ],
        ], 201);
    }

    /**
     * Partially update a value scoped to its parent attribute.
     *
     * The route uses scoped model binding, so a value belonging to another
     * attribute is treated as not found.
     */
    public function update(
        UpdateAttributeValueRequest $request,
        Attribute $attribute,
        AttributeValue $value,
    ): JsonResponse {
        $value->fill($request->validated());
        $value->save();

        return response()->json([
            'message' => 'Attribute value updated successfully.',
            'data' => [
                'attribute_value' => AttributeValueResource::make(
                    $value->refresh(),
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Delete a value only when ProductVariant references are safe.
     *
     * If the integration pivot exists, it must have a restrictive foreign
     * key to attribute_values.id. Without that database guarantee, a read
     * check alone cannot prevent concurrent references and deletion fails
     * closed with HTTP 409.
     */
    public function destroy(
        Attribute $attribute,
        AttributeValue $value,
    ): JsonResponse {
        $connection = $value->getConnection();
        $schema = $connection->getSchemaBuilder();

        $attributeValuesTable = $value->getTable();

        $variantPivotTable = $schema->hasTable('variant_attribute_values')
            ? 'variant_attribute_values'
            : (new ProductVariant)->attributeValues()->getTable();

        try {
            $conflictMessage = $connection->transaction(
                function () use (
                    $attribute,
                    $value,
                    $connection,
                    $schema,
                    $attributeValuesTable,
                    $variantPivotTable,
                ): ?string {
                    $lockedValue = AttributeValue::query()
                        ->where('attribute_id', $attribute->getKey())
                        ->lockForUpdate()
                        ->findOrFail($value->getKey());

                    /*
                     * Product/Stock owns the ProductVariant integration
                     * schema. Do not create a placeholder pivot table.
                     */
                    if ($schema->hasTable($variantPivotTable)) {
                        if (! $schema->hasColumn(
                            $variantPivotTable,
                            'attribute_value_id',
                        )) {
                            return 'Attribute value cannot be deleted safely because the variant_attribute_values.attribute_value_id reference is unavailable. Set status to false to hide the value instead.';
                        }

                        /*
                         * A restrictive FK is required in addition to the
                         * reference query to protect concurrent writes.
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
                            return 'Attribute value cannot be deleted safely because variant_attribute_values.attribute_value_id must reference attribute_values.id with ON DELETE RESTRICT or NO ACTION. Configure the restrictive foreign key in the ProductVariant schema, or set status to false to hide the value instead.';
                        }

                        $hasVariantReferences = $connection
                            ->table($variantPivotTable)
                            ->where(
                                'attribute_value_id',
                                $lockedValue->getKey(),
                            )
                            ->exists();

                        if ($hasVariantReferences) {
                            return 'Attribute value cannot be deleted while it is used by ProductVariants. Set status to false to hide the value instead.';
                        }
                    }

                    // Other database foreign keys remain the final authority.
                    $lockedValue->delete();

                    return null;
                },
            );
        } catch (QueryException $exception) {
            if (! $this->isForeignKeyConstraintViolation($exception)) {
                throw $exception;
            }

            return response()->json([
                'message' => 'Attribute value cannot be deleted because another record references it. Set status to false to hide the value instead.',
            ], 409);
        }

        if ($conflictMessage !== null) {
            return response()->json([
                'message' => $conflictMessage,
            ], 409);
        }

        return response()->json([
            'message' => 'Attribute value deleted successfully.',
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
