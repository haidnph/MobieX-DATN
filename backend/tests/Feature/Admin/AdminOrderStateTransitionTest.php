<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    private ShippingMethod $shippingMethod;

    private ProductVariant $variant;

    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $managePermission = Permission::firstOrCreate(
            ['slug' => 'orders.manage'],
            ['name' => 'Quản lý đơn hàng', 'description' => 'Quản lý đơn hàng']
        );

        $viewPermission = Permission::firstOrCreate(
            ['slug' => 'orders.view'],
            ['name' => 'Xem đơn hàng', 'description' => 'Xem đơn hàng']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$managePermission->id, $viewPermission->id]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

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

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'iPhone 15 Pro',
            'slug' => 'iphone-15-pro',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP15P-128-BLK',
            'option_signature' => 'color:black',
            'price' => 25000000,
            'cost_price' => 20000000,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 20,
            'reserved_quantity' => 2,
            'min_stock' => 5,
        ]);
    }

    private function createOrderWithItem(string $status = 'pending', int $quantity = 2): Order
    {
        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'MX-ORD-'.uniqid(),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 50000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 50030000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0987654321',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Cau Giay',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->variant->product->name,
            'variant_name' => 'Black',
            'sku' => $this->variant->sku,
            'price' => 25000000,
            'cost_price_snapshot' => 20000000,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    public function test_unauthorized_user_cannot_update_order_status(): void
    {
        $order = $this->createOrderWithItem('pending');

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(403);
    }

    public function test_valid_sequential_transitions_up_to_delivered(): void
    {
        $order = $this->createOrderWithItem('pending', 2);

        // 1. Pending -> Confirmed
        $res1 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
                'note' => 'Admin xac nhan don',
            ]);
        $res1->assertStatus(200);
        $this->assertEquals('confirmed', $order->fresh()->status);

        // 2. Confirmed -> Processing
        $res2 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'processing',
                'note' => 'Dang dong goi',
            ]);
        $res2->assertStatus(200);
        $this->assertEquals('processing', $order->fresh()->status);

        // Stock before dispatch
        $this->assertEquals(20, $this->inventory->fresh()->on_hand_quantity);
        $this->assertEquals(2, $this->inventory->fresh()->reserved_quantity);

        // 3. Processing -> Shipping (Dispatch happens here!)
        $res3 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'shipping',
                'note' => 'Giao cho don vi van chuyen',
            ]);
        $res3->assertStatus(200);
        $this->assertEquals('shipping', $order->fresh()->status);

        // Check stock dispatched
        $freshInv = $this->inventory->fresh();
        $this->assertEquals(18, $freshInv->on_hand_quantity); // 20 - 2
        $this->assertEquals(0, $freshInv->reserved_quantity); // 2 - 2

        // Verify dispatch transaction recorded
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'type' => 'dispatch',
            'quantity_delta' => -2,
            'reserved_delta' => -2,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);

        // 4. Shipping -> Delivered
        $res4 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'delivered',
                'note' => 'Khach da nhan hang',
            ]);
        $res4->assertStatus(200);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);

        // Ensure stock was NOT modified again at delivered
        $this->assertEquals(18, $this->inventory->fresh()->on_hand_quantity);
        $this->assertEquals(0, $this->inventory->fresh()->reserved_quantity);
    }

    public function test_skipping_transition_is_rejected(): void
    {
        $order = $this->createOrderWithItem('pending', 2);

        // Try skipping from pending directly to shipping
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'shipping',
            ]);

        $response->assertStatus(422);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_backward_transition_from_delivered_is_rejected(): void
    {
        $order = $this->createOrderWithItem('delivered', 2);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'pending',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_cancel_order_releases_reserved_stock(): void
    {
        $order = $this->createOrderWithItem('pending', 2);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'cancelled',
                'cancel_reason' => 'Khach doi y',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);

        // Reserved quantity should be released back
        $this->assertEquals(0, $this->inventory->fresh()->reserved_quantity);
        $this->assertEquals(20, $this->inventory->fresh()->on_hand_quantity);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'type' => 'release',
            'quantity_delta' => 0,
            'reserved_delta' => -2,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }
}
