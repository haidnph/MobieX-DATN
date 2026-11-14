<?php

namespace Tests\Feature\Coupons;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCouponValidationTest extends TestCase
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
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP15-128',
            'price' => 20000000,
            'is_active' => true,
        ]);
    }

    private function createCartWithSubtotal(int $quantity = 1): Cart
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

    public function test_unauthenticated_user_cannot_apply_coupon(): void
    {
        $response = $this->postJson('/api/cart/apply-coupon', [
            'coupon_code' => 'DISCOUNT10',
        ]);

        $response->assertStatus(401);
    }

    public function test_apply_valid_percent_coupon_with_max_discount(): void
    {
        $this->createCartWithSubtotal(1); // subtotal = 20,000,000

        $coupon = Coupon::create([
            'code' => 'SALE10',
            'discount_type' => 'percent',
            'discount_value' => 10, // 10% of 20M is 2M, but max_discount is 1M
            'max_discount' => 1000000,
            'min_order_amount' => 5000000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/apply-coupon', [
                'coupon_code' => 'SALE10',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Áp dụng mã giảm giá thành công.',
                'data' => [
                    'coupon_code' => 'SALE10',
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'subtotal' => 20000000,
                    'discount_amount' => 1000000,
                    'final_total' => 19000000,
                ],
            ]);
    }

    public function test_apply_valid_fixed_coupon(): void
    {
        $this->createCartWithSubtotal(1); // subtotal = 20,000,000

        $coupon = Coupon::create([
            'code' => 'FIXED500',
            'discount_type' => 'fixed',
            'discount_value' => 500000,
            'min_order_amount' => 1000000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/apply-coupon', [
                'coupon_code' => 'FIXED500',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'coupon_code' => 'FIXED500',
                    'discount_type' => 'fixed',
                    'discount_value' => 500000,
                    'discount_amount' => 500000,
                    'final_total' => 19500000,
                ],
            ]);
    }

    public function test_reject_expired_coupon(): void
    {
        $this->createCartWithSubtotal(1);

        Coupon::create([
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'end_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/apply-coupon', [
                'coupon_code' => 'EXPIRED',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Mã giảm giá đã hết hạn sử dụng.',
            ]);
    }

    public function test_reject_coupon_when_usage_limit_reached(): void
    {
        $this->createCartWithSubtotal(1);

        Coupon::create([
            'code' => 'OUTOFSTOCK',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'usage_limit' => 5,
            'used_count' => 3,
            'reserved_count' => 2, // 3 + 2 = 5 >= limit
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/apply-coupon', [
                'coupon_code' => 'OUTOFSTOCK',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Mã giảm giá đã hết lượt sử dụng.',
            ]);
    }

    public function test_reject_coupon_when_subtotal_below_minimum(): void
    {
        $this->createCartWithSubtotal(1); // subtotal = 20M

        Coupon::create([
            'code' => 'BIGBUY',
            'discount_type' => 'fixed',
            'discount_value' => 1000000,
            'min_order_amount' => 50000000, // min 50M
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/apply-coupon', [
                'coupon_code' => 'BIGBUY',
            ]);

        $response->assertStatus(422);
    }

    public function test_get_cart_with_valid_coupon_calculates_discount_and_total(): void
    {
        $this->createCartWithSubtotal(1); // subtotal = 20,000,000

        Coupon::create([
            'code' => 'GETCART100K',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'min_order_amount' => 500000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart?coupon_code=GETCART100K');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'subtotal' => 20000000,
                    'discount' => 100000,
                    'total' => 19900000,
                    'coupon_code' => 'GETCART100K',
                    'coupon_invalid_reason' => null,
                ],
            ]);
    }

    public function test_get_cart_with_invalid_coupon_returns_reason_and_zero_discount(): void
    {
        $this->createCartWithSubtotal(1); // subtotal = 20,000,000

        Coupon::create([
            'code' => 'TOOHIGH',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'min_order_amount' => 50000000, // min 50M > 20M
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart?coupon_code=TOOHIGH');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'subtotal' => 20000000,
                    'discount' => 0,
                    'total' => 20000000,
                    'coupon_code' => null,
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['coupon_invalid_reason']);
        $this->assertStringContainsString('tối thiểu', $data['coupon_invalid_reason']);
    }
}
