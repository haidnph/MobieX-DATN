<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    // API hiển thị danh sách điện thoại, hỗ trợ tìm kiếm
    public function index(Request $request)
    {
        // Kiểm tra các tham số lọc
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'ram' => 'nullable|string|max:50',
            'storage' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,active,inactive,out_of_stock',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Product::with([
            'brand',
            'category',
            'variants',
            'images',
        ]);

        // Tìm kiếm theo tên hoặc slug
        if (! empty($validated['keyword'])) {
            $keyword = $validated['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('slug', 'like', '%'.$keyword.'%');
            });
        }

        // Lọc theo thương hiệu
        if (! empty($validated['brand_id'])) {
            $query->where('brand_id', $validated['brand_id']);
        }

        // Lọc theo danh mục, bao gồm các danh mục con trực tiếp
        if (! empty($validated['category_id'])) {
            $categoryId = (int) $validated['category_id'];

            $categoryIds = Category::where('parent_id', $categoryId)
                ->pluck('id')
                ->toArray();

            $categoryIds[] = $categoryId;

            $query->whereIn('category_id', $categoryIds);
        }

        // Lọc theo trạng thái
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Lọc giá, RAM, bộ nhớ theo cùng một biến thể
        $hasVariantFilters =
            isset($validated['min_price']) ||
            isset($validated['max_price']) ||
            ! empty($validated['ram']) ||
            ! empty($validated['storage']);

        if ($hasVariantFilters) {
            $query->whereHas('variants', function ($variantQuery) use ($validated) {

                // Khoảng giá
                if (isset($validated['min_price'])) {
                    $variantQuery->where(
                        'price',
                        '>=',
                        $validated['min_price']
                    );
                }

                if (isset($validated['max_price'])) {
                    $variantQuery->where(
                        'price',
                        '<=',
                        $validated['max_price']
                    );
                }

                // RAM
                if (! empty($validated['ram'])) {
                    $ram = $validated['ram'];

                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($ram) {
                        $valueQuery
                            ->where('value', $ram)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'ram');
                            });
                    });
                }

                // Bộ nhớ trong
                if (! empty($validated['storage'])) {
                    $storage = $validated['storage'];

                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($storage) {
                        $valueQuery
                            ->where('value', $storage)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'storage');
                            });
                    });
                }
            });
        }

        // Phân trang
        $perPage = $validated['per_page'] ?? 10;

        $products = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data' => $products,
        ], 200);
    }

    public function show($id)
    {
        $product = Product::with([
            'brand',
            'category',
            'images',
            'specifications',
            'variants.inventory',
            'variants.images',
            'variants.attributeValues.attribute',
        ])->find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công',
            'data' => $product,
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
            'data' => $product,
        ], 201);
    }

    // API cập nhật sản phẩm
    public function update(Request $request, $id)
    {
        // Tìm sản phẩm theo ID
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        // Kiểm tra dữ liệu đầu vào
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($request->route('id'))],
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
            'data' => $product,
        ], 200);
    }

    // API xóa sản phẩm
    public function destroy($id)
    {
        // Tìm sản phẩm theo ID kèm các quan hệ
        $product = Product::with(['variants.inventory', 'images', 'specifications'])->find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        // Kiểm tra xem sản phẩm hoặc bất kỳ biến thể nào đã từng phát sinh đơn hàng chưa
        $variantIds = $product->variants->pluck('id')->toArray();
        $hasOrders = DB::table('order_items')
            ->where('product_id', $product->id)
            ->exists();

        if ($hasOrders) {
            // Không được hard delete để bảo vệ toàn vẹn lịch sử hóa đơn & kế toán
            $product->update(['status' => 'inactive']);
            $product->variants()->update(['is_active' => false]);

            return response()->json([
                'status' => true,
                'is_deactivated' => true,
                'message' => 'Sản phẩm "'.$product->name.'" đã có đơn hàng trong hệ thống nên không thể xóa vĩnh viễn. Hệ thống đã chuyển trạng thái sang "Ngừng kinh doanh" (inactive) để ẩn khỏi cửa hàng và bảo toàn lịch sử đơn hàng.',
                'data' => $product,
            ], 200);
        }

        // Nếu sản phẩm chưa có đơn hàng nào, tiến hành xóa sạch an toàn trong DB Transaction
        try {
            DB::transaction(function () use ($product) {
                // 1. Xóa ảnh sản phẩm
                $product->images()->delete();

                // 2. Xóa thông số kỹ thuật
                $product->specifications()->delete();

                // 3. Xóa các liên kết thuộc tính
                $product->attributeValues()->delete();

                // 4. Xóa đánh giá (nếu có)
                $product->reviews()->delete();

                // 5. Xóa các biến thể và tồn kho
                foreach ($product->variants as $variant) {
                    DB::table('cart_items')
                        ->where('product_variant_id', $variant->id)
                        ->delete();

                    DB::table('product_variant_attribute_values')
                        ->where('product_variant_id', $variant->id)
                        ->delete();

                    DB::table('inventory_transactions')
                        ->where('product_variant_id', $variant->id)
                        ->delete();

                    DB::table('inventories')
                        ->where('product_variant_id', $variant->id)
                        ->delete();

                    $variant->delete();
                }

                // 6. Xóa chính sản phẩm
                $product->delete();
            });

            return response()->json([
                'status' => true,
                'message' => 'Xóa sản phẩm thành công',
            ], 200);
        } catch (\Throwable $e) {
            $product->update(['status' => 'inactive']);
            $product->variants()->update(['is_active' => false]);

            return response()->json([
                'status' => true,
                'is_deactivated' => true,
                'message' => 'Sản phẩm có dữ liệu ràng buộc nên đã được chuyển sang trạng thái "Ngừng kinh doanh".',
                'data' => $product,
            ], 200);
        }
    }
}
