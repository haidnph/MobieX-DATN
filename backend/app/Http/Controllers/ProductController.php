<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // API hiển thị danh sách điện thoại
    public function index()
    {
        $products = Product::with(['brand', 'category', 'variants'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data' => $products
        ], 200);
    }

    public function show($id)
    {
        $product = Product::with([
            'brand',
            'category',
            'variants'
        ])->find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công',
            'data' => $product
        ], 200);
    }

    // API thêm sản phẩm mới
    public function store(Request $request)
    {
        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,active,inactive,out_of_stock',
        ]);

        // Tạo sản phẩm
        $product = Product::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Thêm sản phẩm thành công',
            'data' => $product
        ], 201);
    }
    // API cập nhật sản phẩm
    public function update(Request $request, $id)
    {
        // Tìm sản phẩm theo ID
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:products,slug,' . $id,
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,active,inactive,out_of_stock',
        ]);

        // Cập nhật sản phẩm
        $product->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật sản phẩm thành công',
            'data' => $product
        ], 200);
    }
    // API xóa sản phẩm
    public function destroy($id)
    {
        // Tìm sản phẩm theo ID
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        // Xóa sản phẩm
        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Xóa sản phẩm thành công'
        ], 200);
    }
}
