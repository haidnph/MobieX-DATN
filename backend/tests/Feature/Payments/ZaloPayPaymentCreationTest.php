<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZaloPayPaymentCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zalopay.app_id' => '2554',
            'zalopay.key1' => 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn',
            'zalopay.key2' => 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf',
            'zalopay.endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
            'zalopay.query_endpoint' => 'https://sb-openapi.zalopay.vn/v2/query',
            'zalopay.return_url' => 'http://localhost:5173/payment/zalopay/return',
            'zalopay.callback_url' => 'http://localhost:8000/api/payments/zalopay/callback',
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
            'order_code' => 'MX-ZP-'.uniqid(),
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
            'payment_method' => 'zalopay',
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }

    public function test_user_can_create_zalopay_payment(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response([
                'return_code' => 1,
                'return_message' => 'Giao dịch thành công',
                'order_url' => 'https://qcgateway.zalopay.vn/openinapp?order=TEST_ORDER',
                'zp_trans_token' => 'ZP_TOKEN_TEST',
            ], 200),
        ]);

        $order = $this->createOrder($this->user);

        $response = $this->actingAs($this->user)->postJson('/api/payments/zalopay/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order_url', 'https://qcgateway.zalopay.vn/openinapp?order=TEST_ORDER')
            ->assertJsonPath('data.payment_url', 'https://qcgateway.zalopay.vn/openinapp?order=TEST_ORDER')
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.grand_total', 10030000);

        Http::assertSent(fn ($request) => (int) $request['expire_duration_seconds'] === 1770);

        $this->actingAs($this->user)->postJson('/api/payments/zalopay/create', [
            'order_id' => $order->id,
        ])->assertOk()
            ->assertJsonPath('data.order_url', 'https://qcgateway.zalopay.vn/openinapp?order=TEST_ORDER');
        Http::assertSentCount(1);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'zalopay',
            'status' => 'pending',
        ]);
    }

    public function test_idor_protection_prevents_paying_other_users_order(): void
    {
        $order = $this->createOrder($this->otherUser);

        $response = $this->actingAs($this->user)->postJson('/api/payments/zalopay/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $order = $this->createOrder($this->user, 'paid', 'confirmed');

        $response = $this->actingAs($this->user)->postJson('/api/payments/zalopay/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Đơn hàng này đã được thanh toán thành công.');
    }

    public function test_expired_reservation_cannot_start_zalopay_payment(): void
    {
        $order = $this->createOrder($this->user);
        $order->update(['reservation_expires_at' => now()->subMinute()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/zalopay/create', ['order_id' => $order->id])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'method' => 'zalopay']);
    }

    public function test_missing_zalopay_credentials_do_not_create_payment_attempt(): void
    {
        config(['zalopay.app_id' => '', 'zalopay.key1' => '', 'zalopay.key2' => '']);
        $order = $this->createOrder($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/payments/zalopay/create', ['order_id' => $order->id])
            ->assertStatus(503);

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'method' => 'zalopay']);
    }

    public function test_cannot_pay_cancelled_order(): void
    {
        $order = $this->createOrder($this->user, 'pending', 'cancelled');

        $response = $this->actingAs($this->user)->postJson('/api/payments/zalopay/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Không thể thanh toán cho đơn hàng đã bị hủy.');
    }
}
