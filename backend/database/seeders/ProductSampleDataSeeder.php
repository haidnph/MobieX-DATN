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
 * ProductSampleDataSeeder — 100+ rich sample products for client UI/UX testing.
 *
 * Seeds phones, tablets, earphones, chargers, cables, cases, smartwatches.
 * Idempotent via firstOrCreate / updateOrCreate.
 */
class ProductSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $brands   = Brand::all()->keyBy('slug');
        $cats     = Category::all()->keyBy('slug');

        // Ensure extra brands exist
        $this->ensureExtraBrands($brands);
        $this->ensureExtraCategories($cats);

        // Reload after ensuring
        $brands = Brand::all()->keyBy('slug');
        $cats   = Category::all()->keyBy('slug');

        $this->seedPhones($brands, $cats);
        $this->seedAccessories($brands, $cats);
        $this->seedReviews();
    }

    // ── Ensure extra brands ───────────────────────────────────────────────────
    private function ensureExtraBrands(object $brands): void
    {
        $extra = [
            ['name' => 'Vivo',    'slug' => 'vivo',    'description' => 'Hãng smartphone Trung Quốc nổi tiếng với camera selfie và thiết kế mỏng.', 'status' => true],
            ['name' => 'Realme',  'slug' => 'realme',  'description' => 'Thương hiệu smartphone tầm trung giá tốt với hiệu năng cao.', 'status' => true],
            ['name' => 'OnePlus', 'slug' => 'oneplus', 'description' => 'Flagship killer nổi tiếng với tốc độ và phần mềm sạch.', 'status' => true],
            ['name' => 'Asus',    'slug' => 'asus',    'description' => 'Hãng công nghệ Đài Loan nổi tiếng với laptop và smartphone gaming ROG.', 'status' => true],
            ['name' => 'Lenovo',  'slug' => 'lenovo',  'description' => 'Tập đoàn công nghệ toàn cầu với máy tính bảng và laptop đa dạng.', 'status' => true],
            ['name' => 'JBL',     'slug' => 'jbl',     'description' => 'Thương hiệu âm thanh hàng đầu thế giới với loa và tai nghe chất lượng cao.', 'status' => true],
        ];

        foreach ($extra as $b) {
            Brand::firstOrCreate(['slug' => $b['slug']], $b);
        }
    }

    // ── Ensure extra categories ───────────────────────────────────────────────
    private function ensureExtraCategories(object $cats): void
    {
        $phoneCatId = Category::where('slug', 'dien-thoai')->value('id') ?? 1;
        $accCatId   = Category::where('slug', 'phu-kien')->value('id') ?? 2;

        $extra = [
            ['parent_id' => null,       'name' => 'Máy tính bảng', 'slug' => 'may-tinh-bang',    'sort_order' => 3, 'is_active' => true, 'is_featured' => true, 'featured_sort_order' => 3],
            ['parent_id' => null,       'name' => 'Smartwatch',     'slug' => 'smartwatch',       'sort_order' => 4, 'is_active' => true, 'is_featured' => true, 'featured_sort_order' => 4],
            ['parent_id' => $accCatId,  'name' => 'Tai nghe',       'slug' => 'tai-nghe',         'sort_order' => 4, 'is_active' => true, 'is_featured' => false, 'featured_sort_order' => 0],
            ['parent_id' => $accCatId,  'name' => 'Loa bluetooth',  'slug' => 'loa-bluetooth',    'sort_order' => 5, 'is_active' => true, 'is_featured' => false, 'featured_sort_order' => 0],
            ['parent_id' => $phoneCatId,'name' => 'Samsung Galaxy', 'slug' => 'samsung-galaxy',   'sort_order' => 3, 'is_active' => true, 'is_featured' => false, 'featured_sort_order' => 0],
            ['parent_id' => $phoneCatId,'name' => 'Xiaomi Mi',      'slug' => 'xiaomi-mi',        'sort_order' => 4, 'is_active' => true, 'is_featured' => false, 'featured_sort_order' => 0],
        ];

        foreach ($extra as $c) {
            Category::firstOrCreate(['slug' => $c['slug']], $c);
        }
    }

    // ── Seed phones ───────────────────────────────────────────────────────────
    private function seedPhones(object $brands, object $cats): void
    {
        $apple    = $brands->get('apple');
        $samsung  = $brands->get('samsung');
        $xiaomi   = $brands->get('xiaomi');
        $oppo     = $brands->get('oppo');
        $sony     = $brands->get('sony');
        $vivo     = $brands->get('vivo');
        $realme   = $brands->get('realme');
        $oneplus  = $brands->get('oneplus');
        $asus     = $brands->get('asus');

        $iphoneCat  = $cats->get('iphone');
        $androidCat = $cats->get('android');
        $sgCat      = $cats->get('samsung-galaxy');
        $xiCat      = $cats->get('xiaomi-mi');
        $phoneCat   = $cats->get('dien-thoai');
        $tabletCat  = $cats->get('may-tinh-bang');

        $phones = [
            // ── Apple iPhones ──────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 16', 'slug' => 'iphone-16',
                    'description' => 'Chip A18, Camera 48MP Fusion, Dynamic Island và cổng USB-C. Phiên bản tiêu chuẩn hoàn hảo nhất từ trước đến nay.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP16-128-BLK', 'option_signature' => 'color:den|storage:128gb', 'price' => 22990000, 'compare_price' => 24990000, 'cost_price' => 19000000, 'stock' => 40],
                    ['sku' => 'IP16-128-WHT', 'option_signature' => 'color:trang|storage:128gb', 'price' => 22990000, 'compare_price' => 24990000, 'cost_price' => 19000000, 'stock' => 35],
                    ['sku' => 'IP16-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 25990000, 'compare_price' => 27990000, 'cost_price' => 21500000, 'stock' => 25],
                    ['sku' => 'IP16-256-ULM', 'option_signature' => 'color:ultramarine|storage:256gb', 'price' => 25990000, 'compare_price' => 27990000, 'cost_price' => 21500000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 16 Plus', 'slug' => 'iphone-16-plus',
                    'description' => 'Màn hình 6.7 inch Super Retina XDR, pin lớn hơn, chip A18 mạnh mẽ — lựa chọn hoàn hảo cho màn hình lớn.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP16P-128-TL', 'option_signature' => 'color:teal|storage:128gb', 'price' => 27990000, 'compare_price' => 29990000, 'cost_price' => 23000000, 'stock' => 20],
                    ['sku' => 'IP16P-256-TL', 'option_signature' => 'color:teal|storage:256gb', 'price' => 30990000, 'compare_price' => 32990000, 'cost_price' => 26000000, 'stock' => 15],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 15 Pro', 'slug' => 'iphone-15-pro',
                    'description' => 'Titan tự nhiên với nút Action mới, A17 Pro và camera 48MP tiên tiến. Trải nghiệm Pro hoàn hảo.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP15PRO-128-NAT', 'option_signature' => 'color:titan-tu-nhien|storage:128gb', 'price' => 23990000, 'compare_price' => 28990000, 'cost_price' => 19500000, 'stock' => 15],
                    ['sku' => 'IP15PRO-256-BLK', 'option_signature' => 'color:titan-den|storage:256gb', 'price' => 27490000, 'compare_price' => 32490000, 'cost_price' => 23000000, 'stock' => 10],
                    ['sku' => 'IP15PRO-512-WHT', 'option_signature' => 'color:titan-trang|storage:512gb', 'price' => 32490000, 'compare_price' => 37490000, 'cost_price' => 27000000, 'stock' => 5],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 14', 'slug' => 'iphone-14',
                    'description' => 'Chip A15 Bionic, cụm camera dual 12MP và màn hình Super Retina XDR. Giá trị hàng đầu phân khúc tầm trung.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1615526675159-e248c3021d3f?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP14-128-MID', 'option_signature' => 'color:midnight|storage:128gb', 'price' => 15990000, 'compare_price' => 19990000, 'cost_price' => 12500000, 'stock' => 30],
                    ['sku' => 'IP14-256-PRP', 'option_signature' => 'color:tim|storage:256gb', 'price' => 18990000, 'compare_price' => 22990000, 'cost_price' => 15000000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 13', 'slug' => 'iphone-13',
                    'description' => 'Chip A15 Bionic, pin cải thiện 20%, màn hình sáng hơn 28% — model cũ nhưng vẫn rất mạnh mẽ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1632661674596-df8be070a5c5?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP13-128-GRN', 'option_signature' => 'color:xanh-la|storage:128gb', 'price' => 12490000, 'compare_price' => 15990000, 'cost_price' => 9500000, 'stock' => 25],
                    ['sku' => 'IP13-128-PNK', 'option_signature' => 'color:hong|storage:128gb', 'price' => 12490000, 'compare_price' => 15990000, 'cost_price' => 9500000, 'stock' => 20],
                    ['sku' => 'IP13-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 14490000, 'compare_price' => 17990000, 'cost_price' => 11000000, 'stock' => 15],
                ],
            ],

            // ── Samsung Galaxy ─────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy S25 Ultra', 'slug' => 'samsung-galaxy-s25-ultra',
                    'description' => 'Snapdragon 8 Elite, S Pen tích hợp, camera 200MP với AI thế hệ mới và màn hình Dynamic AMOLED 2X 6.9 inch.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-S25U-256-SLV', 'option_signature' => 'color:bac|storage:256gb', 'price' => 32990000, 'compare_price' => 35990000, 'cost_price' => 27000000, 'stock' => 20],
                    ['sku' => 'SS-S25U-512-BLK', 'option_signature' => 'color:den|storage:512gb', 'price' => 37990000, 'compare_price' => 41990000, 'cost_price' => 31000000, 'stock' => 12],
                    ['sku' => 'SS-S25U-1TB-TIT', 'option_signature' => 'color:titan-xanh|storage:1tb', 'price' => 44990000, 'compare_price' => 48990000, 'cost_price' => 37000000, 'stock' => 5],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy S25', 'slug' => 'samsung-galaxy-s25',
                    'description' => 'Snapdragon 8 Elite, màn hình 6.2 inch Dynamic AMOLED 120Hz, pin 4000mAh với sạc nhanh 25W.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-S25-128-BLU', 'option_signature' => 'color:xanh-duong|storage:128gb', 'price' => 21990000, 'compare_price' => 23990000, 'cost_price' => 18000000, 'stock' => 30],
                    ['sku' => 'SS-S25-256-GRN', 'option_signature' => 'color:xanh-la|storage:256gb', 'price' => 24990000, 'compare_price' => 26990000, 'cost_price' => 20500000, 'stock' => 25],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy A55 5G', 'slug' => 'samsung-galaxy-a55-5g',
                    'description' => 'Chip Exynos 1480, màn hình Super AMOLED 6.6 inch 120Hz, camera 50MP OIS, chống nước IP67.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1543721-4c77-4567-b34e-e4de26c06bba?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-A55-128-ICY', 'option_signature' => 'color:ice-blue|storage:128gb', 'price' => 9990000, 'compare_price' => 11990000, 'cost_price' => 7800000, 'stock' => 50],
                    ['sku' => 'SS-A55-256-AWE', 'option_signature' => 'color:awesome-navy|storage:256gb', 'price' => 11990000, 'compare_price' => 13990000, 'cost_price' => 9500000, 'stock' => 35],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy A35 5G', 'slug' => 'samsung-galaxy-a35-5g',
                    'description' => 'Màn hình Super AMOLED 6.6 inch 120Hz, pin 5000mAh, camera 50MP — giá trị tầm trung xuất sắc.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1543721-4c77-4567-b34e-e4de26c06bba?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-A35-128-LLD', 'option_signature' => 'color:lila|storage:128gb', 'price' => 7490000, 'compare_price' => 8990000, 'cost_price' => 5800000, 'stock' => 60],
                    ['sku' => 'SS-A35-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 8990000, 'compare_price' => 10490000, 'cost_price' => 7000000, 'stock' => 40],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy M55 5G', 'slug' => 'samsung-galaxy-m55-5g',
                    'description' => 'Pin khổng lồ 6000mAh, sạc nhanh 45W, Snapdragon 7 Gen 1 và màn hình Super AMOLED 6.7 inch.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1543721-4c77-4567-b34e-e4de26c06bba?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-M55-256-BLU', 'option_signature' => 'color:xanh-duong|storage:256gb', 'price' => 8490000, 'compare_price' => 9990000, 'cost_price' => 6500000, 'stock' => 45],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy Z Fold 6', 'slug' => 'samsung-galaxy-z-fold6',
                    'description' => 'Smartphone gập cao cấp với màn hình ngoài 6.3 inch và màn hình trong 7.6 inch, Snapdragon 8 Gen 3.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-ZF6-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 48990000, 'compare_price' => 54990000, 'cost_price' => 41000000, 'stock' => 8],
                    ['sku' => 'SS-ZF6-512-SLV', 'option_signature' => 'color:bac|storage:512gb', 'price' => 55990000, 'compare_price' => 59990000, 'cost_price' => 47000000, 'stock' => 5],
                ],
            ],

            // ── Xiaomi ────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi 15 Ultra Leica', 'slug' => 'xiaomi-15-ultra-leica',
                    'description' => 'Snapdragon 8 Elite, camera Leica 50MP, sạc siêu nhanh HyperCharge 90W và pin 5300mAh.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'MI15U-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 29990000, 'compare_price' => 32990000, 'cost_price' => 25000000, 'stock' => 15],
                    ['sku' => 'MI15U-512-WHT', 'option_signature' => 'color:trang|storage:512gb', 'price' => 34990000, 'compare_price' => 37990000, 'cost_price' => 29000000, 'stock' => 8],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi Redmi Note 13 Pro+', 'slug' => 'xiaomi-redmi-note-13-pro-plus',
                    'description' => 'Dimensity 7200 Ultra, camera 200MP OIS, màn hình AMOLED 6.67 inch 120Hz và sạc nhanh 120W.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RDN13P-256-AUR', 'option_signature' => 'color:aurora-purple|storage:256gb', 'price' => 8990000, 'compare_price' => 10490000, 'cost_price' => 6800000, 'stock' => 55],
                    ['sku' => 'RDN13P-512-BLK', 'option_signature' => 'color:midnight-black|storage:512gb', 'price' => 10490000, 'compare_price' => 11990000, 'cost_price' => 8000000, 'stock' => 35],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi POCO F6 Pro', 'slug' => 'xiaomi-poco-f6-pro',
                    'description' => 'Snapdragon 8 Gen 2, màn hình LTPO AMOLED 2K 120Hz, pin 5000mAh sạc nhanh 67W. Flagship killer thực sự.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'PF6P-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 12990000, 'compare_price' => 14990000, 'cost_price' => 10000000, 'stock' => 30],
                    ['sku' => 'PF6P-512-WHT', 'option_signature' => 'color:trang|storage:512gb', 'price' => 14990000, 'compare_price' => 16990000, 'cost_price' => 12000000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi Redmi 13C', 'slug' => 'xiaomi-redmi-13c',
                    'description' => 'Helio G85, màn hình 6.74 inch 90Hz, camera 50MP — điện thoại giá rẻ dành cho mọi đối tượng.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RD13C-128-GRN', 'option_signature' => 'color:xanh-la|storage:128gb', 'price' => 2990000, 'compare_price' => 3490000, 'cost_price' => 2200000, 'stock' => 100],
                    ['sku' => 'RD13C-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 3490000, 'compare_price' => 3990000, 'cost_price' => 2600000, 'stock' => 80],
                ],
            ],

            // ── OPPO ──────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $oppo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'OPPO Find X8 Pro', 'slug' => 'oppo-find-x8-pro',
                    'description' => 'Dimensity 9400, camera Hasselblad 50MP, sạc siêu nhanh SUPERVOOC 80W và chống nước IP69.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'FX8P-256-BLK', 'option_signature' => 'color:den-nhung|storage:256gb', 'price' => 27990000, 'compare_price' => 30990000, 'cost_price' => 23000000, 'stock' => 15],
                    ['sku' => 'FX8P-512-WHT', 'option_signature' => 'color:trang-moon|storage:512gb', 'price' => 32990000, 'compare_price' => 35990000, 'cost_price' => 27000000, 'stock' => 8],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $oppo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'OPPO Reno 12 Pro', 'slug' => 'oppo-reno-12-pro',
                    'description' => 'Dimensity 9200+, camera chân dung AI 50MP, thiết kế mỏng nhẹ 7.42mm và màn hình AMOLED 6.7 inch 120Hz.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RN12P-256-GRN', 'option_signature' => 'color:xanh-ngoc|storage:256gb', 'price' => 11990000, 'compare_price' => 13990000, 'cost_price' => 9000000, 'stock' => 35],
                    ['sku' => 'RN12P-512-PNK', 'option_signature' => 'color:hong-pastel|storage:512gb', 'price' => 13990000, 'compare_price' => 15990000, 'cost_price' => 11000000, 'stock' => 25],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $oppo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'OPPO A3x', 'slug' => 'oppo-a3x',
                    'description' => 'Snapdragon 695 5G, pin 5100mAh sạc nhanh 33W, chống nước IP54. Điện thoại tầm thấp đáng mua.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'OA3X-128-BLU', 'option_signature' => 'color:xanh-duong|storage:128gb', 'price' => 3490000, 'compare_price' => 3990000, 'cost_price' => 2500000, 'stock' => 80],
                ],
            ],

            // ── Sony ──────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $sony?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Sony Xperia 1 VI', 'slug' => 'sony-xperia-1-vi',
                    'description' => 'Snapdragon 8 Gen 3, màn hình OLED 6.5 inch 120Hz, zoom quang học 85-170mm Zeiss và chống nước IP65/68.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SX1VI-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 32990000, 'compare_price' => 36990000, 'cost_price' => 27000000, 'stock' => 10],
                    ['sku' => 'SX1VI-512-GRN', 'option_signature' => 'color:khaki-green|storage:512gb', 'price' => 37990000, 'compare_price' => 41990000, 'cost_price' => 31000000, 'stock' => 5],
                ],
            ],

            // ── Vivo ──────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $vivo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Vivo X100 Ultra', 'slug' => 'vivo-x100-ultra',
                    'description' => 'Dimensity 9300+, camera Zeiss 200MP, sạc siêu nhanh 80W và pin 6000mAh. Siêu phẩm chụp ảnh đêm.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'VX100U-512-BLK', 'option_signature' => 'color:den|storage:512gb', 'price' => 34990000, 'compare_price' => 38990000, 'cost_price' => 29000000, 'stock' => 10],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $vivo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Vivo V40 Lite', 'slug' => 'vivo-v40-lite',
                    'description' => 'Snapdragon 695 5G, camera selfie AI 50MP siêu rõ, pin 6000mAh sạc nhanh 44W.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'VV40L-256-PRP', 'option_signature' => 'color:tim|storage:256gb', 'price' => 7490000, 'compare_price' => 8990000, 'cost_price' => 5500000, 'stock' => 50],
                    ['sku' => 'VV40L-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 7490000, 'compare_price' => 8990000, 'cost_price' => 5500000, 'stock' => 40],
                ],
            ],

            // ── Realme ────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $realme?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Realme GT 6T 5G', 'slug' => 'realme-gt-6t-5g',
                    'description' => 'Snapdragon 7+ Gen 3, màn hình 1.5K AMOLED 144Hz, sạc nhanh SUPERVOOC 120W. Hiệu năng vượt tầm giá.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RGT6T-256-BLK', 'option_signature' => 'color:den|storage:256gb', 'price' => 10990000, 'compare_price' => 12990000, 'cost_price' => 8500000, 'stock' => 35],
                    ['sku' => 'RGT6T-512-GRN', 'option_signature' => 'color:racing-green|storage:512gb', 'price' => 12490000, 'compare_price' => 13990000, 'cost_price' => 9800000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $realme?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Realme C65', 'slug' => 'realme-c65',
                    'description' => 'Helio G85, pin 5000mAh sạc nhanh 33W, màn hình 6.67 inch 90Hz — giá rẻ nhất phân khúc.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RC65-128-GLD', 'option_signature' => 'color:vang|storage:128gb', 'price' => 2490000, 'compare_price' => 2990000, 'cost_price' => 1800000, 'stock' => 120],
                ],
            ],

            // ── OnePlus ───────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $oneplus?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'OnePlus 13R', 'slug' => 'oneplus-13r',
                    'description' => 'Snapdragon 8 Gen 2, màn hình ProXDR 6.78 inch 120Hz, pin 6000mAh sạc nhanh 100W SUPERVOOC.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'OP13R-256-BLK', 'option_signature' => 'color:nebula-noir|storage:256gb', 'price' => 15990000, 'compare_price' => 17990000, 'cost_price' => 12500000, 'stock' => 25],
                ],
            ],

            // ── Asus ROG ──────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $asus?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Asus ROG Phone 8 Pro', 'slug' => 'asus-rog-phone-8-pro',
                    'description' => 'Snapdragon 8 Gen 3, màn hình OLED 165Hz, hệ thống làm mát AeroActive Cooler 8, pin 5500mAh. Gaming beast.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ROG8P-512-BLK', 'option_signature' => 'color:phantom-black|storage:512gb', 'price' => 29990000, 'compare_price' => 33990000, 'cost_price' => 24000000, 'stock' => 10],
                ],
            ],

            // ── Tablets ───────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $tabletCat?->id,
                    'product_type' => 'phone', 'name' => 'iPad Pro M4 11 inch', 'slug' => 'ipad-pro-m4-11-inch',
                    'description' => 'Chip M4 mạnh mẽ, màn hình Ultra Retina XDR 11 inch OLED, mỏng nhất thế giới chỉ 5.1mm.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IPDP-M4-256-SLV', 'option_signature' => 'color:bac|storage:256gb', 'price' => 27990000, 'compare_price' => 29990000, 'cost_price' => 23000000, 'stock' => 20],
                    ['sku' => 'IPDP-M4-512-BLK', 'option_signature' => 'color:khong-gian-den|storage:512gb', 'price' => 32990000, 'compare_price' => 34990000, 'cost_price' => 27000000, 'stock' => 15],
                    ['sku' => 'IPDP-M4-1TB-SLV', 'option_signature' => 'color:bac|storage:1tb', 'price' => 42990000, 'compare_price' => 44990000, 'cost_price' => 36000000, 'stock' => 8],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $tabletCat?->id,
                    'product_type' => 'phone', 'name' => 'iPad Air M2 11 inch', 'slug' => 'ipad-air-m2-11-inch',
                    'description' => 'Chip M2, màn hình Liquid Retina 11 inch 2360×1640px, hỗ trợ Apple Pencil Pro và Magic Keyboard.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IPAM2-128-BLU', 'option_signature' => 'color:xanh-duong|storage:128gb', 'price' => 16990000, 'compare_price' => 18990000, 'cost_price' => 13500000, 'stock' => 25],
                    ['sku' => 'IPAM2-256-GRY', 'option_signature' => 'color:grey|storage:256gb', 'price' => 19990000, 'compare_price' => 21990000, 'cost_price' => 16000000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $tabletCat?->id,
                    'product_type' => 'phone', 'name' => 'iPad 10th Gen', 'slug' => 'ipad-10th-gen',
                    'description' => 'Chip A14 Bionic, màn hình 10.9 inch Liquid Retina, thiết kế mới hoàn toàn với USB-C và 5G tùy chọn.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP10-64-YLW', 'option_signature' => 'color:vang|storage:64gb', 'price' => 11490000, 'compare_price' => 12990000, 'cost_price' => 9000000, 'stock' => 30],
                    ['sku' => 'IP10-256-BLU', 'option_signature' => 'color:xanh-duong|storage:256gb', 'price' => 14490000, 'compare_price' => 15990000, 'cost_price' => 11500000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $tabletCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy Tab S10 Ultra', 'slug' => 'samsung-galaxy-tab-s10-ultra',
                    'description' => 'Snapdragon 8 Gen 3, màn hình Super AMOLED 14.6 inch, S Pen đi kèm và 12GB RAM.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SGT-S10U-256-GRY', 'option_signature' => 'color:graphite|storage:256gb', 'price' => 29990000, 'compare_price' => 32990000, 'cost_price' => 24500000, 'stock' => 12],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $lenovo?->id ?? $brands->get('lenovo')?->id, 'category_id' => $tabletCat?->id,
                    'product_type' => 'phone', 'name' => 'Lenovo Tab P12 Pro', 'slug' => 'lenovo-tab-p12-pro',
                    'description' => 'Snapdragon 870, màn hình AMOLED 12.6 inch 2K, 8GB RAM và pin 10200mAh. Máy tính bảng Android tầm trung xuất sắc.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'LTP12P-256-GRY', 'option_signature' => 'color:storm-grey|storage:256gb', 'price' => 12990000, 'compare_price' => 14990000, 'cost_price' => 10000000, 'stock' => 20],
                ],
            ],

            // ── Out-of-stock / draft products ─────────────────────────────
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone SE 4 (2024)', 'slug' => 'iphone-se-4-2024',
                    'description' => 'Chip A17 Pro trong thân hình nhỏ gọn — model SE mới nhất với màn hình 6.1 inch và Dynamic Island.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IPSE4-128-BLK', 'option_signature' => 'color:den|storage:128gb', 'price' => 11990000, 'compare_price' => 12990000, 'cost_price' => 9500000, 'stock' => 0],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy S23 FE', 'slug' => 'samsung-galaxy-s23-fe',
                    'description' => 'Fan Edition của Galaxy S23 với Snapdragon 8 Gen 1, camera 50MP và màn hình AMOLED 6.4 inch.',
                    'status' => 'inactive',
                ],
                'image' => 'https://images.unsplash.com/photo-1543721-4c77-4567-b34e-e4de26c06bba?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-S23FE-256-MNT', 'option_signature' => 'color:mint|storage:256gb', 'price' => 10490000, 'compare_price' => 13990000, 'cost_price' => 8000000, 'stock' => 0],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi 14T Pro (Sắp về)', 'slug' => 'xiaomi-14t-pro-sap-ve',
                    'description' => 'Dimensity 9300+, camera Leica 50MP, sạc siêu nhanh 100W. Dự kiến về hàng tháng 12/2024.',
                    'status' => 'draft',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'MI14T-256-TIT', 'option_signature' => 'color:titan-blue|storage:256gb', 'price' => 15990000, 'compare_price' => 17990000, 'cost_price' => 12500000, 'stock' => 0],
                ],
            ],

            // ── Bổ sung các phân khúc giá khác nhau để test bộ lọc ──
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $iphoneCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'iPhone 15 Plus 128GB', 'slug' => 'iphone-15-plus-128gb',
                    'description' => 'Màn hình 6.7 inch Super Retina XDR, Dynamic Island, thời lượng pin cả ngày dài và cổng USB-C tiện lợi.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'IP15P-128-PNK', 'option_signature' => 'color:hong|storage:128gb', 'price' => 22490000, 'compare_price' => 24990000, 'cost_price' => 19000000, 'stock' => 20],
                    ['sku' => 'IP15P-256-BLU', 'option_signature' => 'color:xanh-duong|storage:256gb', 'price' => 25490000, 'compare_price' => 27990000, 'cost_price' => 21500000, 'stock' => 15],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy Z Flip 6', 'slug' => 'samsung-galaxy-z-flip6',
                    'description' => 'Smartphone gập vỏ sò nhỏ gọn thời trang, màn hình phụ FlexWindow 3.4 inch và camera 50MP AI.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-ZF6-256-YLW', 'option_signature' => 'color:vang-toa-sang|storage:256gb', 'price' => 24990000, 'compare_price' => 28990000, 'cost_price' => 20500000, 'stock' => 18],
                    ['sku' => 'SS-ZF6-512-BLU', 'option_signature' => 'color:xanh-maya|storage:512gb', 'price' => 28990000, 'compare_price' => 32990000, 'cost_price' => 24000000, 'stock' => 12],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sgCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Samsung Galaxy A15 5G', 'slug' => 'samsung-galaxy-a15-5g',
                    'description' => 'Màn hình Super AMOLED 90Hz rực rỡ, pin trâu 5000mAh, kết nối 5G tốc độ cao và camera 50MP.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1543721-4c77-4567-b34e-e4de26c06bba?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-A15-128-BLU', 'option_signature' => 'color:xanh-duong|storage:128gb', 'price' => 4690000, 'compare_price' => 5290000, 'cost_price' => 3800000, 'stock' => 80],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $xiaomi?->id, 'category_id' => $xiCat?->id ?? $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Xiaomi POCO X6 Pro 5G', 'slug' => 'xiaomi-poco-x6-pro-5g',
                    'description' => 'Dimensity 8300-Ultra siêu khủng trong tầm giá, màn hình CrystalRes 120Hz Flow AMOLED và sạc Turbo 67W.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'PC-X6P-256-YLW', 'option_signature' => 'color:vang-poco|storage:256gb', 'price' => 7990000, 'compare_price' => 9990000, 'cost_price' => 6500000, 'stock' => 45],
                    ['sku' => 'PC-X6P-512-BLK', 'option_signature' => 'color:den|storage:512gb', 'price' => 8990000, 'compare_price' => 10990000, 'cost_price' => 7400000, 'stock' => 30],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $oppo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'OPPO Reno 11 F 5G', 'slug' => 'oppo-reno-11f-5g',
                    'description' => 'Thiết kế viền siêu mỏng kháng nước IP65, camera chuyên gia chân dung 64MP và sạc siêu nhanh SUPERVOOC 67W.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RN11F-256-GRN', 'option_signature' => 'color:xanh-co-may|storage:256gb', 'price' => 7290000, 'compare_price' => 8490000, 'cost_price' => 5900000, 'stock' => 50],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $vivo?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Vivo Y28 4G', 'slug' => 'vivo-y28-4g',
                    'description' => 'Pin siêu khủng 6000mAh bền bỉ 4 năm, loa kép âm lượng 300% và màn hình 90Hz bảo vệ mắt.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'VY28-128-ORG', 'option_signature' => 'color:cam-anh-duong|storage:128gb', 'price' => 4990000, 'compare_price' => 5790000, 'cost_price' => 3900000, 'stock' => 60],
                    ['sku' => 'VY28-256-GRN', 'option_signature' => 'color:xanh-ngoc|storage:256gb', 'price' => 5990000, 'compare_price' => 6490000, 'cost_price' => 4800000, 'stock' => 40],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $realme?->id, 'category_id' => $androidCat?->id ?? $phoneCat?->id,
                    'product_type' => 'phone', 'name' => 'Realme Note 50', 'slug' => 'realme-note-50',
                    'description' => 'Màn hình 6.74 inch 90Hz mượt mà, thân máy siêu mỏng 7.99mm, kháng nước chuẩn IP54 với giá siêu rẻ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'RMN50-64-BLU', 'option_signature' => 'color:xanh-anh-kim|storage:64gb', 'price' => 1990000, 'compare_price' => 2490000, 'cost_price' => 1500000, 'stock' => 100],
                    ['sku' => 'RMN50-128-BLK', 'option_signature' => 'color:den-dem|storage:128gb', 'price' => 2390000, 'compare_price' => 2890000, 'cost_price' => 1800000, 'stock' => 90],
                ],
            ],
        ];

        foreach ($phones as $entry) {
            $this->seedProduct($entry);
        }
    }

    // ── Seed accessories ──────────────────────────────────────────────────────
    private function seedAccessories(object $brands, object $cats): void
    {
        $apple   = $brands->get('apple');
        $samsung = $brands->get('samsung');
        $anker   = $brands->get('anker');
        $baseus  = $brands->get('baseus');
        $sony    = $brands->get('sony');
        $jbl     = $brands->get('jbl');

        $accCat     = $cats->get('phu-kien');
        $sacCat     = $cats->get('sac');
        $capCat     = $cats->get('cap-sac');
        $opCat      = $cats->get('op-lung');
        $taiNgheCat = $cats->get('tai-nghe');
        $loaCat     = $cats->get('loa-bluetooth');

        $accessories = [
            // ── Tai nghe ──────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Apple AirPods 4 ANC', 'slug' => 'apple-airpods-4-anc',
                    'description' => 'AirPods 4 với Chủ động khử tiếng ồn, chip H2 mới và bộ nhớ NAND cải tiến. Âm thanh xuất sắc từ thiết kế hở.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'APP4-ANC-WHT', 'option_signature' => 'color:trang', 'price' => 3990000, 'compare_price' => 4490000, 'cost_price' => 3200000, 'stock' => 60],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $sony?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Sony WH-1000XM5', 'slug' => 'sony-wh-1000xm5',
                    'description' => 'Tai nghe chụp tai ANC hàng đầu thế giới với 30 giờ pin, 8 micro khử tiếng ồn và âm thanh Hi-Res 360.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SWH5-BLK', 'option_signature' => 'color:den', 'price' => 7490000, 'compare_price' => 8990000, 'cost_price' => 5800000, 'stock' => 30],
                    ['sku' => 'SWH5-BEG', 'option_signature' => 'color:beige', 'price' => 7490000, 'compare_price' => 8990000, 'cost_price' => 5800000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $sony?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Sony WF-1000XM5', 'slug' => 'sony-wf-1000xm5',
                    'description' => 'Tai nghe true wireless ANC nhỏ nhất, nhẹ nhất từ Sony với chất âm Hi-Res và pin 24 giờ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SWF5-BLK', 'option_signature' => 'color:den', 'price' => 5990000, 'compare_price' => 6990000, 'cost_price' => 4700000, 'stock' => 25],
                    ['sku' => 'SWF5-SLV', 'option_signature' => 'color:bac-platinum', 'price' => 5990000, 'compare_price' => 6990000, 'cost_price' => 4700000, 'stock' => 15],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Samsung Galaxy Buds3 Pro', 'slug' => 'samsung-galaxy-buds3-pro',
                    'description' => 'TWS ANC cao cấp với thiết kế cánh thăng bằng mới, ANC mạnh hơn 2x và chất âm Hi-Fi 24-bit.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SGB3P-SLV', 'option_signature' => 'color:bac', 'price' => 3990000, 'compare_price' => 4990000, 'cost_price' => 3000000, 'stock' => 40],
                    ['sku' => 'SGB3P-BLK', 'option_signature' => 'color:den', 'price' => 3990000, 'compare_price' => 4990000, 'cost_price' => 3000000, 'stock' => 35],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $jbl?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'JBL Tune 770NC', 'slug' => 'jbl-tune-770nc',
                    'description' => 'Tai nghe chụp tai ANC với 70 giờ pin, kết nối đa điểm và âm thanh JBL Pure Bass.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'JBL770-BLK', 'option_signature' => 'color:den', 'price' => 1890000, 'compare_price' => 2490000, 'cost_price' => 1400000, 'stock' => 50],
                    ['sku' => 'JBL770-BLU', 'option_signature' => 'color:xanh-duong', 'price' => 1890000, 'compare_price' => 2490000, 'cost_price' => 1400000, 'stock' => 40],
                    ['sku' => 'JBL770-WHT', 'option_signature' => 'color:trang', 'price' => 1890000, 'compare_price' => 2490000, 'cost_price' => 1400000, 'stock' => 35],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $jbl?->id, 'category_id' => $taiNgheCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'JBL Wave Buds', 'slug' => 'jbl-wave-buds',
                    'description' => 'TWS giá rẻ với 32 giờ pin, kháng nước IPX2 và âm thanh JBL Pure Bass.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'JBLWB-WHT', 'option_signature' => 'color:trang', 'price' => 590000, 'compare_price' => 790000, 'cost_price' => 420000, 'stock' => 80],
                    ['sku' => 'JBLWB-BLK', 'option_signature' => 'color:den', 'price' => 590000, 'compare_price' => 790000, 'cost_price' => 420000, 'stock' => 70],
                ],
            ],

            // ── Sạc ──────────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $anker?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Sạc nhanh Anker 523 Nano III 47W', 'slug' => 'sac-anker-523-nano-iii-47w',
                    'description' => 'GaN tối ưu, 2 cổng USB-C + USB-A, sạc nhanh PD 3.0 và QC 3.0 đồng thời.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ANK523-BLK', 'option_signature' => 'color:den', 'price' => 590000, 'compare_price' => 750000, 'cost_price' => 420000, 'stock' => 75],
                    ['sku' => 'ANK523-WHT', 'option_signature' => 'color:trang', 'price' => 590000, 'compare_price' => 750000, 'cost_price' => 420000, 'stock' => 60],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $anker?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Pin sạc dự phòng Anker PowerCore 20000 PD', 'slug' => 'pin-sac-du-phong-anker-powercore-20000-pd',
                    'description' => '20000mAh, PD 30W, 2 cổng USB-C + 2 USB-A, sạc được laptop MacBook và iPhone cùng lúc.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ANKPC20-BLK', 'option_signature' => 'color:den', 'price' => 1290000, 'compare_price' => 1590000, 'cost_price' => 950000, 'stock' => 55],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $baseus?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Sạc không dây Baseus Magnetic 15W', 'slug' => 'sac-khong-day-baseus-magnetic-15w',
                    'description' => 'Sạc không dây MagSafe tương thích 15W, bộ đế giữ có nam châm giúp sạc thẳng đứng tiện lợi.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'BSMS15-WHT', 'option_signature' => 'color:trang', 'price' => 490000, 'compare_price' => 650000, 'cost_price' => 340000, 'stock' => 90],
                    ['sku' => 'BSMS15-BLK', 'option_signature' => 'color:den', 'price' => 490000, 'compare_price' => 650000, 'cost_price' => 340000, 'stock' => 75],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Samsung 45W Super Fast Charger', 'slug' => 'samsung-45w-super-fast-charger',
                    'description' => 'Bộ sạc nhanh 45W USB-C chính hãng Samsung, tương thích Galaxy S22 trở lên và hầu hết thiết bị USB-C.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS45W-BLK', 'option_signature' => 'color:den', 'price' => 590000, 'compare_price' => 690000, 'cost_price' => 430000, 'stock' => 80],
                ],
            ],

            // ── Cáp sạc ──────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $baseus?->id, 'category_id' => $capCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Cáp Baseus Crystal USB-C to Lightning 1m', 'slug' => 'cap-baseus-crystal-usbc-lightning-1m',
                    'description' => 'Cáp sạc nhanh 20W USB-C sang Lightning dài 1m, bọc dệt bền dày, hỗ trợ MFi chính thức Apple.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'BSCC-UL1M-WHT', 'option_signature' => 'color:trang|length:1m', 'price' => 199000, 'compare_price' => 290000, 'cost_price' => 130000, 'stock' => 150],
                    ['sku' => 'BSCC-UL2M-WHT', 'option_signature' => 'color:trang|length:2m', 'price' => 290000, 'compare_price' => 390000, 'cost_price' => 200000, 'stock' => 100],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $anker?->id, 'category_id' => $capCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Cáp Anker 240W USB-C to USB-C Nylon', 'slug' => 'cap-anker-240w-usbc-nylon',
                    'description' => 'Cáp USB-C to USB-C 240W chuẩn USB4, hỗ trợ PD 3.1, bọc dệt nylon cao cấp dài 1.8m.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ANK240W-BLK-1.8', 'option_signature' => 'color:den|length:1.8m', 'price' => 350000, 'compare_price' => 450000, 'cost_price' => 240000, 'stock' => 120],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $capCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Apple USB-C Charge Cable 2m (Chính hãng)', 'slug' => 'apple-usbc-charge-cable-2m',
                    'description' => 'Cáp USB-C chính hãng Apple dài 2m, hỗ trợ sạc nhanh và truyền dữ liệu USB 2.0.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'APUSBC-2M-WHT', 'option_signature' => 'color:trang|length:2m', 'price' => 590000, 'compare_price' => 690000, 'cost_price' => 420000, 'stock' => 60],
                ],
            ],

            // ── Ốp lưng ──────────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $opCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Apple MagSafe Silicone Case iPhone 16 Pro Max', 'slug' => 'apple-magsafe-silicone-case-iphone-16-pro-max',
                    'description' => 'Ốp lưng silicon chính hãng Apple MagSafe, chất liệu silicon mềm mịn, lớp vi sợi nội thất bảo vệ máy.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1601972599720-36938d4ecd31?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'APMS-SC-IP16PM-BLK', 'option_signature' => 'color:den', 'price' => 1190000, 'compare_price' => 1390000, 'cost_price' => 850000, 'stock' => 45],
                    ['sku' => 'APMS-SC-IP16PM-BLU', 'option_signature' => 'color:xanh-duong', 'price' => 1190000, 'compare_price' => 1390000, 'cost_price' => 850000, 'stock' => 40],
                    ['sku' => 'APMS-SC-IP16PM-GRN', 'option_signature' => 'color:xanh-la', 'price' => 1190000, 'compare_price' => 1390000, 'cost_price' => 850000, 'stock' => 35],
                    ['sku' => 'APMS-SC-IP16PM-RED', 'option_signature' => 'color:do', 'price' => 1190000, 'compare_price' => 1390000, 'cost_price' => 850000, 'stock' => 30],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $baseus?->id, 'category_id' => $opCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Ốp lưng Baseus Liquid Silica Gel iPhone 16 Pro', 'slug' => 'op-lung-baseus-liquid-silica-gel-iphone-16-pro',
                    'description' => 'Ốp lưng silicon lỏng cao cấp, cảm giác cầm mềm mịn, bảo vệ toàn diện 360 độ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1601972599720-36938d4ecd31?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'BSLSG-IP16P-BLK', 'option_signature' => 'color:den', 'price' => 299000, 'compare_price' => 399000, 'cost_price' => 200000, 'stock' => 90],
                    ['sku' => 'BSLSG-IP16P-PRP', 'option_signature' => 'color:tim', 'price' => 299000, 'compare_price' => 399000, 'cost_price' => 200000, 'stock' => 70],
                    ['sku' => 'BSLSG-IP16P-BLU', 'option_signature' => 'color:xanh-duong', 'price' => 299000, 'compare_price' => 399000, 'cost_price' => 200000, 'stock' => 65],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $opCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Samsung Galaxy S25 Ultra Smart View Wallet Case', 'slug' => 'samsung-s25-ultra-smart-view-wallet-case',
                    'description' => 'Bao da Smart View chính hãng, nắp lật xem giờ và thông báo, kiêm ví thẻ tiện lợi.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1601972599720-36938d4ecd31?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'SS-SVWC-S25U-BLK', 'option_signature' => 'color:den', 'price' => 890000, 'compare_price' => 990000, 'cost_price' => 640000, 'stock' => 35],
                ],
            ],

            // ── Loa bluetooth ─────────────────────────────────────────────
            [
                'product' => [
                    'brand_id' => $jbl?->id, 'category_id' => $loaCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'JBL Charge 5', 'slug' => 'jbl-charge-5',
                    'description' => 'Loa bluetooth chống nước IP67, pin 20 giờ, cổng sạc USB-C ra ngoài và âm thanh JBL Pro Sound.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'JBL-C5-BLK', 'option_signature' => 'color:den', 'price' => 3490000, 'compare_price' => 3990000, 'cost_price' => 2600000, 'stock' => 30],
                    ['sku' => 'JBL-C5-BLU', 'option_signature' => 'color:xanh-duong', 'price' => 3490000, 'compare_price' => 3990000, 'cost_price' => 2600000, 'stock' => 25],
                    ['sku' => 'JBL-C5-RED', 'option_signature' => 'color:do', 'price' => 3490000, 'compare_price' => 3990000, 'cost_price' => 2600000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $jbl?->id, 'category_id' => $loaCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'JBL Flip 7', 'slug' => 'jbl-flip-7',
                    'description' => 'Loa bluetooth di động IP67, pin 12 giờ, Auracast 1-to-many và thiết kế mới siêu gọn nhẹ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'JBL-F7-BLK', 'option_signature' => 'color:den', 'price' => 1990000, 'compare_price' => 2390000, 'cost_price' => 1450000, 'stock' => 50],
                    ['sku' => 'JBL-F7-TL', 'option_signature' => 'color:teal', 'price' => 1990000, 'compare_price' => 2390000, 'cost_price' => 1450000, 'stock' => 40],
                    ['sku' => 'JBL-F7-RED', 'option_signature' => 'color:do', 'price' => 1990000, 'compare_price' => 2390000, 'cost_price' => 1450000, 'stock' => 35],
                ],
            ],

            // ── Loa bluetooth mini ──
            [
                'product' => [
                    'brand_id' => $jbl?->id, 'category_id' => $loaCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'JBL GO 4 Mini', 'slug' => 'jbl-go-4-mini',
                    'description' => 'Loa di động siêu nhỏ gọn bỏ túi, chuẩn chống bụi nước IP67, thời lượng pin 7 giờ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'JBL-GO4-BLK', 'option_signature' => 'color:den', 'price' => 890000, 'compare_price' => 1090000, 'cost_price' => 600000, 'stock' => 70],
                    ['sku' => 'JBL-GO4-BLU', 'option_signature' => 'color:xanh-duong', 'price' => 890000, 'compare_price' => 1090000, 'cost_price' => 600000, 'stock' => 50],
                ],
            ],

            // ── Smartwatches (Đồng hồ thông minh) ──
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $cats->get('smartwatch')?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Apple Watch Series 10 GPS 42mm', 'slug' => 'apple-watch-series-10-gps-42mm',
                    'description' => 'Màn hình OLED góc nhìn rộng siêu sáng, thiết kế mỏng nhất từ trước đến nay, sạc nhanh 80% trong 30 phút.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'AW-S10-42-BLK', 'option_signature' => 'color:nhom-den-bong|size:42mm', 'price' => 10490000, 'compare_price' => 10990000, 'cost_price' => 8800000, 'stock' => 25],
                    ['sku' => 'AW-S10-42-SLV', 'option_signature' => 'color:nhom-bac|size:42mm', 'price' => 10490000, 'compare_price' => 10990000, 'cost_price' => 8800000, 'stock' => 20],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $apple?->id, 'category_id' => $cats->get('smartwatch')?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Apple Watch Ultra 2 Titanium 49mm', 'slug' => 'apple-watch-ultra-2-49mm',
                    'description' => 'Vỏ titan chuẩn hàng không vũ trụ, GPS tần số kép độ chính xác cao, thời lượng pin đến 72 giờ ở chế độ nguồn điện thấp.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'AW-ULTRA2-BLK', 'option_signature' => 'color:titan-den|size:49mm', 'price' => 21490000, 'compare_price' => 22990000, 'cost_price' => 18000000, 'stock' => 15],
                    ['sku' => 'AW-ULTRA2-NAT', 'option_signature' => 'color:titan-tu-nhien|size:49mm', 'price' => 21490000, 'compare_price' => 22990000, 'cost_price' => 18000000, 'stock' => 10],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $samsung?->id, 'category_id' => $cats->get('smartwatch')?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Samsung Galaxy Watch 7 Bluetooth 40mm', 'slug' => 'samsung-galaxy-watch-7-40mm',
                    'description' => 'Bộ vi xử lý 3nm mạnh mẽ, cảm biến BioActive thế hệ mới theo dõi giấc ngủ và nhịp tim chính xác tuyệt đối.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'GW7-40-GRN', 'option_signature' => 'color:xanh-quan-doi|size:40mm', 'price' => 5990000, 'compare_price' => 6990000, 'cost_price' => 4500000, 'stock' => 30],
                    ['sku' => 'GW7-40-CRM', 'option_signature' => 'color:kem|size:40mm', 'price' => 5990000, 'compare_price' => 6990000, 'cost_price' => 4500000, 'stock' => 25],
                ],
            ],

            // ── Củ sạc & pin dự phòng cao cấp ──
            [
                'product' => [
                    'brand_id' => $baseus?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Củ sạc nhanh Baseus GaN5 Pro 100W 4 cổng', 'slug' => 'cu-sac-baseus-gan5-pro-100w',
                    'description' => 'Công suất khủng 100W với 2 cổng Type-C và 2 cổng USB-A, sạc nhanh cho cả MacBook Pro và iPhone.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'BS-GAN5-100W-BLK', 'option_signature' => 'color:den', 'price' => 890000, 'compare_price' => 1150000, 'cost_price' => 620000, 'stock' => 55],
                ],
            ],
            [
                'product' => [
                    'brand_id' => $anker?->id, 'category_id' => $sacCat?->id ?? $accCat?->id,
                    'product_type' => 'accessory', 'name' => 'Pin dự phòng Anker MagGo Qi2 10000mAh', 'slug' => 'pin-du-phong-anker-maggo-qi2-10000mah',
                    'description' => 'Chuẩn sạc không dây Qi2 15W hít nam châm siêu chắc, màn hình thông minh hiển thị pin và thời gian sạc.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    ['sku' => 'ANK-MAGGO-10K-WHT', 'option_signature' => 'color:trang', 'price' => 1490000, 'compare_price' => 1890000, 'cost_price' => 1050000, 'stock' => 40],
                    ['sku' => 'ANK-MAGGO-10K-BLK', 'option_signature' => 'color:den', 'price' => 1490000, 'compare_price' => 1890000, 'cost_price' => 1050000, 'stock' => 35],
                ],
            ],
        ];

        foreach ($accessories as $entry) {
            $this->seedProduct($entry);
        }
    }

    // ── Seed reviews ─────────────────────────────────────────────────────────
    private function seedReviews(): void
    {
        $customer = User::where('email', 'customer@mobilex.tech')->first();
        if (! $customer) {
            return;
        }

        $shippingMethod = ShippingMethod::first();
        if (! $shippingMethod) {
            $shippingMethod = ShippingMethod::create([
                'name' => 'Giao hàng tiêu chuẩn',
                'code' => 'standard',
                'description' => 'Giao hàng toàn quốc 2-3 ngày',
                'fee' => 0.00,
                'is_active' => true,
            ]);
        }

        $order = Order::firstOrCreate(
            ['order_code' => 'MX-DEMO-SAMPLE-DELIVERED'],
            [
                'user_id' => $customer->id,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => 60000000.00,
                'discount_amount' => 0.00,
                'shipping_fee' => 0.00,
                'total_amount' => 60000000.00,
                'status' => 'delivered',
                'payment_status' => 'paid',
                'payment_method' => 'vnpay',
                'shipping_name' => $customer->name ?? 'Demo Customer',
                'shipping_phone' => '0987654321',
                'shipping_province_name' => 'Hà Nội',
                'shipping_province_code' => '01',
                'shipping_ward_name' => 'Phương Canh',
                'shipping_ward_code' => '00001',
                'shipping_address_line' => 'Tòa nhà FPT Polytechnic',
                'currency_code' => 'VND',
                'created_at' => now()->subDays(5),
            ]
        );

        $reviewData = [
            ['slug' => 'iphone-16', 'rating' => 5, 'title' => 'Siêu phẩm 2024!', 'content' => 'iPhone 16 mượt mà đến không ngờ, chip A18 xử lý mọi thứ cực nhanh. Camera chụp ảnh đêm đẹp hơn hẳn 15. Rất hài lòng!'],
            ['slug' => 'samsung-galaxy-s25', 'rating' => 5, 'title' => 'Galaxy S25 đỉnh của đỉnh', 'content' => 'Màn hình đẹp, pin bền, AI Galaxy cực kỳ hữu ích. Camera 50MP chi tiết rất cao. Đáng tiền từng đồng!'],
            ['slug' => 'xiaomi-redmi-note-13-pro-plus', 'rating' => 4, 'title' => 'Cực ngon cho tầm trung', 'content' => 'Camera 200MP chụp ảnh cực chi tiết, sạc 120W siêu nhanh. Màn hình AMOLED đẹp. Hiệu năng ổn với tầm giá này.'],
            ['slug' => 'sony-wh-1000xm5', 'rating' => 5, 'title' => 'Tai nghe ANC tốt nhất', 'content' => 'Khử tiếng ồn hoàn toàn trong yên tĩnh, âm thanh chi tiết và ấm áp. 30 giờ pin dùng cả tuần không cần sạc. Cực kỳ hài lòng!'],
            ['slug' => 'jbl-charge-5', 'rating' => 4, 'title' => 'Loa nhỏ âm to', 'content' => 'Âm thanh JBL Pro Sound nghe rất thỏa mái, bass đủ sâu không bị vỡ. Chống nước IP67 tốt, dùng được cả ngoài bãi biển.'],
            ['slug' => 'ipad-pro-m4-11-inch', 'rating' => 5, 'title' => 'iPad Pro M4 siêu mỏng siêu nhanh', 'content' => 'Màn hình OLED đẹp không tưởng, chip M4 mạnh như laptop. Dùng thiết kế đồ họa rất tốt. Chỉ tiếc giá hơi cao.'],
        ];

        foreach ($reviewData as $r) {
            $product = Product::where('slug', $r['slug'])->first();
            if (! $product) {
                continue;
            }

            $variant = ProductVariant::where('product_id', $product->id)->first();
            if (! $variant) {
                continue;
            }

            $orderItem = OrderItem::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->option_signature ?? 'Tiêu chuẩn',
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'cost_price_snapshot' => $variant->cost_price ?? 0,
                    'quantity' => 1,
                    'created_at' => $order->created_at,
                ]
            );

            Review::firstOrCreate(
                [
                    'order_item_id' => $orderItem->id,
                ],
                [
                    'user_id'    => $customer->id,
                    'product_id' => $product->id,
                    'rating'  => $r['rating'],
                    'title'   => $r['title'],
                    'content' => $r['content'],
                    'status'  => 'approved',
                ]
            );
        }
    }

    // ── Helper: seed a single product entry ──────────────────────────────────
    private function seedProduct(array $entry): void
    {
        $pData   = $entry['product'];
        $product = Product::firstOrCreate(['slug' => $pData['slug']], $pData);

        // Primary image
        ProductImage::firstOrCreate(
            ['product_id' => $product->id, 'is_primary' => true],
            ['image_path' => $entry['image'], 'alt_text' => $product->name, 'sort_order' => 0]
        );

        // Variants & Inventory
        foreach ($entry['variants'] as $vData) {
            $stock = $vData['stock'];
            unset($vData['stock']);

            $variant = ProductVariant::firstOrCreate(
                ['product_id' => $product->id, 'sku' => $vData['sku']],
                array_merge($vData, ['is_active' => true])
            );

            $inventory = Inventory::firstOrCreate(
                ['product_variant_id' => $variant->id],
                ['on_hand_quantity' => $stock, 'reserved_quantity' => 0, 'min_stock' => 5]
            );

            InventoryTransaction::firstOrCreate(
                ['idempotency_key' => "sample_init_v{$variant->id}"],
                [
                    'product_variant_id' => $variant->id,
                    'type'               => 'import',
                    'quantity_delta'     => $stock,
                    'reserved_delta'     => 0,
                    'unit_cost'          => $vData['cost_price'] ?? null,
                    'note'               => 'Khởi tạo tồn kho mẫu',
                    'created_at'         => now(),
                ]
            );
        }
    }
}
