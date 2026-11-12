<?php


namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductSpecificationDefinition;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Tạo SKU duy nhất cho biến thể.
     */
    private function generateVariantSku(Product $product, ?string $requestedSku = null): string
    {
        if (! empty($requestedSku)) {
            $sku = strtoupper(trim($requestedSku));

            if (ProductVariant::where('sku', $sku)->exists()) {
                throw ValidationException::withMessages([
                    'sku' => ['SKU này đã tồn tại. Vui lòng sử dụng SKU khác.'],
                ]);
            }

            return $sku;
        }

        $source = Str::ascii($product->slug ?: $product->name);
        $cleanCode = strtoupper(substr(
            preg_replace('/[^A-Za-z0-9]/', '', $source) ?: 'PRODUCT',
            0,
            8
        ));

        $baseSku = "MBX-{$cleanCode}-STD-{$product->id}";
        $sku = $baseSku;
        $suffix = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $baseSku . '-' . $suffix;
            $suffix++;
        }

        return $sku;
    }

    /**
     * Xác định sản phẩm có thuộc nhóm phụ kiện hay không.
     */
    private function isAccessoryCategory(?int $categoryId): bool
    {
        if (! $categoryId) {
            return false;
        }

        $accessoryCategory = Category::where('name', 'Phụ kiện')
            ->whereNull('parent_id')
            ->first();

        if (! $accessoryCategory) {
            return false;
        }

        $selectedCategory = Category::find($categoryId);

        if (! $selectedCategory) {
            return false;
        }

        return (int) $selectedCategory->id === (int) $accessoryCategory->id
            || (int) $selectedCategory->parent_id === (int) $accessoryCategory->id;
    }

    /**
     * Ghi lịch sử điều chỉnh tồn kho.
     */
    private function createInventoryTransaction(
        ProductVariant $variant,
        int $quantity,
        string $type,
        int $productId,
        string $note
    ): void {
        if ($quantity <= 0) {
            return;
        }

        InventoryTransaction::create([
            'variant_id' => $variant->id,
            'user_id' => Auth::id(),
            'transaction_type' => $type,
            'quantity' => $quantity,
            'reference_type' => 'product_update',
            'reference_id' => $productId,
            'note' => $note,
        ]);
    }

    /**
     * API hiển thị danh sách sản phẩm.
     */
    public function index(Request $request)
    {
        if ($request->filled('q') && ! $request->filled('keyword')) {
            $request->merge(['keyword' => $request->q]);
        } elseif ($request->filled('search') && ! $request->filled('keyword')) {
            $request->merge(['keyword' => $request->search]);
        }

        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'brand_id' => 'nullable',
            'category_id' => 'nullable|integer|exists:categories,id',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'ram' => 'nullable|string|max:50',
            'storage' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,active,inactive,out_of_stock',
            'sort_by' => 'nullable|string|in:price_asc,price_desc,newest,oldest,name_asc,name_desc,popular',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Product::with([
            'brand',
            'category',
            'variants.inventory',
            'variants.attributeValues.attribute',
            'images',
        ]);

        if (! empty($validated['keyword'])) {
            $keyword = $validated['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            });
        }

        // Lọc theo thương hiệu (hỗ trợ single id, mảng id hoặc chuỗi phân cách bởi dấu phẩy)
        if (! empty($validated['brand_id'])) {
            $rawBrand = $validated['brand_id'];
            if (is_array($rawBrand)) {
                $brandIds = array_filter(array_map('intval', $rawBrand));
            } else {
                $brandIds = array_filter(array_map('intval', explode(',', (string) $rawBrand)));
            }
            if (! empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
            }
        }

        if (! empty($validated['category_id'])) {
            $categoryId = (int) $validated['category_id'];

            $categoryIds = Category::where('parent_id', $categoryId)
                ->pluck('id')
                ->toArray();

            $categoryIds[] = $categoryId;

            $query->whereIn('category_id', $categoryIds);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $hasVariantFilters =
            isset($validated['min_price']) ||
            isset($validated['max_price']) ||
            ! empty($validated['ram']) ||
            ! empty($validated['storage']);

        if ($hasVariantFilters) {
            $query->whereHas('variants', function ($variantQuery) use ($validated) {
                if (isset($validated['min_price'])) {
                    $variantQuery->where('price', '>=', $validated['min_price']);
                }

                if (isset($validated['max_price'])) {
                    $variantQuery->where('price', '<=', $validated['max_price']);
                }

                if (! empty($validated['ram'])) {
                    $ram = $validated['ram'];

                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($ram) {
                        $valueQuery->where('value', $ram)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'ram');
                            });
                    });
                }

                if (! empty($validated['storage'])) {
                    $storage = $validated['storage'];

                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($storage) {
                        $valueQuery->where('value', $storage)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'storage');
                            });
                    });
                }
            });
        }

        // Sắp xếp
        $sortBy = $validated['sort_by'] ?? 'newest';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy(
                    ProductVariant::selectRaw('COALESCE(MIN(price), 0)')
                        ->whereColumn('product_variants.product_id', 'products.id'),
                    'asc'
                );
                break;
            case 'price_desc':
                $query->orderBy(
                    ProductVariant::selectRaw('COALESCE(MAX(price), 0)')
                        ->whereColumn('product_variants.product_id', 'products.id'),
                    'desc'
                );
                break;
            case 'name_asc':
                $query->orderByRaw('LOWER(name) asc');
                break;
            case 'name_desc':
                $query->orderByRaw('LOWER(name) desc');
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'popular':
                $query->withCount('reviews')->orderByDesc('reviews_count')->latest();
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        // Phân trang
        $perPage = $validated['per_page'] ?? 10;

        $products = $query
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data' => $products,
        ], 200);
    }

    /**
     * API so sánh sản phẩm.
     */
    public function compare(Request $request)
    {
        $idsParam = $request->input('ids');

        if (empty($idsParam)) {
            return response()->json([
                'status' => false,
                'message' => 'Vui lòng cung cấp danh sách ID sản phẩm cần so sánh.',
            ], 422);
        }

        if (is_string($idsParam)) {
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        } elseif (is_array($idsParam)) {
            $ids = array_filter(array_map('intval', $idsParam));
        } else {
            $ids = [];
        }

        $ids = array_slice(array_unique($ids), 0, 4);

        if (empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => 'Danh sách ID sản phẩm không hợp lệ.',
            ], 422);
        }

        $products = Product::with([
            'brand',
            'category',
            'images',
            'specifications.definition',
            'variants.inventory',
            'variants.attributeValues.attribute',
        ])->whereIn('id', $ids)->get();

        $comparableDefinitions = \App\Models\ProductSpecificationDefinition::query()
            ->where('is_comparable', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($comparableDefinitions->isEmpty()) {
            $comparableDefinitions = \App\Models\ProductSpecification::query()
                ->select('specification_name')
                ->distinct()
                ->whereNotNull('specification_name')
                ->orderBy('specification_name')
                ->get();
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy dữ liệu so sánh sản phẩm thành công',
            'data' => [
                'products' => $products,
                'comparable_definitions' => $comparableDefinitions,
            ],
        ], 200);
    }

    /**
     * Lấy danh sách điện thoại đang hoạt động để chọn sản phẩm tương thích.
     */
    public function compatiblePhones(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $phoneCategory = Category::where('name', 'Điện thoại')
            ->whereNull('parent_id')
            ->first();

        if (! $phoneCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy danh mục Điện thoại',
            ], 404);
        }

        $categoryIds = Category::where('parent_id', $phoneCategory->id)
            ->pluck('id')
            ->toArray();

        $categoryIds[] = $phoneCategory->id;

        $query = Product::with(['brand', 'category'])
            ->whereIn('category_id', $categoryIds)
            ->where('status', 'active');

        if (! empty($validated['keyword'])) {
            $keyword = $validated['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            });
        }

        $products = $query
            ->latest()
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách điện thoại tương thích thành công',
            'data' => $products,
        ], 200);
    }

    /**
     * API lấy chi tiết sản phẩm.
     */
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

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công',
            'data' => $product,
        ], 200);
    }

    /**
     * API thêm sản phẩm mới.
     */
    public function store(Request $request)
    {
        $isAccessory = $this->isAccessoryCategory(
            $request->filled('category_id')
                ? (int) $request->input('category_id')
                : null
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,active,inactive,out_of_stock',
            'price' => $isAccessory
                ? 'required|numeric|min:0'
                : 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('product_variants', 'sku'),
            ],
            'image_url' => 'nullable|string|max:1000',
        ]);

        $product = DB::transaction(function () use ($validated, $isAccessory) {
            $productData = collect($validated)->only([
                'name',
                'slug',
                'description',
                'brand_id',
                'category_id',
                'status',
            ])->toArray();

            $product = Product::create($productData);

            $sku = $this->generateVariantSku(
                $product,
                $validated['sku'] ?? null
            );

            $price = isset($validated['price'])
                ? (float) $validated['price']
                : ($isAccessory ? 0 : 15990000);

            $stockQuantity = isset($validated['stock_quantity'])
                ? (int) $validated['stock_quantity']
                : ($isAccessory ? 0 : 50);

            $variant = $product->variants()->create([
                'sku' => $sku,
                'price' => $price,
                'compare_price' => $validated['compare_price'] ?? null,
                'status' => 'active',
            ]);

            $variant->inventory()->create([
                'quantity' => $stockQuantity,
                'reserved_quantity' => 0,
                'min_stock' => 5,
            ]);

            // Ghi nhận số lượng tồn kho ban đầu nếu có nhập tồn.
            if ($stockQuantity > 0) {
                $this->createInventoryTransaction(
                    $variant,
                    $stockQuantity,
                    'import',
                    $product->id,
                    'Ghi nhận tồn kho ban đầu khi tạo sản phẩm'
                );
            }

            if (! empty($validated['image_url'])) {
                $imageUrl = $validated['image_url'];
            } elseif (! $isAccessory) {
                $imageUrl = 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&auto=format&fit=crop&q=80';
            } else {
                $imageUrl = null;
            }

            if ($imageUrl !== null) {
                $product->images()->create([
                    'variant_id' => $variant->id,
                    'image_url' => $imageUrl,
                    'alt_text' => $product->name,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }

            return $product;
        });

        $product->load([
            'brand',
            'category',
            'images',
            'variants.inventory',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thêm sản phẩm thành công',
            'data' => $product,
        ], 201);
    }

    /**
     * API cập nhật sản phẩm.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,active,inactive,out_of_stock',
            'price' => 'sometimes|nullable|numeric|min:0',
            'compare_price' => 'sometimes|nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|nullable|integer|min:0',
            'image_url' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($product, $validated) {
            // Cập nhật thông tin sản phẩm.
            $productData = collect($validated)->only([
                'name',
                'slug',
                'description',
                'brand_id',
                'category_id',
                'status',
            ])->toArray();

            $product->update($productData);

            $firstVariant = $product->variants()
                ->orderBy('id')
                ->first();

            // Tạo biến thể nếu sản phẩm chưa có biến thể.
            if (
                ! $firstVariant &&
                (
                    isset($validated['price']) ||
                    array_key_exists('compare_price', $validated) ||
                    isset($validated['stock_quantity'])
                )
            ) {
                $firstVariant = $product->variants()->create([
                    'sku' => $this->generateVariantSku($product),
                    'price' => $validated['price'] ?? 0,
                    'compare_price' => $validated['compare_price'] ?? null,
                    'status' => 'active',
                ]);
            }

            if ($firstVariant) {
                $variantData = [];

                if (array_key_exists('price', $validated)
                    && $validated['price'] !== null) {
                    $variantData['price'] = $validated['price'];
                }

                if (array_key_exists('compare_price', $validated)) {
                    $variantData['compare_price'] = $validated['compare_price'];
                }

                if (! empty($variantData)) {
                    $firstVariant->update($variantData);
                }

                // Chỉ điều chỉnh tồn kho khi request có gửi stock_quantity.
                if (
                    array_key_exists('stock_quantity', $validated) &&
                    $validated['stock_quantity'] !== null
                ) {
                    $inventory = $firstVariant->inventory()
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        $inventory = $firstVariant->inventory()->create([
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'min_stock' => 5,
                        ]);
                    }

                    $oldQuantity = (int) $inventory->quantity;
                    $newQuantity = (int) $validated['stock_quantity'];
                    $reservedQuantity = (int) $inventory->reserved_quantity;

                    if ($newQuantity < $reservedQuantity) {
                        throw ValidationException::withMessages([
                            'stock_quantity' => [
                                'Tồn kho không được nhỏ hơn số lượng sản phẩm đang được giữ chỗ.',
                            ],
                        ]);
                    }

                    $difference = $newQuantity - $oldQuantity;

                    if ($difference !== 0) {
                        $inventory->update([
                            'quantity' => $newQuantity,
                        ]);

                        $this->createInventoryTransaction(
                            $firstVariant,
                            abs($difference),
                            $difference > 0 ? 'import' : 'export',
                            $product->id,
                            $difference > 0
                                ? 'Điều chỉnh tăng tồn kho khi cập nhật sản phẩm'
                                : 'Điều chỉnh giảm tồn kho khi cập nhật sản phẩm'
                        );
                    }
                }
            }

            // Cập nhật ảnh đại diện.
            if (! empty($validated['image_url'])) {
                $primaryImg = $product->images()
                    ->where('is_primary', true)
                    ->first();

                if ($primaryImg) {
                    $primaryImg->update([
                        'image_url' => $validated['image_url'],
                    ]);
                } else {
                    $product->images()->create([
                        'image_url' => $validated['image_url'],
                        'alt_text' => $product->name,
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]);
                }
            }
        });

        $product->load([
            'brand',
            'category',
            'images',
            'variants.inventory',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật sản phẩm thành công',
            'data' => $product,
        ], 200);
    }

    /**
     * Ngừng kinh doanh một sản phẩm.
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        DB::transaction(function () use ($product) {
            $product->update([
                'status' => 'inactive',
            ]);

            $product->variants()->update([
                'status' => 'inactive',
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Đã ngừng kinh doanh sản phẩm thành công.',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'status' => 'inactive',
            ],
        ], 200);
    }

    /**
     * Cập nhật trạng thái nhiều sản phẩm.
     */
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|distinct|exists:products,id',
            'status' => 'required|in:active,draft,inactive,out_of_stock',
        ]);

        DB::transaction(function () use ($validated) {
            Product::whereIn('id', $validated['ids'])
                ->update([
                    'status' => $validated['status'],
                ]);

            // Chỉ thay đổi trạng thái biến thể khi sản phẩm được
            // kích hoạt hoặc ngừng kinh doanh.
            if (in_array($validated['status'], ['active', 'inactive'], true)) {
                ProductVariant::whereIn('product_id', $validated['ids'])
                    ->update([
                        'status' => $validated['status'],
                    ]);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật trạng thái cho '
                . count($validated['ids'])
                . ' sản phẩm thành công.',
        ], 200);
    }

    /**
     * Ngừng kinh doanh nhiều sản phẩm.
     * Không xóa vật lý để bảo toàn dữ liệu liên quan.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|distinct|exists:products,id',
        ]);

        $count = count($validated['ids']);

        DB::transaction(function () use ($validated) {
            Product::whereIn('id', $validated['ids'])
                ->update([
                    'status' => 'inactive',
                ]);

            ProductVariant::whereIn('product_id', $validated['ids'])
                ->update([
                    'status' => 'inactive',
                ]);
        });

        return response()->json([
            'status' => true,
            'deactivated_count' => $count,
            'message' => "Đã ngừng kinh doanh {$count} sản phẩm thành công.",
        ], 200);
    }

    /**
     * Đồng bộ thông số kỹ thuật của sản phẩm.
     */
    public function syncSpecifications(Request $request, $product)
    {
        $product = $product instanceof Product ? $product : Product::find($product);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        $specs = $request->input('specs', []);
        $first = $specs[0] ?? [];

        if (empty($specs) || array_key_exists('definition_id', $first)) {
            $data = $request->validate([
                'specs' => 'present|array',
                'specs.*.definition_id' => 'required|exists:product_specification_definitions,id',
                'specs.*.value' => 'nullable',
                'specs.*.sort_order' => 'nullable|integer|min:0',
            ]);

            \Illuminate\Support\Facades\DB::transaction(function () use ($product, $data) {
                foreach ($data['specs'] as $index => $spec) {
                    $def = \App\Models\ProductSpecificationDefinition::find($spec['definition_id']);
                    $payload = [
                        'product_id' => $product->id,
                        'specification_definition_id' => $spec['definition_id'],
                        'value_text' => null,
                        'numeric_value' => null,
                        'value_boolean' => null,
                        'sort_order' => $spec['sort_order'] ?? ($index + 1),
                    ];

                    if ($def) {
                        if ($def->value_type === 'number') {
                            $payload['numeric_value'] = is_numeric($spec['value']) ? (float) $spec['value'] : null;
                        } elseif ($def->value_type === 'boolean') {
                            $payload['value_boolean'] = filter_var($spec['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        } else {
                            $payload['value_text'] = $spec['value'] !== null ? (string) $spec['value'] : null;
                        }
                    }

                    \App\Models\ProductSpecification::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'specification_definition_id' => $spec['definition_id'],
                        ],
                        $payload
                    );
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'Specifications synced',
                'count' => count($data['specs']),
                'data' => $product->specifications()->with('definition')->orderBy('sort_order')->get(),
            ], 200);
        }

        $validated = $request->validate([
            'specs' => ['present', 'array'],
            'specs.*.specification_name' => [
                'required',
                'string',
                'max:255',
            ],
            'specs.*.specification_value' => [
                'required',
                'string',
                'max:1000',
            ],
            'specs.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $validated) {
            $product->specifications()->delete();

            foreach ($validated['specs'] as $index => $spec) {
                $code = \Illuminate\Support\Str::slug($spec['specification_name']);
                $def = \App\Models\ProductSpecificationDefinition::firstOrCreate(
                    ['code' => $code ?: ('spec_' . ($index + 1))],
                    [
                        'name' => trim($spec['specification_name']),
                        'value_type' => 'text',
                        'applies_to' => 'all',
                        'is_comparable' => true,
                        'is_active' => true,
                    ]
                );

                $product->specifications()->create([
                    'specification_definition_id' => $def->id,
                    'value_text' => trim($spec['specification_value']),
                    'sort_order' => $spec['sort_order'] ?? $index,
                ]);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Đồng bộ thông số kỹ thuật thành công.',
            'count' => count($validated['specs']),
            'data' => $product->specifications()
                ->with('definition')
                ->orderBy('sort_order')
                ->get(),
        ], 200);
    }
}
