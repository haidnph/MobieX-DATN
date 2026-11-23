<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoMoCallbackFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_failure_callback_marks_payment_and_order_failed(): void
    {
        config([
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'test-access-key',
            'momo.secret_key' => 'test-secret-key',
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'MM-FAIL-'.uniqid(),
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
            'payment_method' => 'momo',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
        $gatewayOrderId = "MX_MM_{$order->id}_ABC123";

        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-MM-OLD-'.uniqid(),
            'idempotency_key' => 'momo-order-old-'.$order->id,
            'method' => 'momo',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'failed',
            'gateway_transaction_id' => "MX_MM_{$order->id}_OLD123",
        ]);

        Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-MM-'.uniqid(),
            'idempotency_key' => 'momo-order-'.$order->id,
            'method' => 'momo',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => $gatewayOrderId,
        ]);

        $payload = [
            'partnerCode' => config('momo.partner_code'),
            'orderId' => $gatewayOrderId,
            'requestId' => "REQ_MM_{$order->id}_ABC123",
            'amount' => '250000',
            'orderInfo' => 'MobieX test payment',
            'orderType' => 'momo_wallet',
            'transId' => 'MOMO-FAILED-001',
            'resultCode' => 1006,
            'message' => 'Transaction failed',
            'payType' => 'qr',
            'responseTime' => '1795430000000',
            'extraData' => '',
        ];
        $rawHash = 'accessKey='.config('momo.access_key')
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
        $payload['signature'] = hash_hmac('sha256', $rawHash, config('momo.secret_key'));

        $this->postJson('/api/payments/momo/callback', $payload)
            ->assertOk()
            ->assertJsonPath('resultCode', 0);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'payment_status' => 'failed',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
            'gateway_transaction_id' => $gatewayOrderId,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway_transaction_id' => "MX_MM_{$order->id}_OLD123",
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'method' => 'momo',
            'event_key' => 'MOMO-FAILED-001',
            'signature_verified' => true,
            'processing_status' => 'failed',
        ]);

        $this->postJson('/api/payments/momo/callback', $payload)
            ->assertOk()
            ->assertJsonPath('resultCode', 0);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }
}
