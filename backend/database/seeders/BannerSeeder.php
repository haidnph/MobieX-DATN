<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Seed marketing hero banners for the storefront.
     */
    public function run(): void
    {
        $banners = [
            [
                'title' => 'iPhone 16 Pro Max — Titanium Đỉnh Cao',
                'image_path' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=1400&auto=format&fit=crop',
                'target_url' => '/products',
                'position' => 'home',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Samsung Galaxy S24 Ultra — Quyền Năng Galaxy AI',
                'image_path' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=1400&auto=format&fit=crop',
                'target_url' => '/products',
                'position' => 'home',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Hệ Sinh Thái Phụ Kiện Chính Hãng Anker & Baseus',
                'image_path' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?q=80&w=1400&auto=format&fit=crop',
                'target_url' => '/products?category=accessories',
                'position' => 'home',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::firstOrCreate(['title' => $banner['title']], $banner);
        }
    }
}
