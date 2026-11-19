<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderAndReviewSeeder extends Seeder
{
    /**
     * Seed realistic historical orders, status timelines, and verified customer reviews.
     */
    public function run(): void
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

        // ── 1. Completed & Delivered Order with Verified Reviews ──
        $iphone = Product::where('slug', 'iphone-16-pro-max')->first();
        $iphoneVariant = $iphone ? ProductVariant::where('product_id', $iphone->id)->first() : null;

        $airpods = Product::where('slug', 'apple-airpods-pro-2-usbc')->first();
        $airpodsVariant = $airpods ? ProductVariant::where('product_id', $airpods->id)->first() : null;

        if ($iphone && $iphoneVariant && $airpods && $airpodsVariant) {
            $subtotal = $iphoneVariant->price + $airpodsVariant->price;
            $orderCode = 'MX-'.strtoupper(Str::random(8));

            $order = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-DELIVERED-01'],
                [
                    'user_id' => $customer->id,
                    'shipping_method_id' => $shippingMethod->id,
                    'shipping_method_code' => $shippingMethod->code,
                    'shipping_method_name' => $shippingMethod->name,
                    'subtotal' => $subtotal,
                    'discount_amount' => 100000.00,
                    'shipping_fee' => 0.00,
                    'total_amount' => $subtotal - 100000.00,
                    'currency_code' => 'VND',
                    'shipping_name' => $customer->name,
                    'shipping_phone' => $customer->phone ?? '0987654321',
                    'shipping_province_code' => '01',
                    'shipping_province_name' => 'Hà Nội',
                    'shipping_ward_code' => '001',
                    'shipping_ward_name' => 'Phương Canh',
                    'shipping_address_line' => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                    'payment_method' => 'vnpay',
                    'payment_status' => 'paid',
                    'status' => 'delivered',
                    'customer_note' => 'Giao giờ hành chính giúp mình',
                    'delivered_at' => now()->subDays(2),
                    'created_at' => now()->subDays(5),
                    'updated_at' => now()->subDays(2),
                ]
            );

            // Status Timeline
            $statuses = [
                ['status' => 'pending', 'from_status' => null, 'note' => 'Đơn hàng được tạo thành công', 'created_at' => now()->subDays(5)],
                ['status' => 'confirmed', 'from_status' => 'pending', 'note' => 'Hệ thống đã xác nhận thanh toán qua VNPay', 'created_at' => now()->subDays(4)],
                ['status' => 'processing', 'from_status' => 'confirmed', 'note' => 'Đang đóng gói tại kho tổng MobieX', 'created_at' => now()->subDays(3)],
                ['status' => 'shipping', 'from_status' => 'processing', 'note' => 'Đã xuất kho bàn giao cho bưu tá', 'created_at' => now()->subDays(3)->addHours(4)],
                ['status' => 'delivered', 'from_status' => 'shipping', 'note' => 'Giao hàng thành công cho khách hàng', 'created_at' => now()->subDays(2)],
            ];

            foreach ($statuses as $st) {
                OrderStatusHistory::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'status' => $st['status'],
                    ],
                    array_merge($st, ['order_id' => $order->id])
                );
            }

            // Order Items
            $item1 = OrderItem::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $iphoneVariant->id,
                ],
                [
                    'product_id' => $iphone->id,
                    'product_name' => $iphone->name,
                    'variant_name' => $iphoneVariant->option_signature,
                    'sku' => $iphoneVariant->sku,
                    'price' => $iphoneVariant->price,
                    'cost_price_snapshot' => $iphoneVariant->cost_price,
                    'quantity' => 1,
                    'created_at' => now()->subDays(5),
                ]
            );

            $item2 = OrderItem::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $airpodsVariant->id,
                ],
                [
                    'product_id' => $airpods->id,
                    'product_name' => $airpods->name,
                    'variant_name' => $airpodsVariant->option_signature,
                    'sku' => $airpodsVariant->sku,
                    'price' => $airpodsVariant->price,
                    'cost_price_snapshot' => $airpodsVariant->cost_price,
                    'quantity' => 1,
                    'created_at' => now()->subDays(5),
                ]
            );

            // Verified Reviews
            Review::firstOrCreate(
                ['order_item_id' => $item1->id],
                [
                    'product_id' => $iphone->id,
                    'user_id' => $customer->id,
                    'rating' => 5,
                    'title' => 'Chất lượng iPhone 16 Pro Max trên cả tuyệt vời!',
                    'content' => 'Máy đóng gói nguyên seal chính hãng, viền titan nhẹ và cầm rất đầm tay. Màu titan sa mạc cực kỳ sang trọng, camera chụp đêm siêu nét. Giao hàng rất nhanh.',
                    'status' => 'approved',
                    'moderated_at' => now()->subDay(),
                    'created_at' => now()->subDays(2),
                ]
            );

            Review::firstOrCreate(
                ['order_item_id' => $item2->id],
                [
                    'product_id' => $airpods->id,
                    'user_id' => $customer->id,
                    'rating' => 5,
                    'title' => 'Chống ồn đỉnh cao, kết nối mượt mà',
                    'content' => 'AirPods Pro 2 bản cổng USB-C rất tiện dụng khi dùng chung sạc với iPhone. Chế độ chống ồn ANC cách âm tuyệt đối khi đi ngoài đường.',
                    'status' => 'approved',
                    'moderated_at' => now()->subDay(),
                    'created_at' => now()->subDays(2),
                ]
            );
        }

        // ── 2. Pending Order (for testing cancellation / admin workflow) ──
        $samsung = Product::where('slug', 'samsung-galaxy-s24-ultra')->first();
        $samsungVariant = $samsung ? ProductVariant::where('product_id', $samsung->id)->first() : null;

        if ($samsung && $samsungVariant) {
            $pendingOrder = Order::firstOrCreate(
                ['order_code' => 'MX-DEMO-PENDING-02'],
                [
                    'user_id' => $customer->id,
                    'shipping_method_id' => $shippingMethod->id,
                    'shipping_method_code' => $shippingMethod->code,
                    'shipping_method_name' => $shippingMethod->name,
                    'subtotal' => $samsungVariant->price,
                    'discount_amount' => 0.00,
                    'shipping_fee' => 0.00,
                    'total_amount' => $samsungVariant->price,
                    'currency_code' => 'VND',
                    'shipping_name' => $customer->name,
                    'shipping_phone' => $customer->phone ?? '0987654321',
                    'shipping_province_code' => '01',
                    'shipping_province_name' => 'Hà Nội',
                    'shipping_ward_code' => '001',
                    'shipping_ward_name' => 'Phương Canh',
                    'shipping_address_line' => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                    'payment_method' => 'cod',
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'customer_note' => 'Gọi điện trước khi giao',
                    'created_at' => now()->subHours(3),
                ]
            );

            OrderItem::firstOrCreate(
                [
                    'order_id' => $pendingOrder->id,
                    'product_variant_id' => $samsungVariant->id,
                ],
                [
                    'product_id' => $samsung->id,
                    'product_name' => $samsung->name,
                    'variant_name' => $samsungVariant->option_signature,
                    'sku' => $samsungVariant->sku,
                    'price' => $samsungVariant->price,
                    'cost_price_snapshot' => $samsungVariant->cost_price,
                    'quantity' => 1,
                    'created_at' => now()->subHours(3),
                ]
            );

            OrderStatusHistory::firstOrCreate(
                [
                    'order_id' => $pendingOrder->id,
                    'status' => 'pending',
                ],
                [
                    'order_id' => $pendingOrder->id,
                    'status' => 'pending',
                    'from_status' => null,
                    'note' => 'Đơn hàng mới tạo, đang chờ xác nhận',
                    'created_at' => now()->subHours(3),
                ]
            );
        }
    }
}
