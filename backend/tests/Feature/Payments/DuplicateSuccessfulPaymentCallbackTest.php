<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateSuccessfulPaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $vnpaySecret = 'duplicate-vnpay-test-secret';

    private function createPaidOrderWithSecondAttempt(string $method, bool $alreadyPaid = true): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $shippingCode = 'STD-'.strtoupper(substr(uniqid(), -6));
        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => $shippingCode,
            'fee' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'DUP-'.strtoupper($method).'-'.uniqid(),
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 250000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 250000,
            'currency_code' => 'VND',
            'shipping_name' => 'Test Customer',
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Test Ward',
            'shipping_address_line' => 'Test address',
            'payment_method' => $method,
            'payment_status' => $alreadyPaid ? 'paid' : 'failed',
            'status' => $alreadyPaid ? 'confirmed' : 'cancelled',
        ]);

        if ($alreadyPaid) {
            $this->createPayment($order, 'success', 'first-'.$order->id);
        }
        $gatewayId = match ($method) {
            'zalopay' => '261123_'.$order->id.'_abc123',
            'momo' => 'MX_MM_'.$order->id.'_DUP123',
            default => null,
        };
        $second = $this->createPayment($order, $alreadyPaid ? 'pending' : 'cancelled', 'second-'.$order->id, $gatewayId);

        return [$order, $second];
    }

    private function createPayment(Order $order, string $status, string $suffix, ?string $gatewayId = null): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-'.strtoupper($suffix),
            'idempotency_key' => 'duplicate-test-'.$suffix,
            'method' => $order->payment_method,
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => $status,
            'gateway_transaction_id' => $gatewayId,
        ]);
    }

    public function test_vnpay_late_success_is_recorded_for_manual_refund_reconciliation(): void
    {
        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => $this->vnpaySecret,
        ]);
        [$order, $second] = $this->createPaidOrderWithSecondAttempt('vnpay');
        $params = $this->signVNPay([
            'vnp_TmnCode' => 'TEST_TMN',
            'vnp_Amount' => '25000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'duplicate payment',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => 'VNP-DUP-'.$order->id,
            'vnp_TxnRef' => $second->transaction_code,
        ]);
        $url = '/api/payments/vnpay/ipn?'.http_build_query($params);

        $this->getJson($url)->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertDuplicateAttemptRecorded($order, $second, 'vnpay', 'VNP-DUP-'.$order->id);
        $this->getJson($url)->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertSame(1, \DB::table('payment_webhook_events')->where('method', 'vnpay')->count());
    }

    public function test_zalopay_late_success_is_recorded_for_manual_refund_reconciliation(): void
    {
        config(['zalopay.key2' => 'duplicate-zalopay-test-key']);
        [$order, $second] = $this->createPaidOrderWithSecondAttempt('zalopay');
        $payload = [
            'app_id' => 2554,
            'app_trans_id' => $second->gateway_transaction_id,
            'zp_trans_id' => 'ZP-DUP-'.$order->id,
            'amount' => 250000,
        ];
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $callback = ['data' => $data, 'mac' => hash_hmac('sha256', $data, 'duplicate-zalopay-test-key')];

        $this->postJson('/api/payments/zalopay/callback', $callback)->assertOk()->assertJsonPath('return_code', 1);
        $this->assertDuplicateAttemptRecorded($order, $second, 'zalopay', 'ZP-DUP-'.$order->id);
        $this->postJson('/api/payments/zalopay/callback', $callback)->assertOk()->assertJsonPath('return_code', 1);
        $this->assertSame(1, \DB::table('payment_webhook_events')->where('method', 'zalopay')->count());
    }

    public function test_momo_late_success_is_recorded_for_manual_refund_reconciliation(): void
    {
        config([
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'duplicate-access-key',
            'momo.secret_key' => 'duplicate-momo-test-secret',
        ]);
        [$order, $second] = $this->createPaidOrderWithSecondAttempt('momo');
        $payload = [
            'partnerCode' => config('momo.partner_code'),
            'orderId' => $second->gateway_transaction_id,
            'requestId' => 'REQ-DUP-'.$order->id,
            'amount' => '250000',
            'orderInfo' => 'MobieX duplicate payment test',
            'orderType' => 'momo_wallet',
            'transId' => 'MOMO-DUP-'.$order->id,
            'resultCode' => 0,
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1795430000000',
            'extraData' => '',
        ];
        $payload['signature'] = hash_hmac('sha256', $this->momoRawSignature($payload), config('momo.secret_key'));

        $this->postJson('/api/payments/momo/callback', $payload)->assertOk()->assertJsonPath('resultCode', 0);
        $this->assertDuplicateAttemptRecorded($order, $second, 'momo', 'MOMO-DUP-'.$order->id);
        $this->postJson('/api/payments/momo/callback', $payload)->assertOk()->assertJsonPath('resultCode', 0);
        $this->assertSame(1, \DB::table('payment_webhook_events')->where('method', 'momo')->count());
    }

    public function test_late_success_callbacks_for_cancelled_orders_require_manual_refund(): void
    {
        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => $this->vnpaySecret,
            'zalopay.key2' => 'duplicate-zalopay-test-key',
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'duplicate-access-key',
            'momo.secret_key' => 'duplicate-momo-test-secret',
        ]);

        foreach (['vnpay', 'zalopay', 'momo'] as $method) {
            [$order, $attempt] = $this->createPaidOrderWithSecondAttempt($method, false);
            $eventKey = $this->sendSuccessCallback($method, $order, $attempt);

            $this->assertSame('cancelled', $order->fresh()->status);
            $this->assertSame('failed', $order->fresh()->payment_status);
            $this->assertTrue($attempt->fresh()->refund_required);
            $this->assertNotNull($attempt->fresh()->gateway_succeeded_at);
            $this->assertDatabaseHas('payment_webhook_events', [
                'method' => $method,
                'event_key' => $eventKey,
                'processing_status' => 'failed',
            ]);
        }
    }

    private function assertDuplicateAttemptRecorded(Order $order, Payment $payment, string $method, string $eventKey): void
    {
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('success', $order->payments()->where('id', '!=', $payment->id)->value('status'));
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->refund_required);
        $this->assertNotNull($payment->fresh()->gateway_succeeded_at);
        $this->assertTrue($payment->fresh()->response_data['refund_required']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'method' => $method,
            'event_key' => $eventKey,
            'processing_status' => 'failed',
        ]);
    }

    private function signVNPay(array $params): array
    {
        ksort($params);
        $hashData = collect($params)
            ->map(fn ($value, $key) => urlencode($key).'='.urlencode((string) $value))
            ->implode('&');
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $this->vnpaySecret);

        return $params;
    }

    private function sendSuccessCallback(string $method, Order $order, Payment $attempt): string
    {
        if ($method === 'vnpay') {
            $eventKey = 'VNP-CANCEL-DUP-'.$order->id;
            $params = $this->signVNPay([
                'vnp_TmnCode' => 'TEST_TMN',
                'vnp_Amount' => '25000000',
                'vnp_Command' => 'pay',
                'vnp_CreateDate' => date('YmdHis'),
                'vnp_CurrCode' => 'VND',
                'vnp_IpAddr' => '127.0.0.1',
                'vnp_Locale' => 'vn',
                'vnp_OrderInfo' => 'cancelled order late success',
                'vnp_ResponseCode' => '00',
                'vnp_TransactionStatus' => '00',
                'vnp_TransactionNo' => $eventKey,
                'vnp_TxnRef' => $attempt->transaction_code,
            ]);
            $this->getJson('/api/payments/vnpay/ipn?'.http_build_query($params))->assertOk()->assertJsonPath('RspCode', '00');

            return $eventKey;
        }

        if ($method === 'zalopay') {
            $eventKey = 'ZP-CANCEL-DUP-'.$order->id;
            $payload = [
                'app_id' => 2554,
                'app_trans_id' => $attempt->gateway_transaction_id,
                'zp_trans_id' => $eventKey,
                'amount' => 250000,
            ];
            $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $callback = ['data' => $data, 'mac' => hash_hmac('sha256', $data, 'duplicate-zalopay-test-key')];
            $this->postJson('/api/payments/zalopay/callback', $callback)->assertOk()->assertJsonPath('return_code', 1);

            return $eventKey;
        }

        $eventKey = 'MOMO-CANCEL-DUP-'.$order->id;
        $payload = [
            'partnerCode' => config('momo.partner_code'),
            'orderId' => $attempt->gateway_transaction_id,
            'requestId' => 'REQ-CANCEL-DUP-'.$order->id,
            'amount' => '250000',
            'orderInfo' => 'MobieX cancelled order late success test',
            'orderType' => 'momo_wallet',
            'transId' => $eventKey,
            'resultCode' => 0,
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1795430000000',
            'extraData' => '',
        ];
        $payload['signature'] = hash_hmac('sha256', $this->momoRawSignature($payload), config('momo.secret_key'));
        $this->postJson('/api/payments/momo/callback', $payload)->assertOk()->assertJsonPath('resultCode', 0);

        return $eventKey;
    }

    private function momoRawSignature(array $payload): string
    {
        return 'accessKey='.config('momo.access_key')
            .'&amount='.$payload['amount']
            .'&extraData='.$payload['extraData']
            .'&message='.$payload['message']
            .'&orderId='.$payload['orderId']
            .'&orderInfo='.$payload['orderInfo']
            .'&orderType='.$payload['orderType']
            .'&partnerCode='.$payload['partnerCode']
            .'&payType='.$payload['payType']
            .'&requestId='.$payload['requestId']
            .'&responseTime='.$payload['responseTime']
            .'&resultCode='.$payload['resultCode']
            .'&transId='.$payload['transId'];
    }
}
