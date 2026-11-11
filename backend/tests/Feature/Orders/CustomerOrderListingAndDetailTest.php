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

class CustomerOrderListingAndDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private ShippingMethod $shippingMethod;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['is_active' => true]);
        $this->userB = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Dell',
            'slug' => 'dell',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'accessory',
            'name' => 'Dell XPS 15',
            'slug' => 'dell-xps-15',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'XPS15-I7-16-512',
            'option_signature' => 'ram:16gb',
            'price' => 35000000,
            'is_active' => true,
        ]);
    }

    private function createOrderForUser(User $user, string $code, string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => $code,
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 35000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 35030000,
            'currency_code' => 'VND',
            'shipping_name' => $user->name,
            'shipping_phone' => '0901234567',
            'shipping_address_line' => '456 Phố Huế, Hà Nội',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Phố Huế',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Dell XPS 15',
            'variant_name' => 'RAM 16GB',
            'sku' => $this->variant->sku,
            'price' => 35000000,
            'quantity' => 1,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => null,
            'status' => $status,
            'note' => 'Khởi tạo đơn hàng.',
            'created_at' => now(),
        ]);

        return $order;
    }

    public function test_user_can_list_only_their_own_orders_with_status_filter(): void
    {
        $orderA1 = $this->createOrderForUser($this->userA, 'MX-ORD-A1', 'pending');
        $orderA2 = $this->createOrderForUser($this->userA, 'MX-ORD-A2', 'confirmed');
        $orderB = $this->createOrderForUser($this->userB, 'MX-ORD-B1', 'pending');

        $response = $this->actingAs($this->userA)->getJson('/api/orders');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $orderCodes = array_column($data, 'order_code');
        $this->assertContains('MX-ORD-A1', $orderCodes);
        $this->assertContains('MX-ORD-A2', $orderCodes);
        $this->assertNotContains('MX-ORD-B1', $orderCodes);

        // Filter by status=confirmed
        $filteredResponse = $this->actingAs($this->userA)->getJson('/api/orders?status=confirmed');
        $filteredResponse->assertOk();
        $filteredData = $filteredResponse->json('data');
        $this->assertCount(1, $filteredData);
        $this->assertEquals('MX-ORD-A2', $filteredData[0]['order_code']);
    }

    public function test_user_can_view_own_order_detail(): void
    {
        $order = $this->createOrderForUser($this->userA, 'MX-ORD-A3', 'pending');

        $response = $this->actingAs($this->userA)->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.order_code', 'MX-ORD-A3');
        $response->assertJsonPath('data.items.0.sku', $this->variant->sku);
        $this->assertNotEmpty($response->json('data.status_histories'));
    }

    public function test_idor_check_prevents_user_from_accessing_another_users_order(): void
    {
        $orderB = $this->createOrderForUser($this->userB, 'MX-ORD-B2', 'pending');

        $response = $this->actingAs($this->userA)->getJson("/api/orders/{$orderB->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Bạn không có quyền truy cập đơn hàng này.',
        ]);
    }
}
