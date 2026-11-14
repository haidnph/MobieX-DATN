<?php

namespace Tests\Feature\Coupons;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCouponReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    private ProductVariant $variant;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

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

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Giao hàng tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
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
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP15-128',
            'price' => 20000000,
            'cost_price' => 16000000,
            'is_active' => true,
        ]);

        Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
            'min_stock' => 2,
        ]);
    }

    private function setupCart(int $quantity = 1): Cart
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $this->variant->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }

    public function test_checkout_with_coupon_reserves_slot_and_records_redemption(): void
    {
        $this->setupCart(1);

        $coupon = Coupon::create([
            'code' => 'SALE500K',
            'discount_type' => 'fixed',
            'discount_value' => 500000,
            'usage_limit' => 5,
            'reserved_count' => 0,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'shipping_name' => 'Nguyen Van B',
            'shipping_phone' => '0987654321',
            'shipping_address' => '456 Le Loi, Quan 1, TP HCM',
            'shipping_province_code' => 'VN-SG',
            'shipping_province_name' => 'Ho Chi Minh',
            'shipping_ward_code' => '002',
            'shipping_ward_name' => 'Ben Nghe',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
            'coupon_code' => 'SALE500K',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $payload);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Check coupon reserved count incremented
        $this->assertEquals(1, $coupon->fresh()->reserved_count);

        // Check order calculations
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'SALE500K',
            'subtotal' => 20000000,
            'discount_amount' => 500000,
            'shipping_fee' => 30000,
            'total_amount' => 19530000, // 20M - 500K + 30K
        ]);

        // Check coupon redemption recorded
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'user_id' => $this->user->id,
            'discount_amount' => 500000,
            'status' => 'reserved',
        ]);
    }

    public function test_checkout_fails_when_coupon_usage_limit_reached(): void
    {
        $this->setupCart(1);

        Coupon::create([
            'code' => 'LIMIT1',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'usage_limit' => 1,
            'reserved_count' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'shipping_name' => 'Nguyen Van B',
            'shipping_phone' => '0987654321',
            'shipping_address' => '456 Le Loi',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
            'coupon_code' => 'LIMIT1',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    public function test_customer_cancelling_order_releases_reserved_coupon(): void
    {
        $this->setupCart(1);

        $coupon = Coupon::create([
            'code' => 'CANCELTEST',
            'discount_type' => 'fixed',
            'discount_value' => 200000,
            'usage_limit' => 10,
            'reserved_count' => 0,
            'is_active' => true,
        ]);

        $orderRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_name' => 'Nguyen Van B',
                'shipping_phone' => '0987654321',
                'shipping_address' => '456 Le Loi',
                'shipping_method_id' => $this->shippingMethod->id,
                'payment_method' => 'COD',
                'coupon_code' => 'CANCELTEST',
            ]);

        $orderId = $orderRes->json('data.id');
        $this->assertEquals(1, $coupon->fresh()->reserved_count);

        // Cancel order by customer
        $cancelRes = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/orders/{$orderId}/cancel", [
                'cancel_reason' => 'Khong muon mua nua',
            ]);

        $cancelRes->assertStatus(200);

        // Check coupon reserved count released
        $this->assertEquals(0, $coupon->fresh()->reserved_count);

        // Check redemption status updated to released
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'status' => 'released',
        ]);
    }

    public function test_order_delivery_marks_coupon_as_consumed_and_increments_used_count(): void
    {
        $this->setupCart(1);

        $coupon = Coupon::create([
            'code' => 'DELIVERTEST',
            'discount_type' => 'fixed',
            'discount_value' => 150000,
            'usage_limit' => 10,
            'reserved_count' => 0,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $orderRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_name' => 'Nguyen Van Delivery',
                'shipping_phone' => '0987654322',
                'shipping_address' => '789 Tran Phu',
                'shipping_method_id' => $this->shippingMethod->id,
                'payment_method' => 'COD',
                'coupon_code' => 'DELIVERTEST',
            ]);

        $orderId = $orderRes->json('data.id');
        $this->assertEquals(1, $coupon->fresh()->reserved_count);
        $this->assertEquals(0, $coupon->fresh()->used_count);

        // Transition through order states: pending -> confirmed -> processing -> shipping -> delivered
        $states = ['confirmed', 'processing', 'shipping', 'delivered'];
        foreach ($states as $st) {
            $this->actingAs($this->adminUser, 'sanctum')
                ->patchJson("/api/admin/orders/{$orderId}/status", [
                    'status' => $st,
                    'note' => "Chuyển sang {$st}",
                ])
                ->assertStatus(200);
        }

        // Verify coupon counts updated
        $freshCoupon = $coupon->fresh();
        $this->assertEquals(0, $freshCoupon->reserved_count);
        $this->assertEquals(1, $freshCoupon->used_count);

        // Verify coupon redemption status updated to consumed
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'status' => 'consumed',
        ]);
    }
}
