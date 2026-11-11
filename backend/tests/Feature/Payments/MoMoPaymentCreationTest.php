<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoMoPaymentCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'klm0567shj9qRrA',
            'momo.secret_key' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'momo.query_endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/query',
            'momo.return_url' => 'http://localhost:5173/payment/momo/return',
            'momo.callback_url' => 'http://localhost:8000/api/payments/momo/callback',
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
            'order_code' => 'MX-MM-'.uniqid(),
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
            'payment_method' => 'momo',
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }

    public function test_user_can_create_momo_payment(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => Http::response([
                'resultCode' => 0,
                'message' => 'Thành công.',
                'payUrl' => 'https://test-payment.momo.vn/v2/gateway/pay?token=TEST_MM_PAYURL',
            ], 200),
        ]);

        $order = $this->createOrder($this->user);

        $response = $this->actingAs($this->user)->postJson('/api/payments/momo/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order_url', 'https://test-payment.momo.vn/v2/gateway/pay?token=TEST_MM_PAYURL')
            ->assertJsonPath('data.payment_url', 'https://test-payment.momo.vn/v2/gateway/pay?token=TEST_MM_PAYURL')
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.grand_total', 10030000);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'momo',
            'status' => 'pending',
        ]);
    }

    public function test_idor_protection_prevents_paying_other_users_order(): void
    {
        $order = $this->createOrder($this->otherUser);

        $response = $this->actingAs($this->user)->postJson('/api/payments/momo/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $order = $this->createOrder($this->user, 'paid', 'confirmed');

        $response = $this->actingAs($this->user)->postJson('/api/payments/momo/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Đơn hàng này đã được thanh toán thành công.');
    }

    public function test_cannot_pay_cancelled_order(): void
    {
        $order = $this->createOrder($this->user, 'pending', 'cancelled');

        $response = $this->actingAs($this->user)->postJson('/api/payments/momo/create', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Không thể thanh toán cho đơn hàng đã bị hủy.');
    }
}
