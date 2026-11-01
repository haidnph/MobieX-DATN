<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    // Lấy danh sách hình ảnh
    public function index(Request $request)
    {
        $query = ProductImage::with(['product', 'variant'])
            ->orderBy('sort_order');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    }

    // Thêm hình ảnh
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'image_url' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
        ]);

        // Kiểm tra biến thể có thuộc sản phẩm được chọn không
        if (!empty($validated['variant_id'])) {
            $variant = ProductVariant::findOrFail($validated['variant_id']);

            if ($variant->product_id != $validated['product_id']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Biến thể không thuộc sản phẩm này.',
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated) {
            // Nếu ảnh mới là ảnh chính, bỏ ảnh chính cũ của sản phẩm
            if (!empty($validated['is_primary'])) {
                ProductImage::where('product_id', $validated['product_id'])
                    ->update(['is_primary' => false]);
            }

            $image = ProductImage::create($validated);

            return response()->json([
                'status' => true,
                'message' => 'Thêm hình ảnh sản phẩm thành công.',
                'data' => $image,
            ], 201);
        });
    }

    // Cập nhật hình ảnh
    public function update(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);

        $validated = $request->validate([
            'variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'image_url' => 'sometimes|required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'is_primary' => 'sometimes|boolean',
        ]);

        if (
            array_key_exists('variant_id', $validated)
            && !empty($validated['variant_id'])
        ) {
            $variant = ProductVariant::findOrFail($validated['variant_id']);

            if ($variant->product_id != $image->product_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Biến thể không thuộc sản phẩm này.',
                ], 422);
            }
        }

        return DB::transaction(function () use ($image, $validated) {
            if (!empty($validated['is_primary'])) {
                ProductImage::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_primary' => false]);
            }

            $image->update($validated);

            return response()->json([
                'status' => true,
                'message' => 'Cập nhật hình ảnh thành công.',
                'data' => $image->fresh(),
            ]);
        });
    }

    // Xóa hình ảnh
    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);
        $image->delete();

        return response()->json([
            'status' => true,
            'message' => 'Xóa hình ảnh thành công.',
        ]);
    }
}