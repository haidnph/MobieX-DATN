<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRefundReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Order $order;

    private Payment $duplicateCapture;

    private Payment $originalCapture;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(
            ['slug' => 'orders.manage'],
            ['name' => 'Manage orders', 'description' => 'Manage orders and payment reconciliation'],
        );
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Admin', 'is_system' => true],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($role->id);

        $shipping = ShippingMethod::create([
            'name' => 'Standard', 'code' => 'STD-REFUND', 'fee' => 0, 'is_active' => true,
        ]);
        $this->order = Order::create([
            'order_code' => 'REFUND-'.uniqid(),
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
            'payment_method' => 'vnpay',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $this->originalCapture = Payment::create([
            'order_id' => $this->order->id,
            'transaction_code' => 'PAY-ORIGINAL-'.$this->order->id,
            'idempotency_key' => 'refund-original-'.$this->order->id,
            'method' => 'vnpay',
            'amount' => 250000,
            'currency_code' => 'VND',
            'status' => 'success',
            'gateway_transaction_id' => 'VNP-ORIGINAL-'.$this->order->id,
        ]);
        $this->duplicateCapture = Payment::create([
            'order_id' => $this->order->id,
            'transaction_code' => 'PAY-DUP-'.$this->order->id,
            'idempotency_key' => 'refund-duplicate-'.$this->order->id,
            'method' => 'vnpay',
            'amount' => 250000,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => 'VNP-DUP-'.$this->order->id,
            'refund_required' => true,
            'gateway_succeeded_at' => now(),
            'response_data' => ['duplicate_success_payload' => ['transaction' => 'capture']],
        ]);
    }

    public function test_admin_can_record_an_externally_completed_full_refund_idempotently(): void
    {
        $payload = [
            'refund_transaction_id' => 'VNP-REFUND-'.uniqid(),
            'refund_amount' => 250000,
            'refund_note' => 'Refund completed in VNPay merchant portal.',
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->duplicateCapture->id}/confirm-refund", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refund_required', false)
            ->assertJsonPath('data.refund_transaction_id', $payload['refund_transaction_id']);

        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame('success', $this->order->payments()->where('transaction_code', 'PAY-ORIGINAL-'.$this->order->id)->value('status'));
        $this->assertSame(1, $this->order->payments()->where('status', 'refunded')->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->duplicateCapture->id}/confirm-refund", $payload)
            ->assertOk();
    }

    public function test_admin_can_reconcile_a_normal_refund_and_cancel_the_refunded_order(): void
    {
        $this->duplicateCapture->delete();
        $payload = [
            'refund_transaction_id' => 'VNP-NORMAL-REFUND-'.uniqid(),
            'refund_amount' => 250000,
            'refund_note' => 'Refund completed in VNPay merchant portal.',
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->originalCapture->id}/confirm-refund", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $this->assertSame('refunded', $this->order->fresh()->payment_status);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$this->order->id}/status", [
                'status' => 'cancelled',
                'cancel_reason' => 'Customer cancellation after refund',
            ])
            ->assertOk();

        $this->assertSame('cancelled', $this->order->fresh()->status);
    }

    public function test_refund_reconciliation_requires_the_full_captured_amount(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->duplicateCapture->id}/confirm-refund", [
                'refund_transaction_id' => 'VNP-REFUND-PARTIAL',
                'refund_amount' => 100000,
                'refund_note' => 'Attempted partial refund.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_amount');

        $this->assertSame('pending', $this->duplicateCapture->fresh()->status);
        $this->assertTrue($this->duplicateCapture->fresh()->refund_required);
    }

    public function test_refund_transaction_reference_cannot_be_reused(): void
    {
        $reference = 'VNP-REFUND-UNIQUE-'.uniqid();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->duplicateCapture->id}/confirm-refund", [
                'refund_transaction_id' => $reference,
                'refund_amount' => 250000,
                'refund_note' => 'Refund completed in VNPay merchant portal.',
            ])
            ->assertOk();

        $secondCapture = Payment::create([
            'order_id' => $this->order->id,
            'transaction_code' => 'PAY-DUP-SECOND-'.$this->order->id,
            'idempotency_key' => 'refund-duplicate-second-'.$this->order->id,
            'method' => 'vnpay',
            'amount' => 250000,
            'currency_code' => 'VND',
            'status' => 'pending',
            'gateway_transaction_id' => 'VNP-DUP-SECOND-'.$this->order->id,
            'refund_required' => true,
            'gateway_succeeded_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$secondCapture->id}/confirm-refund", [
                'refund_transaction_id' => $reference,
                'refund_amount' => 250000,
                'refund_note' => 'This reference is already assigned.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_transaction_id');
    }

    public function test_refund_reconciliation_requires_order_management_permission(): void
    {
        $customer = User::factory()->create(['is_active' => true]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/admin/payments/{$this->duplicateCapture->id}/confirm-refund", [
                'refund_transaction_id' => 'VNP-REFUND-UNAUTH',
                'refund_amount' => 250000,
                'refund_note' => 'This request must be rejected.',
            ])
            ->assertForbidden();
    }

    public function test_manual_reconciliation_is_blocked_while_gateway_refund_status_is_ambiguous(): void
    {
        PaymentRefund::create([
            'payment_id' => $this->originalCapture->id,
            'requested_by' => $this->admin->id,
            'method' => 'vnpay',
            'request_id' => '01M8REFUNDAMBIGUOUS000000000',
            'amount' => $this->originalCapture->amount,
            'status' => 'unknown',
            'note' => 'Provider response was not received.',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->originalCapture->id}/confirm-refund", [
                'refund_transaction_id' => 'VNP-MANUAL-DURING-AUTO',
                'refund_amount' => 250000,
                'refund_note' => 'Cannot safely confirm while provider is unknown.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');

        $this->assertSame('success', $this->originalCapture->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }
}
