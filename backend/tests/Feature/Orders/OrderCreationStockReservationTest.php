<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationStockReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductVariant $variant;

    private Inventory $inventory;

    private ShippingMethod $shippingMethod;

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
            'option_signature' => 'color:titanium',
            'price' => 30000000,
            'compare_price' => 32000000,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 2,
            'min_stock' => 2,
        ]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Hỏa tốc',
            'code' => 'EXPRESS',
            'fee' => 50000,
            'is_active' => true,
        ]);
    }

    public function test_order_creation_reserves_stock_without_reducing_on_hand_quantity(): void
    {
        // 1. Arrange: add item to cart
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
            'price_snapshot' => 30000000,
        ]);

        $initialOnHand = $this->inventory->on_hand_quantity;
        $initialReserved = $this->inventory->reserved_quantity;

        // 2. Act: create order
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'shipping_name' => 'Nguyễn Văn A',
                'shipping_phone' => '0987654321',
                'shipping_address' => '123 Đường Cầu Giấy, Hà Nội',
                'shipping_method_id' => $this->shippingMethod->id,
                'payment_method' => 'COD',
                'customer_note' => 'Giao giờ hành chính',
            ]);

        // 3. Assert HTTP response
        $response->assertCreated();
        $response->assertJsonPath('message', 'Đặt hàng thành công.');
        $orderId = $response->json('data.id');

        // 4. Assert Database Orders & Items
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_name' => 'Nguyễn Văn A',
            'shipping_fee' => 50000,
            'total_amount' => 90050000,
            'reservation_expires_at' => null,
            'shipping_province_code' => '',
            'shipping_province_name' => '',
            'shipping_ward_code' => '',
            'shipping_ward_name' => '',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ]);

        // 5. Assert DoD: on_hand_quantity unchanged, reserved_quantity increased, available_quantity decreased
        $this->inventory->refresh();
        $this->assertEquals($initialOnHand, $this->inventory->on_hand_quantity, 'on_hand_quantity must stay unchanged upon reservation');
        $this->assertEquals($initialReserved + 3, $this->inventory->reserved_quantity, 'reserved_quantity must increment by ordered quantity');
        $this->assertEquals(5, $this->inventory->available_quantity, 'available_quantity must be (on_hand - reserved)');

        // 6. Assert DoD: exactly one reserve transaction created with idempotency key
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'user_id' => $this->user->id,
            'type' => 'reserve',
            'quantity_delta' => 0,
            'reserved_delta' => 3,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'idempotency_key' => "order_{$orderId}_var_{$this->variant->id}_reserve",
        ]);

        // 7. Assert OrderStatusHistory created
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $orderId,
            'from_status' => null,
            'status' => 'pending',
        ]);

        // 8. Assert Cart is emptied
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_customer_can_checkout_with_zalopay_payment_method(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_at_addition' => $this->variant->price,
        ]);

        $payload = [
            'shipping_name' => 'Lê Thị B',
            'shipping_phone' => '0987654321',
            'shipping_province_code' => '01',
            'shipping_province_name' => 'Thành phố Hà Nội',
            'shipping_ward_code' => '00001',
            'shipping_ward_name' => 'Phường Phúc Xá',
            'shipping_address_line' => '456 Đường ZaloPay',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'zalopay',
            'customer_note' => 'Giao giờ hành chính',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'zalopay')
            ->assertJsonPath('data.payment_status', 'pending');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'payment_method' => 'zalopay',
            'payment_status' => 'pending',
        ]);
    }

    public function test_order_creation_fails_when_product_is_inactive(): void
    {
        $this->variant->product->update(['status' => 'inactive']);

        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);

        $payload = [
            'shipping_name' => 'Lê Thị C',
            'shipping_phone' => '0987654321',
            'shipping_address_line' => '789 Đường Test',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_order_creation_fails_when_quantity_exceeds_available_inventory(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 999, // available is only 8
        ]);

        $payload = [
            'shipping_name' => 'Lê Thị D',
            'shipping_phone' => '0987654321',
            'shipping_address_line' => '789 Đường Test',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_repeating_checkout_after_success_does_not_create_a_duplicate_order(): void
    {
        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);
        $payload = [
            'shipping_name' => 'Checkout Retry',
            'shipping_phone' => '0987654321',
            'shipping_address_line' => '123 Retry Street',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ];

        $this->actingAs($this->user)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson('/api/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertSame(1, Order::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(3, $this->inventory->fresh()->reserved_quantity);
        $this->assertSame(1, InventoryTransaction::query()
            ->where('user_id', $this->user->id)
            ->where('type', 'reserve')
            ->count());
    }

    public function test_only_one_customer_can_reserve_the_last_available_unit(): void
    {
        $this->inventory->update([
            'on_hand_quantity' => 1,
            'reserved_quantity' => 0,
        ]);
        $secondCustomer = User::factory()->create(['is_active' => true]);

        foreach ([$this->user, $secondCustomer] as $customer) {
            $cart = Cart::create([
                'user_id' => $customer->id,
                'status' => 'active',
            ]);
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $this->variant->id,
                'quantity' => 1,
            ]);
        }

        $payload = [
            'shipping_name' => 'Last Item Buyer',
            'shipping_phone' => '0987654321',
            'shipping_address_line' => '123 Last Item Street',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ];

        $this->actingAs($this->user)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $this->actingAs($secondCustomer)
            ->postJson('/api/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $this->inventory->fresh()->reserved_quantity);
        $this->assertSame(0, $this->inventory->fresh()->available_quantity);
        $this->assertSame(1, InventoryTransaction::query()->where('type', 'reserve')->count());
    }

    public function test_checkout_locks_and_reserves_variants_in_stable_id_order(): void
    {
        $secondVariant = ProductVariant::create([
            'product_id' => $this->variant->product_id,
            'sku' => 'IP15PM-512-TI',
            'option_signature' => 'color:titanium;storage:512gb',
            'price' => 32000000,
            'is_active' => true,
        ]);
        Inventory::create([
            'product_variant_id' => $secondVariant->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 0,
            'min_stock' => 2,
        ]);

        $cart = Cart::create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Insert in reverse order; checkout must still acquire inventory locks
        // in the same order for every cart containing these variants.
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $secondVariant->id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/orders', [
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0987654321',
            'shipping_address_line' => '123 Cau Giay',
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'COD',
        ])->assertCreated();

        $reservationVariantIds = InventoryTransaction::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $response->json('data.id'))
            ->where('type', 'reserve')
            ->orderBy('id')
            ->pluck('product_variant_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$this->variant->id, $secondVariant->id], $reservationVariantIds);
    }
}
