<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProductStressTestSeeder — Seeds 1,000 products with variants for stress and scale testing.
 *
 * Runs via: php artisan db:seed --class=ProductStressTestSeeder
 * Fast bulk operations via transactions.
 */
class ProductStressTestSeeder extends Seeder
{
    public function run(): void
    {
        $brandIds = Brand::pluck('id')->toArray();
        if (empty($brandIds)) {
            $brand = Brand::create(['name' => 'MobieX Scale Brand', 'slug' => 'mobiex-scale-brand', 'status' => true]);
            $brandIds = [$brand->id];
        }

        $catIds = Category::pluck('id')->toArray();
        if (empty($catIds)) {
            $cat = Category::create(['name' => 'Thiết bị quy mô', 'slug' => 'thiet-bi-quy-mo', 'is_active' => true]);
            $catIds = [$cat->id];
        }

        $now = now();
        $batchSize = 100;
        $totalProducts = 1000;

        $this->command?->info("🚀 Starting stress test seeder: {$totalProducts} products...");

        for ($b = 0; $b < ($totalProducts / $batchSize); $b++) {
            DB::transaction(function () use ($b, $batchSize, $brandIds, $catIds, $now) {
                for ($i = 1; $i <= $batchSize; $i++) {
                    $index = ($b * $batchSize) + $i;
                    $slug = "stress-product-scale-{$index}";

                    $product = Product::withTrashed()->where('slug', $slug)->first();
                    if ($product) {
                        if ($product->trashed()) {
                            $product->restore();
                        }
                    } else {
                        $product = Product::create([
                            'brand_id' => $brandIds[($index - 1) % count($brandIds)],
                            'category_id' => $catIds[($index - 1) % count($catIds)],
                            'product_type' => ($index % 3 === 0) ? 'accessory' : 'phone',
                            'name' => "MobieX Stress Scale Product #{$index} 5G Performance",
                            'slug' => $slug,
                            'description' => "Sản phẩm kiểm thử tải cao và hiệu năng truy vấn danh mục #{$index}.",
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $sku = "STRESS-SKU-{$index}";
                    $price = 500000 + (($index * 25000) % 35000000);

                    $variant = ProductVariant::withTrashed()->where('sku', $sku)->first();
                    if ($variant) {
                        if ($variant->trashed()) {
                            $variant->restore();
                        }
                    } else {
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $sku,
                            'option_signature' => "capacity:128gb",
                            'price' => $price,
                            'compare_price' => $price + 500000,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    Inventory::firstOrCreate(
                        ['product_variant_id' => $variant->id],
                        ['on_hand_quantity' => 25, 'reserved_quantity' => 0, 'min_stock' => 5]
                    );

                    ProductImage::firstOrCreate(
                        ['product_id' => $product->id, 'is_primary' => true],
                        [
                            'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=600&auto=format&fit=crop',
                            'alt_text' => $product->name,
                            'sort_order' => 0,
                        ]
                    );
                }
            });
        }

        $this->command?->info("✅ Successfully seeded {$totalProducts} stress test products.");
    }
}
