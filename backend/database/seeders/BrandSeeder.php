<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Seed canonical flagship technology brands.
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Apple',
                'slug' => 'apple',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg',
                'description' => 'Thương hiệu công nghệ hàng đầu thế giới với hệ sinh thái iPhone, iPad, Mac và phụ kiện cao cấp.',
                'status' => true,
            ],
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/2/24/Samsung_Logo.svg',
                'description' => 'Tập đoàn công nghệ tiên phong toàn cầu với dòng sản phẩm Galaxy Flagship và Galaxy AI.',
                'status' => true,
            ],
            [
                'name' => 'Xiaomi',
                'slug' => 'xiaomi',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/a/ae/Xiaomi_logo_%282021-%29.svg',
                'description' => 'Hãng công nghệ sáng tạo với triết lý mang công nghệ đỉnh cao đến với mọi người.',
                'status' => true,
            ],
            [
                'name' => 'OPPO',
                'slug' => 'oppo',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/b/b8/OPPO_Logo.svg',
                'description' => 'Chuyên gia nhiếp ảnh chân dung và thiết kế smartphone thời thượng với công nghệ sạc nhanh SuperVOOC.',
                'status' => true,
            ],
            [
                'name' => 'Sony',
                'slug' => 'sony',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/c/ca/Sony_logo.svg',
                'description' => 'Đỉnh cao âm thanh và công nghệ cảm biến hình ảnh Nhật Bản.',
                'status' => true,
            ],
            [
                'name' => 'Anker',
                'slug' => 'anker',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/d/dd/Anker_Innovations_logo.svg',
                'description' => 'Thương hiệu phụ kiện sạc số 1 thế giới với công nghệ GaNPrime tiên tiến.',
                'status' => true,
            ],
            [
                'name' => 'Baseus',
                'slug' => 'baseus',
                'logo_path' => 'https://upload.wikimedia.org/wikipedia/commons/e/ec/Baseus_logo.png',
                'description' => 'Hãng phụ kiện công nghệ sáng tạo, tiện dụng và hiện đại.',
                'status' => true,
            ],
        ];

        foreach ($brands as $b) {
            Brand::firstOrCreate(['slug' => $b['slug']], $b);
        }
    }
}
