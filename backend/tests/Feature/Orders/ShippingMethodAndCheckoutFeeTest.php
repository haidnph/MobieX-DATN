<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingMethodAndCheckoutFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductVariant $variant;

    private ShippingMethod $standardShipping;

    private ShippingMethod $expressShipping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

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
            'name' => 'iPhone 15 Pro Max',
            'slug' => 'iphone-15-pro-max',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP15PM-256-TI',
            'price' => 10000.00,
            'cost_price' => 8000.00,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->standardShipping = ShippingMethod::create([
            'name' => 'Giao hàng tiêu chuẩn',
            'code' => 'standard',
            'fee' => 30000.00,
            'estimated_days' => '3-5 ngày',
            'is_active' => true,
        ]);

        $this->expressShipping = ShippingMethod::create([
            'name' => 'Giao hàng nhanh',
            'code' => 'express',
            'fee' => 50000.00,
            'estimated_days' => '1-2 ngày',
            'is_active' => true,
        ]);
    }

    public function test_can_list_active_shipping_methods(): void
    {
        $response = $this->getJson('/api/shipping-methods');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_checkout_applies_selected_shipping_fee_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);

        $payload = [
            'shipping_name' => 'Nguyễn Văn A',
            'shipping_phone' => '0912345678',
            'shipping_address' => 'Số 123 đường Cầu Giấy, Hà Nội',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
            'shipping_method_id' => $this->expressShipping->id,
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(10000.00, (float) $order->subtotal);
        $this->assertEquals(50000.00, (float) $order->shipping_fee);
        $this->assertEquals(60000.00, (float) $order->total_amount);
        $this->assertEquals($this->expressShipping->id, $order->shipping_method_id);
    }

    public function test_checkout_defaults_to_standard_shipping_when_unspecified(): void
    {
        Sanctum::actingAs($this->user);

        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);

        $payload = [
            'shipping_name' => 'Nguyễn Văn A',
            'shipping_phone' => '0912345678',
            'shipping_address' => 'Số 123 đường Cầu Giấy, Hà Nội',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(10000.00, (float) $order->subtotal);
        $this->assertEquals(30000.00, (float) $order->shipping_fee);
        $this->assertEquals(40000.00, (float) $order->total_amount);
        $this->assertEquals($this->standardShipping->id, $order->shipping_method_id);
    }
}
