<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\MoMoService;
use App\Services\VNPayService;
use App\Services\ZaloPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminPaymentRefundController extends Controller
{
    public function __construct(
        private readonly VNPayService $vnpay,
        private readonly MoMoService $momo,
        private readonly ZaloPayService $zalopay,
    ) {}

    public function store(Request $request, int|string $paymentId): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $orderId = Payment::query()->whereKey($paymentId)->value('order_id');
        if (! $orderId) {
            abort(404);
        }

        [$refund, $created] = DB::transaction(function () use ($request, $paymentId, $orderId, $validated): array {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((int) $payment->order_id !== (int) $order->id) {
                abort(409);
            }

            $existing = PaymentRefund::query()->where('payment_id', $payment->id)->first();
            if ($existing) {
                return [$existing, false];
            }

            $captured = $payment->status === 'success'
                || ($payment->refund_required && $payment->gateway_succeeded_at !== null);
            if (! $captured || ($order->payment_status !== 'paid' && ! $payment->refund_required)) {
                throw ValidationException::withMessages([
                    'payment' => 'Chỉ giao dịch đã được cổng xác nhận thu tiền mới có thể gửi yêu cầu hoàn.',
                ]);
            }
            if (! in_array($payment->method, ['vnpay', 'momo', 'zalopay'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Cổng thanh toán này chưa hỗ trợ hoàn tiền tự động.',
                ]);
            }
            $configured = match ($payment->method) {
                'vnpay' => $this->vnpay->isConfigured(),
                'momo' => $this->momo->isConfigured(),
                'zalopay' => $this->zalopay->isConfigured(),
            };
            if (! $configured) {
                abort(503, 'Cổng thanh toán chưa được cấu hình để gửi yêu cầu hoàn tiền.');
            }

            $responseData = $payment->response_data ?? [];
            $successfulPayload = $responseData['duplicate_success_payload'] ?? $responseData;
            $providerReference = match ($payment->method) {
                'momo' => $successfulPayload['transId'] ?? null,
                'zalopay' => $successfulPayload['zp_trans_id'] ?? null,
                default => $successfulPayload['vnp_TransactionNo'] ?? $payment->gateway_transaction_id,
            };
            if (! is_numeric($providerReference) || (string) ($responseData['vnp_CreateDate'] ?? '') === '' && $payment->method === 'vnpay') {
                throw ValidationException::withMessages([
                    'payment' => 'Thiếu mã giao dịch gốc hoặc thời gian giao dịch của cổng; giao dịch này cần được hoàn thủ công và đối soát.',
                ]);
            }

            $requestId = (string) Str::ulid();
            $providerOrderId = match ($payment->method) {
                'momo' => 'RF_'.$order->id.'_'.substr($requestId, -12),
                'zalopay' => now('Asia/Ho_Chi_Minh')->format('ymd').'_'.config('zalopay.app_id').'_RF'.substr($requestId, -12),
                default => null,
            };

            return [PaymentRefund::create([
                'payment_id' => $payment->id,
                'requested_by' => $request->user()->id,
                'method' => $payment->method,
                'request_id' => $requestId,
                'provider_order_id' => $providerOrderId,
                'amount' => $payment->amount,
                'status' => 'pending',
                'note' => $validated['note'],
            ]), true];
        });

        if (! $created) {
            return response()->json([
                'message' => 'Đã có yêu cầu hoàn tiền cho giao dịch này. Hãy kiểm tra trạng thái để tránh gửi hoàn trùng.',
                'data' => $refund->load('payment'),
            ], $refund->status === 'succeeded' ? 200 : 409);
        }

        $payment = $refund->payment()->with('order')->firstOrFail();
        $response = $this->submitToProvider($refund, $payment);
        $refund = $this->saveProviderResponse($refund, $response, 'request');

        // A successful request response can mean only "accepted". Always query
        // the provider before changing the payment/order to refunded.
        if ($refund->status !== 'failed' && empty($response['transport_error'])) {
            $query = $this->queryProvider($refund->fresh(), $payment);
            $refund = $this->saveProviderResponse($refund, $query, 'query');
        }

        $httpStatus = match ($refund->status) {
            'succeeded' => 200,
            'failed' => 422,
            default => 202,
        };

        return response()->json([
            'message' => $this->statusMessage($refund->status),
            'data' => $refund->fresh()->load('payment'),
        ], $httpStatus);
    }

    public function refresh(Request $request, int|string $paymentId): JsonResponse
    {
        $refund = PaymentRefund::query()->where('payment_id', $paymentId)->firstOrFail();
        if ($refund->status === 'succeeded' || $refund->status === 'failed') {
            return response()->json(['data' => $refund->load('payment')]);
        }

        $payment = $refund->payment()->with('order')->firstOrFail();
        $response = $this->queryProvider($refund, $payment);
        $refund = $this->saveProviderResponse($refund, $response, 'query');

        return response()->json([
            'message' => $this->statusMessage($refund->status),
            'data' => $refund->fresh()->load('payment'),
        ], 200);
    }

    private function submitToProvider(PaymentRefund $refund, Payment $payment): array
    {
        $responseData = $payment->response_data ?? [];
        $successfulPayload = $responseData['duplicate_success_payload'] ?? $responseData;

        return match ($refund->method) {
            'momo' => $this->momo->requestRefund(
                (string) $refund->provider_order_id,
                $refund->request_id,
                (string) ($successfulPayload['transId'] ?? ''),
                (int) round((float) $refund->amount),
                $refund->note,
            ),
            'zalopay' => $this->zalopay->requestRefund(
                (string) $refund->provider_order_id,
                (string) ($successfulPayload['zp_trans_id'] ?? ''),
                (int) round((float) $refund->amount),
                $refund->note,
            ),
            default => $this->vnpay->requestRefund([
                'request_id' => $refund->request_id,
                'transaction_reference' => $payment->transaction_code,
                'amount' => $refund->amount,
                'provider_transaction_id' => $successfulPayload['vnp_TransactionNo'] ?? $payment->gateway_transaction_id,
                'transaction_date' => $responseData['vnp_CreateDate'] ?? null,
                'created_by' => $refund->requester?->name ?? 'MobieX Admin',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'description' => $refund->note,
            ]),
        };
    }

    private function queryProvider(PaymentRefund $refund, Payment $payment): array
    {
        $responseData = $payment->response_data ?? [];
        $successfulPayload = $responseData['duplicate_success_payload'] ?? $responseData;

        return match ($refund->method) {
            'momo' => $this->momo->queryRefund((string) $refund->provider_order_id, $refund->request_id),
            'zalopay' => $this->zalopay->queryRefund($refund->provider_order_id ?: $refund->request_id),
            default => $this->vnpay->queryRefund([
                'query_request_id' => (string) Str::ulid(),
                'transaction_reference' => $payment->transaction_code,
                'transaction_date' => $responseData['vnp_CreateDate'] ?? null,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'description' => $refund->note,
            ]),
        };
    }

    private function saveProviderResponse(PaymentRefund $refund, array $response, string $stage): PaymentRefund
    {
        $status = $this->interpretProviderStatus($refund, $response, $stage);
        $providerRefundId = $response['refund_id']
            ?? $response['refundTransId']
            ?? $response['vnp_TransactionNo']
            ?? data_get(collect($response['refundTrans'] ?? [])->firstWhere('orderId', $refund->provider_order_id), 'transId')
            ?? $refund->provider_refund_id;

        if ($status === 'succeeded') {
            return DB::transaction(function () use ($refund, $response, $stage, $providerRefundId): PaymentRefund {
                $orderId = Payment::query()->whereKey($refund->payment_id)->value('order_id');
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
                $lockedRefund = PaymentRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
                if (in_array($lockedRefund->status, ['succeeded', 'failed'], true)) {
                    return $lockedRefund;
                }

                $this->writeProviderResult($lockedRefund, $response, $stage, $providerRefundId, 'succeeded');
                $this->applyRefundToOrder($order, $payment, $lockedRefund);

                return $lockedRefund->fresh();
            });
        }

        return DB::transaction(function () use ($refund, $response, $stage, $providerRefundId, $status): PaymentRefund {
            $lockedRefund = PaymentRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedRefund->status, ['succeeded', 'failed'], true)) {
                return $lockedRefund;
            }

            $this->writeProviderResult($lockedRefund, $response, $stage, $providerRefundId, $status);

            return $lockedRefund->fresh();
        });
    }

    private function writeProviderResult(
        PaymentRefund $refund,
        array $response,
        string $stage,
        mixed $providerRefundId,
        string $status,
    ): void {
        $refund->update([
            'status' => $status,
            'provider_refund_id' => $providerRefundId ? (string) $providerRefundId : $refund->provider_refund_id,
            'provider_response' => array_merge($refund->provider_response ?? [], [
                $stage => $response,
                'last_checked_at' => now()->toISOString(),
            ]),
            'submitted_at' => $stage === 'request' && empty($response['transport_error']) ? now() : $refund->submitted_at,
            'resolved_at' => in_array($status, ['succeeded', 'failed'], true) ? now() : null,
        ]);
    }

    private function interpretProviderStatus(PaymentRefund $refund, array $response, string $stage): string
    {
        if (! empty($response['transport_error'])) {
            return 'unknown';
        }

        if ($refund->method === 'vnpay') {
            if (! $this->vnpay->verifyRefundApiResponse($response)) {
                return 'unknown';
            }
            $payment = $refund->payment;
            if (($response['vnp_TmnCode'] ?? null) !== (string) config('vnpay.tmn_code')
                || ($response['vnp_TxnRef'] ?? null) !== $payment?->transaction_code) {
                return 'unknown';
            }
            if ($stage === 'request') {
                $code = (string) ($response['vnp_ResponseCode'] ?? '');

                return match ($code) {
                    '00', '94' => 'processing',
                    '95', '91', '02', '03', '97' => 'failed',
                    default => 'unknown',
                };
            }
            if ((int) ($response['vnp_Amount'] ?? 0) !== (int) round((float) $refund->amount * 100)) {
                return 'unknown';
            }
            $type = (string) ($response['vnp_TransactionType'] ?? '');
            $status = (string) ($response['vnp_TransactionStatus'] ?? '');
            if ($type === '02' && $status === '00') {
                return 'succeeded';
            }
            if ($type === '02' && $status === '09') {
                return 'failed';
            }

            return 'processing';
        }

        if ($refund->method === 'momo') {
            if ($stage === 'request' && (int) ($response['resultCode'] ?? -1) !== 0) {
                return in_array((int) ($response['resultCode'] ?? -1), [7000, 7002], true) ? 'processing' : 'failed';
            }
            if ($stage === 'query' && (int) ($response['resultCode'] ?? -1) !== 0) {
                return 'processing';
            }
            foreach (($response['refundTrans'] ?? []) as $transaction) {
                if (($transaction['orderId'] ?? null) !== $refund->provider_order_id) {
                    continue;
                }
                if ((int) ($transaction['resultCode'] ?? -1) === 0
                    && (int) ($transaction['amount'] ?? 0) === (int) round((float) $refund->amount)) {
                    return 'succeeded';
                }
                if ((int) ($transaction['resultCode'] ?? -1) > 0) {
                    return 'failed';
                }
            }

            return 'processing';
        }

        $code = (int) ($response['return_code'] ?? -999);
        if ($stage === 'request') {
            if ($code === 2) {
                return 'failed';
            }

            return 'processing';
        }
        if ($code === 1) {
            return 'succeeded';
        }
        if ($code === 2 && in_array((int) ($response['sub_return_code'] ?? 0), [-2, -13, -14, -32], true)) {
            return 'failed';
        }

        return 'processing';
    }

    private function applyRefundToOrder(Order $order, Payment $payment, PaymentRefund $refund): void
    {
        if ($payment->status !== 'refunded') {
            $transactionId = $refund->provider_refund_id ?: $refund->request_id;
            $payment->update([
                'status' => 'refunded',
                'refund_required' => false,
                'refunded_at' => now(),
                'refund_transaction_id' => $transactionId,
                'refund_note' => $refund->note,
                'refund_confirmed_by' => $refund->requested_by,
            ]);
        }

        $hasOutstandingCapture = Payment::query()->where('order_id', $order->id)->whereKeyNot($payment->id)
            ->where(fn ($query) => $query->where('status', 'success')->orWhere('refund_required', true))
            ->exists();
        if (! $hasOutstandingCapture && $order->payment_status === 'paid') {
            $order->update(['payment_status' => 'refunded']);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $refund->requested_by,
                'from_status' => $order->status,
                'status' => $order->status,
                'note' => 'Gateway refund completed: '.$refund->note,
                'created_at' => now(),
            ]);
        }
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'succeeded' => 'Cổng thanh toán đã xác nhận hoàn tiền thành công.',
            'failed' => 'Cổng thanh toán từ chối yêu cầu hoàn tiền.',
            'unknown' => 'Chưa xác định được kết quả gửi yêu cầu. Hãy truy vấn trạng thái, không gửi yêu cầu mới.',
            default => 'Yêu cầu đã được ghi nhận; cần truy vấn lại để biết kết quả cuối cùng.',
        };
    }
}
