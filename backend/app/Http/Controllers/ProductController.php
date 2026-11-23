<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSaleItem;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDefinition;
use App\Models\ProductVariant;
use App\Services\MediaStorageService;
use App\Services\ProductComparisonService;
use App\Services\Search\SearchIntentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\AttributeValue;

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
     * Tạo SKU tự động dựa trên các giá trị thuộc tính của biến thể.
     */
    private function generateVariantSkuFromAttributes(Product $product, $attributeValues): string
    {
        $productCode = strtoupper(substr(
            preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($product->slug ?: $product->name)) ?: 'PRODUCT',
            0,
            8
        ));

        $attributeCodes = $attributeValues->map(function ($item) {
            $source = $item->code ?: $item->value;

            return strtoupper(
                preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($source)) ?: 'VALUE'
            );
        })->implode('-');

        $baseSku = "MBX-{$productCode}-{$attributeCodes}";
        $baseSku = substr($baseSku, 0, 45);

        $sku = $baseSku;
        $suffix = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $ending = '-' . $suffix;
            $sku = substr($baseSku, 0, 50 - strlen($ending)) . $ending;
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
     * Lấy danh sách ID danh mục bao gồm danh mục hiện tại và tất cả các danh mục con cháu.
     */
    private function getAllCategoryIdsRecursively(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::where('parent_id', $categoryId)->pluck('id')->toArray();
        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getAllCategoryIdsRecursively((int) $childId));
        }

        return array_values(array_unique($ids));
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
            'product_variant_id' => $variant->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity_delta' => in_array($type, ['export', 'dispatch'], true) ? -$quantity : $quantity,
            'unit_cost' => $variant->cost_price,
            'reference_type' => 'product_update',
            'reference_id' => $productId,
            'idempotency_key' => 'tx_' . (string) Str::uuid(),
            'note' => $note,
        ]);
    }

    /**
     * Trả về gợi ý đa tầng (sản phẩm, thương hiệu, danh mục, bí danh, sửa lỗi) cho autocomplete.
     */
    private function searchSuggestions(string $rawKeyword)
    {
        $keyword = trim($rawKeyword);
        $parser = new SearchIntentParser;
        $intent = $parser->parse($keyword);

        $effectiveTerm = $intent->modelAlias ?: ($intent->normalizedQuery ?: $keyword);
        $cleanLower = mb_strtolower($effectiveTerm, 'UTF-8');
        $slugKeyword = Str::slug($effectiveTerm);
        $tokens = ! empty($intent->searchTokens) ? $intent->searchTokens : array_filter(preg_split('/\s+/', $effectiveTerm), fn($t) => mb_strlen($t) > 0);

        // 1. Gợi ý thương hiệu trùng khớp (kể cả brand từ intent)
        $brandQuery = Brand::query()->where('is_active', true);
        if ($intent->brandId) {
            $brandQuery->where(function ($bq) use ($intent, $keyword, $slugKeyword) {
                $bq->where('id', $intent->brandId)
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$slugKeyword}%");
            });
        } else {
            $brandQuery->where(function ($bq) use ($keyword, $slugKeyword) {
                $bq->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$slugKeyword}%");
            });
        }
        $brands = $brandQuery
            ->withCount(['products' => fn($pq) => $pq->whereIn('status', ['active', 'out_of_stock'])])
            ->limit(3)
            ->get(['id', 'name', 'slug', 'logo_path']);

        // 2. Gợi ý danh mục trùng khớp (kể cả category từ intent)
        $categoryQuery = Category::query()->where('is_active', true);
        if ($intent->categoryId) {
            $categoryQuery->where(function ($cq) use ($intent, $keyword, $slugKeyword) {
                $cq->where('id', $intent->categoryId)
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$slugKeyword}%");
            });
        } else {
            $categoryQuery->where(function ($cq) use ($keyword, $slugKeyword) {
                $cq->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$slugKeyword}%");
            });
        }
        $categories = $categoryQuery
            ->withCount(['products' => fn($pq) => $pq->whereIn('status', ['active', 'out_of_stock'])])
            ->limit(3)
            ->get(['id', 'name', 'slug', 'parent_id']);

        // 3. Gợi ý sản phẩm phù hợp nhất (áp dụng relevance ranking)
        $productQuery = Product::query()
            ->whereIn('status', ['active', 'out_of_stock']);

        // Apply detected brand/category if found
        if ($intent->brandId) {
            $productQuery->where('brand_id', $intent->brandId);
        }
        if ($intent->categoryId) {
            $catIds = $this->getAllCategoryIdsRecursively($intent->categoryId);
            $productQuery->whereIn('category_id', $catIds);
        }

        // Apply search keyword matching
        if ($effectiveTerm !== '') {
            $productQuery->where(function ($sub) use ($effectiveTerm, $tokens, $slugKeyword, $keyword) {
                $sub->where(function ($phraseQuery) use ($effectiveTerm, $slugKeyword, $keyword) {
                    $phraseQuery->where('products.name', 'like', "%{$effectiveTerm}%")
                        ->orWhere('products.slug', 'like', "%{$effectiveTerm}%")
                        ->orWhere('products.description', 'like', "%{$effectiveTerm}%")
                        ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$effectiveTerm}%"))
                        ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$effectiveTerm}%"))
                        ->orWhereHas('variants', fn($v) => $v->where('sku', 'like', "%{$effectiveTerm}%"));

                    if ($keyword !== $effectiveTerm) {
                        $phraseQuery->orWhere('products.name', 'like', "%{$keyword}%");
                    }
                    if ($slugKeyword && $slugKeyword !== $effectiveTerm) {
                        $phraseQuery->orWhere('products.slug', 'like', "%{$slugKeyword}%");
                    }
                });

                if (count($tokens) > 1) {
                    $sub->orWhere(function ($tokenQuery) use ($tokens) {
                        foreach ($tokens as $token) {
                            $tokenSlug = Str::slug($token);
                            $tokenQuery->where(function ($q) use ($token, $tokenSlug) {
                                $q->where('products.name', 'like', "%{$token}%")
                                    ->orWhere('products.description', 'like', "%{$token}%")
                                    ->orWhere('products.slug', 'like', "%{$tokenSlug}%")
                                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$token}%"))
                                    ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$token}%"))
                                    ->orWhereHas('variants', fn($v) => $v->where('sku', 'like', "%{$token}%"));

                                if ($tokenSlug && $tokenSlug !== $token) {
                                    $q->orWhere('products.slug', 'like', "%{$tokenSlug}%");
                                }
                            });
                        }
                    });
                }
            });
        }

        $totalCount = (clone $productQuery)->count();

        // Relevance ordering
        $aliasLower = $intent->modelAlias ? mb_strtolower($intent->modelAlias, 'UTF-8') : '';
        $products = $productQuery
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'images' => fn($iq) => $iq->select('id', 'product_id', 'image_path', 'is_primary')->orderBy('sort_order'),
                'variants' => fn($vq) => $vq
                    ->where('is_active', true)
                    ->select('id', 'product_id', 'price', 'compare_price', 'is_active'),
            ])
            ->orderByRaw(
                'CASE 
                    WHEN ? != "" AND LOWER(products.name) LIKE ? THEN 150
                    WHEN LOWER(products.name) = ? THEN 100
                    WHEN LOWER(products.name) LIKE ? THEN 80
                    WHEN LOWER(products.name) LIKE ? THEN 60
                    ELSE 10 
                END DESC',
                [
                    $aliasLower,
                    $aliasLower ? "%{$aliasLower}%" : '',
                    $cleanLower,
                    "{$cleanLower}%",
                    "%{$cleanLower}%",
                ]
            )
            ->latest('products.id')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'category_id', 'brand_id', 'status']);

        return response()->json([
            'status' => true,
            'message' => 'Gợi ý tìm kiếm thành công',
            'data' => [
                'keyword' => $keyword,
                'intent' => $intent->toArray(),
                'has_typo' => $intent->hasTypo,
                'suggested_query' => $intent->suggestedQuery,
                'model_alias' => $intent->modelAlias,
                'products' => $products,
                'brands' => $brands,
                'categories' => $categories,
                'total' => $totalCount,
            ],
        ], 200);
    }

    /**
     * API hiển thị danh sách sản phẩm.
     */
    public function index(Request $request, ProductComparisonService $comparison)
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
            'sort_by' => 'nullable|string|in:relevance,price_asc,price_desc,newest,oldest,name_asc,name_desc,popular',
            'per_page' => 'nullable|integer|min:1|max:100',
            'suggest' => 'nullable|boolean',
            'exclude_criteria' => 'nullable|string|max:255',
        ]);

        if ($request->boolean('suggest')) {
            if (empty($validated['keyword'])) {
                return response()->json([
                    'status' => true,
                    'message' => 'Từ khóa tìm kiếm trống',
                    'data' => [
                        'keyword' => '',
                        'products' => [],
                        'brands' => [],
                        'categories' => [],
                        'total' => 0,
                    ],
                ], 200);
            }

            return $this->searchSuggestions($validated['keyword']);
        }

        $query = Product::whereIn('status', ['active', 'out_of_stock'])->with([
            'brand',
            'category',
            'variants' => fn($variantQuery) => $variantQuery->where('is_active', true),
            'variants.inventory',
            'variants.attributeValues.attribute',
            'images',
        ]);

        $intent = null;
        $excludeCriteria = ! empty($validated['exclude_criteria'])
            ? array_filter(array_map('trim', explode(',', (string) $validated['exclude_criteria'])))
            : [];

        if (! empty($validated['keyword'])) {
            $rawKeyword = trim($validated['keyword']);
            $parser = new SearchIntentParser;
            $intent = $parser->parse($rawKeyword);

            $effectiveTerm = $intent->modelAlias ?: ($intent->normalizedQuery ?: '');
            $tokens = ! empty($intent->searchTokens) ? $intent->searchTokens : array_filter(preg_split('/\s+/', $effectiveTerm), fn($t) => mb_strlen($t) > 0);
            $slugKeyword = Str::slug($effectiveTerm);

            if ($effectiveTerm !== '') {
                $query->where(function ($sub) use ($effectiveTerm, $tokens, $slugKeyword, $rawKeyword) {
                    // Khớp nguyên cụm từ với Tên, Slug, Mô tả, Brand, Category, SKU
                    $sub->where(function ($phraseQuery) use ($effectiveTerm, $slugKeyword, $rawKeyword) {
                        $phraseQuery->where('products.name', 'like', "%{$effectiveTerm}%")
                            ->orWhere('products.slug', 'like', "%{$effectiveTerm}%")
                            ->orWhere('products.description', 'like', "%{$effectiveTerm}%")
                            ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$effectiveTerm}%"))
                            ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$effectiveTerm}%"))
                            ->orWhereHas('variants', fn($v) => $v->where('sku', 'like', "%{$effectiveTerm}%"));

                        if ($rawKeyword !== $effectiveTerm) {
                            $phraseQuery->orWhere('products.name', 'like', "%{$rawKeyword}%");
                        }
                        if ($slugKeyword && $slugKeyword !== $effectiveTerm) {
                            $phraseQuery->orWhere('products.slug', 'like', "%{$slugKeyword}%");
                        }
                    });

                    // Khớp đa từ (multi-token): mỗi từ khóa đều phải xuất hiện trên sản phẩm hoặc thuộc tính liên quan
                    if (count($tokens) > 1) {
                        $sub->orWhere(function ($tokenQuery) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenSlug = Str::slug($token);
                                $tokenQuery->where(function ($q) use ($token, $tokenSlug) {
                                    $q->where('products.name', 'like', "%{$token}%")
                                        ->orWhere('products.description', 'like', "%{$token}%")
                                        ->orWhere('products.slug', 'like', "%{$tokenSlug}%")
                                        ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$token}%"))
                                        ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$token}%"))
                                        ->orWhereHas('variants', fn($v) => $v->where('sku', 'like', "%{$token}%"));

                                    if ($tokenSlug && $tokenSlug !== $token) {
                                        $q->orWhere('products.slug', 'like', "%{$tokenSlug}%");
                                    }
                                });
                            }
                        });
                    }
                });
            }
        }

        // Lọc theo thương hiệu (hỗ trợ single id, mảng id hoặc chuỗi phân cách bởi dấu phẩy, hoặc từ intent)
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
        } elseif ($intent && $intent->brandId && ! in_array('brand', $excludeCriteria)) {
            $query->where('brand_id', $intent->brandId);
        }

        // Lọc theo danh mục (hỗ trợ phân cấp đệ quy hoặc từ intent)
        if (! empty($validated['category_id'])) {
            $categoryId = (int) $validated['category_id'];
            $categoryIds = $this->getAllCategoryIdsRecursively($categoryId);
            $query->whereIn('category_id', $categoryIds);
        } elseif ($intent && $intent->categoryId && ! in_array('category', $excludeCriteria)) {
            $categoryIds = $this->getAllCategoryIdsRecursively($intent->categoryId);
            $query->whereIn('category_id', $categoryIds);
        }

        // Lọc thông số kỹ thuật (Hardware Specs) từ Search Intent
        if ($intent && ! empty($intent->specs) && ! in_array('spec', $excludeCriteria)) {
            // Dung lượng pin (battery)
            if (! empty($intent->specs['battery_min'])) {
                $minBattery = (int) $intent->specs['battery_min'];
                $query->where(function ($bq) use ($minBattery) {
                    $bq->whereHas('specifications', function ($sq) use ($minBattery) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'battery_capacity'))
                            ->where('numeric_value', '>=', $minBattery);
                    })->orWhere('products.description', 'like', '%pin%');
                });
            }

            // Công suất sạc (wattage)
            if (! empty($intent->specs['charging_wattage'])) {
                $watt = (int) $intent->specs['charging_wattage'];
                $query->where(function ($wq) use ($watt) {
                    $wq->whereHas('specifications', function ($sq) use ($watt) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'charging_power'))
                            ->where('numeric_value', '>=', $watt);
                    })
                        ->orWhere('products.name', 'like', "%{$watt}W%")
                        ->orWhere('products.name', 'like', "%{$watt} W%")
                        ->orWhere('products.description', 'like', "%{$watt}W%")
                        ->orWhere('products.description', 'like', "%{$watt} W%");
                });
            }

            // Tần số quét (refresh rate)
            if (! empty($intent->specs['refresh_rate'])) {
                $hz = (int) $intent->specs['refresh_rate'];
                $query->where(function ($hq) use ($hz) {
                    $hq->whereHas('specifications', function ($sq) use ($hz) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'display_resolution'))
                            ->where('value_text', 'like', "%{$hz}Hz%");
                    })
                        ->orWhere('products.name', 'like', "%{$hz}Hz%")
                        ->orWhere('products.description', 'like', "%{$hz}Hz%");
                });
            }

            // Vi xử lý (chipset)
            if (! empty($intent->specs['chipset'])) {
                $chip = $intent->specs['chipset'];
                $query->where(function ($cq) use ($chip) {
                    $cq->whereHas('specifications', function ($sq) use ($chip) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'chipset'))
                            ->where('value_text', 'like', "%{$chip}%");
                    })
                        ->orWhere('products.name', 'like', "%{$chip}%")
                        ->orWhere('products.description', 'like', "%{$chip}%");
                });
            }

            // Cổng kết nối (connector_type)
            if (! empty($intent->specs['connector_type'])) {
                $conn = $intent->specs['connector_type'];
                $query->where(function ($cq) use ($conn) {
                    $cq->whereHas('specifications', function ($sq) use ($conn) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'connector_type'))
                            ->where('value_text', 'like', "%{$conn}%");
                    })
                        ->orWhere('products.name', 'like', "%{$conn}%")
                        ->orWhere('products.description', 'like', "%{$conn}%");
                });
            }

            // Chiều dài cáp (cable_length)
            if (! empty($intent->specs['cable_length'])) {
                $len = $intent->specs['cable_length'];
                $query->where(function ($lq) use ($len) {
                    $lq->whereHas('specifications', function ($sq) use ($len) {
                        $sq->whereHas('definition', fn($dq) => $dq->where('code', 'cable_length'))
                            ->where('numeric_value', '>=', $len);
                    })
                        ->orWhere('products.name', 'like', "%{$len}m%")
                        ->orWhere('products.name', 'like', "%{$len} m%")
                        ->orWhere('products.description', 'like', "%{$len}m%");
                });
            }
        }

        // Lọc nhu cầu sử dụng (Needs / Use Cases)
        if ($intent && ! empty($intent->intents) && ! in_array('need', $excludeCriteria)) {
            if (in_array('gaming', $intent->intents)) {
                $query->where(function ($gq) {
                    $gq->where('products.name', 'like', '%gaming%')
                        ->orWhere('products.description', 'like', '%gaming%')
                        ->orWhere('products.description', 'like', '%chơi game%')
                        ->orWhereHas('specifications', function ($sq) {
                            $sq->where(function ($dq) {
                                $dq->where('value_text', 'like', '%Snapdragon 8%')
                                    ->orWhere('value_text', 'like', '%A18%')
                                    ->orWhere('value_text', 'like', '%A17%')
                                    ->orWhere('value_text', 'like', '%A16%')
                                    ->orWhere('value_text', 'like', '%120Hz%');
                            });
                        });
                });
            }

            if (in_array('photography', $intent->intents)) {
                $query->where(function ($pq) {
                    $pq->where('products.name', 'like', '%camera%')
                        ->orWhere('products.name', 'like', '%pro%')
                        ->orWhere('products.name', 'like', '%ultra%')
                        ->orWhere('products.description', 'like', '%camera%')
                        ->orWhere('products.description', 'like', '%chụp ảnh%')
                        ->orWhereHas('specifications', fn($sq) => $sq->whereHas('definition', fn($d) => $d->whereIn('code', ['rear_camera', 'front_camera'])));
                });
            }
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $user = Auth::guard('sanctum')->user() ?? Auth::user();
            $isAdmin = $user && (
                (method_exists($user, 'hasRole') && $user->hasRole('admin')) ||
                (method_exists($user, 'hasPermission') && ($user->hasPermission('products.manage') || $user->hasPermission('products.view')))
            );

            if (! $isAdmin) {
                $query->where('status', 'active');
            }
        }

        // Biến thể: Giá, RAM, Storage (hợp nhất từ request và search intent)
        $targetMinPrice = $validated['min_price'] ?? (($intent && ! in_array('price', $excludeCriteria)) ? $intent->minPrice : null);
        $targetMaxPrice = $validated['max_price'] ?? (($intent && ! in_array('price', $excludeCriteria)) ? $intent->maxPrice : null);
        $targetRam = $validated['ram'] ?? (($intent && ! in_array('ram', $excludeCriteria)) ? $intent->ram : null);
        $targetStorage = $validated['storage'] ?? (($intent && ! in_array('storage', $excludeCriteria)) ? $intent->storage : null);

        $hasVariantFilters =
            isset($targetMinPrice) ||
            isset($targetMaxPrice) ||
            ! empty($targetRam) ||
            ! empty($targetStorage);

        if ($hasVariantFilters) {
            $query->whereHas('variants', function ($variantQuery) use ($targetMinPrice, $targetMaxPrice, $targetRam, $targetStorage) {
                if (isset($targetMinPrice)) {
                    $variantQuery->where('price', '>=', $targetMinPrice);
                }

                if (isset($targetMaxPrice)) {
                    $variantQuery->where('price', '<=', $targetMaxPrice);
                }

                if (! empty($targetRam)) {
                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($targetRam) {
                        $valueQuery->where('value', $targetRam)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'ram');
                            });
                    });
                }

                if (! empty($targetStorage)) {
                    $variantQuery->whereHas('attributeValues', function ($valueQuery) use ($targetStorage) {
                        $valueQuery->where('value', $targetStorage)
                            ->whereHas('attribute', function ($attributeQuery) {
                                $attributeQuery->where('code', 'storage');
                            });
                    });
                }
            });
        }

        // Sắp xếp
        $sortBy = $validated['sort_by'] ?? (! empty($validated['keyword']) ? 'relevance' : 'newest');
        switch ($sortBy) {
            case 'relevance':
                if (! empty($validated['keyword'])) {
                    $rawKw = trim($validated['keyword']);
                    $modelAlias = $intent->modelAlias ?? '';
                    $aliasLower = $modelAlias ? mb_strtolower($modelAlias, 'UTF-8') : '';
                    $termToRank = $modelAlias ?: ($intent->normalizedQuery ?: $rawKw);
                    $cleanLower = mb_strtolower($termToRank, 'UTF-8');
                    $detectedBrandId = $intent->brandId ?? 0;

                    $query->orderByRaw(
                        'CASE 
                            WHEN ? != "" AND LOWER(products.name) LIKE ? THEN 150
                            WHEN LOWER(products.name) = ? THEN 100
                            WHEN LOWER(products.name) LIKE ? THEN 80
                            WHEN LOWER(products.name) LIKE ? THEN 60
                            WHEN ? != 0 AND products.brand_id = ? THEN 40
                            ELSE 10 
                        END DESC',
                        [
                            $aliasLower,
                            $aliasLower ? "%{$aliasLower}%" : '',
                            $cleanLower,
                            "{$cleanLower}%",
                            "%{$cleanLower}%",
                            $detectedBrandId,
                            $detectedBrandId,
                        ]
                    )->latest('products.id');
                } else {
                    $query->latest();
                }
                break;
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

        $comparison->attachGroups($products->getCollection());

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data' => $products,
            'intent' => $intent ? $intent->toArray() : null,
        ], 200);
    }

    /**
     * Return hidden products only through the permission-protected admin API.
     */
    public function adminShow(int $id)
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

        foreach ($product->variants as $variant) {
            $variant->makeVisible('cost_price');
        }

        $this->attachFlashSalePricing($product);

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công',
            'data' => $product,
        ]);
    }

    /**
     * API so sánh sản phẩm.
     */
    public function compare(Request $request, ProductComparisonService $comparison)
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

        $products = Product::whereIn('status', ['active', 'out_of_stock'])->with([
            'brand',
            'category',
            'images',
            'specifications.definition',
            'variants' => fn($variantQuery) => $variantQuery->where('is_active', true),
            'variants.inventory',
            'variants.attributeValues.attribute',
        ])->whereIn('id', $ids)->get();

        $groups = $products
            ->map(fn(Product $product) => $comparison->groupForCategory($product->category_id))
            ->unique()
            ->values();

        if ($groups->count() > 1) {
            return response()->json([
                'status' => false,
                'message' => 'Chỉ có thể so sánh các sản phẩm cùng nhóm danh mục. Hãy bỏ sản phẩm khác loại khỏi danh sách.',
            ], 422);
        }

        $comparison->attachGroups($products);

        // Bảo toàn thứ tự ID sản phẩm người dùng chọn
        $products = $products->sortBy(function ($p) use ($ids) {
            $idx = array_search($p->id, $ids);

            return $idx !== false ? $idx : 999;
        })->values();

        // Thu thập các định nghĩa thông số có thể so sánh hoặc đang gắn vào các sản phẩm được chọn
        $defIdsFromProducts = $products->flatMap(function ($p) {
            return $p->specifications->pluck('specification_definition_id');
        })->filter()->unique()->toArray();

        $comparableDefinitions = ProductSpecificationDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($defIdsFromProducts) {
                $query->where('is_comparable', true);
                if (! empty($defIdsFromProducts)) {
                    $query->orWhereIn('id', $defIdsFromProducts);
                }
            })
            ->orderBy('sort_order')
            ->get();

        if ($comparableDefinitions->isEmpty()) {
            $comparableDefinitions = ProductSpecification::query()
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
    public function show(string $id, ProductComparisonService $comparison)
    {
        $query = Product::whereIn('status', ['active', 'out_of_stock'])->with([
            'brand',
            'category',
            'images',
            'specifications.definition',
            'variants' => fn($variantQuery) => $variantQuery->where('is_active', true),
            'variants.inventory',
            'variants.images',
            'variants.attributeValues.attribute',
        ]);

        // Chấp nhận cả numeric ID lẫn slug (SEO-friendly URLs)
        if (is_numeric($id)) {
            $product = $query->find((int) $id);
        } else {
            $product = $query->where('slug', $id)->first();
        }

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        $comparison->attachGroups([$product]);

        $this->attachFlashSalePricing($product);

        return response()->json([
            'status' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công',
            'data' => $product,
        ], 200);
    }

    private function attachFlashSalePricing(Product $product): void
    {
        if ($product->status !== 'active') {
            return;
        }

        $saleItems = FlashSaleItem::query()
            ->whereIn('product_variant_id', $product->variants->pluck('id'))
            ->whereHas('flashSale', function ($saleQuery): void {
                $saleQuery->whereIn('status', ['scheduled', 'active'])
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>', now());
            })
            ->with('flashSale')
            ->orderBy('flash_sale_id')
            ->get()
            ->unique('product_variant_id')
            ->keyBy('product_variant_id');

        foreach ($product->variants as $variant) {
            $saleItem = $saleItems->get($variant->id);
            if (! $saleItem || (float) $saleItem->sale_price >= (float) $variant->price) {
                continue;
            }

            $remainingQuantity = $saleItem->quantity_limit === null
                ? (int) ($variant->inventory?->available_quantity ?? 0)
                : (int) $saleItem->quantity_limit - (int) $saleItem->quantity_reserved - (int) $saleItem->quantity_sold;

            if ($remainingQuantity <= 0 || (int) ($variant->inventory?->available_quantity ?? 0) <= 0) {
                continue;
            }

            $variant->setAttribute('flash_sale_price', (float) $saleItem->sale_price);
            $variant->setAttribute('flash_sale_original_price', (float) $variant->price);
            $variant->setAttribute('flash_sale_end_at', $saleItem->flashSale->end_at->toIso8601String());
        }
    }

    /**
     * API thêm sản phẩm mới.
     */

    public function store(Request $request)
    {
        if ($request->exists('status') && ! $request->user()->hasPermission('products.publish')) {
            return response()->json(['message' => 'You do not have permission to publish products.'], 403);
        }

        $hasVariants = $request->exists('variants');

        $isAccessory = $this->isAccessoryCategory(
            $request->filled('category_id')
                ? (int) $request->input('category_id')
                : null
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'product_type' => 'nullable|string|in:phone,accessory,tablet,laptop',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,active,inactive,out_of_stock',

            'price' => $isAccessory && ! $hasVariants
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

            // Danh sách biến thể
            'variants' => 'sometimes|array|min:1',
            'variants.*.attribute_value_ids' => 'required|array|min:1',
            'variants.*.attribute_value_ids.*' => [
                'required',
                'integer',
                'exists:attribute_values,id',
            ],
          'variants.*.price' => 'required|numeric|min:0',
            'variants.*.compare_price' => 'nullable|numeric|min:0',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'variants.*.min_stock' => 'nullable|integer|min:0',

            'image_url' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'primary_index' => 'nullable|integer|min:0|max:10',
        ]);

        $product = DB::transaction(function () use ($request, $validated, $isAccessory, $hasVariants) {
            $productData = collect($validated)->only([
                'name',
                'slug',
                'product_type',
                'description',
                'brand_id',
                'category_id',
                'status',
            ])->toArray();

            if (empty($productData['product_type'])) {
                $productData['product_type'] = $isAccessory ? 'accessory' : 'phone';
            }

            $product = Product::create($productData);

            $createdVariants = collect();

            if ($hasVariants) {
                $combinations = [];

                foreach ($validated['variants'] as $index => $variantData) {
                    $attributeValueIds = array_map(
                        'intval',
                        $variantData['attribute_value_ids']
                    );
                    if (count($attributeValueIds) !== count(array_unique($attributeValueIds))) {
                        throw ValidationException::withMessages([
                            "variants.{$index}.attribute_value_ids" => [
                                'Không được chọn trùng giá trị thuộc tính trong cùng một biến thể.',
                            ],
                        ]);
                    }

                    $attributeValues = AttributeValue::with('attribute')
                        ->whereIn('id', $attributeValueIds)
                        ->get();

                    if ($attributeValues->count() !== count($attributeValueIds)) {
                        throw ValidationException::withMessages([
                            "variants.{$index}.attribute_value_ids" => [
                                'Có giá trị thuộc tính không tồn tại.',
                            ],
                        ]);
                    }

                    if ($attributeValues->contains(fn($value) => ! $value->is_active)) {
                        throw ValidationException::withMessages([
                            "variants.{$index}.attribute_value_ids" => [
                                'Không thể sử dụng giá trị thuộc tính đã bị vô hiệu hóa.',
                            ],
                        ]);
                    }

                    // Mỗi biến thể chỉ được chọn một giá trị cho mỗi thuộc tính.
                    $attributeIds = $attributeValues
                        ->pluck('attribute_id');

                    if ($attributeIds->unique()->count() !== $attributeIds->count()) {
                        throw ValidationException::withMessages([
                            "variants.{$index}.attribute_value_ids" => [
                                'Một biến thể không được chọn nhiều giá trị của cùng một thuộc tính.',
                            ],
                        ]);
                    }

                    // Không cho phép hai biến thể có cùng tổ hợp thuộc tính.
                    $combinationKey = $attributeValues
                        ->pluck('id')
                        ->sort()
                        ->implode('-');

                    if (isset($combinations[$combinationKey])) {
                        throw ValidationException::withMessages([
                            "variants.{$index}.attribute_value_ids" => [
                                'Tổ hợp thuộc tính này đã được khai báo ở biến thể khác.',
                            ],
                        ]);
                    }

                    $combinations[$combinationKey] = true;

                    // Sinh SKU riêng cho từng tổ hợp.
                    $sku = $this->generateVariantSkuFromAttributes(
                        $product,
                        $attributeValues
                    );

                    // Tạo chuỗi mô tả tổ hợp thuộc tính.
                    $optionSignature = $attributeValues
                        ->map(fn($value) => $value->attribute->name . ': ' . $value->value)
                        ->implode(' | ');

                    $variant = $product->variants()->create([
                        'sku' => $sku,
                        'option_signature' => $optionSignature,
                        'price' => $variantData['price'],
                        'compare_price' => $variantData['compare_price'] ?? null,
                        'cost_price' => $variantData['cost_price'] ?? null,
                        'status' => 'active',
                    ]);

                    // Đồng bộ cả attribute_id và attribute_value_id.
                    $syncData = [];

                    foreach ($attributeValues as $attributeValue) {
                        $syncData[$attributeValue->id] = [
                            'attribute_id' => $attributeValue->attribute_id,
                        ];
                    }

                    $variant->attributeValues()->sync($syncData);

                    // Tạo tồn kho riêng cho từng biến thể.
                    $quantity = (int) ($variantData['quantity'] ?? 0);
                    $minStock = (int) ($variantData['min_stock'] ?? 5);

                    $variant->inventory()->create([
                        'on_hand_quantity' => $quantity,
                        'reserved_quantity' => 0,
                        'min_stock' => $minStock,
                    ]);

                    if ($quantity > 0) {
                        $this->createInventoryTransaction(
                            $variant,
                            $quantity,
                            'import',
                            $product->id,
                            'Nhập tồn kho ban đầu khi tạo biến thể sản phẩm'
                        );
                    }

                    $createdVariants->push($variant);
                }
            } else {
                // Giữ nguyên cách tạo một biến thể mặc định cho luồng cũ.
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
                    'on_hand_quantity' => $stockQuantity,
                    'reserved_quantity' => 0,
                    'min_stock' => 5,
                ]);

                if ($stockQuantity > 0) {
                    $this->createInventoryTransaction(
                        $variant,
                        $stockQuantity,
                        'import',
                        $product->id,
                        'Ghi nhận tồn kho ban đầu khi tạo sản phẩm'
                    );
                }

                $createdVariants->push($variant);
            }

            // Biến thể đầu tiên được dùng để gắn ảnh khi tạo sản phẩm.
            $firstVariant = $createdVariants->first();

            if ($request->hasFile('images')) {
                $files = $request->file('images');
                $primaryIdx = $request->has('primary_index')
                    ? (int) $request->input('primary_index')
                    : 0;

                $mediaService = app(MediaStorageService::class);

                foreach ($files as $idx => $file) {
                    $uploadResult = $mediaService->upload($file, 'products');

                    $product->images()->create([
                        'variant_id' => $firstVariant?->id,
                        'image_url' => $uploadResult['url'],
                        'image_path' => $uploadResult['path'],
                        'storage_provider' => $uploadResult['provider'],
                        'public_id' => $uploadResult['public_id'],
                        'alt_text' => $product->name,
                        'is_primary' => ($idx === $primaryIdx),
                        'sort_order' => $idx,
                    ]);
                }
            } elseif (! empty($validated['image_url'])) {
                $product->images()->create([
                    'variant_id' => $firstVariant?->id,
                    'image_url' => $validated['image_url'],
                    'alt_text' => $product->name,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            } elseif (! $isAccessory) {
                $product->images()->create([
                    'variant_id' => $firstVariant?->id,
                    'image_url' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&auto=format&fit=crop&q=80',
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
            'variants.attributeValues.attribute',
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
        if ($request->exists('status') && ! $request->user()->hasPermission('products.publish')) {
            return response()->json(['message' => 'You do not have permission to publish products.'], 403);
        }

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

                if (
                    array_key_exists('price', $validated)
                    && $validated['price'] !== null
                ) {
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

            DB::transaction(function () use ($product, $data) {
                foreach ($data['specs'] as $index => $spec) {
                    $def = ProductSpecificationDefinition::find($spec['definition_id']);
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

                    ProductSpecification::updateOrCreate(
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

        DB::transaction(function () use ($product, $validated) {
            $product->specifications()->delete();

            foreach ($validated['specs'] as $index => $spec) {
                $code = Str::slug($spec['specification_name']);
                $def = ProductSpecificationDefinition::firstOrCreate(
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
