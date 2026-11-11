<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCompatibilityController extends Controller
{
    /**
     * Lấy danh sách điện thoại tương thích với một phụ kiện.
     */
    public function index($id)
    {
        $accessory = Product::find($id);

        if (! $accessory) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm phụ kiện',
            ], 404);
        }

        $phones = $accessory->compatibleProducts()
            ->with(['brand', 'category'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách điện thoại tương thích thành công',
            'data' => $phones,
        ], 200);
    }

    /**
     * Thêm điện thoại tương thích cho phụ kiện.
     */
    public function store(Request $request, $id)
    {
        $accessory = Product::find($id);

        if (! $accessory) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm phụ kiện',
            ], 404);
        }

        $validated = $request->validate([
            'compatible_product_id' => 'required|integer|exists:products,id',
            'note' => 'nullable|string|max:255',
        ]);

        if ((int) $id === (int) $validated['compatible_product_id']) {
            return response()->json([
                'status' => false,
                'message' => 'Phụ kiện không thể tương thích với chính nó',
            ], 422);
        }

        $phone = Product::find($validated['compatible_product_id']);

        if (! $phone) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy điện thoại tương thích',
            ], 404);
        }

        $exists = DB::table('product_compatibilities')
            ->where('product_id', $id)
            ->where('compatible_product_id', $phone->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Điện thoại này đã được liên kết với phụ kiện',
            ], 409);
        }

        DB::table('product_compatibilities')->insert([
            'product_id' => $id,
            'compatible_product_id' => $phone->id,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thêm điện thoại tương thích thành công',
        ], 201);
    }

    /**
     * Cập nhật ghi chú tương thích.
     */
    public function update(Request $request, $id, $phoneId)
    {
        $validated = $request->validate([
            'note' => 'present|nullable|string|max:255',
        ]);

        $updated = DB::table('product_compatibilities')
            ->where('product_id', $id)
            ->where('compatible_product_id', $phoneId)
            ->update([
                'note' => $validated['note'],
            ]);

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy liên kết tương thích hoặc không có thay đổi',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật ghi chú tương thích thành công',
        ], 200);
    }

    /**
     * Xóa liên kết điện thoại tương thích.
     */
    public function destroy($id, $phoneId)
    {
        $deleted = DB::table('product_compatibilities')
            ->where('product_id', $id)
            ->where('compatible_product_id', $phoneId)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy liên kết tương thích',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Xóa điện thoại tương thích thành công',
        ], 200);
    }
}
