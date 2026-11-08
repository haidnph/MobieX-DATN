<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private ShippingMethod $shippingMethod;

    private ProductVariant $variant;

    private Inventory $inventory;

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
            'name' => 'Tablets',
            'slug' => 'tablets',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'accessory',
            'name' => 'Galaxy Tab S9',
            'slug' => 'galaxy-tab-s9',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TAB-S9-128',
            'option_signature' => 'storage:128gb',
            'price' => 15000000,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 2, // 2 items currently reserved by an order
            'min_stock' => 2,
        ]);
    }

    private function createOrder(User $user, string $status = 'pending', int $quantity = 2): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'MX-CAN-'.rand(1000, 9999),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 15000000 * $quantity,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => (15000000 * $quantity) + 30000,
            'currency_code' => 'VND',
            'shipping_name' => $user->name,
            'shipping_phone' => '0901234567',
            'shipping_address_line' => '789 Giải Phóng, Hà Nội',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Phương Liệt',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Galaxy Tab S9',
            'variant_name' => 'Storage 128GB',
            'sku' => $this->variant->sku,
            'price' => 15000000,
            'quantity' => $quantity,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => null,
            'status' => $status,
            'note' => 'Đơn hàng được khởi tạo.',
            'created_at' => now(),
        ]);

        return $order;
    }

    public function test_customer_can_cancel_pending_order_and_release_stock(): void
    {
        $order = $this->createOrder($this->userA, 'pending', 2);

        $this->assertEquals(2, $this->inventory->reserved_quantity);
        $this->assertEquals(8, $this->inventory->available_quantity);

        $response = $this->actingAs($this->userA)
            ->patchJson("/api/orders/{$order->id}/cancel", [
                'cancel_reason' => 'Tôi đổi ý không muốn mua nữa',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Hủy đơn hàng thành công.');
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.cancel_reason', 'Tôi đổi ý không muốn mua nữa');

        // Verify stock released: reserved_quantity reduced back to 0, available restored to 10
        $this->inventory->refresh();
        $this->assertEquals(0, $this->inventory->reserved_quantity);
        $this->assertEquals(10, $this->inventory->available_quantity);
        $this->assertEquals(10, $this->inventory->on_hand_quantity);

        // Verify release transaction logged
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'user_id' => $this->userA->id,
            'type' => 'release',
            'reserved_delta' => -2,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'idempotency_key' => "order_{$order->id}_var_{$this->variant->id}_release",
        ]);

        // Verify status history
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'status' => 'cancelled',
        ]);
    }

    public function test_second_cancellation_attempt_returns_422(): void
    {
        $order = $this->createOrder($this->userA, 'pending', 2);

        // First cancel
        $this->actingAs($this->userA)
            ->patchJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        // Second cancel
        $response = $this->actingAs($this->userA)
            ->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_cancelling_non_pending_order_returns_422(): void
    {
        $order = $this->createOrder($this->userA, 'confirmed', 2);

        $response = $this->actingAs($this->userA)
            ->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_idor_user_cannot_cancel_other_users_order(): void
    {
        $orderB = $this->createOrder($this->userB, 'pending', 2);

        $response = $this->actingAs($this->userA)
            ->patchJson("/api/orders/{$orderB->id}/cancel");

        $response->assertStatus(403);
    }
}
