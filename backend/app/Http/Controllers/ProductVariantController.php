<?php


namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    // API lấy danh sách biến thể sản phẩm
    public function index()
    {
        $variants = ProductVariant::with([
            'product',
            'attributeValues.attribute',
            'inventory',
        ])->latest()->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách biến thể thành công',
            'data' => $variants,
        ], 200);
    }

    // API thêm biến thể sản phẩm
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'prohibited',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',

            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'required|distinct|exists:attribute_values,id',

            'quantity' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        if ($this->hasDuplicateAttributeTypes(
            $validated['attribute_value_ids'] ?? []
        )) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => [
                    'Mỗi thuộc tính chỉ được chọn một giá trị cho mỗi biến thể.',
                ],
            ]);
        }

        // Tạo biến thể và tồn kho trong một giao dịch
        $variant = DB::transaction(function () use ($validated) {
            $product = Product::findOrFail($validated['product_id']);

            $attributeValues = AttributeValue::with('attribute')
                ->whereIn('id', $validated['attribute_value_ids'] ?? [])
                ->get();

            if ($this->checkDuplicateCombination(
                $product->id,
                $validated['attribute_value_ids'] ?? []
            )) {
                throw ValidationException::withMessages([
                    'attribute_value_ids' => [
                        'Biến thể này đã tồn tại với cùng tổ hợp thuộc tính trong sản phẩm.',
                    ],
                ]);
            }

            // Tự động sinh SKU
            $sku = $this->generateUniqueSku($product, $attributeValues);

            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku' => $sku,
                'price' => $validated['price'],
                'compare_price' => $validated['compare_price'] ?? null,
                'cost_price' => $validated['cost_price'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            // Liên kết các giá trị thuộc tính
            $variant->attributeValues()->sync(
                $validated['attribute_value_ids'] ?? []
            );

            // Tạo tồn kho ban đầu
            $initialQuantity = $validated['quantity'] ?? 0;

            DB::table('inventories')->insert([
                'variant_id' => $variant->id,
                'quantity' => $initialQuantity,
                'reserved_quantity' => 0,
                'min_stock' => $validated['min_stock'] ?? 0,
                'updated_at' => now(),
            ]);

            // Ghi lịch sử nhập kho ban đầu nếu có số lượng
            if ($initialQuantity > 0) {
                $this->recordInventoryTransaction(
                    $variant->id,
                    $initialQuantity,
                    'import',
                    'variant_create',
                    $variant->id,
                    'Nhập kho ban đầu khi tạo biến thể'
                );
            }

            return $variant;
        });

        // Tải đầy đủ thông tin, bao gồm tồn kho
        $variant->load([
            'product',
            'attributeValues.attribute',
            'inventory',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thêm biến thể sản phẩm thành công',
            'data' => $variant,
        ], 201);
    }

    // API xem chi tiết biến thể sản phẩm
    public function show($id)
    {
        $variant = ProductVariant::with([
            'product',
            'attributeValues.attribute',
            'inventory',
        ])->find($id);

        if (! $variant) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy biến thể sản phẩm',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết biến thể thành công',
            'data' => $variant,
        ], 200);
    }

    // API cập nhật biến thể sản phẩm
    public function update(Request $request, $id)
    {
        $variant = ProductVariant::find($id);

        if (! $variant) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy biến thể sản phẩm',
            ], 404);
        }

        $validated = $request->validate([
            'sku' => 'prohibited',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'sometimes|nullable|numeric|min:0',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'image_url' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',

            'attribute_value_ids' => 'sometimes|array',
            'attribute_value_ids.*' => 'required|distinct|exists:attribute_values,id',

            'quantity' => 'sometimes|required|integer|min:0',
            'min_stock' => 'sometimes|required|integer|min:0',
        ]);

        if (
            array_key_exists('attribute_value_ids', $validated)
            && $this->hasDuplicateAttributeTypes($validated['attribute_value_ids'])
        ) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => [
                    'Mỗi thuộc tính chỉ được chọn một giá trị cho mỗi biến thể.',
                ],
            ]);
        }

        DB::transaction(function () use ($variant, $validated) {
            // Kiểm tra trùng tổ hợp thuộc tính
            if (array_key_exists('attribute_value_ids', $validated)) {
                if ($this->checkDuplicateCombination(
                    $variant->product_id,
                    $validated['attribute_value_ids'],
                    $variant->id
                )) {
                    throw ValidationException::withMessages([
                        'attribute_value_ids' => [
                            'Tổ hợp thuộc tính này đã tồn tại ở một biến thể khác.',
                        ],
                    ]);
                }
            }

            // Cập nhật thông tin biến thể
            $variantData = [];

            foreach ([
                'price',
                'compare_price',
                'cost_price',
                'image_url',
                'status',
            ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $variantData[$field] = $validated[$field];
                }
            }

            if (! empty($variantData)) {
                $variant->update($variantData);
            }

            // Cập nhật thuộc tính nếu có
            if (array_key_exists('attribute_value_ids', $validated)) {
                $variant->attributeValues()->sync(
                    $validated['attribute_value_ids']
                );
            }

            // Cập nhật tồn kho nếu có gửi dữ liệu
            if (
                array_key_exists('quantity', $validated)
                || array_key_exists('min_stock', $validated)
            ) {
                // Khóa bản ghi tồn kho để tránh cập nhật đồng thời
                $inventory = DB::table('inventories')
                    ->where('variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                // Tạo tồn kho nếu chưa tồn tại
                if (! $inventory) {
                    DB::table('inventories')->insert([
                        'variant_id' => $variant->id,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'min_stock' => 0,
                        'updated_at' => now(),
                    ]);

                    $inventory = DB::table('inventories')
                        ->where('variant_id', $variant->id)
                        ->lockForUpdate()
                        ->first();
                }

                $inventoryData = [
                    'updated_at' => now(),
                ];

                // Cập nhật số lượng tồn tối thiểu
                if (array_key_exists('min_stock', $validated)) {
                    $inventoryData['min_stock'] = $validated['min_stock'];
                }

                // Cập nhật số lượng tồn kho
                if (array_key_exists('quantity', $validated)) {
                    $oldQuantity = (int) $inventory->quantity;
                    $newQuantity = (int) $validated['quantity'];
                    $reservedQuantity = (int) $inventory->reserved_quantity;

                    // Không cho tồn kho thấp hơn số lượng đã giữ
                    if ($newQuantity < $reservedQuantity) {
                        throw ValidationException::withMessages([
                            'quantity' => [
                                'Tồn kho không thể thấp hơn số lượng sản phẩm đã được giữ.',
                            ],
                        ]);
                    }

                    $difference = $newQuantity - $oldQuantity;

                    $inventoryData['quantity'] = $newQuantity;

                    // Ghi lịch sử nhập kho khi tăng số lượng
                    if ($difference > 0) {
                        $this->recordInventoryTransaction(
                            $variant->id,
                            $difference,
                            'import',
                            'variant_update',
                            $variant->id,
                            'Điều chỉnh tăng tồn kho khi cập nhật biến thể'
                        );
                    }

                    // Ghi lịch sử xuất kho khi giảm số lượng
                    if ($difference < 0) {
                        $this->recordInventoryTransaction(
                            $variant->id,
                            abs($difference),
                            'export',
                            'variant_update',
                            $variant->id,
                            'Điều chỉnh giảm tồn kho khi cập nhật biến thể'
                        );
                    }
                }

                DB::table('inventories')
                    ->where('variant_id', $variant->id)
                    ->update($inventoryData);
            }
        });

        // Tải lại dữ liệu mới nhất
        $variant->refresh();

        $variant->load([
            'product',
            'attributeValues.attribute',
            'inventory',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật biến thể sản phẩm thành công',
            'data' => $variant,
        ], 200);
    }

    // API xóa biến thể sản phẩm
    public function destroy($id)
    {
        try {
            $result = DB::transaction(function () use ($id) {
                // Tìm và khóa biến thể trong quá trình xóa
                $variant = ProductVariant::where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Không tìm thấy biến thể sản phẩm',
                    ];
                }

                // Kiểm tra dữ liệu liên quan trong giỏ hàng
                $hasCart = DB::table('cart_items')
                    ->where('product_variant_id', $id)
                    ->exists();

                // Kiểm tra dữ liệu liên quan trong flash sale
                $hasFlashSale = DB::table('flash_sale_items')
                    ->where('product_variant_id', $id)
                    ->exists();

                // Kiểm tra dữ liệu liên quan trong đơn hàng
                $hasOrder = DB::table('order_items')
                    ->where('product_variant_id', $id)
                    ->exists();

                // Kiểm tra lịch sử giao dịch kho
                $hasTransactions = DB::table('inventory_transactions')
                    ->where('variant_id', $id)
                    ->exists();

                if (
                    $hasCart
                    || $hasFlashSale
                    || $hasOrder
                    || $hasTransactions
                ) {
                    return [
                        'status' => false,
                        'code' => 409,
                        'message' => 'Không thể xóa biến thể vì đang có dữ liệu liên quan',
                    ];
                }

                // Xóa các dữ liệu phụ thuộc
                DB::table('variant_attribute_values')
                    ->where('variant_id', $id)
                    ->delete();

                DB::table('inventories')
                    ->where('variant_id', $id)
                    ->delete();

                DB::table('product_images')
                    ->where('variant_id', $id)
                    ->delete();

                // Xóa biến thể
                $variant->delete();

                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Xóa biến thể sản phẩm thành công',
                ];
            });

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
            ], $result['code']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Xóa biến thể thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ghi nhận lịch sử nhập xuất kho.
     */
    private function recordInventoryTransaction(
        int $variantId,
        int $quantity,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        string $note
    ): void {
        InventoryTransaction::create([
            'variant_id' => $variantId,
            'user_id' => Auth::id(),
            'transaction_type' => $transactionType,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
        ]);
    }

    /**
     * Tạo SKU duy nhất cho biến thể.
     */
    private function generateUniqueSku($product, $attributeValues)
    {
        $productCode = $this->generateProductCode($product->slug);

        $colorCode = null;
        $ramCode = null;
        $storageCode = null;
        $otherCodes = [];

        foreach ($attributeValues as $attributeValue) {
            $attributeCode = $attributeValue->attribute->code ?? '';
            $value = trim($attributeValue->value);

            if ($attributeCode === 'color') {
                $normalizedColor = Str::lower(Str::ascii($value));

                $colorMap = [
                    'den' => 'BLK',
                    'trang' => 'WHT',
                    'xanh duong' => 'BLU',
                    'titan tu nhien' => 'NAT',
                    'titan trang' => 'TWH',
                ];

                $colorCode = $colorMap[$normalizedColor]
                    ?? strtoupper(substr(
                        preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($value)),
                        0,
                        3
                    ));
            } elseif ($attributeCode === 'ram') {
                preg_match('/\d+/', $value, $matches);
                $ramCode = $matches[0] ?? null;
            } elseif ($attributeCode === 'storage') {
                preg_match('/\d+/', $value, $matches);
                $storageCode = $matches[0] ?? null;
            } else {
                $attributePrefix = strtoupper(substr($attributeCode, 0, 3));

                $attributeValueCode = strtoupper(
                    preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($value))
                );

                if ($attributePrefix && $attributeValueCode) {
                    $otherCodes[] = $attributePrefix . $attributeValueCode;
                }
            }
        }

        $parts = ['MBX', $productCode];

        if ($colorCode) {
            $parts[] = $colorCode;
        }

        if ($ramCode) {
            $parts[] = $ramCode;
        }

        if ($storageCode) {
            $parts[] = $storageCode;
        }

        $parts = array_merge($parts, $otherCodes);

        $baseSku = implode('-', $parts);

        // Kiểm tra trùng SKU và tự thêm hậu tố
        $sku = $baseSku;
        $counter = 2;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Tạo mã sản phẩm từ slug.
     */
    private function generateProductCode($slug)
    {
        $words = explode('-', Str::lower($slug));

        if (empty($words)) {
            return 'PRODUCT';
        }

        $code = strtoupper(substr($words[0], 0, 2));

        foreach (array_slice($words, 1) as $word) {
            if (preg_match('/^\d+$/', $word)) {
                $code .= $word;
            } else {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }

        return $code ?: 'PRODUCT';
    }

    /**
     * Kiểm tra trùng tổ hợp thuộc tính trong cùng sản phẩm.
     */
    private function checkDuplicateCombination(
        $productId,
        array $attributeValueIds,
        $exceptVariantId = null
    ): bool {
        $attributeValueIds = array_map('intval', $attributeValueIds);
        sort($attributeValueIds);

        if (empty($attributeValueIds)) {
            return false;
        }

        $variants = ProductVariant::where('product_id', $productId)
            ->when($exceptVariantId, function ($query) use ($exceptVariantId) {
                $query->where('id', '!=', $exceptVariantId);
            })
            ->with('attributeValues:id')
            ->get();

        foreach ($variants as $existingVariant) {
            $existingIds = $existingVariant->attributeValues
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($existingIds === $attributeValueIds) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra một biến thể có chọn nhiều giá trị thuộc cùng thuộc tính không.
     */
    private function hasDuplicateAttributeTypes(array $attributeValueIds): bool
    {
        if (empty($attributeValueIds)) {
            return false;
        }

        $duplicateCount = AttributeValue::whereIn('id', $attributeValueIds)
            ->select('attribute_id')
            ->groupBy('attribute_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return $duplicateCount > 0;
    }
}
