<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTransferCustomerReportTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private ShippingMethod $shipping;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['is_active' => true]);
        $this->shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STANDARD',
            'fee' => 0,
            'is_active' => true,
        ]);
    }

    private function createBankOrder(string $status = 'pending'): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'order_code' => 'BANK-REPORT-'.uniqid(),
            'shipping_method_id' => $this->shipping->id,
            'shipping_method_code' => $this->shipping->code,
            'shipping_method_name' => $this->shipping->name,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => $this->customer->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Phường Một',
            'shipping_address_line' => '12 Test Street',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'status' => $status,
        ]);
    }

    private function createPendingPayment(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'transaction_code' => 'PAY-BANK-'.uniqid(),
            'idempotency_key' => 'bank-report-'.$order->id,
            'method' => 'bank_transfer',
            'amount' => $order->total_amount,
            'currency_code' => 'VND',
            'status' => 'pending',
        ]);
    }

    public function test_customer_report_is_idempotent_and_does_not_mark_payment_paid(): void
    {
        $order = $this->createBankOrder();
        $payment = $this->createPendingPayment($order);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/bank-transfer/confirm-paid', ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.is_waiting_confirmation', true);

        $firstReportedAt = $payment->fresh()->response_data['customer_reported_at'];

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/bank-transfer/confirm-paid', ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.is_waiting_confirmation', true);

        $this->assertSame($firstReportedAt, $payment->fresh()->response_data['customer_reported_at']);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_customer_cannot_report_cancelled_order_or_without_pending_payment(): void
    {
        $cancelled = $this->createBankOrder('cancelled');
        $this->createPendingPayment($cancelled);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/bank-transfer/confirm-paid', ['order_id' => $cancelled->id])
            ->assertUnprocessable();

        $activeWithoutAttempt = $this->createBankOrder();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/bank-transfer/confirm-paid', ['order_id' => $activeWithoutAttempt->id])
            ->assertUnprocessable();

        $this->assertSame(0, OrderStatusHistory::where('order_id', $cancelled->id)->count());
        $this->assertSame(0, OrderStatusHistory::where('order_id', $activeWithoutAttempt->id)->count());
    }

    public function test_customer_cannot_report_another_users_bank_transfer(): void
    {
        $order = $this->createBankOrder();
        $otherCustomer = User::factory()->create(['is_active' => true]);
        $this->createPendingPayment($order);

        $this->actingAs($otherCustomer, 'sanctum')
            ->postJson('/api/payments/bank-transfer/confirm-paid', ['order_id' => $order->id])
            ->assertForbidden();

        $this->assertSame(0, OrderStatusHistory::where('order_id', $order->id)->count());
    }
}
