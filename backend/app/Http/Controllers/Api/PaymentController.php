<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\MoMoService;
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
        $order = Order::where('order_code', $orderCode)->first();

        // Dual-confirmation: update order status directly upon return if not yet confirmed by IPN
        if ($order && $isSuccess && $order->payment_status !== 'paid') {
            DB::transaction(function () use ($order, $queryParams, $transactionNo) {
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'method' => 'vnpay'],
                    [
                        'transaction_code' => 'PAY-'.Str::upper(Str::random(16)),
                        'idempotency_key' => 'vnpay_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $transactionNo,
                    'response_data' => $queryParams,
                    'paid_at' => now(),
                ]);

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
                    'note' => 'Thanh toán VNPay thành công (Return URL - Mã GD: '.$transactionNo.')',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                });
            });
        }

        return response()->json([
            'status' => $isSuccess ? 'success' : 'failed',
            'message' => $isSuccess ? 'Giao dịch thanh toán thành công.' : 'Giao dịch không thành công hoặc bị hủy.',
            'data' => [
                'order_id' => $order?->id,
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
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                });
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

        $zpResult = $this->zalopayService->createPaymentOrder([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'amount' => $order->total_amount,
            'app_user' => (string) $request->user()->id,
            'items' => $items,
            'bank_code' => $validated['bank_code'] ?? null,
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
            ], 422);
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

        // Find order by order_id or order_code
        $order = null;
        if ($request->filled('order_id')) {
            $order = Order::find($request->query('order_id'));
        }
        if (! $order && ! empty($appTransId)) {
            $parts = explode('_', $appTransId);
            if (count($parts) >= 2) {
                $candidate = $parts[1];
                if (is_numeric($candidate)) {
                    $order = Order::find((int) $candidate);
                } else {
                    $order = Order::where('order_code', 'LIKE', "%{$candidate}%")->first();
                }
            }
        }

        if ($order && $isSuccess && $order->payment_status !== 'paid') {
            DB::transaction(function () use ($order, $queryParams, $appTransId) {
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'method' => 'zalopay'],
                    [
                        'transaction_code' => 'PAY-ZP-'.Str::upper(Str::random(14)),
                        'idempotency_key' => 'zalopay_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $appTransId,
                    'response_data' => $queryParams,
                    'paid_at' => now(),
                ]);

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
                    'note' => 'Thanh toán ZaloPay thành công (Return URL - Mã GD: '.$appTransId.')',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                });
            });
        }

        return response()->json([
            'status' => $isSuccess ? 'success' : 'failed',
            'message' => $isSuccess ? 'Giao dịch ZaloPay thành công.' : 'Giao dịch ZaloPay không thành công hoặc bị hủy.',
            'data' => [
                'order_id' => $order?->id,
                'order_code' => $order?->order_code,
                'app_trans_id' => $appTransId,
                'status' => $status,
                'amount' => $amount,
                'bank_code' => $bankCode,
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

        // Find Order
        $order = null;
        $parts = explode('_', $appTransId);
        if (count($parts) >= 2) {
            $candidate = $parts[1];
            if (is_numeric($candidate)) {
                $order = Order::find((int) $candidate);
            } else {
                $order = Order::where('order_code', 'LIKE', "%{$candidate}%")->first();
            }
        }

        if (! $order) {
            return response()->json([
                'return_code' => 0,
                'return_message' => 'order not found',
            ]);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'return_code' => 1,
                'return_message' => 'order already paid',
            ]);
        }

        DB::transaction(function () use ($order, $dataArr, $appTransId, $zpTransId) {
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id, 'method' => 'zalopay'],
                [
                    'transaction_code' => 'PAY-ZP-'.Str::upper(Str::random(14)),
                    'idempotency_key' => 'zalopay_order_'.$order->id.'_'.uniqid(),
                    'amount' => $order->total_amount,
                    'currency_code' => 'VND',
                ]
            );

            $payment->update([
                'status' => 'success',
                'gateway_transaction_id' => $zpTransId ?: $appTransId,
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
                app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
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

        $mmResult = $this->momoService->createPaymentOrder([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'amount' => $order->total_amount,
            'order_info' => 'MobieX - Thanh toán đơn hàng '.$order->order_code,
        ]);

        if (! empty($mmResult['order_id'])) {
            $payment->update([
                'gateway_transaction_id' => $mmResult['order_id'],
            ]);
        }

        if (($mmResult['status'] ?? '') === 'failed' || (empty($mmResult['qr_code_url']) && empty($mmResult['deeplink']))) {
            return response()->json([
                'message' => $mmResult['message'] ?? 'Không thể khởi tạo cổng thanh toán MoMo.',
                'error' => $mmResult['message'] ?? 'Lỗi kết nối cổng thanh toán MoMo',
            ], 422);
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
            ? Payment::where('order_id', $order->id)->where('method', 'momo')->first()
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

        if ($order && $isSuccess && $order->payment_status !== 'paid') {
            DB::transaction(function () use ($order, $queryParams, $transId, $orderIdParam) {
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'method' => 'momo'],
                    [
                        'transaction_code' => 'PAY-MM-'.Str::upper(Str::random(14)),
                        'idempotency_key' => 'momo_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $transId ?: $orderIdParam,
                    'response_data' => $queryParams,
                    'paid_at' => now(),
                ]);

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
                    'note' => 'Thanh toán MoMo thành công (Return URL - Mã GD: '.($transId ?: $orderIdParam).')',
                    'created_at' => now(),
                ]);

                DB::afterCommit(function () use ($order) {
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                });
            });
        }

        return response()->json([
            'status' => $isSuccess ? 'success' : 'failed',
            'message' => $isSuccess ? 'Giao dịch MoMo thành công.' : 'Giao dịch MoMo không thành công hoặc bị hủy.',
            'data' => [
                'order_id' => $order?->id,
                'order_code' => $order?->order_code,
                'trans_id' => $transId,
                'order_id_param' => $orderIdParam,
                'result_code' => $resultCode,
                'amount' => $amount,
                'pay_type' => $payType,
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
        $payment = Payment::where('order_id', $order->id)->where('method', 'momo')->first();
        if (
            $order->payment_method !== 'momo' ||
            abs((float) $order->total_amount - $callbackAmount) >= 0.01 ||
            ! $payment ||
            ! hash_equals((string) $payment->gateway_transaction_id, $orderIdParam)
        ) {
            return response()->json(['resultCode' => 99, 'message' => 'Payment details do not match the order.'], 400);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'resultCode' => 0,
                'message' => 'Order already paid',
            ]);
        }

        if ($resultCode === 0) {
            DB::transaction(function () use ($order, $payload, $transId, $orderIdParam) {
                $payment = Payment::firstOrCreate(
                    ['order_id' => $order->id, 'method' => 'momo'],
                    [
                        'transaction_code' => 'PAY-MM-'.Str::upper(Str::random(14)),
                        'idempotency_key' => 'momo_order_'.$order->id.'_'.uniqid(),
                        'amount' => $order->total_amount,
                        'currency_code' => 'VND',
                    ]
                );

                $payment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $transId ?: $orderIdParam,
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
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                });
            });
        }

        return response()->json([
            'resultCode' => 0,
            'message' => 'success',
        ]);
    }

    /**
     * Mark coupon as consumed upon successful payment.
     */
    private function markCouponAsConsumed(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        $coupon = Coupon::whereKey($order->coupon_id)
            ->lockForUpdate()
            ->first();

        if ($coupon) {
            $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
            $coupon->used_count += 1;
            $coupon->save();
        }

        CouponRedemption::where('order_id', $order->id)
            ->where('status', 'reserved')
            ->update(['status' => 'consumed']);
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

        $order = Order::findOrFail($validated['order_id']);

        if ($order->payment_method !== 'bank_transfer') {
            return response()->json(['message' => 'Phương thức thanh toán của đơn hàng không phải chuyển khoản ngân hàng.'], 422);
        }

        if ($request->user() && (int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện hành động này.',
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn hàng này đã được xác nhận thanh toán trước đó.',
                'data' => ['payment_status' => 'paid'],
            ]);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('method', 'bank_transfer')
            ->first();

        if ($payment) {
            $responseData = $payment->response_data ?? [];
            $responseData['customer_reported_at'] = now()->toIso8601String();
            $payment->update([
                'response_data' => $responseData,
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $request->user()?->id,
            'from_status' => $order->status,
            'status' => $order->status,
            'note' => 'Khách hàng thông báo đã chuyển khoản qua VietQR. Đang chờ đối soát biến động số dư.',
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã ghi nhận thông báo chuyển khoản. Hệ thống đang chờ đối soát biến động số dư tài khoản.',
            'data' => [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'is_waiting_confirmation' => true,
            ],
        ]);
    }

    /**
     * Bộ mô phỏng giao dịch ngân hàng (Dành cho kiểm thử ĐATN và Admin).
     */
    public function simulateBankTransfer(Request $request): JsonResponse
    {
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
                $fromStatus = $order->status;
                $payment = Payment::firstOrCreate(
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
                        app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                    } catch (\Throwable) {}
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
        $payment = Payment::where('order_id', $order->id)
            ->where('method', 'bank_transfer')
            ->first();

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'response_data' => ['note' => $validated['note'] ?? 'Bank transfer simulation failed'],
            ]);
        }

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
                $fromStatus = $order->status;
                $payment = Payment::firstOrCreate(
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
                        app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                    } catch (\Throwable) {}
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
        $payment = Payment::where('order_id', $order->id)->where('method', 'momo')->first();
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'response_data' => ['note' => 'MoMo payment simulation failed'],
            ]);
        }

        return response()->json([
            'message' => 'Mô phỏng thanh toán MoMo thất bại.',
            'data' => ['payment_status' => 'failed'],
        ]);
    }
}
