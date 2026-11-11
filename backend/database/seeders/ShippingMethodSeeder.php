<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Giao hàng tiêu chuẩn',
                'code' => 'standard',
                'description' => 'DEMO: phí và thời gian giao hàng mẫu, cần cấu hình lại trước khi vận hành thật.',
                'fee' => 30000.00,
                'estimated_days' => '3-5 ngày',
                'is_active' => true,
            ],
            [
                'name' => 'Giao hàng nhanh',
                'code' => 'express',
                'description' => 'DEMO: phí và thời gian giao hàng mẫu, cần cấu hình lại trước khi vận hành thật.',
                'fee' => 50000.00,
                'estimated_days' => '1-2 ngày',
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
