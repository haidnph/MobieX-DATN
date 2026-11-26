<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherUser;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => 'TEST_SECRET_KEY_1234567890',
            'vnpay.transaction_api_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'zalopay.app_id' => '2554',
            'zalopay.key1' => 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn',
            'zalopay.key2' => 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf',
            'zalopay.query_endpoint' => 'https://sb-openapi.zalopay.vn/v2/query',
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'klm0567shj9qRrA',
            'momo.secret_key' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'momo.query_endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/query',
        ]);

        $this->customer = User::factory()->create(['is_active' => true]);
        $this->otherUser = User::factory()->create(['is_active' => true]);
        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STD-VERIFY',
            'fee' => 0,
            'is_active' => true,
        ]);
    }

    private function createOrder(string $method, float $amount = 500000, string $paymentStatus = 'pending', string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'order_code' => 'MX-VRF-'.strtoupper(substr(uniqid(), -6)),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => $amount,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => $amount,
            'currency_code' => 'VND',
            'shipping_name' => $this->customer->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Test Ward',
            'shipping_address_line' => '123 Test St',
            'payment_method' => $method,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }

    public function test_verify_status_enforces_idor_ownership(): void
    {
        $order = $this->createOrder('vnpay');

        $this->actingAs($this->otherUser, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertStatus(403);
    }

    public function test_verify_status_settles_zalopay_from_gateway_query_and_handles_late_webhook_idempotently(): void
    {
        $order = $this->createOrder('zalopay', 1500000);
        $appTransId = date('ymd').'_'.$order->id.'_abcdef';
        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-ZP-VERIFY-'.$order->id,
            'idempotency_key' => 'zp-verify-'.$order->id,
            'method' => 'zalopay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => $appTransId,
        ]);

        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response([
                'return_code' => 1,
                'zp_trans_id' => 'ZP-REAL-999111',
                'amount' => 1500000,
            ], 200),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.gateway_verified', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'success',
            'gateway_transaction_id' => $appTransId,
        ]);

        // Subsequent late webhook with same attempt should remain idempotent (NO false duplicate capture)
        $callbackPayload = [
            'app_id' => 2554,
            'app_trans_id' => $appTransId,
            'zp_trans_id' => 'ZP-REAL-999111',
            'amount' => 1500000,
        ];
        $dataJson = json_encode($callbackPayload, JSON_UNESCAPED_SLASHES);
        $this->postJson('/api/payments/zalopay/callback', [
            'data' => $dataJson,
            'mac' => hash_hmac('sha256', $dataJson, (string) config('zalopay.key2')),
        ])->assertOk()->assertJsonPath('return_code', 1);

        $this->assertFalse((bool) $payment->fresh()->refund_required);
    }

    public function test_verify_status_rejects_amount_mismatch_from_gateway(): void
    {
        $order = $this->createOrder('zalopay', 1500000);
        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-ZP-MISMATCH-'.$order->id,
            'idempotency_key' => 'zp-mismatch-'.$order->id,
            'method' => 'zalopay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => date('ymd').'_'.$order->id.'_112233',
        ]);

        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response([
                'return_code' => 1,
                'zp_trans_id' => 'ZP-FAKE-AMOUNT',
                'amount' => 10000, // mismatched amount
            ], 200),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.gateway_verified', false);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_verify_status_settles_momo_from_gateway_query(): void
    {
        $order = $this->createOrder('momo', 990000);
        $momoOrderId = 'MX_MM_'.$order->id.'_ABCDEF';
        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-MM-VERIFY-'.$order->id,
            'idempotency_key' => 'mm-verify-'.$order->id,
            'method' => 'momo',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => $momoOrderId,
            'response_data' => ['request_id' => 'REQ_MM_'.$order->id.'_ABCDEF'],
        ]);

        Http::fake([
            'test-payment.momo.vn/*' => Http::response([
                'resultCode' => 0,
                'orderId' => $momoOrderId,
                'transId' => 'MOMO-REAL-777',
                'amount' => 990000,
            ], 200),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.gateway_verified', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }

    public function test_verify_status_settles_vnpay_only_with_valid_signature_and_matching_amount(): void
    {
        $order = $this->createOrder('vnpay', 250000);
        $txnCode = 'PAY-VNP-VRF-'.$order->id;
        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => $txnCode,
            'idempotency_key' => 'vnp-verify-'.$order->id,
            'method' => 'vnpay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'response_data' => ['vnp_CreateDate' => date('YmdHis')],
        ]);

        $body = [
            'vnp_ResponseId' => 'RESP-99',
            'vnp_Command' => 'querydr',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'TEST_TMN',
            'vnp_TxnRef' => $txnCode,
            'vnp_Amount' => '25000000',
            'vnp_TransactionNo' => 'VNP-REAL-555',
            'vnp_TransactionType' => '01',
            'vnp_TransactionStatus' => '00',
        ];
        $fields = [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef',
            'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
            'vnp_TransactionStatus', 'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount',
        ];
        $signed = implode('|', array_map(static fn (string $field): string => $body[$field] ?? '', $fields));
        $body['vnp_SecureHash'] = hash_hmac('sha512', $signed, 'TEST_SECRET_KEY_1234567890');

        Http::fake([
            'sandbox.vnpayment.vn/*' => Http::response($body, 200),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('data.gateway_verified', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }

    public function test_verify_status_rejects_vnpay_query_with_invalid_signature(): void
    {
        $order = $this->createOrder('vnpay', 250000);
        $txnCode = 'PAY-VNP-TAMPER-'.$order->id;
        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => $txnCode,
            'idempotency_key' => 'vnp-tamper-'.$order->id,
            'method' => 'vnpay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'response_data' => ['vnp_CreateDate' => date('YmdHis')],
        ]);

        Http::fake([
            'sandbox.vnpayment.vn/*' => Http::response([
                'vnp_ResponseCode' => '00',
                'vnp_TransactionStatus' => '00',
                'vnp_TxnRef' => $txnCode,
                'vnp_Amount' => '25000000',
                'vnp_SecureHash' => 'tampered-invalid-signature',
            ], 200),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/payments/orders/{$order->id}/verify-status")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data.gateway_verified', false);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_create_payment_rejects_mismatched_order_payment_method(): void
    {
        $codOrder = $this->createOrder('cod', 250000);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/vnpay/create', ['order_id' => $codOrder->id])
            ->assertStatus(422);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/zalopay/create', ['order_id' => $codOrder->id])
            ->assertStatus(422);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/momo/create', ['order_id' => $codOrder->id])
            ->assertStatus(422);
    }
}
