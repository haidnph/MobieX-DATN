<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VNPayReturnUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;
    private VNPayService $vnpayService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vnpay.tmn_code' => 'CGXZLS0Z',
            'vnpay.hash_secret' => 'XNBCJFAKAZQSGTARRLGCHVZWCIOIGSHN',
            'vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'vnpay.return_url' => 'http://localhost:5173/payment/vnpay/return',
        ]);

        $this->vnpayService = app(VNPayService::class);

        $this->user = User::factory()->create(['is_active' => true]);

        $shipping = ShippingMethod::create([
            'code' => 'STANDARD',
            'name' => 'Tiêu chuẩn',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'user_id' => $this->user->id,
            'order_code' => 'ORD-TEST-RETURN-'.uniqid(),
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 20000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 20030000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyễn Văn Test',
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

    public function test_vnpay_return_updates_order_status_to_paid_and_confirmed(): void
    {
        $params = [
            'vnp_Amount' => (string) (20030000 * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_CardType' => 'ATM',
            'vnp_OrderInfo' => 'Thanh toán đơn hàng',
            'vnp_PayDate' => date('YmdHis'),
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'CGXZLS0Z',
            'vnp_TransactionNo' => '14888999',
            'vnp_TxnRef' => $this->order->order_code,
        ];

        // Generate HMAC signature using test secret
        ksort($params);
        $hashdata = '';
        $i = 0;
        foreach ($params as $k => $v) {
            if ($i === 1) {
                $hashdata .= '&'.urlencode($k).'='.urlencode($v);
            } else {
                $hashdata .= urlencode($k).'='.urlencode($v);
                $i = 1;
            }
        }
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashdata, 'XNBCJFAKAZQSGTARRLGCHVZWCIOIGSHN');

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($params));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => $this->order->id,
                    'order_code' => $this->order->order_code,
                    'response_code' => '00',
                ],
            ]);

        $this->order->refresh();
        $this->assertEquals('paid', $this->order->payment_status);
        $this->assertEquals('confirmed', $this->order->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'status' => 'success',
            'gateway_transaction_id' => '14888999',
        ]);
    }

    public function test_vnpay_return_rejects_invalid_signature(): void
    {
        $params = [
            'vnp_Amount' => (string) (20030000 * 100),
            'vnp_ResponseCode' => '00',
            'vnp_TxnRef' => $this->order->order_code,
            'vnp_SecureHash' => 'INVALID_HASH_1234567890',
        ];

        $response = $this->getJson('/api/payments/vnpay/return?'.http_build_query($params));

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
