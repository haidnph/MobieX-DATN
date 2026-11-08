<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Seed promotional coupons for cart validation and order discounts.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'MOBIEX100K',
                'discount_type' => 'fixed',
                'discount_value' => 100000.00,
                'min_order_amount' => 500000.00,
                'max_discount' => null,
                'usage_limit' => 500,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'WELCOME10',
                'discount_type' => 'percent',
                'discount_value' => 10.00,
                'min_order_amount' => 1000000.00,
                'max_discount' => 500000.00,
                'usage_limit' => 1000,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'FLAGSHIP1TR',
                'discount_type' => 'fixed',
                'discount_value' => 1000000.00,
                'min_order_amount' => 20000000.00,
                'max_discount' => null,
                'usage_limit' => 50,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
        }
    }
}
