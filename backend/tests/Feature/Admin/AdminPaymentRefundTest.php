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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'momo.partner_code' => 'MOMO',
            'momo.access_key' => 'test-access',
            'momo.secret_key' => 'test-secret',
        ]);

        $permission = Permission::firstOrCreate(['slug' => 'orders.manage'], ['name' => 'Manage orders']);
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($role->id);

        $shipping = ShippingMethod::create([
            'name' => 'Standard', 'code' => 'STD-AUTO-REFUND', 'fee' => 0, 'is_active' => true,
        ]);
        $this->order = Order::create([
            'order_code' => 'AUTO-REFUND-'.uniqid(),
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
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'transaction_code' => 'PAY-REFUND-'.$this->order->id,
            'idempotency_key' => 'refund-'.$this->order->id,
            'method' => 'momo',
            'amount' => 250000,
            'currency_code' => 'VND',
            'status' => 'success',
            'gateway_transaction_id' => 'MOMO-ORDER-'.$this->order->id,
            'response_data' => ['transId' => '123456789'],
        ]);
    }

    public function test_momo_refund_is_finalized_only_after_query_confirms_success(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/refund/query')) {
                return Http::response([
                    'resultCode' => 0,
                    'refundTrans' => [[
                        'orderId' => $request->data()['orderId'],
                        'amount' => 250000,
                        'resultCode' => 0,
                        'transId' => 99887766,
                    ]],
                ]);
            }

            return Http::response(['resultCode' => 0, 'message' => 'Accepted']);
        });

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.provider_refund_id', '99887766');

        $this->assertSame('refunded', $this->payment->fresh()->status);
        $this->assertSame('refunded', $this->order->fresh()->payment_status);
        Http::assertSentCount(2);

        // Repeating the initiating request returns the existing refund and never
        // sends a second financial operation to the provider.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');
        Http::assertSentCount(2);
    }

    public function test_refund_history_migration_rollback_refuses_to_delete_existing_records(): void
    {
        $refund = PaymentRefund::create([
            'payment_id' => $this->payment->id,
            'method' => 'momo',
            'request_id' => 'refund-history-preservation-test',
            'amount' => 250000,
            'status' => 'succeeded',
            'note' => 'Preserve the audit record during rollback.',
        ]);
        $migration = require database_path('migrations/2026_11_23_000006_create_payment_refunds_table.php');

        try {
            $migration->down();
            $this->fail('The migration should refuse to remove non-empty payment refund history.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Cannot drop payment refund history while reconciliation records exist.',
                $exception->getMessage(),
            );
        }

        $this->assertModelExists($refund);
    }

    public function test_ambiguous_gateway_timeout_is_queried_without_resubmitting_refund(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/refund/query')) {
                return Http::response([
                    'resultCode' => 0,
                    'refundTrans' => [[
                        'orderId' => $request->data()['orderId'],
                        'amount' => 250000,
                        'resultCode' => 0,
                        'transId' => 88776655,
                    ]],
                ]);
            }

            return Http::response(['error' => 'gateway timeout'], 504);
        });

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'unknown');

        $this->assertSame('success', $this->payment->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        Http::assertSentCount(1);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertStatus(409);
        Http::assertSentCount(1);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        Http::assertSentCount(2);
        $this->assertSame('refunded', $this->payment->fresh()->status);
    }

    public function test_refund_request_requires_order_management_permission(): void
    {
        $customer = User::factory()->create(['is_active' => true]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertForbidden();

        $this->assertDatabaseCount('payment_refunds', 0);
    }

    public function test_missing_gateway_credentials_do_not_create_a_stuck_refund_attempt(): void
    {
        config(['momo.partner_code' => '', 'momo.access_key' => '', 'momo.secret_key' => '']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertStatus(503);

        $this->assertDatabaseCount('payment_refunds', 0);
        $this->assertSame('success', $this->payment->fresh()->status);
    }

    public function test_vnpay_request_uses_original_transaction_date_and_queries_final_status(): void
    {
        config(['vnpay.tmn_code' => 'DEMO1234', 'vnpay.hash_secret' => 'vnp-test-secret']);
        $this->order->update(['payment_method' => 'vnpay']);
        $this->payment->update([
            'method' => 'vnpay',
            'gateway_transaction_id' => '123456789',
            'response_data' => [
                'vnp_CreateDate' => '20261123101530',
                'vnp_TransactionNo' => '123456789',
            ],
        ]);
        $seenCreateDate = null;
        Http::fake(function ($request) use (&$seenCreateDate) {
            $body = $request->data();
            if (($body['vnp_Command'] ?? '') === 'refund') {
                $seenCreateDate = $body['vnp_TransactionDate'] ?? null;

                return Http::response($this->signedVnpResponse([
                    'vnp_ResponseId' => 'RESP-REFUND-1',
                    'vnp_Command' => 'refund',
                    'vnp_ResponseCode' => '00',
                    'vnp_Message' => 'Accepted',
                    'vnp_TmnCode' => 'DEMO1234',
                    'vnp_TxnRef' => $body['vnp_TxnRef'],
                    'vnp_Amount' => $body['vnp_Amount'],
                ]));
            }

            return Http::response($this->signedVnpResponse([
                'vnp_ResponseId' => 'RESP-QUERY-1',
                'vnp_Command' => 'querydr',
                'vnp_ResponseCode' => '00',
                'vnp_Message' => 'Success',
                'vnp_TmnCode' => 'DEMO1234',
                'vnp_TxnRef' => $body['vnp_TxnRef'],
                'vnp_Amount' => '25000000',
                'vnp_TransactionType' => '02',
                'vnp_TransactionStatus' => '00',
                'vnp_TransactionNo' => '998877665',
            ]));
        });

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.provider_refund_id', '998877665');

        $this->assertSame('20261123101530', $seenCreateDate);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ($request->data()['vnp_TransactionType'] ?? null) === '02'
            && isset($request->data()['vnp_SecureHash']));
    }

    public function test_zalopay_refund_acceptance_is_followed_by_status_query(): void
    {
        config(['zalopay.app_id' => '2553', 'zalopay.key1' => 'test-key1', 'zalopay.key2' => 'test-key2']);
        $this->order->update(['payment_method' => 'zalopay']);
        $this->payment->update([
            'method' => 'zalopay',
            'gateway_transaction_id' => '261123_1_abc123',
            'response_data' => ['zp_trans_id' => '998812345'],
        ]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/query_refund')) {
                return Http::response(['return_code' => 1, 'return_message' => 'success']);
            }

            return Http::response(['return_code' => 1, 'refund_id' => 787878]);
        });

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.provider_refund_id', '787878');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => isset($request->data()['mac'])
            && isset($request->data()['m_refund_id'])
            && isset($request->data()['zp_trans_id']));
    }

    public function test_vnpay_unsigned_success_query_does_not_mark_payment_refunded(): void
    {
        config(['vnpay.tmn_code' => 'DEMO1234', 'vnpay.hash_secret' => 'vnp-test-secret']);
        $this->order->update(['payment_method' => 'vnpay']);
        $this->payment->update([
            'method' => 'vnpay',
            'gateway_transaction_id' => '123456789',
            'response_data' => ['vnp_CreateDate' => '20261123101530', 'vnp_TransactionNo' => '123456789'],
        ]);
        Http::fake(function ($request) {
            $body = $request->data();
            if (($body['vnp_Command'] ?? '') === 'refund') {
                return Http::response($this->signedVnpResponse([
                    'vnp_ResponseId' => 'RESP-REFUND-2',
                    'vnp_Command' => 'refund',
                    'vnp_ResponseCode' => '00',
                    'vnp_Message' => 'Accepted',
                    'vnp_TmnCode' => 'DEMO1234',
                    'vnp_TxnRef' => $body['vnp_TxnRef'],
                    'vnp_Amount' => $body['vnp_Amount'],
                ]));
            }

            return Http::response([
                'vnp_TransactionType' => '02',
                'vnp_TransactionStatus' => '00',
                'vnp_TransactionNo' => '998877665',
            ]);
        });

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/refund", ['note' => 'Customer requested a full refund.'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'unknown');

        $this->assertSame('success', $this->payment->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    /** @param array<string, string> $response
     * @return array<string, string>
     */
    private function signedVnpResponse(array $response): array
    {
        $fields = [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef',
            'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
            'vnp_TransactionStatus', 'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount',
        ];
        $signed = implode('|', array_map(static fn (string $field): string => $response[$field] ?? '', $fields));
        $response['vnp_SecureHash'] = hash_hmac('sha512', $signed, 'vnp-test-secret');

        return $response;
    }
}
