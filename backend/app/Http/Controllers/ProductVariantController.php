<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    // API lấy danh sách biến thể sản phẩm
    public function index()
    {
        $variants = ProductVariant::with([
            'product',
            'attributeValues.attribute'
        ])->latest()->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách biến thể thành công',
            'data' => $variants
        ], 200);
    }


    // API thêm biến thể sản phẩm
    public function store(Request $request)
    {
        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:100|unique:product_variants,sku',
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

        // Thực hiện tạo biến thể và tồn kho trong một giao dịch
        $variant = DB::transaction(function () use ($validated) {

            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku' => $validated['sku'],
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
            'attributeValues.attribute'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thêm biến thể sản phẩm thành công',
            'data' => $variant
        ], 201);
    }
    // API xem chi tiết biến thể sản phẩm
    public function show($id)
    {
        $variant = ProductVariant::with([
            'product',
            'attributeValues.attribute'
        ])->find($id);

        if (!$variant) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy biến thể sản phẩm'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết biến thể thành công',
            'data' => $variant
        ], 200);
    }

    // API cập nhật biến thể sản phẩm
    public function update(Request $request, $id)
    {
        $variant = ProductVariant::find($id);

        if (!$variant) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy biến thể sản phẩm'
            ], 404);
        }

        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'sku' => 'sometimes|required|string|max:100|unique:product_variants,sku,' . $id,
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

        DB::transaction(function () use ($variant, $validated) {

            // Cập nhật thông tin biến thể
            $variant->update([
                'sku' => $validated['sku'] ?? $variant->sku,
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
            'attributeValues.attribute'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật biến thể sản phẩm thành công',
            'data' => $variant
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

                if (!$variant) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Không tìm thấy biến thể sản phẩm'
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
                        'message' => 'Không thể xóa biến thể vì đang có dữ liệu liên quan'
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
                    'message' => 'Xóa biến thể sản phẩm thành công'
                ];
            });

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message']
            ], $result['code']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Xóa biến thể thất bại',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
