<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSpecificationDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function compare(Request $request)
    {
        $idsParam = $request->input('ids');
        if (empty($idsParam)) {
            return response()->json([
                'status' => false,
                'message' => 'Vui lòng cung cấp danh sách ID sản phẩm cần so sánh (tham số ids)',
            ], 422);
        }

        if (is_string($idsParam)) {
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        } elseif (is_array($idsParam)) {
            $ids = array_filter(array_map('intval', $idsParam));
        } else {
            $ids = [];
        }

        if (empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => 'Danh sách ID sản phẩm không hợp lệ',
            ], 422);
        }

        // Tối đa 4 sản phẩm so sánh cùng lúc
        $ids = array_slice(array_unique($ids), 0, 4);

        $products = Product::with([
            'brand',
            'category',
            'images',
            'specifications.definition',
            'variants.inventory',
            'variants.attributeValues.attribute',
        ])->whereIn('id', $ids)->get();

        $comparableDefinitions = ProductSpecificationDefinition::where('is_active', true)
            ->where('is_comparable', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Lấy dữ liệu so sánh sản phẩm thành công',
            'data' => [
                'products' => $products,
                'comparable_definitions' => $comparableDefinitions,
            ],
        ], 200);
    }

    public function show($id)
    {
        $product = Product::with([
            'brand',
            'category',
            'images',
            'specifications.definition',
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

        // Tự động bảo toàn dữ liệu: Đảm bảo sản phẩm luôn có ít nhất 1 biến thể có tồn kho để mua hàng
        if ($product->variants->isEmpty()) {
            $cleanCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($product->slug ?? $product->name)), 0, 8));
            $defaultSku = "MBX-{$cleanCode}-STD-{$product->id}";

            $variant = $product->variants()->create([
                'sku' => $defaultSku,
                'option_signature' => 'Phiên bản tiêu chuẩn',
                'price' => 15990000.00,
                'compare_price' => 17990000.00,
                'cost_price' => 13000000.00,
                'is_active' => true,
            ]);

            $variant->inventory()->create([
                'on_hand_quantity' => 50,
                'reserved_quantity' => 0,
                'min_stock' => 5,
            ]);

            if ($product->images->isEmpty()) {
                $product->images()->create([
                    'product_variant_id' => $variant->id,
                    'image_path' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&auto=format&fit=crop&q=80',
                    'alt_text' => $product->name,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }

            $product->load([
                'images',
                'variants.inventory',
                'variants.images',
                'variants.attributeValues.attribute',
            ]);
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
            'price' => 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:1000',
        ]);

        $product = DB::transaction(function () use ($validated) {
            $productData = collect($validated)->only([
                'name', 'slug', 'description', 'brand_id', 'category_id', 'status',
            ])->toArray();

            $product = Product::create($productData);

            // Tự động tạo biến thể mặc định và tồn kho ban đầu
            $cleanCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($product->slug ?? $product->name)), 0, 8));
            $sku = ! empty($validated['sku'])
                ? strtoupper(trim($validated['sku']))
                : "MBX-{$cleanCode}-STD-{$product->id}";

            $price = isset($validated['price']) ? (float) $validated['price'] : 15990000.00;
            $comparePrice = isset($validated['compare_price']) ? (float) $validated['compare_price'] : null;
            $stockQuantity = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : 50;

            $variant = $product->variants()->create([
                'sku' => $sku,
                'option_signature' => 'Phiên bản tiêu chuẩn',
                'price' => $price,
                'compare_price' => $comparePrice,
                'is_active' => true,
            ]);

            $variant->inventory()->create([
                'on_hand_quantity' => $stockQuantity,
                'reserved_quantity' => 0,
                'min_stock' => 5,
            ]);

            // Thêm hình ảnh ban đầu nếu có
            $imageUrl = ! empty($validated['image_url'])
                ? $validated['image_url']
                : 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&auto=format&fit=crop&q=80';

            $product->images()->create([
                'product_variant_id' => $variant->id,
                'image_path' => $imageUrl,
                'alt_text' => $product->name,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return $product;
        });

        $product->load(['brand', 'category', 'images', 'variants.inventory']);

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
            'price' => 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $productData = collect($validated)->only([
                'name', 'slug', 'description', 'brand_id', 'category_id', 'status',
            ])->toArray();

            $product->update($productData);

            // Cập nhật giá & tồn kho của biến thể đầu tiên nếu có truyền lên
            $firstVariant = $product->variants()->first();
            if ($firstVariant) {
                if (isset($validated['price'])) {
                    $firstVariant->price = $validated['price'];
                }
                if (array_key_exists('compare_price', $validated)) {
                    $firstVariant->compare_price = $validated['compare_price'];
                }
                $firstVariant->save();

                if (isset($validated['stock_quantity'])) {
                    $inventory = $firstVariant->inventory;
                    if ($inventory) {
                        $inventory->on_hand_quantity = (int) $validated['stock_quantity'];
                        $inventory->save();
                    } else {
                        $firstVariant->inventory()->create([
                            'on_hand_quantity' => (int) $validated['stock_quantity'],
                            'reserved_quantity' => 0,
                            'min_stock' => 5,
                        ]);
                    }
                }
            } elseif (isset($validated['price']) || isset($validated['stock_quantity'])) {
                $cleanCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($product->slug ?? $product->name)), 0, 8));
                $newVar = $product->variants()->create([
                    'sku' => "MBX-{$cleanCode}-STD-{$product->id}",
                    'option_signature' => 'Phiên bản tiêu chuẩn',
                    'price' => $validated['price'] ?? 15990000.00,
                    'compare_price' => $validated['compare_price'] ?? null,
                    'is_active' => true,
                ]);
                $newVar->inventory()->create([
                    'on_hand_quantity' => $validated['stock_quantity'] ?? 50,
                    'reserved_quantity' => 0,
                    'min_stock' => 5,
                ]);
            }

            if (! empty($validated['image_url'])) {
                $primaryImg = $product->images()->where('is_primary', true)->first();
                if ($primaryImg) {
                    $primaryImg->update(['image_path' => $validated['image_url']]);
                } else {
                    $product->images()->create([
                        'image_path' => $validated['image_url'],
                        'alt_text' => $product->name,
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]);
                }
            }
        });

        $product->load(['brand', 'category', 'images', 'variants.inventory']);

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

    /**
     * Bulk update status for multiple products.
     */
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:products,id',
            'status' => 'required|in:active,draft,inactive,out_of_stock',
        ]);

        $status = $validated['status'];
        $isActive = ($status === 'active');

        DB::transaction(function () use ($validated, $status, $isActive) {
            Product::whereIn('id', $validated['ids'])->update(['status' => $status]);
            ProductVariant::whereIn('product_id', $validated['ids'])->update(['is_active' => $isActive]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật trạng thái cho '.count($validated['ids']).' sản phẩm thành công.',
        ]);
    }

    /**
     * Bulk safe-delete multiple products.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:products,id',
        ]);

        $deletedCount = 0;
        $deactivatedCount = 0;

        foreach ($validated['ids'] as $id) {
            $product = Product::find($id);
            if (! $product) {
                continue;
            }

            $hasOrders = DB::table('order_items')
                ->where('product_id', $product->id)
                ->exists();

            if ($hasOrders) {
                $product->update(['status' => 'inactive']);
                $product->variants()->update(['is_active' => false]);
                $deactivatedCount++;

                continue;
            }

            try {
                DB::transaction(function () use ($product) {
                    $product->images()->delete();
                    $product->specifications()->delete();
                    $product->attributeValues()->delete();
                    $product->reviews()->delete();

                    foreach ($product->variants as $variant) {
                        DB::table('cart_items')->where('product_variant_id', $variant->id)->delete();
                        DB::table('product_variant_attribute_values')->where('product_variant_id', $variant->id)->delete();
                        DB::table('inventory_transactions')->where('product_variant_id', $variant->id)->delete();
                        DB::table('inventories')->where('product_variant_id', $variant->id)->delete();
                        $variant->delete();
                    }

                    $product->delete();
                });
                $deletedCount++;
            } catch (\Throwable $e) {
                $product->update(['status' => 'inactive']);
                $product->variants()->update(['is_active' => false]);
                $deactivatedCount++;
            }
        }

        return response()->json([
            'status' => true,
            'deleted_count' => $deletedCount,
            'deactivated_count' => $deactivatedCount,
            'message' => "Đã xử lý: {$deletedCount} sản phẩm đã xóa, {$deactivatedCount} sản phẩm đã chuyển sang ẩn (do có đơn hàng).",
        ]);
    }
}
