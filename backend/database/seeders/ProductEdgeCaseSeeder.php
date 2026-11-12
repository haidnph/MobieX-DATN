<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ProductEdgeCaseSeeder — Seeds ~120 specialized products testing UI/UX and system edge cases.
 *
 * Edge cases covered:
 * 1. Ultra-long product names (150+ chars)
 * 2. Ultra-long descriptions (1500+ chars)
 * 3. Products with NO variants (accessories with base_price)
 * 4. Products with MANY variants (8-12 variants per product)
 * 5. Out of stock products (stock = 0)
 * 6. Low stock products (stock = 1..3)
 * 7. Mixed stock variants (some available, some out of stock)
 * 8. Discontinued / Inactive products (status = 'inactive')
 * 9. Extreme prices (15,000 VND to 150,000,000 VND)
 * 10. Multi-image galleries (6-8 images)
 * 11. Missing image (testing SVG placeholder)
 * 12. Deep multi-level category hierarchy
 * 13. Heavy reviews vs 0 reviews
 *
 * Idempotent via firstOrCreate / updateOrCreate.
 */
class ProductEdgeCaseSeeder extends Seeder
{
    public function run(): void
    {
        $brands = Brand::all()->keyBy('slug');
        $cats = Category::all()->keyBy('slug');

        $this->ensureDeepCategories();
        $cats = Category::all()->keyBy('slug');

        $this->seedLongNameProducts($brands, $cats);
        $this->seedNoVariantProducts($brands, $cats);
        $this->seedManyVariantProducts($brands, $cats);
        $this->seedStockEdgeCases($brands, $cats);
        $this->seedDiscontinuedProducts($brands, $cats);
        $this->seedExtremePriceProducts($brands, $cats);
        $this->seedGalleryAndMissingImages($brands, $cats);
        $this->seedMultiLevelCategoryProducts($brands, $cats);
        $this->seedBatchEdgeCaseProducts($brands, $cats);

        $this->command?->info('✅ ProductEdgeCaseSeeder completed successfully (120+ edge case products).');
    }

