<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
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
}
