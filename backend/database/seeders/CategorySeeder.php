<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Canonical reference categories from MobieX Unified Schema.
     */
    public function run(): void
    {
        $categories = [
            [
                'id' => 1,
                'parent_id' => null,
                'name' => 'Điện thoại',
                'slug' => 'dien-thoai',
                'sort_order' => 1,
                'is_active' => true,
                'is_featured' => true,
                'featured_sort_order' => 1,
            ],
            [
                'id' => 2,
                'parent_id' => null,
                'name' => 'Phụ kiện',
                'slug' => 'phu-kien',
                'sort_order' => 2,
                'is_active' => true,
                'is_featured' => true,
                'featured_sort_order' => 2,
            ],
            [
                'id' => 3,
                'parent_id' => 1,
                'name' => 'iPhone',
                'slug' => 'iphone',
                'sort_order' => 1,
                'is_active' => true,
                'is_featured' => false,
                'featured_sort_order' => 0,
            ],
            [
                'id' => 4,
                'parent_id' => 1,
                'name' => 'Android',
                'slug' => 'android',
                'sort_order' => 2,
                'is_active' => true,
                'is_featured' => false,
                'featured_sort_order' => 0,
            ],
            [
                'id' => 5,
                'parent_id' => 2,
                'name' => 'Sạc',
                'slug' => 'sac',
                'sort_order' => 1,
                'is_active' => true,
                'is_featured' => false,
                'featured_sort_order' => 0,
            ],
            [
                'id' => 6,
                'parent_id' => 2,
                'name' => 'Cáp sạc',
                'slug' => 'cap-sac',
                'sort_order' => 2,
                'is_active' => true,
                'is_featured' => false,
                'featured_sort_order' => 0,
            ],
            [
                'id' => 7,
                'parent_id' => 2,
                'name' => 'Ốp lưng',
                'slug' => 'op-lung',
                'sort_order' => 3,
                'is_active' => true,
                'is_featured' => false,
                'featured_sort_order' => 0,
            ],
        ];

        foreach ($categories as $data) {
            Category::query()->updateOrCreate(
                ['id' => $data['id']],
                $data,
            );
        }
    }
}
