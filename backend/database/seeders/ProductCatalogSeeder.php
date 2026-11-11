<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Seed flagship devices, accessories, variants, images, and inventory reserves.
     */
    public function run(): void
    {
        $apple = Brand::where('slug', 'apple')->first();
        $samsung = Brand::where('slug', 'samsung')->first();
        $xiaomi = Brand::where('slug', 'xiaomi')->first();
        $anker = Brand::where('slug', 'anker')->first();

        $phoneCat = Category::where('slug', 'dien-thoai')->first();
        $iphoneCat = Category::where('slug', 'iphone')->first() ?? $phoneCat;
        $androidCat = Category::where('slug', 'android')->first() ?? $phoneCat;
        $accCat = Category::where('slug', 'phu-kien')->first();

        $items = [
            // ── 1. iPhone 16 Pro Max ──
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $iphoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'iPhone 16 Pro Max',
                    'slug' => 'iphone-16-pro-max',
                    'description' => 'Thiết kế titan chuẩn hàng không vũ trụ với nút Điều Khiển Camera hoàn toàn mới. Chip A18 Pro mang đến hiệu năng đồ họa cực đỉnh và thời lượng pin đột phá chưa từng có.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'IP16PM-256-TI',
                        'option_signature' => 'color:titan-den|storage:256gb',
                        'price' => 34990000.00,
                        'compare_price' => 36990000.00,
                        'cost_price' => 30000000.00,
                        'stock' => 25,
                    ],
                    [
                        'sku' => 'IP16PM-512-NAT',
                        'option_signature' => 'color:titan-tu-nhien|storage:512gb',
                        'price' => 40990000.00,
                        'compare_price' => 42990000.00,
                        'cost_price' => 35000000.00,
                        'stock' => 15,
                    ],
                    [
                        'sku' => 'IP16PM-1TB-DES',
                        'option_signature' => 'color:titan-sa-mac|storage:1tb',
                        'price' => 46990000.00,
                        'compare_price' => 48990000.00,
                        'cost_price' => 40000000.00,
                        'stock' => 8,
                    ],
                ],
            ],

            // ── 2. Samsung Galaxy S24 Ultra ──
            [
                'product' => [
                    'brand_id' => $samsung?->id,
                    'category_id' => $androidCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Samsung Galaxy S24 Ultra',
                    'slug' => 'samsung-galaxy-s24-ultra',
                    'description' => 'Đỉnh cao công nghệ di động tích hợp Galaxy AI đột phá. Khung viền Titan bền bỉ cùng bút S Pen tích hợp và hệ thống camera 200MP zoom quang học ấn tượng.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'SS-S24U-256-GRAY',
                        'option_signature' => 'color:titan-xam|storage:256gb',
                        'price' => 29990000.00,
                        'compare_price' => 33990000.00,
                        'cost_price' => 25000000.00,
                        'stock' => 30,
                    ],
                    [
                        'sku' => 'SS-S24U-512-BLACK',
                        'option_signature' => 'color:titan-den|storage:512gb',
                        'price' => 34490000.00,
                        'compare_price' => 37990000.00,
                        'cost_price' => 29000000.00,
                        'stock' => 12,
                    ],
                ],
            ],

            // ── 3. Xiaomi 14 Pro ──
            [
                'product' => [
                    'brand_id' => $xiaomi?->id,
                    'category_id' => $androidCat?->id,
                    'product_type' => 'phone',
                    'name' => 'Xiaomi 14 Pro Leica',
                    'slug' => 'xiaomi-14-pro-leica',
                    'description' => 'Ống kính quang học Leica Summilux huyền thoại kết hợp vi xử lý Snapdragon 8 Gen 3 và màn hình AMOLED siêu sáng 3000 nits.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'MI14P-256-BLK',
                        'option_signature' => 'color:den-bong|storage:256gb',
                        'price' => 18990000.00,
                        'compare_price' => 20990000.00,
                        'cost_price' => 15500000.00,
                        'stock' => 20,
                    ],
                ],
            ],

            // ── 4. iPhone 15 ──
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $iphoneCat?->id,
                    'product_type' => 'phone',
                    'name' => 'iPhone 15 128GB',
                    'slug' => 'iphone-15-128gb',
                    'description' => 'Dynamic Island tiện ích, camera chính 48MP và cổng sạc USB-C phổ biến toàn cầu trên thân máy kính pha màu bền bỉ.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'IP15-128-PINK',
                        'option_signature' => 'color:hong-pastel|storage:128gb',
                        'price' => 19490000.00,
                        'compare_price' => 21990000.00,
                        'cost_price' => 16500000.00,
                        'stock' => 35,
                    ],
                    [
                        'sku' => 'IP15-128-BLUE',
                        'option_signature' => 'color:xanh-duong|storage:128gb',
                        'price' => 19490000.00,
                        'compare_price' => 21990000.00,
                        'cost_price' => 16500000.00,
                        'stock' => 25,
                    ],
                ],
            ],

            // ── 5. AirPods Pro 2 USB-C ──
            [
                'product' => [
                    'brand_id' => $apple?->id,
                    'category_id' => $accCat?->id,
                    'product_type' => 'accessory',
                    'name' => 'Tai nghe Apple AirPods Pro 2 (USB-C)',
                    'slug' => 'apple-airpods-pro-2-usbc',
                    'description' => 'Chủ động khử tiếng ồn gấp 2 lần, Chế độ Xuyên Âm thích ứng và Âm Thanh Không Gian cá nhân hóa sống động từng chi tiết.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'APP2-USBC-WHT',
                        'option_signature' => 'color:trang-magsafe',
                        'price' => 5490000.00,
                        'compare_price' => 6190000.00,
                        'cost_price' => 4500000.00,
                        'stock' => 50,
                    ],
                ],
            ],

            // ── 6. Sạc Anker GaNPrime 65W ──
            [
                'product' => [
                    'brand_id' => $anker?->id,
                    'category_id' => $accCat?->id,
                    'product_type' => 'accessory',
                    'name' => 'Củ sạc nhanh Anker 735 GaNPrime 65W 3 cổng',
                    'slug' => 'cu-sac-nhanh-anker-735-ganprime-65w',
                    'description' => 'Công nghệ GaNPrime tiên tiến, kích thước siêu nhỏ gọn với 2 cổng USB-C và 1 cổng USB-A sạc cùng lúc 3 thiết bị tốc độ cao.',
                    'status' => 'active',
                ],
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?q=80&w=600&auto=format&fit=crop',
                'variants' => [
                    [
                        'sku' => 'ANK-735-65W-BLK',
                        'option_signature' => 'color:xam-khong-gian',
                        'price' => 1190000.00,
                        'compare_price' => 1450000.00,
                        'cost_price' => 850000.00,
                        'stock' => 45,
                    ],
                ],
            ],
        ];

        foreach ($items as $entry) {
            $pData = $entry['product'];
            $product = Product::firstOrCreate(['slug' => $pData['slug']], $pData);

            // Primary image
            ProductImage::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'is_primary' => true,
                ],
                [
                    'image_path' => $entry['image'],
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                ]
            );

            // Variants & Inventory
            foreach ($entry['variants'] as $vData) {
                $stock = $vData['stock'];
                unset($vData['stock']);

                $variant = ProductVariant::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $vData['sku'],
                    ],
                    array_merge($vData, [
                        'is_active' => true,
                    ])
                );

                // Inventory record
                $inventory = Inventory::firstOrCreate(
                    ['product_variant_id' => $variant->id],
                    [
                        'on_hand_quantity' => $stock,
                        'reserved_quantity' => 0,
                        'min_stock' => 5,
                    ]
                );

                // Initial inventory transaction log
                InventoryTransaction::firstOrCreate(
                    ['idempotency_key' => "init_variant_{$variant->id}"],
                    [
                        'product_variant_id' => $variant->id,
                        'type' => 'import',
                        'quantity_delta' => $stock,
                        'reserved_delta' => 0,
                        'unit_cost' => $vData['cost_price'] ?? null,
                        'note' => 'Khởi tạo tồn kho ban đầu',
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
