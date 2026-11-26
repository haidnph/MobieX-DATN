<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\FlashSaleOrderService;
use App\Services\FlashSalePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FlashSaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductVariant $variant;

    private FlashSale $sale;

    private FlashSaleItem $saleItem;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'MobieX', 'slug' => 'mobiex', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Sale phone',
            'slug' => 'sale-phone',
            'status' => 'active',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SALE-PHONE-1',
            'price' => 1000000,
            'is_active' => true,
        ]);
        Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
            'min_stock' => 1,
        ]);
        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);

        $this->sale = FlashSale::create([
            'name' => 'Weekend Sale',
            'slug' => 'weekend-sale',
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'status' => 'active',
        ]);
        $this->saleItem = FlashSaleItem::create([
            'flash_sale_id' => $this->sale->id,
            'product_variant_id' => $this->variant->id,
            'sale_price' => 750000,
            'quantity_limit' => 2,
        ]);
    }

    public function test_checkout_uses_and_reserves_the_active_flash_sale_price(): void
    {
        $this->putCartItem(2);

        $response = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload());

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '1500000.00');

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_variant_id' => $this->variant->id,
            'flash_sale_id' => $this->sale->id,
            'price' => 750000,
            'quantity' => 2,
        ]);
        $this->assertSame(2, $this->saleItem->fresh()->quantity_reserved);
    }

    public function test_checkout_rejects_quantity_above_remaining_flash_sale_limit(): void
    {
        $this->putCartItem(3);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertSame(0, $this->saleItem->fresh()->quantity_reserved);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'reserved_quantity' => 0,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_limit_counts_successful_and_reserved_flash_sale_items(): void
    {
        $this->saleItem->update(['quantity_limit' => 10, 'max_per_customer' => 2]);
        $this->putCartItem(1);
        $firstOrder = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();

        $this->putCartItem(1);
        $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $this->assertSame(0, app(FlashSalePricing::class)->customerRemaining($this->saleItem->fresh(), $this->user->id));

        $this->putCartItem(1);
        $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseHas('orders', [
            'id' => $firstOrder->json('data.id'),
            'flash_sale_reservation_status' => 'reserved',
        ]);
        $this->assertSame(2, $this->saleItem->fresh()->quantity_reserved);
    }

    public function test_cancelled_flash_sale_reservation_no_longer_counts_toward_customer_limit(): void
    {
        $this->saleItem->update(['quantity_limit' => 10, 'max_per_customer' => 2]);
        $this->putCartItem(2);
        $firstOrder = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $this->actingAs($this->user)->postJson('/api/orders/'.$firstOrder->json('data.id').'/cancel')->assertOk();

        $this->putCartItem(2);
        $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $this->assertSame(2, $this->saleItem->fresh()->quantity_reserved);
    }

    public function test_null_customer_limit_allows_more_than_default_single_item(): void
    {
        $this->saleItem->update(['quantity_limit' => 10, 'max_per_customer' => null]);
        $this->putCartItem(3);
        $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $this->assertSame(3, $this->saleItem->fresh()->quantity_reserved);
    }

    public function test_cart_reports_real_flash_sale_price_and_remaining_quantity(): void
    {
        $this->putCartItem(1);

        $response = $this->actingAs($this->user)->getJson('/api/cart')->assertOk();

        $response->assertJsonPath('data.items.0.price', 750000)
            ->assertJsonPath('data.items.0.flash_sale.sale_price', 750000)
            ->assertJsonPath('data.items.0.flash_sale.quantity_remaining', 2)
            ->assertJsonPath('data.subtotal', 750000);
    }

    public function test_product_detail_reports_active_flash_sale_price_for_the_variant(): void
    {
        ProductVariant::create([
            'product_id' => $this->variant->product_id,
            'sku' => 'SALE-PHONE-INACTIVE',
            'option_signature' => 'state:inactive',
            'price' => 900000,
            'is_active' => false,
        ]);

        $this->getJson('/api/products/'.$this->variant->product_id)
            ->assertOk()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.flash_sale_price', 750000)
            ->assertJsonPath('data.variants.0.flash_sale_original_price', 1000000)
            ->assertJsonMissingPath('data.variants.0.cost_price');

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissing(['sku' => 'SALE-PHONE-INACTIVE']);
    }

    public function test_only_campaigns_inside_their_active_window_apply_flash_sale_prices(): void
    {
        $this->putCartItem(1);

        foreach ([
            ['status' => 'draft', 'start_at' => now()->subHour(), 'end_at' => now()->addHour(), 'expected' => 1000000],
            ['status' => 'scheduled', 'start_at' => now()->addHour(), 'end_at' => now()->addHours(2), 'expected' => 1000000],
            ['status' => 'active', 'start_at' => now()->subHour(), 'end_at' => now()->addHour(), 'expected' => 750000],
            ['status' => 'cancelled', 'start_at' => now()->subHour(), 'end_at' => now()->addHour(), 'expected' => 1000000],
            ['status' => 'active', 'start_at' => now()->subHours(2), 'end_at' => now()->subHour(), 'expected' => 1000000],
        ] as $state) {
            $this->sale->update([
                'status' => $state['status'],
                'start_at' => $state['start_at'],
                'end_at' => $state['end_at'],
            ]);

            $this->actingAs($this->user)->getJson('/api/cart')
                ->assertOk()
                ->assertJsonPath('data.items.0.price', $state['expected']);
        }
    }

    public function test_variant_management_apis_require_authentication(): void
    {
        $this->getJson('/api/product-variants/'.$this->variant->id)
            ->assertUnauthorized();

        $this->getJson('/api/product-variants')
            ->assertUnauthorized();
    }

    public function test_customer_order_response_does_not_expose_cost_snapshots(): void
    {
        $this->putCartItem(1);
        $created = $this->actingAs($this->user)
            ->postJson('/api/orders', $this->checkoutPayload())
            ->assertCreated();

        $this->actingAs($this->user)
            ->getJson('/api/orders/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.cost_price_snapshot')
            ->assertJsonMissingPath('data.items.0.variant.cost_price');
    }

    public function test_coupon_preview_does_not_stack_a_coupon_on_a_flash_sale_item(): void
    {
        $this->putCartItem(1);
        Coupon::create([
            'code' => 'SALESTACK',
            'discount_type' => 'fixed',
            'discount_value' => 100000,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/cart?coupon_code=SALESTACK')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 750000)
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.coupon_invalid_reason', 'Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng.');

        $this->actingAs($this->user)
            ->postJson('/api/cart/apply-coupon', ['coupon_code' => 'SALESTACK'])
            ->assertUnprocessable();
    }

    public function test_cancel_releases_flash_sale_reservation_and_payment_consumes_it_once(): void
    {
        $this->putCartItem(2);
        $response = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));

        $this->actingAs($this->user)
            ->postJson('/api/orders/'.$order->id.'/cancel')
            ->assertOk();

        $this->assertSame(0, $this->saleItem->fresh()->quantity_reserved);
        $this->assertSame(0, $this->saleItem->fresh()->quantity_sold);
        app(FlashSaleOrderService::class)->releaseOrderReservations($order);
        $this->assertSame('released', $order->fresh()->flash_sale_reservation_status);

        // Start another order and exercise the same settlement operation used by payment callbacks.
        $this->putCartItem(1);
        $response = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $settledOrder = Order::query()->findOrFail($response->json('data.id'));

        app(FlashSaleOrderService::class)->consumeOrderReservations($settledOrder);
        app(FlashSaleOrderService::class)->consumeOrderReservations($settledOrder);
        app(FlashSaleOrderService::class)->releaseOrderReservations($settledOrder);

        $this->assertSame(0, $this->saleItem->fresh()->quantity_reserved);
        $this->assertSame(1, $this->saleItem->fresh()->quantity_sold);
        $this->assertSame('consumed', $settledOrder->fresh()->flash_sale_reservation_status);
    }

    public function test_expired_unpaid_order_releases_flash_sale_reservation_once(): void
    {
        $this->putCartItem(1);
        $response = $this->actingAs($this->user)->postJson('/api/orders', $this->checkoutPayload())->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $order->update(['payment_method' => 'vnpay', 'reservation_expires_at' => now()->subMinute()]);

        Artisan::call('orders:expire-reservations');
        Artisan::call('orders:expire-reservations');

        $this->assertSame(0, $this->saleItem->fresh()->quantity_reserved);
        $this->assertSame(0, $this->saleItem->fresh()->quantity_sold);
        $this->assertSame('released', $order->fresh()->flash_sale_reservation_status);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'reserved_quantity' => 0,
        ]);
    }

    private function putCartItem(int $quantity): void
    {
        $cart = Cart::firstOrCreate(['user_id' => $this->user->id], ['status' => 'active']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => $quantity,
        ]);
    }

    private function checkoutPayload(): array
    {
        return [
            'shipping_name' => 'Test Customer',
            'shipping_phone' => '0912345678',
            'shipping_address_line' => '123 Test Street',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ];
    }
}
