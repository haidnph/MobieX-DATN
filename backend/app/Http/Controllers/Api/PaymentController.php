<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private readonly VNPayService $vnpayService
    ) {}

    /**
     * Generate VNPay Sandbox payment redirect URL for an order.
     */
    public function createVNPayPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'bank_code' => ['nullable', 'string', 'max:20'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        // Prevent IDOR: Order must belong to authenticated user
        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện thanh toán cho đơn hàng này.',
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn hàng này đã được thanh toán thành công.',
            ], 422);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Không thể thanh toán cho đơn hàng đã bị hủy.',
            ], 422);
        }

        // Record or find pending payment attempt
        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'vnpay',
                'status' => 'pending',
            ],
            [
                'transaction_code' => 'PAY-'.Str::upper(Str::random(16)),
                'idempotency_key' => 'vnpay_order_'.$order->id.'_'.uniqid(),
                'amount' => $order->total_amount,
                'currency_code' => 'VND',
            ]
        );

        $paymentUrl = $this->vnpayService->createPaymentUrl([
            'order_code' => $order->order_code,
            'amount' => $order->total_amount,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'bank_code' => $validated['bank_code'] ?? null,
        ]);

        return response()->json([
            'message' => 'Tạo URL thanh toán VNPay thành công.',
            'data' => [
                'payment_id' => $payment->id,
                'order_code' => $order->order_code,
                'amount' => (float) $order->total_amount,
                'payment_url' => $paymentUrl,
            ],
        ]);
    }

    /**
     * Handle user redirect back from VNPay payment portal.
     */
    public function vnpayReturn(Request $request): JsonResponse
    {
        $queryParams = $request->query();

        if (empty($queryParams) || ! $this->vnpayService->verifySignature($queryParams)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký số phản hồi từ VNPay không hợp lệ.',
            ], 400);
        }

        $orderCode = (string) $request->query('vnp_TxnRef');
        $responseCode = (string) $request->query('vnp_ResponseCode');
        $transactionNo = (string) $request->query('vnp_TransactionNo');
        $amount = ((float) $request->query('vnp_Amount', 0)) / 100;

        $isSuccess = ($responseCode === '00');

        return response()->json([
            'status' => $isSuccess ? 'success' : 'failed',
            'message' => $isSuccess ? 'Giao dịch thanh toán thành công.' : 'Giao dịch không thành công hoặc bị hủy.',
            'data' => [
                'order_code' => $orderCode,
                'response_code' => $responseCode,
                'transaction_no' => $transactionNo,
                'amount' => $amount,
            ],
        ], $isSuccess ? 200 : 400);
    }

    /**
     * Handle Server-to-Server IPN callback from VNPay.
     */
    public function vnpayIpn(Request $request): JsonResponse
    {
        $inputData = $request->query();

        // 1. Verify HMAC-SHA512 Signature
        if (empty($inputData) || ! $this->vnpayService->verifySignature($inputData)) {
            return response()->json([
                'RspCode' => '97',
                'Message' => 'Invalid Signature',
            ]);
        }

        $vnpTxnRef = (string) $request->query('vnp_TxnRef');
        $vnpAmount = (int) $request->query('vnp_Amount', 0);
        $vnpResponseCode = (string) $request->query('vnp_ResponseCode');
        $vnpTransactionNo = (string) $request->query('vnp_TransactionNo');

        // 2. Find Order
        $order = Order::where('order_code', $vnpTxnRef)->first();
        if (! $order) {
            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found',
            ]);
        }

        // 3. Verify Amount
        $expectedAmount = (int) round(((float) $order->total_amount) * 100);
        if ($expectedAmount !== $vnpAmount) {
            return response()->json([
                'RspCode' => '04',
                'Message' => 'Invalid Amount',
            ]);
        }

        // 4. Check if already confirmed/paid
        if ($order->payment_status === 'paid') {
            return response()->json([
                'RspCode' => '02',
                'Message' => 'Order already confirmed',
            ]);
        }

        // 5. Process Payment Update inside Database Transaction
        DB::transaction(function () use ($order, $inputData, $vnpResponseCode, $vnpTransactionNo) {
            // Find existing pending payment or create one
            $payment = Payment::where('order_id', $order->id)
                ->where('method', 'vnpay')
                ->lockForUpdate()
                ->first();

            $isSuccess = ($vnpResponseCode === '00');
            $status = $isSuccess ? 'success' : 'failed';

            if (! $payment) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'transaction_code' => 'PAY-'.Str::upper(Str::random(16)),
                    'idempotency_key' => 'vnpay_order_'.$order->id.'_'.uniqid(),
                    'method' => 'vnpay',
                    'amount' => $order->total_amount,
                    'currency_code' => 'VND',
                    'status' => $status,
                    'gateway_transaction_id' => $vnpTransactionNo,
                    'response_data' => $inputData,
                    'paid_at' => $isSuccess ? now() : null,
                ]);
            } else {
                $payment->update([
                    'status' => $status,
                    'gateway_transaction_id' => $vnpTransactionNo,
                    'response_data' => $inputData,
                    'paid_at' => $isSuccess ? now() : null,
                ]);
            }

            // Record Webhook Event for Audit & Idempotency
            PaymentWebhookEvent::firstOrCreate(
                [
                    'method' => 'vnpay',
                    'event_key' => $vnpTransactionNo ?: ('tx_'.$order->id.'_'.time()),
                ],
                [
                    'payment_id' => $payment->id,
                    'provider_reference' => $vnpTransactionNo,
                    'signature_verified' => true,
                    'processing_status' => $isSuccess ? 'processed' : 'failed',
                    'event_payload' => $inputData,
                    'processed_at' => now(),
                ]
            );

            if ($isSuccess) {
                $fromStatus = $order->status;
                $newStatus = ($fromStatus === 'pending') ? 'confirmed' : $fromStatus;

                $order->update([
                    'payment_status' => 'paid',
                    'status' => $newStatus,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'from_status' => $fromStatus,
                    'status' => $newStatus,
                    'note' => 'Thanh toán VNPay thành công (Mã GD: '.$vnpTransactionNo.')',
                    'created_at' => now(),
                ]);
            } else {
                $order->update([
                    'payment_status' => 'failed',
                ]);
            }
        });

        // 6. Return response to VNPay
        return response()->json([
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ]);
    }
}
