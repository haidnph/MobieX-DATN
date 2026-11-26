<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\FlashSaleOrderService;
use App\Services\MoMoService;
use App\Services\OrderMailService;
use App\Services\VNPayService;
use App\Services\ZaloPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private readonly VNPayService $vnpayService,
        private readonly ZaloPayService $zalopayService,
        private readonly MoMoService $momoService
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

        if ($order->payment_method !== 'vnpay') {
            return response()->json([
                'message' => 'Phương thức thanh toán của đơn hàng không phải VNPay.',
            ], 422);
        }

        if (! $this->vnpayService->isConfigured()) {
            return response()->json(['message' => 'VNPay chưa được cấu hình thông tin merchant.'], 503);
        }

        $paymentWindow = $this->paymentWindowSeconds($order);
        if ($paymentWindow === null) {
            return response()->json(['message' => 'Thời hạn giữ hàng không đủ để bắt đầu thanh toán. Hãy đặt đơn hàng mới.'], 422);
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

        $responseData = $payment->response_data ?? [];
        $createDate = (string) ($responseData['vnp_CreateDate'] ?? now('Asia/Ho_Chi_Minh')->format('YmdHis'));
        $paymentUrl = $this->vnpayService->createPaymentUrl([
            'order_code' => $payment->transaction_code,
            'amount' => $order->total_amount,
            'create_date' => $createDate,
            'expires_at' => now()->addSeconds(min(1200, $paymentWindow)),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'bank_code' => $validated['bank_code'] ?? null,
        ]);
        if (empty($responseData['vnp_CreateDate'])) {
            $responseData['vnp_CreateDate'] = $createDate;
            $payment->update(['response_data' => $responseData]);
        }

        return response()->json([
            'message' => 'Tạo URL thanh toán VNPay thành công.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'amount' => (float) $order->total_amount,
                'payment_url' => $paymentUrl,
                'order' => [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'grand_total' => (float) $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
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

        $transactionReference = (string) $request->query('vnp_TxnRef');
        $responseCode = (string) $request->query('vnp_ResponseCode');
        $transactionNo = (string) $request->query('vnp_TransactionNo');
        $amount = ((float) $request->query('vnp_Amount', 0)) / 100;

        $isSuccess = ($responseCode === '00');
        $payment = Payment::query()
            ->where('method', 'vnpay')
            ->where('transaction_code', $transactionReference)
            ->with('order')
            ->first();
        $order = $payment?->order;

        if (! $order) {
            $order = Order::where('order_code', $transactionReference)->first();
            $payment = $order
                ? Payment::where('order_id', $order->id)->where('method', 'vnpay')->first()
                : null;
        }

        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if (! $payment) {
            return response()->json(['status' => 'error', 'message' => 'VNPay payment attempt not found.'], 404);
        }

        $expectedAmount = (int) round((float) $order->total_amount * 100);
        if ($order->payment_method !== 'vnpay' || $expectedAmount !== (int) $request->query('vnp_Amount', 0)) {
            return response()->json(['status' => 'error', 'message' => 'Thông tin thanh toán không khớp đơn hàng.'], 422);
        }

        // Return URLs are browser callbacks. Only the signed server-to-server IPN
        // is allowed to change the order/payment state.
        $isConfirmed = $order->payment_status === 'paid';
        $returnStatus = $isSuccess
            ? ($isConfirmed ? 'success' : 'pending_confirmation')
            : 'failed';

        return response()->json([
            'status' => $returnStatus,
            'message' => $isConfirmed
                ? 'Thanh toán đã được hệ thống xác nhận.'
                : ($isSuccess ? 'VNPay đã nhận giao dịch. Đơn hàng đang chờ hệ thống đối soát.' : 'Giao dịch không thành công hoặc bị hủy.'),
            'data' => [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'response_code' => $responseCode,
                'transaction_no' => $transactionNo,
                'amount' => $amount,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
                'verification_status' => $returnStatus,
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
        $paymentByReference = Payment::query()
            ->where('method', 'vnpay')
            ->where('transaction_code', $vnpTxnRef)
            ->with('order')
            ->first();
        $order = $paymentByReference?->order;

        // Keep callbacks created before unique provider references working.
        if (! $order) {
            $order = Order::where('order_code', $vnpTxnRef)->first();
            $paymentByReference = $order
                ? Payment::where('order_id', $order->id)->where('method', 'vnpay')->orderByDesc('id')->first()
                : null;
        }
        if (! $order) {
            return response()->json([
                'RspCode' => '01',
                'Message' => 'Order not found',
            ]);
        }

        if ($order->payment_method !== 'vnpay') {
            return response()->json([
                'RspCode' => '04',
                'Message' => 'Invalid payment method',
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

        // 5. Process Payment Update inside Database Transaction
        $callbackResult = DB::transaction(function () use ($order, $inputData, $vnpResponseCode, $vnpTransactionNo, $paymentByReference) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $paymentByReference
                ? Payment::query()->whereKey($paymentByReference->id)->lockForUpdate()->first()
                : Payment::query()->where('order_id', $order->id)->where('method', 'vnpay')->lockForUpdate()->first();

            $isSuccess = ($vnpResponseCode === '00');
            if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
                if ($isSuccess && $payment && $payment->status !== 'success') {
                    $this->recordDuplicateGatewaySuccess(
                        $order,
                        $payment,
                        'vnpay',
                        $vnpTransactionNo ?: $payment->gateway_transaction_id ?: $payment->transaction_code,
                        $vnpTransactionNo ?: $payment->transaction_code,
                        $inputData,
                    );

                    return 'duplicate_capture';
                }

                return $order->payment_status === 'paid' ? 'already_paid' : 'cancelled';
            }

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
                    'response_data' => array_merge($payment->response_data ?? [], $inputData),
                    'paid_at' => $isSuccess ? now() : null,
                ]);
            } else {
                $payment->update([
                    'status' => $status,
                    'gateway_transaction_id' => $vnpTransactionNo,
                    'response_data' => array_merge($payment->response_data ?? [], $inputData),
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

                $this->markCouponAsConsumed($order);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'from_status' => $fromStatus,
                    'status' => $newStatus,
                    'note' => 'Thanh toán VNPay thành công (Mã GD: '.$vnpTransactionNo.')',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    app(OrderMailService::class)->sendOrderConfirmation($order);
                });
            } else {
                $order->update([
                    'payment_status' => 'failed',
                ]);
            }

            return 'processed';
        });

        // 6. Return response to VNPay
        if ($callbackResult === 'already_paid') {
            return response()->json([
                'RspCode' => '02',
                'Message' => 'Order already confirmed',
            ]);
        }

        return response()->json([
            'RspCode' => '00',
            'Message' => 'Confirm Success',
        ]);
    }

    /**
     * Generate ZaloPay Sandbox payment redirect URL for an order.
     */
    public function createZaloPayPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'bank_code' => ['nullable', 'string', 'max:20'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

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

        if ($order->payment_method !== 'zalopay') {
            return response()->json([
                'message' => 'Phương thức thanh toán của đơn hàng không phải ZaloPay.',
            ], 422);
        }

        if (! $this->zalopayService->isConfigured()) {
            return response()->json(['message' => 'ZaloPay chưa được cấu hình thông tin merchant.'], 503);
        }

        $paymentWindow = $this->paymentWindowSeconds($order);
        if ($paymentWindow === null) {
            return response()->json(['message' => 'Thời hạn giữ hàng không đủ để bắt đầu thanh toán. Hãy đặt đơn hàng mới.'], 422);
        }

        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'zalopay',
                'status' => 'pending',
            ],
            [
                'transaction_code' => 'PAY-ZP-'.Str::upper(Str::random(14)),
                'idempotency_key' => 'zalopay_order_'.$order->id.'_'.uniqid(),
                'amount' => $order->total_amount,
                'currency_code' => 'VND',
            ]
        );

        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'itemid' => (string) $item->product_id,
                'itemname' => $item->product_name,
                'itemprice' => (int) $item->price,
                'itemquantity' => (int) $item->quantity,
            ];
        }

        $savedCheckout = $payment->response_data ?? [];
        $zpResult = ! empty($savedCheckout['order_url'])
            ? [
                'status' => 'success',
                'order_url' => $savedCheckout['order_url'],
                'zp_trans_token' => $savedCheckout['zp_trans_token'] ?? null,
                'app_trans_id' => $payment->gateway_transaction_id,
            ]
            : $this->zalopayService->createPaymentOrder([
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_reference' => $payment->transaction_code,
                'amount' => $order->total_amount,
                'app_user' => (string) $request->user()->id,
                'items' => $items,
                'bank_code' => $validated['bank_code'] ?? null,
                'expires_in_seconds' => $paymentWindow,
            ]);

        if (! empty($zpResult['app_trans_id'])) {
            $payment->update([
                'gateway_transaction_id' => $zpResult['app_trans_id'],
            ]);
        }

        if (($zpResult['status'] ?? '') === 'failed' || empty($zpResult['order_url'])) {
            return response()->json([
                'message' => $zpResult['sub_return_message'] ?? $zpResult['return_message'] ?? 'Không thể khởi tạo cổng thanh toán ZaloPay.',
                'error' => $zpResult['return_message'] ?? 'Lỗi kết nối cổng thanh toán ZaloPay',
            ], ($zpResult['error_code'] ?? null) === 'provider_unreachable' ? 503 : 422);
        }

        if (empty($savedCheckout['order_url'])) {
            $payment->update(['response_data' => [
                'order_url' => $zpResult['order_url'],
                'zp_trans_token' => $zpResult['zp_trans_token'] ?? null,
                'checkout_created_at' => now()->toIso8601String(),
            ]]);
        }

        return response()->json([
            'message' => 'Tạo đơn thanh toán ZaloPay thành công.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'amount' => (float) $order->total_amount,
                'order_url' => $zpResult['order_url'],
                'payment_url' => $zpResult['order_url'],
                'zp_trans_token' => $zpResult['zp_trans_token'] ?? null,
                'app_trans_id' => $zpResult['app_trans_id'] ?? null,
                'order' => [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'grand_total' => (float) $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ],
        ]);
    }

    /**
     * Handle return redirect from ZaloPay (Customer Browser).
     * Performs dual-confirmation with HMAC-SHA256 checksum verification.
     */
    public function zalopayReturn(Request $request): JsonResponse
    {
        $queryParams = $request->query();

        if (empty($queryParams) || ! $this->zalopayService->verifyReturnChecksum($queryParams)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký số xác thực từ ZaloPay không hợp lệ.',
            ], 400);
        }

        $appTransId = (string) $request->query('apptransid', '');
        $status = (int) $request->query('status', 0);
        $amount = ((float) $request->query('amount', 0));
        $bankCode = (string) $request->query('bankcode', 'ZALOPAY');
        $isSuccess = ($status === 1);

        // app_trans_id is generated as YYMMDD_<order_id>_<suffix>.
        $order = null;
        if (preg_match('/^\d{6}_(\d+)_([a-f0-9]{6})$/i', $appTransId, $matches)) {
            $order = Order::find((int) $matches[1]);
        }

        $payment = $order
            ? Payment::where('order_id', $order->id)->where('method', 'zalopay')->where('gateway_transaction_id', $appTransId)->first()
            : null;
        if ($isSuccess && (
            ! $order
            || $order->payment_method !== 'zalopay'
            || abs((float) $order->total_amount - $amount) >= 0.01
            || ! $payment
            || ! hash_equals((string) $payment->gateway_transaction_id, $appTransId)
        )) {
            return response()->json(['status' => 'error', 'message' => 'Thông tin giao dịch ZaloPay không khớp đơn hàng.'], 422);
        }

        $isConfirmed = $order?->payment_status === 'paid';
        $returnStatus = $isSuccess
            ? ($isConfirmed ? 'success' : 'pending_confirmation')
            : 'failed';

        return response()->json([
            'status' => $returnStatus,
            'message' => $isConfirmed
                ? 'Thanh toán đã được hệ thống xác nhận.'
                : ($isSuccess ? 'ZaloPay đã nhận giao dịch. Đơn hàng đang chờ hệ thống đối soát.' : 'Giao dịch ZaloPay không thành công hoặc bị hủy.'),
            'data' => [
                'order_id' => $order?->id,
                'order_code' => $order?->order_code,
                'app_trans_id' => $appTransId,
                'status' => $status,
                'amount' => $amount,
                'bank_code' => $bankCode,
                'payment_status' => $order?->payment_status,
                'order_status' => $order?->status,
                'verification_status' => $returnStatus,
            ],
        ], $isSuccess ? 200 : 400);
    }

    /**
     * Handle Server-to-Server Webhook Callback from ZaloPay.
     */
    public function zalopayCallback(Request $request): JsonResponse
    {
        $dataJson = (string) $request->input('data', '');
        $mac = (string) $request->input('mac', '');

        if (empty($dataJson) || empty($mac) || ! $this->zalopayService->verifyCallback($dataJson, $mac)) {
            return response()->json([
                'return_code' => -1,
                'return_message' => 'mac not equal',
            ]);
        }

        $dataArr = json_decode($dataJson, true);
        if (! is_array($dataArr)) {
            return response()->json([
                'return_code' => 0,
                'return_message' => 'invalid data format',
            ]);
        }

        $appTransId = (string) ($dataArr['app_trans_id'] ?? '');
        $zpTransId = (string) ($dataArr['zp_trans_id'] ?? '');

        // app_trans_id is generated as YYMMDD_<order_id>_<suffix>.
        $order = null;
        if (preg_match('/^\d{6}_(\d+)_([a-f0-9]{6})$/i', $appTransId, $matches)) {
            $order = Order::whereKey((int) $matches[1])->lockForUpdate()->first();
        }

        if (! $order) {
            return response()->json([
                'return_code' => 0,
                'return_message' => 'order not found',
            ]);
        }

        $callbackAmount = (int) ($dataArr['amount'] ?? 0);
        $payment = Payment::where('order_id', $order->id)
            ->where('method', 'zalopay')
            ->where('gateway_transaction_id', $appTransId)
            ->first();
        if (
            $order->payment_method !== 'zalopay'
            || $callbackAmount !== (int) round((float) $order->total_amount)
            || ! $payment
            || ! hash_equals((string) $payment->gateway_transaction_id, $appTransId)
        ) {
            return response()->json([
                'return_code' => 2,
                'return_message' => 'Payment details do not match the order.',
            ]);
        }

        DB::transaction(function () use ($order, $dataArr, $appTransId, $zpTransId, $payment) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
                if ($payment->status !== 'success') {
                    $this->recordDuplicateGatewaySuccess(
                        $order,
                        $payment,
                        'zalopay',
                        $zpTransId ?: $appTransId,
                        $zpTransId ?: $appTransId,
                        $dataArr,
                    );
                }

                return;
            }

            $payment->update([
                'status' => 'success',
                'gateway_transaction_id' => $appTransId,
                'response_data' => $dataArr,
                'paid_at' => now(),
            ]);

            PaymentWebhookEvent::firstOrCreate(
                [
                    'method' => 'zalopay',
                    'event_key' => $zpTransId ?: $appTransId,
                ],
                [
                    'payment_id' => $payment->id,
                    'provider_reference' => $zpTransId ?: $appTransId,
                    'signature_verified' => true,
                    'processing_status' => 'processed',
                    'event_payload' => $dataArr,
                    'processed_at' => now(),
                ]
            );

            $fromStatus = $order->status;
            $newStatus = ($fromStatus === 'pending') ? 'confirmed' : $fromStatus;

            $order->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
            ]);

            $this->markCouponAsConsumed($order);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => null,
                'from_status' => $fromStatus,
                'status' => $newStatus,
                'note' => 'Thanh toán ZaloPay thành công (Webhook - Mã GD: '.$zpTransId.')',
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($order) {
                app(OrderMailService::class)->sendOrderConfirmation($order);
            });
        });

        return response()->json([
            'return_code' => 1,
            'return_message' => 'success',
        ]);
    }

    /**
     * Generate MoMo Sandbox payment redirect URL for an order.
     */
    public function createMoMoPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

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

        if ($order->payment_method !== 'momo') {
            return response()->json([
                'message' => 'Phương thức thanh toán của đơn hàng không phải Ví MoMo.',
            ], 422);
        }

        if (! $this->momoService->isConfigured()) {
            return response()->json(['message' => 'MoMo chưa được cấu hình thông tin merchant.'], 503);
        }

        $paymentWindow = $this->paymentWindowSeconds($order);
        if ($paymentWindow === null) {
            return response()->json(['message' => 'Thời hạn giữ hàng không đủ để bắt đầu thanh toán. Hãy đặt đơn hàng mới.'], 422);
        }

        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'momo',
                'status' => 'pending',
            ],
            [
                'transaction_code' => 'PAY-MM-'.Str::upper(Str::random(14)),
                'idempotency_key' => 'momo_order_'.$order->id.'_'.uniqid(),
                'amount' => $order->total_amount,
                'currency_code' => 'VND',
            ]
        );

        $savedCheckout = $payment->response_data ?? [];
        $mmResult = ! empty($savedCheckout['order_url'])
            ? [
                'status' => 'success',
                'order_url' => $savedCheckout['order_url'],
                'qr_code_url' => $savedCheckout['qr_code_url'] ?? null,
                'deeplink' => $savedCheckout['deeplink'] ?? null,
                'order_id' => $payment->gateway_transaction_id,
                'request_id' => $savedCheckout['request_id'] ?? null,
            ]
            : $this->momoService->createPaymentOrder([
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_reference' => $payment->transaction_code,
                'amount' => $order->total_amount,
                'order_info' => 'MobieX - Thanh toán đơn hàng '.$order->order_code,
                'expires_in_minutes' => min(30, intdiv($paymentWindow, 60)),
            ]);

        if (! empty($mmResult['order_id'])) {
            $payment->update([
                'gateway_transaction_id' => $mmResult['order_id'],
            ]);
        }

        if (
            ($mmResult['status'] ?? '') === 'failed'
            || (empty($mmResult['order_url']) && empty($mmResult['qr_code_url']) && empty($mmResult['deeplink']))
        ) {
            return response()->json([
                'message' => $mmResult['message'] ?? 'Không thể khởi tạo cổng thanh toán MoMo.',
                'error' => $mmResult['message'] ?? 'Lỗi kết nối cổng thanh toán MoMo',
            ], ($mmResult['error_code'] ?? null) === 'provider_unreachable' ? 503 : 422);
        }

        if (empty($savedCheckout['order_url']) && ! empty($mmResult['order_url'])) {
            $payment->update(['response_data' => [
                'order_url' => $mmResult['order_url'],
                'qr_code_url' => $mmResult['qr_code_url'] ?? null,
                'deeplink' => $mmResult['deeplink'] ?? null,
                'request_id' => $mmResult['request_id'] ?? null,
                'checkout_created_at' => now()->toIso8601String(),
            ]]);
        }

        $momoConfig = config('payment_accounts.momo');
        $momoPhone = $momoConfig['phone'];
        $momoName = $momoConfig['name'];

        return response()->json([
            'message' => 'Tạo đơn thanh toán MoMo thành công.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'amount' => (float) $order->total_amount,
                'order_url' => $mmResult['order_url'] ?? null,
                'payment_url' => $mmResult['order_url'] ?? null,
                'qr_code_url' => $mmResult['qr_code_url'] ?? null,
                'deeplink' => $mmResult['deeplink'] ?? null,
                'momo_phone' => $momoPhone,
                'momo_name' => $momoName,
                'transfer_content' => $order->order_code,
                'order' => [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'grand_total' => (float) $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ],
        ]);
    }

    /**
     * Handle return redirect from MoMo (Customer Browser).
     * Performs dual-confirmation with HMAC-SHA256 signature verification.
     */
    public function momoReturn(Request $request): JsonResponse
    {
        $queryParams = $request->query();

        if (empty($queryParams) || ! $this->momoService->verifyReturnSignature($queryParams)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký số xác thực từ MoMo không hợp lệ.',
            ], 400);
        }

        $orderIdParam = (string) $request->query('orderId', '');
        $resultCode = (int) $request->query('resultCode', -1);
        $amount = (float) $request->query('amount', 0);
        $transId = (string) $request->query('transId', '');
        $payType = (string) $request->query('payType', 'momo');
        $isSuccess = ($resultCode === 0);

        // Find order by parsing orderIdParam: MX_MM_<order_id>_<suffix>
        $order = null;
        if (! empty($orderIdParam)) {
            $parts = explode('_', $orderIdParam);
            if (count($parts) >= 3 && is_numeric($parts[2])) {
                $order = Order::find((int) $parts[2]);
            }
        }
        $payment = $order
            ? Payment::where('order_id', $order->id)->where('method', 'momo')->where('gateway_transaction_id', $orderIdParam)->first()
            : null;
        if ($isSuccess && (
            ! $order ||
            $order->payment_method !== 'momo' ||
            abs((float) $order->total_amount - $amount) >= 0.01 ||
            ! $payment ||
            ! hash_equals((string) $payment->gateway_transaction_id, $orderIdParam)
        )) {
            return response()->json(['status' => 'error', 'message' => 'Thông tin giao dịch MoMo không khớp đơn hàng.'], 422);
        }

        $isConfirmed = $order?->payment_status === 'paid';
        $returnStatus = $isSuccess
            ? ($isConfirmed ? 'success' : 'pending_confirmation')
            : 'failed';

        return response()->json([
            'status' => $returnStatus,
            'message' => $isConfirmed
                ? 'Thanh toán đã được hệ thống xác nhận.'
                : ($isSuccess ? 'MoMo đã nhận giao dịch. Đơn hàng đang chờ hệ thống đối soát.' : 'Giao dịch MoMo không thành công hoặc bị hủy.'),
            'data' => [
                'order_id' => $order?->id,
                'order_code' => $order?->order_code,
                'trans_id' => $transId,
                'order_id_param' => $orderIdParam,
                'result_code' => $resultCode,
                'amount' => $amount,
                'pay_type' => $payType,
                'payment_status' => $order?->payment_status,
                'order_status' => $order?->status,
                'verification_status' => $returnStatus,
            ],
        ], $isSuccess ? 200 : 400);
    }

    /**
     * Handle Server-to-Server Webhook Callback from MoMo (IPN).
     */
    public function momoCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload) || ! $this->momoService->verifyCallbackSignature($payload)) {
            return response()->json([
                'resultCode' => 97,
                'message' => 'Invalid signature',
            ], 400);
        }

        $orderIdParam = (string) ($payload['orderId'] ?? '');
        $resultCode = (int) ($payload['resultCode'] ?? -1);
        $transId = (string) ($payload['transId'] ?? '');

        // Find Order
        $order = null;
        if (! empty($orderIdParam)) {
            $parts = explode('_', $orderIdParam);
            if (count($parts) >= 3 && is_numeric($parts[2])) {
                $order = Order::find((int) $parts[2]);
            }
        }

        if (! $order) {
            return response()->json([
                'resultCode' => 11,
                'message' => 'Order not found',
            ]);
        }

        $callbackAmount = (float) ($payload['amount'] ?? 0);
        $payment = Payment::where('order_id', $order->id)->where('method', 'momo')->where('gateway_transaction_id', $orderIdParam)->first();
        if (
            $order->payment_method !== 'momo' ||
            abs((float) $order->total_amount - $callbackAmount) >= 0.01 ||
            ! $payment ||
            ! hash_equals((string) $payment->gateway_transaction_id, $orderIdParam)
        ) {
            return response()->json(['resultCode' => 99, 'message' => 'Payment details do not match the order.'], 400);
        }

        if ($resultCode === 0) {
            DB::transaction(function () use ($order, $payload, $transId, $orderIdParam, $payment) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
                    if ($payment->status !== 'success') {
                        $this->recordDuplicateGatewaySuccess(
                            $order,
                            $payment,
                            'momo',
                            $transId ?: $orderIdParam,
                            $transId ?: $orderIdParam,
                            $payload,
                        );
                    }

                    return;
                }

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $orderIdParam,
                    'response_data' => $payload,
                    'paid_at' => now(),
                ]);

                PaymentWebhookEvent::firstOrCreate(
                    [
                        'method' => 'momo',
                        'event_key' => $transId ?: $orderIdParam,
                    ],
                    [
                        'payment_id' => $payment->id,
                        'provider_reference' => $transId ?: $orderIdParam,
                        'signature_verified' => true,
                        'processing_status' => 'processed',
                        'event_payload' => $payload,
                        'processed_at' => now(),
                    ]
                );

                $fromStatus = $order->status;
                $newStatus = ($fromStatus === 'pending') ? 'confirmed' : $fromStatus;

                $order->update([
                    'payment_status' => 'paid',
                    'status' => $newStatus,
                ]);

                $this->markCouponAsConsumed($order);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'from_status' => $fromStatus,
                    'status' => $newStatus,
                    'note' => 'Thanh toán MoMo thành công (Webhook - Mã GD: '.$transId.')',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    app(OrderMailService::class)->sendOrderConfirmation($order);
                });
            });
        } else {
            DB::transaction(function () use ($order, $payload, $transId, $orderIdParam, $resultCode, $payment) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->payment_status === 'paid') {
                    return;
                }

                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $payment->update([
                    'status' => 'failed',
                    'gateway_transaction_id' => $orderIdParam,
                    'response_data' => $payload,
                    'paid_at' => null,
                ]);

                PaymentWebhookEvent::firstOrCreate(
                    [
                        'method' => 'momo',
                        'event_key' => $transId ?: $orderIdParam,
                    ],
                    [
                        'payment_id' => $payment->id,
                        'provider_reference' => $transId ?: $orderIdParam,
                        'signature_verified' => true,
                        'processing_status' => 'failed',
                        'event_payload' => $payload,
                        'error_message' => 'MoMo reported unsuccessful payment (resultCode '.$resultCode.').',
                        'processed_at' => now(),
                    ]
                );

                $order->update(['payment_status' => 'failed']);
            });
        }

        return response()->json([
            'resultCode' => 0,
            'message' => 'success',
        ]);
    }

    /**
     * Actively verify an order's online payment status against the configured Payment Gateway
     * server-to-server (handles delayed webhooks and local sandbox verification securely).
     */
    public function verifyOrderPaymentStatus(Request $request, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền kiểm tra thanh toán cho đơn hàng này.',
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return $this->paymentStatusResponse($order, true, 'Thanh toán đã được hệ thống xác nhận.');
        }

        if ($order->status === 'cancelled') {
            return $this->paymentStatusResponse($order, false, 'Đơn hàng đã bị hủy.');
        }

        if (! in_array($order->payment_method, ['vnpay', 'zalopay', 'momo'], true)) {
            return $this->paymentStatusResponse($order, false, 'Trạng thái đơn hàng hiện tại.');
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('method', $order->payment_method)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if (! $payment) {
            return $this->paymentStatusResponse(
                $order,
                false,
                $order->payment_status === 'failed'
                    ? 'Giao dịch gần nhất không thành công. Bạn có thể thử thanh toán lại.'
                    : 'Đang chờ xác nhận từ cổng thanh toán.'
            );
        }

        $gatewayVerified = false;

        if ($order->payment_method === 'vnpay') {
            $txnDate = (string) (($payment->response_data ?? [])['vnp_CreateDate']
                ?? $order->created_at?->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis')
                ?? now('Asia/Ho_Chi_Minh')->format('YmdHis'));

            $res = $this->vnpayService->queryTransactionStatus([
                'request_id' => 'QDR-'.$payment->id.'-'.Str::upper(Str::random(6)),
                'transaction_reference' => $payment->transaction_code,
                'transaction_date' => $txnDate,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'description' => 'Verify payment '.$order->order_code,
            ]);

            $expectedAmount = (int) round((float) $order->total_amount * 100);
            if (
                empty($res['transport_error'])
                && $this->vnpayService->verifyTransactionApiResponse($res)
                && (string) ($res['vnp_ResponseCode'] ?? '') === '00'
                && (string) ($res['vnp_TransactionStatus'] ?? '') === '00'
                && (string) ($res['vnp_TxnRef'] ?? '') === (string) $payment->transaction_code
                && (int) ($res['vnp_Amount'] ?? 0) === $expectedAmount
            ) {
                $vnpTxNo = (string) ($res['vnp_TransactionNo'] ?? $payment->transaction_code);
                $this->settleVerifiedGatewayPayment(
                    $order,
                    $payment,
                    'vnpay',
                    $vnpTxNo,
                    $vnpTxNo,
                    $vnpTxNo,
                    $res,
                    'Thanh toán VNPay thành công (Đối soát cổng - Mã GD: '.$vnpTxNo.')'
                );
                $gatewayVerified = true;
            }
        } elseif ($order->payment_method === 'zalopay' && ! empty($payment->gateway_transaction_id)) {
            $res = $this->zalopayService->queryOrderStatus((string) $payment->gateway_transaction_id);
            $expectedAmount = (int) round((float) $order->total_amount);

            if (
                is_array($res)
                && (int) ($res['return_code'] ?? 0) === 1
                && (int) ($res['amount'] ?? 0) === $expectedAmount
            ) {
                $zpTxId = (string) ($res['zp_trans_id'] ?? $payment->gateway_transaction_id);
                $this->settleVerifiedGatewayPayment(
                    $order,
                    $payment,
                    'zalopay',
                    (string) $payment->gateway_transaction_id,
                    $zpTxId,
                    $zpTxId,
                    $res,
                    'Thanh toán ZaloPay thành công (Đối soát cổng - Mã GD: '.$zpTxId.')'
                );
                $gatewayVerified = true;
            }
        } elseif ($order->payment_method === 'momo' && ! empty($payment->gateway_transaction_id)) {
            $requestId = (string) (($payment->response_data ?? [])['request_id']
                ?? ('REQ_MM_'.substr((string) $payment->gateway_transaction_id, 6)));

            $res = $this->momoService->queryOrderStatus((string) $payment->gateway_transaction_id, $requestId);

            if (
                is_array($res)
                && empty($res['transport_error'])
                && (int) ($res['resultCode'] ?? -1) === 0
                && abs((float) ($res['amount'] ?? 0) - (float) $order->total_amount) < 0.01
                && (string) ($res['orderId'] ?? $payment->gateway_transaction_id) === (string) $payment->gateway_transaction_id
            ) {
                $momoTxId = (string) ($res['transId'] ?? $payment->gateway_transaction_id);
                $this->settleVerifiedGatewayPayment(
                    $order,
                    $payment,
                    'momo',
                    (string) $payment->gateway_transaction_id,
                    $momoTxId,
                    $momoTxId,
                    $res,
                    'Thanh toán MoMo thành công (Đối soát cổng - Mã GD: '.$momoTxId.')'
                );
                $gatewayVerified = true;
            }
        }

        $order->refresh();

        return $this->paymentStatusResponse(
            $order,
            $gatewayVerified || $order->payment_status === 'paid',
            $order->payment_status === 'paid'
                ? 'Thanh toán đã được xác nhận từ cổng thanh toán.'
                : 'Chưa nhận được xác nhận hoàn tất từ cổng thanh toán. Hệ thống vẫn đang giữ đơn cho bạn.'
        );
    }

    private function paymentStatusResponse(Order $order, bool $gatewayVerified, string $message): JsonResponse
    {
        return response()->json([
            'status' => $order->payment_status === 'paid'
                ? 'paid'
                : ($order->payment_status === 'failed' ? 'failed' : 'pending'),
            'message' => $message,
            'data' => [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'gateway_verified' => $gatewayVerified,
            ],
        ]);
    }

    /**
     * Atomically settle a verified gateway transaction inside a lock.
     *
     * @param  array<string, mixed>  $gatewayPayload
     */
    private function settleVerifiedGatewayPayment(
        Order $order,
        Payment $payment,
        string $method,
        string $paymentGatewayTxId,
        string $eventKey,
        string $providerReference,
        array $gatewayPayload,
        string $historyNote
    ): void {
        DB::transaction(function () use (
            $order,
            $payment,
            $method,
            $paymentGatewayTxId,
            $eventKey,
            $providerReference,
            $gatewayPayload,
            $historyNote
        ): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === 'paid' || $lockedOrder->status === 'cancelled') {
                if ($lockedPayment->status !== 'success') {
                    $this->recordDuplicateGatewaySuccess(
                        $lockedOrder,
                        $lockedPayment,
                        $method,
                        $eventKey,
                        $providerReference,
                        $gatewayPayload,
                    );
                }

                return;
            }

            $lockedPayment->update([
                'status' => 'success',
                'gateway_transaction_id' => $paymentGatewayTxId,
                'response_data' => array_merge($lockedPayment->response_data ?? [], $gatewayPayload),
                'paid_at' => now(),
            ]);

            PaymentWebhookEvent::firstOrCreate(
                [
                    'method' => $method,
                    'event_key' => $eventKey,
                ],
                [
                    'payment_id' => $lockedPayment->id,
                    'provider_reference' => $providerReference,
                    'signature_verified' => true,
                    'processing_status' => 'processed',
                    'event_payload' => $gatewayPayload,
                    'processed_at' => now(),
                ]
            );

            $fromStatus = $lockedOrder->status;
            $newStatus = ($fromStatus === 'pending') ? 'confirmed' : $fromStatus;

            $lockedOrder->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
            ]);

            $this->markCouponAsConsumed($lockedOrder);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'user_id' => null,
                'from_status' => $fromStatus,
                'status' => $newStatus,
                'note' => $historyNote,
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($lockedOrder) {
                app(OrderMailService::class)->sendOrderConfirmation($lockedOrder);
            });
        });
    }

    private function paymentWindowSeconds(Order $order): ?int
    {
        if (! $order->reservation_expires_at) {
            return max(0, (int) config('orders.reservation_expiration_minutes', 30) * 60 - 30);
        }

        // Keep a small request-latency buffer so gateway links expire before
        // the order releases its reserved inventory.
        $remainingSeconds = $order->reservation_expires_at->getTimestamp() - now()->getTimestamp() - 30;

        return $remainingSeconds >= 300 ? $remainingSeconds : null;
    }

    private function recordDuplicateGatewaySuccess(
        Order $order,
        Payment $payment,
        string $method,
        string $eventKey,
        string $providerReference,
        array $payload,
    ): void {
        if (in_array($payment->status, ['success', 'refunded'], true)) {
            return;
        }

        $payment->update([
            'refund_required' => true,
            'gateway_succeeded_at' => now(),
            'response_data' => array_merge($payment->response_data ?? [], [
                'duplicate_success_payload' => $payload,
                'refund_required' => true,
                'refund_reason' => $order->status === 'cancelled'
                    ? 'The gateway captured payment after the order was cancelled.'
                    : 'Another payment attempt already paid this order.',
            ]),
            'paid_at' => now(),
        ]);

        PaymentWebhookEvent::updateOrCreate(
            ['method' => $method, 'event_key' => $eventKey],
            [
                'payment_id' => $payment->id,
                'provider_reference' => $providerReference,
                'signature_verified' => true,
                'processing_status' => 'failed',
                'event_payload' => $payload,
                'error_message' => 'A second gateway payment succeeded after the order was already paid. Refund or reconciliation is required.',
                'processed_at' => now(),
            ]
        );
    }

    /**
     * Mark coupon as consumed upon successful payment.
     */
    private function markCouponAsConsumed(Order $order): void
    {
        app(FlashSaleOrderService::class)->consumeOrderReservations($order);

        if (! $order->coupon_id) {
            return;
        }

        $redemption = CouponRedemption::query()
            ->where('order_id', $order->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->first();

        if (! $redemption) {
            return;
        }

        $redemption->update(['status' => 'consumed']);

        $coupon = Coupon::whereKey($order->coupon_id)
            ->lockForUpdate()
            ->first();

        if ($coupon) {
            $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
            $coupon->used_count += 1;
            $coupon->save();
        }

    }

    /**
     * Get central payment accounts config for frontend display.
     */
    public function getPaymentConfig(): JsonResponse
    {
        $bank = config('payment_accounts.bank');
        $momo = config('payment_accounts.momo');

        return response()->json([
            'status' => true,
            'data' => [
                'bank' => $bank,
                'momo' => $momo,
            ],
        ]);
    }

    /**
     * Khởi tạo thông tin thanh toán chuyển khoản ngân hàng qua VietQR.
     */
    public function createBankTransferPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        if ($order->payment_method !== 'bank_transfer') {
            return response()->json(['message' => 'Ph??ng th?c thanh to?n c?a ??n h?ng kh?ng ph?i chuy?n kho?n ng?n h?ng.'], 422);
        }

        if ($request->user() && (int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện thanh toán cho đơn hàng này.',
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn hàng này đã được thanh toán thành công.',
                'data' => [
                    'order' => $order,
                    'is_paid' => true,
                ],
            ]);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Không thể thanh toán cho đơn hàng đã bị hủy.',
            ], 422);
        }

        // Tạo hoặc lấy bản ghi thanh toán chờ
        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'bank_transfer',
                'status' => 'pending',
            ],
            [
                'transaction_code' => 'PAY-BANK-'.Str::upper(Str::random(14)),
                'idempotency_key' => 'bank_order_'.$order->id.'_'.uniqid(),
                'amount' => $order->total_amount,
                'currency_code' => 'VND',
            ]
        );

        $bankConfig = config('payment_accounts.bank');
        $bankId = $bankConfig['bank_id'];
        $bankName = $bankConfig['bank_name'];
        $accountNumber = $bankConfig['account_number'];
        $accountName = $bankConfig['account_name'];
        $template = $bankConfig['qr_template'];

        $amount = (int) round((float) $order->total_amount);
        $orderCode = $order->order_code;
        $transferContent = $orderCode;

        // URL tạo mã VietQR tiêu chuẩn
        $vietQrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-%s.png?amount=%d&addInfo=%s&accountName=%s',
            urlencode($bankId),
            urlencode($accountNumber),
            urlencode($template),
            $amount,
            urlencode($transferContent),
            urlencode($accountName)
        );

        $isWaitingConfirmation = ! empty(($payment->response_data ?? [])['customer_reported_at']);

        return response()->json([
            'message' => 'Khởi tạo thông tin thanh toán VietQR thành công.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'amount' => $amount,
                'bank_id' => $bankId,
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'transfer_content' => $transferContent,
                'qr_url' => $vietQrUrl,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
                'is_waiting_confirmation' => $isWaitingConfirmation,
                'order' => [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'grand_total' => $amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ],
        ]);
    }

    /**
     * Khách hàng bấm "Tôi đã thanh toán" - Chuyển sang trạng thái chờ xác nhận.
     * Lưu ý: Không tự ý đánh dấu là đã thanh toán khi khách bấm nút này.
     */
    public function confirmBankTransferPaid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $result = DB::transaction(function () use ($request, $validated): array {
            $order = Order::query()
                ->whereKey($validated['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $order->user_id !== (int) $request->user()->id) {
                return ['status' => 403, 'message' => 'Bạn không có quyền thực hiện hành động này.'];
            }

            if ($order->payment_method !== 'bank_transfer') {
                return ['status' => 422, 'message' => 'Đơn hàng không sử dụng chuyển khoản ngân hàng.'];
            }

            if ($order->status === 'cancelled') {
                return ['status' => 422, 'message' => 'Không thể báo chuyển khoản cho đơn hàng đã hủy.'];
            }

            if ($order->payment_status === 'paid') {
                return [
                    'status' => 200,
                    'message' => 'Đơn hàng này đã được xác nhận thanh toán trước đó.',
                    'data' => ['payment_status' => 'paid'],
                ];
            }

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('method', 'bank_transfer')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return ['status' => 422, 'message' => 'Chưa có giao dịch chuyển khoản đang chờ cho đơn hàng này.'];
            }

            $responseData = $payment->response_data ?? [];
            if (! empty($responseData['customer_reported_at'])) {
                return [
                    'status' => 200,
                    'message' => 'Thông báo chuyển khoản của bạn đang chờ đối soát.',
                    'data' => [
                        'order_id' => $order->id,
                        'order_code' => $order->order_code,
                        'payment_status' => $order->payment_status,
                        'is_waiting_confirmation' => true,
                    ],
                ];
            }

            $responseData['customer_reported_at'] = now()->toIso8601String();
            $payment->update(['response_data' => $responseData]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'from_status' => $order->status,
                'status' => $order->status,
                'note' => 'Khách hàng thông báo đã chuyển khoản qua VietQR. Đang chờ đối soát biến động số dư.',
                'created_at' => now(),
            ]);

            return [
                'status' => 200,
                'message' => 'Đã ghi nhận thông báo chuyển khoản. Hệ thống đang chờ đối soát biến động số dư tài khoản.',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'payment_status' => $order->payment_status,
                    'is_waiting_confirmation' => true,
                ],
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    /**
     * Bộ mô phỏng giao dịch ngân hàng (Dành cho kiểm thử ĐATN và Admin).
     */
    public function simulateBankTransfer(Request $request): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['message' => 'Payment simulation is unavailable in this environment.'], 404);
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'action' => ['required', 'string', 'in:success,failed'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        if ($order->payment_method !== 'bank_transfer' || $order->status === 'cancelled') {
            return response()->json(['message' => 'Đơn hàng không thể mô phỏng thanh toán ngân hàng.'], 422);
        }

        if ($validated['action'] === 'success') {
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'message' => 'Đơn hàng này đã được thanh toán thành công trước đó.',
                    'data' => ['order' => $order],
                ]);
            }

            $transNo = $validated['transaction_no'] ?? ('VCB'.now()->format('YmdHis').rand(100, 999));

            DB::transaction(function () use ($order, $transNo, $validated, $request) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
                abort_unless(
                    $order->payment_method === 'bank_transfer' && $order->status !== 'cancelled',
                    422,
                    'Đơn hàng không thể mô phỏng thanh toán ngân hàng.'
                );
                if ($order->payment_status === 'paid') {
                    return;
                }

                $fromStatus = $order->status;
                $payment = Payment::query()->where('order_id', $order->id)->where('method', 'bank_transfer')->lockForUpdate()->first();
                $payment ??= Payment::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'method' => 'bank_transfer',
                    ],
                    [
                        'transaction_code' => 'PAY-BANK-'.Str::upper(Str::random(14)),
                        'idempotency_key' => 'bank_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $transNo,
                    'paid_at' => now(),
                    'response_data' => ['note' => $validated['note'] ?? 'Bank transfer simulation succeeded'],
                ]);

                $newStatus = $fromStatus === 'pending' ? 'confirmed' : $fromStatus;
                $order->update([
                    'payment_status' => 'paid',
                    'status' => $newStatus,
                ]);

                $this->markCouponAsConsumed($order);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()?->id,
                    'from_status' => $fromStatus,
                    'status' => $newStatus,
                    'note' => 'Xác nhận thanh toán VietQR thành công (Mã GD: '.$transNo.')',
                    'created_at' => now(),
                ]);

                PaymentWebhookEvent::create([
                    'method' => 'bank_transfer',
                    'event_key' => 'sim_bank_'.$transNo,
                    'provider_reference' => $transNo,
                    'signature_verified' => false,
                    'processing_status' => 'processed',
                    'event_payload' => [
                        'order_id' => $order->id,
                        'order_code' => $order->order_code,
                        'amount' => $order->total_amount,
                        'transaction_no' => $transNo,
                        'simulated_by' => $request->user()?->email,
                        'time' => now()->toIso8601String(),
                    ],
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    try {
                        app(OrderMailService::class)->sendOrderConfirmation($order);
                    } catch (\Throwable) {
                    }
                });
            });

            return response()->json([
                'message' => 'Mô phỏng xác nhận chuyển khoản ngân hàng thành công!',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'payment_status' => 'paid',
                    'order_status' => $order->fresh()->status,
                    'transaction_no' => $transNo,
                ],
            ]);
        }

        // Action: failed
        DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->payment_method === 'bank_transfer' && $lockedOrder->status !== 'cancelled',
                422,
                'Đơn hàng không thể mô phỏng thanh toán ngân hàng.'
            );
            abort_unless($lockedOrder->payment_status !== 'paid', 422, 'Không thể mô phỏng thất bại cho đơn đã thanh toán.');

            $payment = Payment::query()->where('order_id', $lockedOrder->id)->where('method', 'bank_transfer')->lockForUpdate()->first();
            if ($payment && $payment->status !== 'success') {
                $payment->update([
                    'status' => 'failed',
                    'response_data' => ['note' => $validated['note'] ?? 'Bank transfer simulation failed'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Mô phỏng giao dịch ngân hàng thất bại.',
            'data' => [
                'order_id' => $order->id,
                'payment_status' => 'failed',
            ],
        ]);
    }

    /**
     * Bộ mô phỏng thanh toán MoMo Sandbox (Dành cho kiểm thử ĐATN).
     */
    public function simulateMoMoPayment(Request $request): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return response()->json(['message' => 'Payment simulation is unavailable in this environment.'], 404);
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'action' => ['required', 'string', 'in:success,failed'],
            'trans_id' => ['nullable', 'string', 'max:50'],
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        if ($order->payment_method !== 'momo' || $order->status === 'cancelled') {
            return response()->json(['message' => 'Đơn hàng không thể mô phỏng thanh toán MoMo.'], 422);
        }

        if ($validated['action'] === 'success') {
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'message' => 'Đơn hàng này đã được thanh toán thành công trước đó.',
                    'data' => ['order' => $order],
                ]);
            }

            $transId = $validated['trans_id'] ?? ((string) time().rand(100, 999));

            DB::transaction(function () use ($order, $transId, $request) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
                abort_unless(
                    $order->payment_method === 'momo' && $order->status !== 'cancelled',
                    422,
                    'Đơn hàng không thể mô phỏng thanh toán MoMo.'
                );
                if ($order->payment_status === 'paid') {
                    return;
                }

                $fromStatus = $order->status;
                $payment = Payment::query()->where('order_id', $order->id)->where('method', 'momo')->lockForUpdate()->first();
                $payment ??= Payment::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'method' => 'momo',
                    ],
                    [
                        'transaction_code' => 'PAY-MM-'.Str::upper(Str::random(14)),
                        'idempotency_key' => 'momo_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $transId,
                    'paid_at' => now(),
                    'response_data' => ['note' => 'MoMo payment simulation succeeded'],
                ]);

                $newStatus = $fromStatus === 'pending' ? 'confirmed' : $fromStatus;
                $order->update([
                    'payment_status' => 'paid',
                    'status' => $newStatus,
                ]);

                $this->markCouponAsConsumed($order);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()?->id,
                    'from_status' => $fromStatus,
                    'status' => $newStatus,
                    'note' => 'Thanh toán MoMo thành công (Mô phỏng Sandbox - Mã GD: '.$transId.')',
                    'created_at' => now(),
                ]);

                PaymentWebhookEvent::create([
                    'method' => 'momo',
                    'event_key' => 'sim_mm_'.$transId,
                    'provider_reference' => $transId,
                    'signature_verified' => false,
                    'processing_status' => 'processed',
                    'event_payload' => [
                        'order_id' => $order->id,
                        'trans_id' => $transId,
                        'amount' => $order->total_amount,
                    ],
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    try {
                        app(OrderMailService::class)->sendOrderConfirmation($order);
                    } catch (\Throwable) {
                    }
                });
            });

            return response()->json([
                'message' => 'Mô phỏng thanh toán MoMo thành công!',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'payment_status' => 'paid',
                    'order_status' => $order->fresh()->status,
                    'trans_id' => $transId,
                ],
            ]);
        }

        // Failed
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->payment_method === 'momo' && $lockedOrder->status !== 'cancelled',
                422,
                'Đơn hàng không thể mô phỏng thanh toán MoMo.'
            );
            abort_unless($lockedOrder->payment_status !== 'paid', 422, 'Không thể mô phỏng thất bại cho đơn đã thanh toán.');

            $payment = Payment::query()->where('order_id', $lockedOrder->id)->where('method', 'momo')->lockForUpdate()->first();
            if ($payment && $payment->status !== 'success') {
                $payment->update([
                    'status' => 'failed',
                    'response_data' => ['note' => 'MoMo payment simulation failed'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Mô phỏng thanh toán MoMo thất bại.',
            'data' => ['payment_status' => 'failed'],
        ]);
    }
}
