<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestOrderLookupTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $shippingMethod = ShippingMethod::create([
            'name' => 'Giao hàng tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP16PM-256',
            'name' => 'Titan Sa Mạc 256GB',
            'price' => 34990000,
            'stock_quantity' => 20,
            'is_active' => true,
            'status' => 'active',
        ]);

        $customer = User::factory()->create([
            'name' => 'Nguyễn Văn A',
            'phone' => '0987654321',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_code' => 'ORD-TRACK-123456',
            'user_id' => $customer->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_code' => $shippingMethod->code,
            'shipping_method_name' => $shippingMethod->name,
            'subtotal' => 34990000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 35020000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyễn Văn A',
            'shipping_phone' => '0987654321',
            'shipping_province_code' => '01',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng Hậu',
            'shipping_address_line' => 'Số 1 Phạm Văn Đồng, Cầu Giấy',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'processing',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $variant->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'quantity' => 1,
            'total' => $variant->price,
        ]);

        OrderStatusHistory::create([
            'order_id' => $this->order->id,
            'status' => 'processing',
            'note' => 'Đang đóng gói kiện hàng',
        ]);
    }

    public function test_guest_can_lookup_order_by_code_and_phone(): void
    {
        $response = $this->postJson('/api/orders/lookup', [
            'order_code' => 'ORD-TRACK-123456',
            'phone' => '0987654321',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'order_code' => 'ORD-TRACK-123456',
                    'shipping_name' => 'Nguyễn Văn A',
                    'status' => 'processing',
                ],
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'order_code',
                    'status',
                    'items',
                    'status_histories',
                ],
            ]);
    }

    public function test_guest_cannot_lookup_with_wrong_phone(): void
    {
        $response = $this->postJson('/api/orders/lookup', [
            'order_code' => 'ORD-TRACK-123456',
            'phone' => '0911111111',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_lookup_validates_required_fields(): void
    {
        $response = $this->postJson('/api/orders/lookup', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_code', 'phone']);
    }
}
