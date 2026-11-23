<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSimulationStateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ShippingMethod $shipping;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::create(['name' => 'Manage orders', 'slug' => 'orders.manage']);
        $role = Role::create([
            'name' => 'Order manager',
            'slug' => 'order-manager-payment-simulation-test',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($role->id);
        $this->shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
    }

    private function createOrder(string $method, string $status, string $paymentStatus): Order
    {
        return Order::create([
            'user_id' => $this->admin->id,
            'order_code' => 'SIM-'.strtoupper($method).'-'.uniqid(),
            'shipping_method_id' => $this->shipping->id,
            'shipping_method_code' => $this->shipping->code,
            'shipping_method_name' => $this->shipping->name,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => $this->admin->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Ward 1',
            'shipping_address_line' => '1 Test Street',
            'payment_method' => $method,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);
    }

    private function createPayment(Order $order, string $status): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'SIM-PAY-'.uniqid(),
            'idempotency_key' => 'sim-payment-'.$order->id,
            'method' => $order->payment_method,
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => $status,
        ]);
    }

    public function test_success_simulation_cannot_resurrect_expired_cancelled_orders(): void
    {
        foreach (['bank_transfer', 'momo'] as $method) {
            $order = $this->createOrder($method, 'cancelled', 'failed');
            $payment = $this->createPayment($order, 'cancelled');
            $endpoint = $method === 'momo'
                ? '/api/admin/payments/momo/simulate'
                : '/api/admin/payments/bank-transfer/simulate';
            $payload = $method === 'momo'
                ? ['order_id' => $order->id, 'action' => 'success']
                : ['order_id' => $order->id, 'action' => 'success'];

            $this->actingAs($this->admin, 'sanctum')
                ->postJson($endpoint, $payload)
                ->assertUnprocessable();

            $this->assertSame('cancelled', $order->fresh()->status);
            $this->assertSame('failed', $order->fresh()->payment_status);
            $this->assertSame('cancelled', $payment->fresh()->status);
        }
    }

    public function test_failed_simulation_cannot_downgrade_a_successful_payment(): void
    {
        foreach (['bank_transfer', 'momo'] as $method) {
            $order = $this->createOrder($method, 'confirmed', 'paid');
            $payment = $this->createPayment($order, 'success');
            $endpoint = $method === 'momo'
                ? '/api/admin/payments/momo/simulate'
                : '/api/admin/payments/bank-transfer/simulate';
            $payload = $method === 'momo'
                ? ['order_id' => $order->id, 'action' => 'failed']
                : ['order_id' => $order->id, 'action' => 'failed'];

            $this->actingAs($this->admin, 'sanctum')
                ->postJson($endpoint, $payload)
                ->assertUnprocessable();

            $this->assertSame('paid', $order->fresh()->payment_status);
            $this->assertSame('success', $payment->fresh()->status);
        }
    }

}
