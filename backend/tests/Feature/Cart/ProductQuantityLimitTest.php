<?php

namespace Tests\Feature\Cart;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductQuantityLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private ProductVariant $firstVariant;

    private ProductVariant $secondVariant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones-limit', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand-limit', 'is_active' => true]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Quantity Limit Phone',
            'slug' => 'quantity-limit-phone',
            'status' => 'active',
        ]);

        $this->firstVariant = $this->createVariant('LIMIT-A');
        $this->secondVariant = $this->createVariant('LIMIT-B');
        Sanctum::actingAs($this->user);
    }

    public function test_add_to_cart_aggregates_variants_of_the_same_product(): void
    {
        $this->postJson('/api/cart/items', ['variant_id' => $this->firstVariant->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/cart/items', ['variant_id' => $this->secondVariant->id, 'quantity' => 2])->assertOk();

        $this->postJson('/api/cart/items', ['variant_id' => $this->firstVariant->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bạn chỉ có thể mua tối đa 4 chiếc cho mỗi sản phẩm trong một đơn hàng.');

        $this->assertSame(4, (int) CartItem::sum('quantity'));
    }

    public function test_cart_update_cannot_raise_product_total_above_four(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'status' => 'active']);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->firstVariant->id,
            'quantity' => 2,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->secondVariant->id,
            'quantity' => 1,
        ]);

        $this->putJson('/api/cart/items/'.$item->id, ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bạn chỉ có thể mua tối đa 4 chiếc cho mỗi sản phẩm trong một đơn hàng.');

        $this->assertSame(3, (int) CartItem::sum('quantity'));
    }

    private function createVariant(string $sku): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => $sku,
            'price' => 100000,
            'is_active' => true,
        ]);
        Inventory::create([
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
            'min_stock' => 0,
        ]);

        return $variant;
    }
}
