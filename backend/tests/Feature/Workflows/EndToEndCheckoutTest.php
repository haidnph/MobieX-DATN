<?php

namespace Tests\Feature\Workflows;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private Product $product;
    private ProductVariant $variant;
    private Inventory $inventory;
    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $manageOrdersPerm = Permission::firstOrCreate(['slug' => 'orders.manage'], ['name' => 'Quản lý đơn']);
        $viewOrdersPerm = Permission::firstOrCreate(['slug' => 'orders.view'], ['name' => 'Xem đơn']);
        $adminRole->permissions()->syncWithoutDetaching([$manageOrdersPerm->id, $viewOrdersPerm->id]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($adminRole->id);

        $this->customer = User::factory()->create(['is_active' => true]);

        // 2. Shipping method
        $this->shippingMethod = ShippingMethod::firstOrCreate(
            ['code' => 'standard'],
            ['name' => 'Giao hàng tiêu chuẩn', 'cost' => 30000, 'is_active' => true]
        );

        // 3. Catalog setup
        $category = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'IP16PM-256-DESERT',
            'option_signature' => 'color:desert-titan,storage:256gb',
            'price' => 34990000,
            'is_active' => true,
        ]);

        // 4. Inventory setup: 10 in stock, 0 reserved
        $this->inventory = Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
            'min_stock' => 2,
        ]);
    }

    /**
     * Test Case 1: Complete end-to-end lifecycle
     * Cart -> Checkout -> Reserve Stock -> Shipping (Dispatch) -> Delivered -> Customer Review
     */
    public function test_full_purchasing_lifecycle_from_cart_to_delivery_and_review(): void
    {
        // ── Step 1: Customer adds item to cart ──
        Sanctum::actingAs($this->customer);

        $addCartRes = $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);
        $addCartRes->assertStatus(200);

        // ── Step 2: Customer checks out with COD ──
        $checkoutRes = $this->postJson('/api/orders', [
            'recipient_name' => 'Trần Văn Demo',
            'phone' => '0988776655',
            'shipping_address' => 'Số 1 Phạm Hùng, Mỹ Đình, Nam Từ Liêm',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
            'note' => 'Giao trong giờ hành chính',
        ]);

        $checkoutRes->assertStatus(201);
        $orderId = $checkoutRes->json('data.id') ?? $checkoutRes->json('id');
        $this->assertNotNull($orderId);

        // Verify inventory reservation: 2 reserved, 10 on hand, 8 available
        $this->inventory->refresh();
        $this->assertEquals(10, $this->inventory->on_hand_quantity);
        $this->assertEquals(2, $this->inventory->reserved_quantity);

        // ── Step 3: Admin confirms and advances order to shipping ──
        Sanctum::actingAs($this->admin);

        // pending -> confirmed
        $this->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])->assertStatus(200);

        // confirmed -> processing
        $this->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'processing',
        ])->assertStatus(200);

        // processing -> shipping (triggers actual dispatch from warehouse)
        $this->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'shipping',
        ])->assertStatus(200);

        // Verify stock dispatched: on_hand reduced to 8, reserved reduced to 0
        $this->inventory->refresh();
        $this->assertEquals(8, $this->inventory->on_hand_quantity);
        $this->assertEquals(0, $this->inventory->reserved_quantity);

        // ── Step 4: Admin marks delivered ──
        $this->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'delivered',
        ])->assertStatus(200);

        // ── Step 5: Customer leaves a 5-star review for the delivered product ──
        Sanctum::actingAs($this->customer);

        $orderItem = \App\Models\OrderItem::where('order_id', $orderId)->first();
        $this->assertNotNull($orderItem);

        $reviewRes = $this->postJson("/api/products/{$this->product->id}/reviews", [
            'order_item_id' => $orderItem->id,
            'rating' => 5,
            'title' => 'Rất hài lòng',
            'content' => 'Sản phẩm chính hãng nguyên seal, đóng gói rất cẩn thận!',
        ]);

        $reviewRes->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'user_id' => $this->customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 5,
        ]);
    }

    /**
     * Test Case 2: Order cancellation releases reserved inventory automatically
     */
    public function test_order_cancellation_releases_reserved_inventory(): void
    {
        Sanctum::actingAs($this->customer);

        // Add 3 items to cart first
        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 3,
        ])->assertStatus(200);

        // Create order
        $checkoutRes = $this->postJson('/api/orders', [
            'recipient_name' => 'Lê Thị Hủy Đơn',
            'phone' => '0912345678',
            'shipping_address' => 'Tòa Keangnam, Mễ Trì, Hà Nội',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
        ]);

        $checkoutRes->assertStatus(201);
        $orderId = $checkoutRes->json('data.id') ?? $checkoutRes->json('id');

        // Check reserved = 3
        $this->inventory->refresh();
        $this->assertEquals(3, $this->inventory->reserved_quantity);

        // Customer cancels the order
        $cancelRes = $this->postJson("/api/orders/{$orderId}/cancel", [
            'reason' => 'Đổi ý không mua nữa',
        ]);
        $cancelRes->assertStatus(200);

        // Verify reserved restored to 0, on hand still 10
        $this->inventory->refresh();
        $this->assertEquals(10, $this->inventory->on_hand_quantity);
        $this->assertEquals(0, $this->inventory->reserved_quantity);
    }

    /**
     * Test Case 3: Over-purchasing is rejected with 422 Unprocessable Entity
     */
    public function test_cannot_purchase_more_than_available_inventory(): void
    {
        Sanctum::actingAs($this->customer);

        // Inventory only has 10 on hand
        // Attempt to add 15 items to cart
        $response = $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 15,
        ]);

        // Must reject because available stock is only 10
        $this->assertTrue(in_array($response->status(), [400, 422]));

        // Ensure zero items were reserved
        $this->inventory->refresh();
        $this->assertEquals(0, $this->inventory->reserved_quantity);
    }
}
