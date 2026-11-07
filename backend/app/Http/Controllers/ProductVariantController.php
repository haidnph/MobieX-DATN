<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
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
        // Kiểm tra dữ liệu đầu vào
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

        // Thực hiện tạo biến thể và tồn kho trong một giao dịch
        $variant = DB::transaction(function () use ($validated) {

            // Lấy thông tin sản phẩm
            $product = Product::findOrFail($validated['product_id']);

            // Lấy các thuộc tính đã chọn
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

            // Nếu người dùng không nhập SKU thì tự động sinh
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

            // Tạo bản ghi tồn kho ban đầu
            DB::table('inventories')->insert([
                'variant_id' => $variant->id,
                'quantity' => $validated['quantity'] ?? 0,
                'reserved_quantity' => 0,
                'min_stock' => $validated['min_stock'] ?? 0,
            ]);

            return $variant;
        });

        // Lấy lại thông tin đầy đủ
        $variant->load([
            'product',
            'attributeValues.attribute',
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

        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'sku' => 'prohibited',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',

            'attribute_value_ids' => 'sometimes|array',
            'attribute_value_ids.*' => 'required|distinct|exists:attribute_values,id',

            'quantity' => 'sometimes|integer|min:0',
            'min_stock' => 'sometimes|integer|min:0',
        ]);
        if (
            array_key_exists('attribute_value_ids', $validated) &&
            $this->hasDuplicateAttributeTypes($validated['attribute_value_ids'])
        ) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => [
                    'Mỗi thuộc tính chỉ được chọn một giá trị cho mỗi biến thể.',
                ],
            ]);
        }
        DB::transaction(function () use ($variant, $validated) {

            // Kiểm tra trùng tổ hợp thuộc tính khi cập nhật
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
            $variant->update([
                'price' => $validated['price'] ?? $variant->price,
                'compare_price' => $validated['compare_price'] ?? $variant->compare_price,
                'cost_price' => $validated['cost_price'] ?? $variant->cost_price,
                'image_url' => $validated['image_url'] ?? $variant->image_url,
                'status' => $validated['status'] ?? $variant->status,
            ]);

            // Cập nhật các giá trị thuộc tính nếu có gửi lên
            if (array_key_exists('attribute_value_ids', $validated)) {
                $variant->attributeValues()->sync(
                    $validated['attribute_value_ids']
                );
            }

            // Cập nhật tồn kho nếu có gửi lên
            if (isset($validated['quantity']) || isset($validated['min_stock'])) {

                $inventory = DB::table('inventories')
                    ->where('variant_id', $variant->id)
                    ->first();

                if ($inventory) {
                    $inventoryData = [];

                    if (isset($validated['quantity'])) {
                        $inventoryData['quantity'] = $validated['quantity'];
                    }

                    if (isset($validated['min_stock'])) {
                        $inventoryData['min_stock'] = $validated['min_stock'];
                    }

                    DB::table('inventories')
                        ->where('variant_id', $variant->id)
                        ->update($inventoryData);
                }
            }
        });

        // Lấy lại dữ liệu đầy đủ
        $variant->refresh();
        $variant->load([
            'product',
            'attributeValues.attribute',
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

                // Kiểm tra dữ liệu liên quan cần được bảo vệ
                $hasCart = DB::table('cart_items')
                    ->where('variant_id', $id)->exists();

                $hasFlashSale = DB::table('flash_sale_items')
                    ->where('variant_id', $id)->exists();

                $hasOrder = DB::table('order_items')
                    ->where('product_variant_id', $id)->exists();

                $hasTransactions = DB::table('inventory_transactions')
                    ->where('variant_id', $id)->exists();

                if ($hasCart || $hasFlashSale || $hasOrder || $hasTransactions) {
                    return [
                        'status' => false,
                        'code' => 409,
                        'message' => 'Không thể xóa biến thể vì đang có dữ liệu liên quan',
                    ];
                }

                // Xóa các dữ liệu phụ thuộc
                DB::table('variant_attribute_values')
                    ->where('variant_id', $id)->delete();

                DB::table('inventories')
                    ->where('variant_id', $id)->delete();

                DB::table('product_images')
                    ->where('variant_id', $id)->delete();

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
     * Tạo SKU duy nhất cho biến thể.
     */
    private function generateUniqueSku($product, $attributeValues)
    {
        // Tạo mã sản phẩm từ slug.
        // Ví dụ: iphone-17-pro-max => IP17PM
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
                    ?? strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($value)), 0, 3));
            } elseif ($attributeCode === 'ram') {
                preg_match('/\d+/', $value, $matches);
                $ramCode = $matches[0] ?? null;
            } elseif ($attributeCode === 'storage') {
                preg_match('/\d+/', $value, $matches);
                $storageCode = $matches[0] ?? null;
            } else {
                // Thuộc tính khác như công suất, kích thước, kết nối...
                $attributePrefix = strtoupper(substr($attributeCode, 0, 3));
                $attributeValueCode = strtoupper(
                    preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($value))
                );

                if ($attributePrefix && $attributeValueCode) {
                    $otherCodes[] = $attributePrefix.$attributeValueCode;
                }
            }
        }

        // Sắp xếp mã theo cấu trúc SKU điện thoại
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

        // Thêm các thuộc tính khác nếu có
        $parts = array_merge($parts, $otherCodes);

        $baseSku = implode('-', $parts);

        // Kiểm tra trùng SKU và tự thêm hậu tố
        $sku = $baseSku;
        $counter = 2;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $baseSku.'-'.$counter;
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

        // Hai ký tự đầu của từ đầu tiên
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

        // Không kiểm tra nếu biến thể chưa có thuộc tính.
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
