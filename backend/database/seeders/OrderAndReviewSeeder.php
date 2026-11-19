
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderAndReviewSeeder extends Seeder
{
    /**
     * Seed demo orders, status histories and verified reviews.
     *
     * Safe for repeated execution.
     * Never overwrite existing business data.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $customer = User::where(
                'email',
                'customer@mobilex.tech'
            )->first();

            if (! $customer) {
                $this->command?->warn(
                    'OrderAndReviewSeeder: Customer not found. Skipped.'
                );

                return;
            }

            // Each developer should configure a different namespace.
            $namespace = Str::slug(
                (string) env(
                    'ORDER_REVIEW_SEEDER_NAMESPACE',
                    gethostname() ?: 'local'
                )
            );

            $namespace = Str::limit($namespace, 20, '');

            // Find or create a dedicated shipping method.
            $shippingMethod = ShippingMethod::firstOrCreate(
                ['code' => 'standard'],
                [
                    'name' => 'Giao hàng tiêu chuẩn',
                    'description' => 'Giao hàng toàn quốc 2-3 ngày',
                    'fee' => 0,
                    'is_active' => true,
                ]
            );

            $this->seedDeliveredOrder(
                $customer,
                $shippingMethod,
                $namespace
            );

            $this->seedPendingOrder(
                $customer,
                $shippingMethod,
                $namespace
            );
        }, 3);
    }

    /**
     * Seed a delivered order and verified reviews.
     */
    private function seedDeliveredOrder(
        User $customer,
        ShippingMethod $shippingMethod,
        string $namespace
    ): void {
        $iphone = Product::where(
            'slug',
            'iphone-16-pro-max'
        )->first();

        $airpods = Product::where(
            'slug',
            'apple-airpods-pro-2-usbc'
        )->first();

        if (! $iphone || ! $airpods) {
            $this->command?->warn(
                'Delivered order skipped: products not found.'
            );

            return;
        }

        $iphoneVariant = ProductVariant::where(
            'product_id',
            $iphone->id
        )->first();

        $airpodsVariant = ProductVariant::where(
            'product_id',
            $airpods->id
        )->first();

        if (! $iphoneVariant || ! $airpodsVariant) {
            $this->command?->warn(
                'Delivered order skipped: product variants not found.'
            );

            return;
        }

        $subtotal = $iphoneVariant->price
            + $airpodsVariant->price;

        $createdAt = now()->subDays(5);
        $deliveredAt = now()->subDays(2);

        $order = Order::firstOrCreate(
            [
                'order_code' => "MX-DEMO-{$namespace}-DELIVERED-01",
            ],
            [
                'user_id' => $customer->id,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => $subtotal,
                'discount_amount' => 100000,
                'shipping_fee' => 0,
                'total_amount' => $subtotal - 100000,
                'currency_code' => 'VND',
                'shipping_name' => $customer->name,
                'shipping_phone' => $customer->phone ?? '0987654321',
                'shipping_province_code' => '01',
                'shipping_province_name' => 'Hà Nội',
                'shipping_ward_code' => '001',
                'shipping_ward_name' => 'Phương Canh',
                'shipping_address_line' =>
                    'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                'payment_method' => 'vnpay',
                'payment_status' => 'paid',
                'status' => 'delivered',
                'customer_note' => 'Giao giờ hành chính giúp mình',
                'delivered_at' => $deliveredAt,
                'created_at' => $createdAt,
                'updated_at' => $deliveredAt,
            ]
        );

        // Do not modify an existing order belonging to another user.
        if ((int) $order->user_id !== (int) $customer->id) {
            $this->command?->warn(
                'Delivered order code collision. Skipped.'
            );

            return;
        }

        // Order status timeline.
        $statuses = [
            [
                'status' => 'pending',
                'from_status' => null,
                'note' => 'Đơn hàng được tạo thành công',
                'created_at' => now()->subDays(5),
            ],
            [
                'status' => 'confirmed',
                'from_status' => 'pending',
                'note' => 'Hệ thống đã xác nhận thanh toán qua VNPay',
                'created_at' => now()->subDays(4),
            ],
            [
                'status' => 'processing',
                'from_status' => 'confirmed',
                'note' => 'Đang đóng gói tại kho tổng MobieX',
                'created_at' => now()->subDays(3),
            ],
            [
                'status' => 'shipping',
                'from_status' => 'processing',
                'note' => 'Đã xuất kho bàn giao cho bưu tá',
                'created_at' => now()->subDays(3)->addHours(4),
            ],
            [
                'status' => 'delivered',
                'from_status' => 'shipping',
                'note' => 'Giao hàng thành công cho khách hàng',
                'created_at' => $deliveredAt,
            ],
        ];

        foreach ($statuses as $status) {
            OrderStatusHistory::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'status' => $status['status'],
                ],
                array_merge($status, [
                    'order_id' => $order->id,
                ])
            );
        }

        // Order items.
        $item1 = $this->createOrderItem(
            $order,
            $iphone,
            $iphoneVariant,
            $createdAt
        );

        $item2 = $this->createOrderItem(
            $order,
            $airpods,
            $airpodsVariant,
            $createdAt
        );

        // Verified customer reviews.
        if ($item1) {
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
                    'created_at' => $deliveredAt,
                ]
            );
        }

        if ($item2) {
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
                    'created_at' => $deliveredAt,
                ]
            );
        }
    }

    /**
     * Seed a pending order for cancellation/admin workflow testing.
     */
    private function seedPendingOrder(
        User $customer,
        ShippingMethod $shippingMethod,
        string $namespace
    ): void {
        $samsung = Product::where(
            'slug',
            'samsung-galaxy-s24-ultra'
        )->first();

        if (! $samsung) {
            $this->command?->warn(
                'Pending order skipped: Samsung product not found.'
            );

            return;
        }

        $variant = ProductVariant::where(
            'product_id',
            $samsung->id
        )->first();

        if (! $variant) {
            $this->command?->warn(
                'Pending order skipped: Samsung variant not found.'
            );

            return;
        }

        $createdAt = now()->subHours(3);

        $order = Order::firstOrCreate(
            [
                'order_code' => "MX-DEMO-{$namespace}-PENDING-02",
            ],
            [
                'user_id' => $customer->id,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => $variant->price,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total_amount' => $variant->price,
                'currency_code' => 'VND',
                'shipping_name' => $customer->name,
                'shipping_phone' => $customer->phone ?? '0987654321',
                'shipping_province_code' => '01',
                'shipping_province_name' => 'Hà Nội',
                'shipping_ward_code' => '001',
                'shipping_ward_name' => 'Phương Canh',
                'shipping_address_line' =>
                    'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'pending',
                'customer_note' => 'Gọi điện trước khi giao',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );

        if ((int) $order->user_id !== (int) $customer->id) {
            $this->command?->warn(
                'Pending order code collision. Skipped.'
            );

            return;
        }

        $this->createOrderItem(
            $order,
            $samsung,
            $variant,
            $createdAt
        );

        OrderStatusHistory::firstOrCreate(
            [
                'order_id' => $order->id,
                'status' => 'pending',
            ],
            [
                'order_id' => $order->id,
                'status' => 'pending',
                'from_status' => null,
                'note' => 'Đơn hàng mới tạo, đang chờ xác nhận',
                'created_at' => $createdAt,
            ]
        );
    }

    /**
     * Create an order item without overwriting existing records.
     */
    private function createOrderItem(
        Order $order,
        Product $product,
        ProductVariant $variant,
        $createdAt
    ): ?OrderItem {
        return OrderItem::firstOrCreate(
            [
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_name' => $variant->option_signature,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'cost_price_snapshot' => $variant->cost_price,
                'quantity' => 1,
                'created_at' => $createdAt,
            ]
        );
    }
}
