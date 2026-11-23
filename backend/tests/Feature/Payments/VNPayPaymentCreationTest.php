<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VNPayPaymentCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => 'SECRET_FOR_TESTING_123',
            'vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'vnpay.return_url' => 'http://localhost:5173/payment/vnpay/return',
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->otherUser = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);
    }

    private function createOrder(User $customer, string $paymentStatus = 'pending', string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_code' => 'MX-ORD-'.uniqid(),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 10000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 10030000,
            'currency_code' => 'VND',
            'shipping_name' => $customer->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Pham Van Dong',
            'payment_method' => 'vnpay',
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }

    public function test_user_can_create_vnpay_payment_url(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/vnpay/create', [
                'order_id' => $order->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'payment_id',
                    'order_code',
                    'amount',
                    'payment_url',
                ],
            ]);

        $paymentUrl = $response->json('data.payment_url');
        $payment = Payment::where('order_id', $order->id)->where('method', 'vnpay')->firstOrFail();
        $this->assertStringContainsString('vnp_TxnRef='.$payment->transaction_code, $paymentUrl);
        $this->assertStringContainsString('vnp_Amount=1003000000', $paymentUrl);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'vnpay',
            'status' => 'pending',
            'amount' => 10030000,
        ]);
    }

    public function test_cannot_create_vnpay_url_for_other_users_order(): void
    {
        $orderOfOther = $this->createOrder($this->otherUser);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/vnpay/create', [
                'order_id' => $orderOfOther->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_vnpay_url_for_already_paid_order(): void
    {
        $order = $this->createOrder($this->user, 'paid', 'confirmed');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/vnpay/create', [
                'order_id' => $order->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_vnpay_return_with_valid_signature_returns_success(): void
    {
        $order = $this->createOrder($this->user);
        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-RETURN-'.uniqid(),
            'idempotency_key' => 'vnpay-return-'.$order->id,
            'method' => 'vnpay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
        ]);
        $service = app(VNPayService::class);
        $url = $service->createPaymentUrl([
            'order_code' => $payment->transaction_code,
            'amount' => $order->total_amount,
        ]);

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        $params['vnp_ResponseCode'] = '00';
        $params['vnp_TransactionNo'] = '14552233';

        // Recompute hash because we added responseCode & transactionNo
        unset($params['vnp_SecureHash']);
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
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'SECRET_FOR_TESTING_123');

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'pending_confirmation',
                'data' => [
                    'order_code' => $order->order_code,
                    'response_code' => '00',
                    'payment_status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_vnpay_return_with_invalid_signature_returns_400(): void
    {
        $response = $this->getJson('/api/payments/vnpay/return?vnp_TxnRef=123&vnp_SecureHash=fake_hash');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Chữ ký số phản hồi từ VNPay không hợp lệ.',
            ]);
    }

    public function test_expired_reservation_cannot_start_vnpay_payment(): void
    {
        $order = $this->createOrder($this->user);
        $order->update(['reservation_expires_at' => now()->subMinute()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/vnpay/create', ['order_id' => $order->id])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'method' => 'vnpay']);
    }

    public function test_missing_vnpay_credentials_do_not_create_payment_attempt(): void
    {
        config(['vnpay.tmn_code' => '', 'vnpay.hash_secret' => '']);
        $order = $this->createOrder($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/vnpay/create', ['order_id' => $order->id])
            ->assertStatus(503);

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'method' => 'vnpay']);
    }
}
