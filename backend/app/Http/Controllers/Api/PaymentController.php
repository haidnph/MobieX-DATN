<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
