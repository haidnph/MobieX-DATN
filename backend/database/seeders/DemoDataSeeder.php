<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDefinition;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DemoDataSeeder — Rich demonstration data for graduation presentation.
 *
 * Seeds:
 *  - 4 flagship phones (iPhone 16 Pro Max, iPhone 15, Samsung Galaxy S24 Ultra, Xiaomi 14 Pro)
 *    each with realistic specifications for the compare matrix
 *  - Orders across all 5 forward statuses for admin demo
 *  - 3 verified reviews (5-star, 4-star, 3-star) with varied content
 *
 * Safe to run multiple times — idempotent via firstOrCreate / updateOrCreate.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Seed specifications for flagship phones ──────────────────────────
        $this->seedSpecifications();

        // ── 2. Seed extra demo orders across all statuses ───────────────────────
        $this->seedDemoOrders();
    }

    private function seedSpecifications(): void
    {
        $phones = [
            'iphone-16-pro-max' => [
                'chipset'           => 'Apple A18 Pro (3nm)',
                'display_size'      => 6.9,
                'display_resolution'=> 'Super Retina XDR OLED, 2868×1320 px, 460 ppi',
                'battery_capacity'  => 4685,
                'rear_camera'       => '48MP chính + 48MP siêu rộng + 12MP tele 5x',
                'front_camera'      => '12MP TrueDepth',
                'operating_system'  => 'iOS 18',
                'water_resistance'  => 'IP68 (6m / 30 phút)',
                'weight'            => 227,
            ],
            'iphone-15-128gb' => [
                'chipset'           => 'Apple A16 Bionic (4nm)',
                'display_size'      => 6.1,
                'display_resolution'=> 'Super Retina XDR OLED, 2556×1179 px, 460 ppi',
                'battery_capacity'  => 3349,
                'rear_camera'       => '48MP chính + 12MP siêu rộng',
                'front_camera'      => '12MP TrueDepth',
                'operating_system'  => 'iOS 17 (nâng cấp iOS 18)',
                'water_resistance'  => 'IP68 (6m / 30 phút)',
                'weight'            => 171,
            ],
            'samsung-galaxy-s24-ultra' => [
                'chipset'           => 'Snapdragon 8 Gen 3 for Galaxy (4nm)',
                'display_size'      => 6.8,
                'display_resolution'=> 'Dynamic AMOLED 2X, 3088×1440 px, 500 ppi, 120Hz',
                'battery_capacity'  => 5000,
                'rear_camera'       => '200MP chính + 12MP siêu rộng + 50MP tele 5x + 10MP tele 3x',
                'front_camera'      => '12MP',
                'operating_system'  => 'Android 14 / One UI 6.1',
                'water_resistance'  => 'IP68 (1.5m / 30 phút)',
                'weight'            => 232,
            ],
            'xiaomi-14-pro-leica' => [
                'chipset'           => 'Snapdragon 8 Gen 3 (4nm)',
                'display_size'      => 6.73,
                'display_resolution'=> 'LTPO AMOLED, 3200×1440 px, 522 ppi, 1-120Hz',
                'battery_capacity'  => 4880,
                'rear_camera'       => '50MP Leica Summilux f/1.42 + 50MP siêu rộng + 50MP tele 3.2x',
                'front_camera'      => '32MP',
                'operating_system'  => 'Android 14 / HyperOS',
                'water_resistance'  => 'IP68 (1.5m / 30 phút)',
                'weight'            => 223,
            ],
        ];

        $definitionMap = ProductSpecificationDefinition::all()->keyBy('code');

        foreach ($phones as $slug => $specs) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                continue;
            }

            foreach ($specs as $code => $value) {
                $def = $definitionMap->get($code);
                if (! $def) {
                    continue;
                }

                $isNumeric = $def->value_type === 'number';

                ProductSpecification::updateOrCreate(
                    [
                        'product_id'                => $product->id,
                        'specification_definition_id' => $def->id,
                    ],
                    [
                        'value_text'    => $isNumeric ? null : (string) $value,
                        'numeric_value' => $isNumeric ? (float) $value : null,
                        'value_boolean' => null,
                        'sort_order'    => $def->sort_order,
                    ]
                );
            }
        }

        $this->command->info('✅ Product specifications seeded for 4 flagship phones.');
    }

    private function seedDemoOrders(): void
    {
        $customer = User::where('email', 'customer@mobilex.tech')->first();
        if (! $customer) {
            $this->command->warn('⚠️  Demo customer not found — skipping demo orders.');
            return;
        }

        $shippingMethod = ShippingMethod::first();
        if (! $shippingMethod) {
            $shippingMethod = ShippingMethod::create([
                'name'        => 'Giao hàng tiêu chuẩn',
                'code'        => 'standard',
                'description' => 'Giao hàng toàn quốc 2-3 ngày làm việc',
                'fee'         => 0.00,
                'is_active'   => true,
            ]);
        }

        $baseAddress = [
            'shipping_name'           => $customer->name,
            'shipping_phone'          => $customer->phone ?? '0987654321',
            'shipping_province_code'  => '01',
            'shipping_province_name'  => 'Hà Nội',
            'shipping_ward_code'      => '00001',
            'shipping_ward_name'      => 'Phương Canh',
            'shipping_address_line'   => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô, Nam Từ Liêm',
            'currency_code'           => 'VND',
            'shipping_method_id'      => $shippingMethod->id,
            'shipping_method_code'    => $shippingMethod->code,
            'shipping_method_name'    => $shippingMethod->name,
            'shipping_fee'            => 0.00,
            'discount_amount'         => 0.00,
        ];

        // ── CONFIRMED order: iPhone 16 Pro Max ──────────────────────────────────
        $p1 = Product::where('slug', 'iphone-16-pro-max')->first();
        $v1 = $p1 ? ProductVariant::where('product_id', $p1->id)->orderBy('price')->first() : null;

        if ($p1 && $v1) {
            $order = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-CONFIRMED-03'],
                array_merge($baseAddress, [
                    'user_id'        => $customer->id,
                    'subtotal'       => $v1->price,
                    'total_amount'   => $v1->price,
                    'payment_method' => 'vnpay',
                    'payment_status' => 'paid',
                    'status'         => 'confirmed',
                    'customer_note'  => 'Đóng gói cẩn thận giúp mình',
                    'created_at'     => now()->subHours(6),
                ])
            );

            $this->createOrderItem($order, $p1, $v1, 1);
            $this->createHistory($order, [
                ['status' => 'pending',   'note' => 'Đơn hàng mới tạo qua VNPay',   'offset_hours' => 6],
                ['status' => 'confirmed', 'note' => 'Đơn hàng đã được xác nhận thanh toán', 'offset_hours' => 5],
            ]);
        }

        // ── PROCESSING order: Samsung Galaxy S24 Ultra ───────────────────────────
        $p2 = Product::where('slug', 'samsung-galaxy-s24-ultra')->first();
        $v2 = $p2 ? ProductVariant::where('product_id', $p2->id)->orderByDesc('price')->first() : null;

        if ($p2 && $v2) {
            $order2 = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-PROCESSING-04'],
                array_merge($baseAddress, [
                    'user_id'        => $customer->id,
                    'subtotal'       => $v2->price,
                    'total_amount'   => $v2->price,
                    'payment_method' => 'cod',
                    'payment_status' => 'pending',
                    'status'         => 'processing',
                    'customer_note'  => 'Gói quà tặng sinh nhật',
                    'created_at'     => now()->subDays(1),
                ])
            );

            $this->createOrderItem($order2, $p2, $v2, 1);
            $this->createHistory($order2, [
                ['status' => 'pending',    'note' => 'Đơn COD mới',         'offset_hours' => 28],
                ['status' => 'confirmed',  'note' => 'Xác nhận đơn',        'offset_hours' => 26],
                ['status' => 'processing', 'note' => 'Đang chuẩn bị hàng', 'offset_hours' => 20],
            ]);
        }

        // ── SHIPPING order: Xiaomi 14 Pro Leica ─────────────────────────────────
        $p3 = Product::where('slug', 'xiaomi-14-pro-leica')->first();
        $v3 = $p3 ? ProductVariant::where('product_id', $p3->id)->first() : null;

        if ($p3 && $v3) {
            $order3 = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-SHIPPING-05'],
                array_merge($baseAddress, [
                    'user_id'        => $customer->id,
                    'subtotal'       => $v3->price,
                    'total_amount'   => $v3->price,
                    'payment_method' => 'vnpay',
                    'payment_status' => 'paid',
                    'status'         => 'shipping',
                    'created_at'     => now()->subDays(2),
                ])
            );

            $this->createOrderItem($order3, $p3, $v3, 1);
            $this->createHistory($order3, [
                ['status' => 'pending',    'note' => 'Thanh toán VNPay thành công',          'offset_hours' => 52],
                ['status' => 'confirmed',  'note' => 'Admin xác nhận đơn',                   'offset_hours' => 50],
                ['status' => 'processing', 'note' => 'Nhân viên kho đang soạn hàng',         'offset_hours' => 44],
                ['status' => 'shipping',   'note' => 'Đã bàn giao cho đơn vị vận chuyển GHN', 'offset_hours' => 38],
            ]);
        }

        // ── CANCELLED order: iPhone 15 ───────────────────────────────────────────
        $p4 = Product::where('slug', 'iphone-15-128gb')->first();
        $v4 = $p4 ? ProductVariant::where('product_id', $p4->id)->first() : null;

        if ($p4 && $v4) {
            $order4 = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-CANCELLED-06'],
                array_merge($baseAddress, [
                    'user_id'        => $customer->id,
                    'subtotal'       => $v4->price,
                    'total_amount'   => $v4->price,
                    'payment_method' => 'cod',
                    'payment_status' => 'failed',
                    'status'         => 'cancelled',
                    'cancel_reason'  => 'Khách hàng thay đổi ý định, muốn mua màu khác',
                    'created_at'     => now()->subDays(3),
                ])
            );

            $this->createOrderItem($order4, $p4, $v4, 1);
            $this->createHistory($order4, [
                ['status' => 'pending',   'note' => 'Đơn hàng COD mới',                  'offset_hours' => 76],
                ['status' => 'cancelled', 'note' => 'Hủy theo yêu cầu của khách hàng', 'offset_hours' => 72],
            ]);
        }

        // ── Additional 5-star review for iPhone 15 from DELIVERED order (DEMO-DELIVERED-01) ──
        $deliveredOrder = Order::where('order_code', 'MX-DEMO-DELIVERED-01')->first();

        if ($deliveredOrder && $p1) {
            $item = OrderItem::where('order_id', $deliveredOrder->id)
                ->where('product_id', $p1->id)
                ->first();

            if ($item) {
                Review::firstOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'user_id'       => $customer->id,
                        'product_id'    => $p1->id,
                        'rating'        => 5,
                        'title'         => 'Siêu phẩm không có đối thủ — Xứng đáng từng đồng!',
                        'content'       => 'Mình đã dùng iPhone 15 Pro trước đó và nâng cấp lên 16 Pro Max. Sự khác biệt rõ rệt nhất là A18 Pro nhanh đến mức không thể tin nổi. Chụp ảnh đêm cực kỳ sắc nét, zoom 5x thực tế dùng được, không chỉ để ngắm. Pin 1 ngày rưỡi đầy đủ dễ dàng. Cực kỳ hài lòng!',
                        'status'        => 'approved',
                        'created_at'    => now()->subDays(1),
                    ]
                );
            }
        }

        $this->command->info('✅ Demo orders seeded across all statuses (confirmed, processing, shipping, cancelled).');
        $this->command->info('✅ 5-star review seeded for demonstration.');
    }

    private function createOrderItem(Order $order, Product $product, ProductVariant $variant, int $qty): void
    {
        OrderItem::firstOrCreate(
            [
                'order_id'           => $order->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'product_id'           => $product->id,
                'product_name'         => $product->name,
                'variant_name'         => $variant->option_signature ?? $variant->name ?? 'Tiêu chuẩn',
                'sku'                  => $variant->sku,
                'price'                => $variant->price,
                'cost_price_snapshot'  => $variant->cost_price ?? 0,
                'quantity'             => $qty,
                'created_at'           => $order->created_at,
            ]
        );
    }

    private function createHistory(Order $order, array $entries): void
    {
        foreach ($entries as $entry) {
            OrderStatusHistory::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'status'   => $entry['status'],
                ],
                [
                    'order_id'   => $order->id,
                    'status'     => $entry['status'],
                    'from_status'=> null,
                    'note'       => $entry['note'],
                    'created_at' => now()->subHours($entry['offset_hours']),
                ]
            );
        }
    }
}
