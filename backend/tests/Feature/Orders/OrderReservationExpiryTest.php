<?php

namespace Tests\Feature\Orders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OrderReservationExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_online_order_releases_stock_but_cod_reservation_remains(): void
    {
        $user = User::factory()->create();
        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Phones', 'slug' => 'expiry-phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test', 'slug' => 'expiry-brand', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Expiry Test Phone',
            'slug' => 'expiry-test-phone',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'EXPIRY-TEST-001',
            'price' => 100000,
            'is_active' => true,
        ]);
        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 3,
            'min_stock' => 1,
        ]);

        $createOrder = function (string $paymentMethod, int $quantity, ?Carbon $expiresAt) use ($user, $shipping, $variant, $product): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'order_code' => 'MX-EXP-'.strtoupper(bin2hex(random_bytes(5))),
                'shipping_method_id' => $shipping->id,
                'shipping_method_code' => $shipping->code,
                'shipping_method_name' => $shipping->name,
                'subtotal' => 100000 * $quantity,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total_amount' => 100000 * $quantity,
                'currency_code' => 'VND',
                'shipping_name' => $user->name,
                'shipping_phone' => '0912345678',
                'shipping_province_code' => 'VN-HN',
                'shipping_province_name' => 'Hà Nội',
                'shipping_ward_code' => '001',
                'shipping_ward_name' => 'Cầu Giấy',
                'shipping_address_line' => 'Test address',
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'status' => 'pending',
                'reservation_expires_at' => $expiresAt,
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'product_name' => $product->name,
                'sku' => $variant->sku,
                'price' => 100000,
                'quantity' => $quantity,
            ]);

            return $order;
        };

        $expiredOrder = $createOrder('vnpay', 2, now()->subMinute());
        $codOrder = $createOrder('cod', 1, null);
        Payment::create([
            'order_id' => $expiredOrder->id,
            'transaction_code' => 'PAY-EXPIRY-TEST',
            'idempotency_key' => 'expiry-test-payment',
            'method' => 'vnpay',
            'amount' => 200000,
            'currency_code' => 'VND',
            'status' => 'pending',
        ]);

        Artisan::call('orders:expire-reservations');

        $this->assertDatabaseHas('orders', [
            'id' => $expiredOrder->id,
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $expiredOrder->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $codOrder->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 1,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_id' => $expiredOrder->id,
            'type' => 'release',
            'reserved_delta' => -2,
            'idempotency_key' => "order_{$expiredOrder->id}_var_{$variant->id}_release_expired",
        ]);
    }

    public function test_expiry_job_leaves_order_untouched_and_fails_when_reserved_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Phones', 'slug' => 'mismatch-phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test', 'slug' => 'mismatch-brand', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Reservation Mismatch Phone',
            'slug' => 'reservation-mismatch-phone',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RESERVATION-MISMATCH-001',
            'price' => 100000,
            'is_active' => true,
        ]);
        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 1,
            'min_stock' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'MX-MISMATCH-'.strtoupper(bin2hex(random_bytes(5))),
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 200000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 200000,
            'currency_code' => 'VND',
            'shipping_name' => $user->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Cau Giay',
            'shipping_address_line' => 'Test address',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'status' => 'pending',
            'reservation_expires_at' => now()->subMinute(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'price' => 100000,
            'quantity' => 2,
        ]);

        $exitCode = Artisan::call('orders:expire-reservations');

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'reserved_quantity' => 1,
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_id' => $order->id,
            'type' => 'release',
        ]);
    }
}
