<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZaloPayCallbackAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_settles_the_matching_attempt_and_is_idempotent(): void
    {
        config(['zalopay.key2' => 'callback-test-key']);
        $user = User::factory()->create(['is_active' => true]);
        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'ZP-ATTEMPT-'.uniqid(),
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
            'payment_method' => 'zalopay',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $olderAttempt = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-ZP-OLD-'.uniqid(),
            'idempotency_key' => 'zalopay-old-'.$order->id,
            'method' => 'zalopay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'failed',
            'gateway_transaction_id' => '261123_'.$order->id.'_old123',
        ]);
        $attemptReference = '261123_'.$order->id.'_abc123';
        $attempt = Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-ZP-CURRENT-'.uniqid(),
            'idempotency_key' => 'zalopay-current-'.$order->id,
            'method' => 'zalopay',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => $attemptReference,
        ]);

        $payload = [
            'app_id' => 2554,
            'app_trans_id' => $attemptReference,
            'zp_trans_id' => 'ZP-TXN-123',
            'amount' => 250000,
        ];
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $callback = ['data' => $data, 'mac' => hash_hmac('sha256', $data, 'callback-test-key')];

        $this->postJson('/api/payments/zalopay/callback', $callback)
            ->assertOk()
            ->assertJsonPath('return_code', 1);

        $this->assertDatabaseHas('payments', [
            'id' => $attempt->id,
            'status' => 'success',
            'gateway_transaction_id' => $attemptReference,
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $olderAttempt->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);

        $this->postJson('/api/payments/zalopay/callback', $callback)
            ->assertOk()
            ->assertJsonPath('return_code', 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }
}
