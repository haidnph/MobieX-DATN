
<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Seed promotional coupons safely.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'MOBIEX100K',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'min_order_amount' => 500000,
                'max_discount' => null,
                'usage_limit' => 500,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'WELCOME10',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'min_order_amount' => 1000000,
                'max_discount' => 500000,
                'usage_limit' => 1000,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'FLAGSHIP1TR',
                'discount_type' => 'fixed',
                'discount_value' => 1000000,
                'min_order_amount' => 20000000,
                'max_discount' => null,
                'usage_limit' => 50,
                'reserved_count' => 0,
                'used_count' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::query()->firstOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