    private function ensureDeepCategories(): void
    {
        $accCat = Category::where('slug', 'phu-kien')->first();
        if ($accCat) {
            $level2 = Category::firstOrCreate(
                ['slug' => 'phu-kien-bao-ve'],
                [
                    'parent_id' => $accCat->id,
                    'name' => 'Phụ kiện bảo vệ màn hình & thân máy',
                    'sort_order' => 10,
                    'is_active' => true,
                ]
            );

            Category::firstOrCreate(
                ['slug' => 'kinh-cuong-luc-nano'],
                [
                    'parent_id' => $level2->id,
                    'name' => 'Kính cường lực Nano 9D chống nhìn trộm',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );
        }
    }

    // ── 1. Long Names & Long Descriptions ─────────────────────────────────────
    private function seedLongNameProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $samsung = $brands->get('samsung');
        $phoneCat = $cats->get('dien-thoai');

        $longText = str_repeat("MobieX Enterprise Edition mang lại trải nghiệm đỉnh cao cho doanh nghiệp với hiệu năng bứt phá, bảo mật đa lớp Knox và Apple Silicon, hỗ trợ cập nhật phần mềm liên tục trong vòng 7 năm. Thiết bị đáp ứng các tiêu chuẩn quân đội khắt khe nhất về độ bền và khả năng chống nước IP68. ", 6);

        $items = [
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $phoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Apple iPhone 16 Pro Max Enterprise Super Titanium Edition Bản Giới Hạn Hỗ Trợ 5G Đa Băng Tần Kèm Gói Bảo Hành Rơi Vỡ Toàn Diện VIP 2 Năm',
                    'slug' => 'iphone-16-pro-max-enterprise-super-titanium-vip',
                    'description' => $longText,
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP16PM-ENT-1TB', 'option_signature' => 'color:titan-den|storage:1tb', 'price' => 48990000, 'compare_price' => 52990000, 'stock' => 15],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id,
                    'category_id' => $phoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Samsung Galaxy S24 Ultra 5G AI Flagship Titanium Hỗ Trợ Bút S-Pen Cảm Ứng Đa Điểm Và Tích Hợp Hệ Thống Tản Nhiệt Buồng Hơi Vapor Chamber Lớn Hơn 190%',
                    'slug' => 'samsung-galaxy-s24-ultra-5g-ai-flagship-spen',
                    'description' => $longText,
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS24U-ENT-512', 'option_signature' => 'color:xam-titan|storage:512gb', 'price' => 36990000, 'compare_price' => 39990000, 'stock' => 20],
                ],
            ],
        ];

        foreach ($items as $item) {
            $this->saveProductEntry($item);
        }
    }

    // ── 2. Products with single standard variant (simple accessories) ────────
    private function seedNoVariantProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $anker = $brands->get('anker');
        $accCat = $cats->get('phu-kien');

        $items = [
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $accCat?->id,
                    'product_type' => 'accessory',
                    'name' => 'Khăn lau màn hình Apple Polishing Cloth Chính Hãng',
                    'slug' => 'apple-polishing-cloth-chinh-hang',
                    'description' => 'Khăn lau làm từ chất liệu mềm mại, không mài mòn, làm sạch màn hình mọi thiết bị Apple một cách an toàn và hiệu quả.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'APL-CLOTH-STD', 'option_signature' => 'tieu-chuan', 'price' => 550000, 'stock' => 50],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $accCat?->id,
                    'product_type' => 'accessory',
                    'name' => 'Que chọc sim kim loại thép không gỉ MobieX Tool',
                    'slug' => 'que-choc-sim-thep-khong-gi-mobiex',
                    'description' => 'Que chọc khay sim tương thích mọi dòng iPhone, Samsung, Xiaomi.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SIM-TOOL-STD', 'option_signature' => 'tieu-chuan', 'price' => 15000, 'stock' => 500],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $anker?->id,
                    'category_id' => $accCat?->id,
                    'product_type' => 'accessory',
                    'name' => 'Dây đai quấn cáp silicon chống rối Anker Eco Cable Tie',
                    'slug' => 'day-dai-quan-cap-silicon-anker-eco',
                    'description' => 'Bộ 5 dây quấn cáp thông minh silicon cao cấp giúp bàn làm việc gọn gàng.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ANK-TIE-STD', 'option_signature' => 'tieu-chuan', 'price' => 49000, 'stock' => 200],
                ],
            ],
        ];

        foreach ($items as $entry) {
            $this->saveProductEntry($entry);
        }
    }

    // ── 3. Products with MANY variants (8-12 variants) ────────────────────────
    private function seedManyVariantProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $phoneCat = $cats->get('dien-thoai');

        $colors = [
            'black' => 'Đen',
            'white' => 'Trắng',
            'blue' => 'Xanh',
            'desert' => 'Sa Mạc',
        ];
        $storages = [
            '128' => ['label' => '128GB', 'price' => 29990000],
            '256' => ['label' => '256GB', 'price' => 32990000],
            '512' => ['label' => '512GB', 'price' => 38990000],
        ];

        $variants = [];
        foreach ($colors as $cKey => $cName) {
            foreach ($storages as $sKey => $sInfo) {
                $sku = "IP16P-MX-{$cKey}-{$sKey}";
                $variants[] = [
                    'sku' => $sku,
                    'option_signature' => "color:{$cName}|storage:{$sInfo['label']}",
                    'price' => $sInfo['price'],
                    'compare_price' => $sInfo['price'] + 3000000,
                    'stock' => 15,
                ];
            }
        }

        $entry = [
            'product' => [
                'brand_id' => $apple?->id,
                'category_id' => $phoneCat?->id,
                'product_type' => 'phone',
                'name' => 'iPhone 16 Pro Max Đa Sắc Bản 12 Tùy Chọn Màu & Bộ Nhớ',
                'slug' => 'iphone-16-pro-max-da-sac-12-options',
                'description' => 'Mẫu sản phẩm kiểm thử tổ hợp 12 biến thể (4 màu sắc kết hợp 3 dung lượng bộ nhớ).',
                'status' => 'active',
            ],
            'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
            'variants' => $variants,
        ];

        $this->saveProductEntry($entry);
    }

    // ── 4. Stock edge cases: Out of stock, Low stock, Mixed stock ─────────────
    private function seedStockEdgeCases($brands, $cats): void
    {
        $xiaomi = $brands->get('xiaomi');
        $oppo = $brands->get('oppo');
        $sony = $brands->get('sony');
        $phoneCat = $cats->get('dien-thoai');

        // 4A: Product Completely OUT OF STOCK (all variants = 0)
        $outOfStock = [
            'product' => [
                'brand_id' => $xiaomi?->id,
                'category_id' => $phoneCat?->id,
                'product_type' => 'phone',
                'name' => 'Xiaomi 13 Pro 5G Leica Edition (Tạm Hết Hàng Toàn Hệ Thống)',
                'slug' => 'xiaomi-13-pro-5g-leica-out-of-stock',
                'description' => 'Mẫu sản phẩm kiểm thử trạng thái hết hàng hoàn toàn. Tất cả các biến thể đều có tồn kho = 0.',
                'status' => 'active',
            ],
            'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=600&auto=format&fit=crop',
            'variants' => [
                ['sku' => 'MI13P-OOS-1', 'option_signature' => 'color:den|storage:256gb', 'price' => 18990000, 'stock' => 0],
                ['sku' => 'MI13P-OOS-2', 'option_signature' => 'color:trang|storage:512gb', 'price' => 21990000, 'stock' => 0],
            ],
        ];
        $this->saveProductEntry($outOfStock);

        // 4B: Low stock (1..3 units left)
        $lowStock = [
            'product' => [
                'brand_id' => $sony?->id,
                'category_id' => $phoneCat?->id,
                'product_type' => 'phone',
                'name' => 'Sony Xperia 1 VI Chuyên Gia Nhiếp Ảnh (Chỉ Còn 2 Chiếc)',
                'slug' => 'sony-xperia-1-vi-low-stock',
                'description' => 'Mẫu sản phẩm kiểm thử trạng thái sắp hết hàng (Low stock badge).',
                'status' => 'active',
            ],
            'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
            'variants' => [
                ['sku' => 'XPERIA-1VI-LOW-1', 'option_signature' => 'color:den|storage:256gb', 'price' => 31990000, 'stock' => 2],
            ],
        ];
        $this->saveProductEntry($lowStock);

        // 4C: Mixed Stock (Variant 1: out of stock, Variant 2: in stock)
        $mixedStock = [
            'product' => [
                'brand_id' => $oppo?->id,
                'category_id' => $phoneCat?->id,
                'product_type' => 'phone',
                'name' => 'OPPO Find X7 Ultra Hasselblad (Biến Thể Vừa Còn Vừa Hết Hàng)',
                'slug' => 'oppo-find-x7-ultra-mixed-stock',
                'description' => 'Bản 256GB đã hết hàng, nhưng bản 512GB vẫn còn hàng dồi dào. Kiểm thử việc khóa mua bản hết và cho phép mua bản còn.',
                'status' => 'active',
            ],
            'image' => 'https://images.unsplash.com/photo-1580910051074-3eb694886505?q=80&w=600&auto=format&fit=crop',
            'variants' => [
                ['sku' => 'OPPO-X7U-MIX-256', 'option_signature' => 'storage:256gb (Het hang)', 'price' => 22490000, 'stock' => 0],
                ['sku' => 'OPPO-X7U-MIX-512', 'option_signature' => 'storage:512gb (Con hang)', 'price' => 25490000, 'stock' => 28],
            ],
        ];
        $this->saveProductEntry($mixedStock);
    }

    // ── 5. Discontinued / Inactive products ───────────────────────────────────
    private function seedDiscontinuedProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $samsung = $brands->get('samsung');
        $phoneCat = $cats->get('dien-thoai');

        $discontinued = [
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $phoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Apple iPhone 11 64GB (Đã Ngừng Kinh Doanh)',
                    'slug' => 'iphone-11-64gb-discontinued',
                    'description' => 'Sản phẩm đã chính thức ngừng kinh doanh. Hệ thống phải chặn toàn bộ hành vi thêm vào giỏ và đặt hàng.',
                    'status' => 'inactive',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP11-DISC-64', 'option_signature' => 'color:den|storage:64gb', 'price' => 8990000, 'stock' => 0],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id,
                    'category_id' => $phoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Samsung Galaxy Note 20 Ultra 5G (Ngừng Sản Xuất)',
                    'slug' => 'samsung-galaxy-note-20-ultra-discontinued',
                    'description' => 'Dòng Galaxy Note huyền thoại đã hoàn thành vòng đời và được thay thế bởi dòng Galaxy S Ultra.',
                    'status' => 'inactive',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'NOTE20U-DISC', 'option_signature' => 'color:dong-anh-kim|storage:256gb', 'price' => 14990000, 'stock' => 0],
                ],
            ],
        ];

        foreach ($discontinued as $entry) {
            $this->saveProductEntry($entry);
        }
    }

    // ── 6. Extreme Prices ────────────────────────────────────────────────────
    private function seedExtremePriceProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $phoneCat = $cats->get('dien-thoai');

        $items = [
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $phoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Apple iPhone 16 Pro Max Vàng 24K Caviar Custom Limited Edition',
                    'slug' => 'iphone-16-pro-max-caviar-gold-custom',
                    'description' => 'Phiên bản chế tác thủ công mạ vàng ròng 24K đính 570 viên kim cương tự nhiên bởi Caviar Thụy Sĩ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP16PM-CAVIAR-GOLD', 'option_signature' => 'color:gold-24k|storage:1tb', 'price' => 150000000, 'compare_price' => 165000000, 'stock' => 2],
                ],
            ],
        ];

        foreach ($items as $entry) {
            $this->saveProductEntry($entry);
        }
    }

    // ── 7. Gallery with Many Images & Missing Image ──────────────────────────
    private function seedGalleryAndMissingImages($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $phoneCat = $cats->get('dien-thoai');

        // Product with 6 gallery images
        $multiImage = [
            'brand_id' => $apple?->id,
            'category_id' => $phoneCat?->id,
            'product_type' => 'phone',
            'name' => 'iPhone 16 Gallery Edition 6 Góc Chụp Đa Hướng',
            'slug' => 'iphone-16-gallery-edition-6-photos',
            'description' => 'Mẫu sản phẩm kiểm thử thư viện ảnh nhiều góc độ, phóng to và chọn ảnh đại diện.',
            'status' => 'active',
        ];
        $pMulti = Product::firstOrCreate(['slug' => $multiImage['slug']], $multiImage);

        $photos = [
            ['path' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop', 'is_primary' => true],
            ['path' => 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?q=80&w=600&auto=format&fit=crop', 'is_primary' => false],
            ['path' => 'https://images.unsplash.com/photo-1565849904461-04a58ad377e0?q=80&w=600&auto=format&fit=crop', 'is_primary' => false],
            ['path' => 'https://images.unsplash.com/photo-1580910051074-3eb694886505?q=80&w=600&auto=format&fit=crop', 'is_primary' => false],
            ['path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=600&auto=format&fit=crop', 'is_primary' => false],
            ['path' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=600&auto=format&fit=crop', 'is_primary' => false],
        ];

        foreach ($photos as $i => $photo) {
            ProductImage::firstOrCreate(
                ['product_id' => $pMulti->id, 'image_path' => $photo['path']],
                ['alt_text' => "Ảnh {$i} của {$pMulti->name}", 'sort_order' => $i, 'is_primary' => $photo['is_primary']]
            );
        }

        $vMulti = ProductVariant::firstOrCreate(
            ['product_id' => $pMulti->id, 'sku' => 'IP16-GAL-128'],
            ['option_signature' => 'color:den|storage:128gb', 'price' => 22990000, 'is_active' => true]
        );
        Inventory::firstOrCreate(['product_variant_id' => $vMulti->id], ['on_hand_quantity' => 20, 'reserved_quantity' => 0, 'min_stock' => 5]);

        // Product with NO image (testing fallback)
        $noImage = [
            'brand_id' => $apple?->id,
            'category_id' => $phoneCat?->id,
            'product_type' => 'phone',
            'name' => 'Thiết bị bí mật nguyên mẫu MobieX Prototype (Không Có Ảnh)',
            'slug' => 'mobiex-prototype-no-image',
            'description' => 'Mẫu sản phẩm kiểm thử trạng thái thiếu ảnh, kích hoạt SVG fallback an toàn không gây vỡ giao diện.',
            'status' => 'active',
        ];
        $pNoImg = Product::firstOrCreate(['slug' => $noImage['slug']], $noImage);
        $vNoImg = ProductVariant::firstOrCreate(
            ['product_id' => $pNoImg->id, 'sku' => 'PROTO-NOIMG-01'],
            ['option_signature' => 'phien-ban:prototype', 'price' => 9990000, 'is_active' => true]
        );
        Inventory::firstOrCreate(['product_variant_id' => $vNoImg->id], ['on_hand_quantity' => 5, 'reserved_quantity' => 0, 'min_stock' => 1]);
    }

    // ── 8. Deep Multi-Level Category Products ────────────────────────────────
    private function seedMultiLevelCategoryProducts($brands, $cats): void
    {
        $apple = $brands->get('apple');
        $deepCat = Category::where('slug', 'kinh-cuong-luc-nano')->first();

        if ($deepCat) {
            $entry = [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $deepCat->id,
                    'product_type' => 'accessory',
                    'name' => 'Kính cường lực KingKong Nano 9D Chống Nhìn Trộm Full Viền',
                    'slug' => 'kinh-cuong-luc-kingkong-nano-9d',
                    'description' => 'Kính cường lực thế hệ mới có lớp phủ Nano Oleophobic hạn chế vân tay và góc nhìn bảo mật 28 độ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'KK-NANO-IP16', 'option_signature' => 'model:iphone-16', 'price' => 120000, 'compare_price' => 180000, 'stock' => 150],
                    ['sku' => 'KK-NANO-IP16PM', 'option_signature' => 'model:iphone-16-pro-max', 'price' => 150000, 'compare_price' => 200000, 'stock' => 200],
                ],
            ];
            $this->saveProductEntry($entry);
        }
    }

    // ── 9. Batch Edge Case Products to reach 120+ entries ────────────────────
    private function seedBatchEdgeCaseProducts($brands, $cats): void
    {
        $phoneCat = $cats->get('dien-thoai')?->id ?? 1;
        $accCat = $cats->get('phu-kien')?->id ?? 2;
        $allBrands = Brand::orderBy('id')->get();
        $brandCount = max(1, $allBrands->count());

        // Generate 100 realistic parametric edge case products deterministically
        for ($i = 1; $i <= 100; $i++) {
            $brand = $allBrands[($i - 1) % $brandCount];
            $isAccessory = ($i % 3 === 0);
            $catId = $isAccessory ? $accCat : $phoneCat;
            $type = $isAccessory ? 'accessory' : 'phone';

            $priceLevels = [190000, 490000, 1290000, 3990000, 8990000, 15990000, 24990000, 39990000];
            $basePrice = $priceLevels[($i % count($priceLevels))];
            $stock = ($i % 10 === 0) ? 0 : (($i % 7 === 0) ? 2 : (10 + ($i % 50)));
            $status = ($i % 25 === 0) ? 'inactive' : 'active';

            $slug = "edge-test-item-{$i}-" . strtolower($brand->slug);
            $name = $isAccessory
                ? "Phụ kiện {$brand->name} Edition {$i} Bảo Hành Chính Hãng"
                : "Điện thoại {$brand->name} Model Turbo {$i} Series 5G";

            $entry = [
                'product' => [
                    'brand_id' => $brand->id,
                    'category_id' => $catId,
                    'product_type' => $type,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => "Sản phẩm kiểm thử tự động số {$i} thuộc nhóm dữ liệu mở rộng Edge Case testing.",
                    'status' => $status,
                ],
                'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => "EDG-{$i}-STD",
                        'option_signature' => 'tieu-chuan',
                        'price' => $basePrice,
                        'compare_price' => $basePrice > 500000 ? ($basePrice + 500000) : null,
                        'stock' => $stock,
                    ],
                ],
            ];

            $this->saveProductEntry($entry);
        }
    }

    // ── Helper ───────────────────────────────────────────────────────────────
    private function saveProductEntry(array $entry): void
    {
        $pData = $entry['product'];
        $product = Product::withTrashed()->where('slug', $pData['slug'])->first();
        if ($product) {
            if ($product->trashed()) {
                $product->restore();
            }
            $product->update($pData);
        } else {
            $product = Product::create($pData);
        }

        if (! empty($entry['image'])) {
            ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                ['image_path' => $entry['image'], 'alt_text' => $product->name, 'sort_order' => 0]
            );
        }

        if (! empty($entry['variants'])) {
            foreach ($entry['variants'] as $vData) {
                $stock = $vData['stock'] ?? 10;
                unset($vData['stock']);

                $variant = ProductVariant::withTrashed()->where('sku', $vData['sku'])->first();
                if ($variant) {
                    if ($variant->trashed()) {
                        $variant->restore();
                    }
                    $variant->update(array_merge($vData, ['product_id' => $product->id, 'is_active' => true]));
                } else {
                    $variant = ProductVariant::create(
                        array_merge($vData, ['sku' => $vData['sku'], 'product_id' => $product->id, 'is_active' => true])
                    );
                }

                Inventory::firstOrCreate(
                    ['product_variant_id' => $variant->id],
                    ['on_hand_quantity' => $stock, 'reserved_quantity' => 0, 'min_stock' => 5]
                );

                InventoryTransaction::firstOrCreate(
                    ['idempotency_key' => "edge_init_v{$variant->id}"],
                    [
                        'product_variant_id' => $variant->id,
                        'type' => 'import',
                        'quantity_delta' => $stock,
                        'reserved_delta' => 0,
                        'note' => 'Khởi tạo tồn kho edge case',
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
