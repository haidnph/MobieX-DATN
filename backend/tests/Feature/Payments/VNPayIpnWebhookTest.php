<?php

namespace Tests\Feature\Payments;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VNPayIpnWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ShippingMethod $shippingMethod;

    private string $secret = 'SECRET_IPN_TEST_KEY_456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => $this->secret,
            'vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'vnpay.return_url' => 'http://localhost:5173/payment/vnpay/return',
        ]);

        $this->user = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);
    }

    private function createOrder(float $amount = 5000000): Order
    {
        return Order::create([
            'user_id' => $this->user->id,
            'order_code' => 'MX-ORD-'.uniqid(),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => $amount,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => $amount,
            'currency_code' => 'VND',
            'shipping_name' => 'Test User',
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Pham Van Dong',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }

    private function buildIpnParams(array $override = []): array
    {
        $params = array_merge([
            'vnp_TmnCode' => 'TEST_TMN',
            'vnp_Amount' => '500000000', // 5,000,000 * 100
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '14667788',
            'vnp_TxnRef' => 'MX-DEFAULT',
        ], $override);

        ksort($params);

        $hashData = '';
        $i = 0;
        foreach ($params as $k => $v) {
            if ($i === 1) {
                $hashData .= '&'.urlencode($k).'='.urlencode((string) $v);
            } else {
                $hashData .= urlencode($k).'='.urlencode((string) $v);
                $i = 1;
            }
        }

        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $this->secret);

        return $params;
    }

    public function test_valid_ipn_settles_order_and_payment(): void
    {
        $order = $this->createOrder(5000000);

        $params = $this->buildIpnParams([
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '500000000',
            'vnp_TransactionNo' => '99887766',
            'vnp_ResponseCode' => '00',
        ]);

        $response = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'RspCode' => '00',
                'Message' => 'Confirm Success',
            ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'success',
            'gateway_transaction_id' => '99887766',
        ]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'method' => 'vnpay',
            'event_key' => '99887766',
            'processing_status' => 'processed',
        ]);
    }

    public function test_valid_ipn_consumes_reserved_flash_sale_quantity(): void
    {
        $order = $this->createOrder(750000);
        $category = Category::create(['name' => 'Phones', 'slug' => 'vnp-sale-phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'MobieX', 'slug' => 'vnp-sale-brand', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Sale phone',
            'slug' => 'vnp-sale-phone',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VNP-SALE-1',
            'price' => 1000000,
            'is_active' => true,
        ]);
        $sale = FlashSale::create([
            'name' => 'VNPay sale',
            'slug' => 'vnpay-sale',
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'status' => 'active',
        ]);
        $saleItem = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 750000,
            'quantity_limit' => 1,
            'quantity_reserved' => 1,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'flash_sale_id' => $sale->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'price' => 750000,
            'quantity' => 1,
        ]);

        $params = $this->buildIpnParams([
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '75000000',
            'vnp_TransactionNo' => 'SALE998877',
            'vnp_ResponseCode' => '00',
        ]);

        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params))
            ->assertOk()
            ->assertJson(['RspCode' => '00']);

        $this->assertSame(0, $saleItem->fresh()->quantity_reserved);
        $this->assertSame(1, $saleItem->fresh()->quantity_sold);
    }

    public function test_ipn_resolves_unique_payment_attempt_reference(): void
    {
        $order = $this->createOrder(5000000);
        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-VNP-'.uniqid(),
            'idempotency_key' => 'vnpay-attempt-'.$order->id,
            'method' => 'vnpay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
        ]);

        $params = $this->buildIpnParams([
            'vnp_TxnRef' => $payment->transaction_code,
            'vnp_Amount' => '500000000',
            'vnp_TransactionNo' => '99887767',
            'vnp_ResponseCode' => '00',
        ]);

        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params))
            ->assertOk()
            ->assertJson(['RspCode' => '00']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'success',
            'gateway_transaction_id' => '99887767',
        ]);
    }

    public function test_ipn_with_invalid_signature_is_rejected(): void
    {
        $order = $this->createOrder(5000000);

        $params = [
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '500000000',
            'vnp_SecureHash' => 'tampered_hash_value',
        ];

        $response = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'RspCode' => '97',
                'Message' => 'Invalid Signature',
            ]);

        $order->refresh();
        $this->assertEquals('pending', $order->payment_status);
    }

    public function test_ipn_with_order_not_found(): void
    {
        $params = $this->buildIpnParams([
            'vnp_TxnRef' => 'NON_EXISTENT_ORDER_CODE',
        ]);

        $response = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'RspCode' => '01',
                'Message' => 'Order not found',
            ]);
    }

    public function test_ipn_with_invalid_amount(): void
    {
        $order = $this->createOrder(5000000);

        // Send 1,000,000 instead of 5,000,000
        $params = $this->buildIpnParams([
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '100000000',
        ]);

        $response = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'RspCode' => '04',
                'Message' => 'Invalid Amount',
            ]);
    }

    public function test_second_ipn_returns_already_confirmed_idempotently(): void
    {
        $order = $this->createOrder(5000000);

        $params = $this->buildIpnParams([
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '500000000',
            'vnp_TransactionNo' => '11223344',
            'vnp_ResponseCode' => '00',
        ]);

        // First call
        $res1 = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));
        $res1->assertStatus(200)->assertJson(['RspCode' => '00']);

        // Second duplicate call
        $res2 = $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params));
        $res2->assertStatus(200)
            ->assertJson([
                'RspCode' => '02',
                'Message' => 'Order already confirmed',
            ]);
    }

    public function test_ipn_rejects_mismatched_tmn_code(): void
    {
        $order = $this->createOrder(5000000);

        $params = $this->buildIpnParams([
            'vnp_TmnCode' => 'WRONG_TMN',
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => '500000000',
        ]);

        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params))
            ->assertOk()
            ->assertJson([
                'RspCode' => '02',
                'Message' => 'Invalid TmnCode',
            ]);

        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    public function test_ipn_handles_processing_suspected_fraud_and_failed_transaction_statuses_safely(): void
    {
        // 1. Processing (vnp_TransactionStatus = 01): keep pending, never paid, never failed
        $processingOrder = $this->createOrder(5000000);
        $processingParams = $this->buildIpnParams([
            'vnp_TxnRef' => $processingOrder->order_code,
            'vnp_Amount' => '500000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '01',
            'vnp_TransactionNo' => 'PROC1001',
        ]);
        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($processingParams))
            ->assertOk()
            ->assertJsonPath('RspCode', '00');
        $this->assertEquals('pending', $processingOrder->fresh()->payment_status);

        // 2. Suspected fraud (vnp_TransactionStatus = 07): never mark paid!
        $fraudOrder = $this->createOrder(5000000);
        $fraudParams = $this->buildIpnParams([
            'vnp_TxnRef' => $fraudOrder->order_code,
            'vnp_Amount' => '500000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '07',
            'vnp_TransactionNo' => 'FRAUD1002',
        ]);
        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($fraudParams))
            ->assertOk()
            ->assertJsonPath('RspCode', '00');
        $this->assertEquals('pending', $fraudOrder->fresh()->payment_status);
        $this->assertEquals('pending', $fraudOrder->fresh()->status);

        // 3. TransactionStatus 02 with ResponseCode 00: must fail, never paid!
        $failedOrder = $this->createOrder(5000000);
        $failedParams = $this->buildIpnParams([
            'vnp_TxnRef' => $failedOrder->order_code,
            'vnp_Amount' => '500000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '02',
            'vnp_TransactionNo' => 'FAIL1003',
        ]);
        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($failedParams))
            ->assertOk()
            ->assertJsonPath('RspCode', '00');
        $this->assertEquals('failed', $failedOrder->fresh()->payment_status);
    }

    public function test_late_failed_ipn_never_overwrites_paid_order_and_return_sees_ipn_first(): void
    {
        $order = $this->createOrder(5000000);
        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-IPN-FIRST-'.$order->id,
            'idempotency_key' => 'vnpay-ipn-first-'.$order->id,
            'method' => 'vnpay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
        ]);

        // IPN arrives BEFORE customer browser hits Return URL
        $successParams = $this->buildIpnParams([
            'vnp_TxnRef' => $payment->transaction_code,
            'vnp_Amount' => '500000000',
            'vnp_TransactionNo' => 'IPNFIRST001',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
        ]);
        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($successParams))
            ->assertOk()
            ->assertJsonPath('RspCode', '00');

        // Return URL then confirms success immediately
        $this->getJson('/api/payments/vnpay/return?'.http_build_query($successParams))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.payment_status', 'paid');

        // Late failed IPN arrives afterwards: must NOT overwrite paid status
        $lateFailParams = $this->buildIpnParams([
            'vnp_TxnRef' => $payment->transaction_code,
            'vnp_Amount' => '500000000',
            'vnp_TransactionNo' => 'LATEFAIL002',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]);
        $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($lateFailParams))
            ->assertOk()
            ->assertJsonPath('RspCode', '02');

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('confirmed', $order->fresh()->status);
    }
}
