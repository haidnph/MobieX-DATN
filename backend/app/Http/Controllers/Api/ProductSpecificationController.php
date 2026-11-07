<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSpecification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSpecificationController extends Controller
{
    /**
     * Lấy danh sách thông số kỹ thuật.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductSpecification::with('product')
            ->orderBy('sort_order');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Xem chi tiết một thông số kỹ thuật.
     */
    public function show($id): JsonResponse
    {
        $specification = ProductSpecification::with('product')->find($id);

        if (! $specification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông số kỹ thuật.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $specification,
        ]);
    }

    /**
     * Thêm thông số kỹ thuật.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'specification_name' => 'required|string|max:255',
            'specification_value' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $specification = ProductSpecification::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Thêm thông số kỹ thuật thành công.',
            'data' => $specification,
        ], 201);
    }

    /**
     * Cập nhật thông số kỹ thuật.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $specification = ProductSpecification::find($id);

        if (! $specification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông số kỹ thuật.',
            ], 404);
        }

        $validated = $request->validate([
            'product_id' => 'sometimes|required|exists:products,id',
            'specification_name' => 'sometimes|required|string|max:255',
            'specification_value' => 'sometimes|required|string',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        $specification->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông số kỹ thuật thành công.',
            'data' => $specification,
        ]);
    }

    /**
     * Xóa thông số kỹ thuật.
     */
    public function destroy($id): JsonResponse
    {
        $specification = ProductSpecification::find($id);

        if (! $specification) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông số kỹ thuật.',
            ], 404);
        }

        $specification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa thông số kỹ thuật thành công.',
        ]);
    }
}
