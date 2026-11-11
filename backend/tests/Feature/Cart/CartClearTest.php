<?php

namespace Tests\Feature\Cart;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartClearTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

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
            'name' => 'iPhone 16 Pro',
            'slug' => 'iphone-16-pro',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP16PRO-128',
            'name' => 'Titan Tự Nhiên 128GB',
            'price' => 28990000,
            'stock_quantity' => 50,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_clear_cart(): void
    {
        $response = $this->deleteJson('/api/cart/clear');
        $response->assertStatus(401);
    }

    public function test_user_can_clear_all_items_from_cart(): void
    {
        Sanctum::actingAs($this->user);

        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('cart_items', 1);

        $response = $this->deleteJson('/api/cart/clear');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Đã xóa toàn bộ sản phẩm khỏi giỏ hàng.',
            ]);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_clearing_empty_cart_returns_success(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/cart/clear');

        $response->assertOk()
            ->assertJson([
                'status' => true,
            ]);
    }
}
