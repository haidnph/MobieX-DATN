<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloPayService
{
    private string $appId;

    private string $key1;

    private string $key2;

    private string $endpoint;

    private string $queryEndpoint;

    private string $refundEndpoint;

    private string $refundQueryEndpoint;

    private string $returnUrl;

    private string $callbackUrl;

    public function __construct()
    {
        $this->appId = (string) config('zalopay.app_id', '');
        $this->key1 = (string) config('zalopay.key1', '');
        $this->key2 = (string) config('zalopay.key2', '');
        $this->endpoint = (string) config('zalopay.endpoint', 'https://sb-openapi.zalopay.vn/v2/create');
        $this->queryEndpoint = (string) config('zalopay.query_endpoint', 'https://sb-openapi.zalopay.vn/v2/query');
        $this->refundEndpoint = (string) config('zalopay.refund_endpoint', 'https://sb-openapi.zalopay.vn/v2/refund');
        $this->refundQueryEndpoint = (string) config('zalopay.refund_query_endpoint', 'https://sb-openapi.zalopay.vn/v2/query_refund');
        $this->returnUrl = (string) config('zalopay.return_url', 'http://localhost:5173/payment/zalopay/return');
        $this->callbackUrl = (string) config('zalopay.callback_url', 'http://localhost:8000/api/payments/zalopay/callback');
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->key1 !== '' && $this->key2 !== '';
    }

    /**
     * Create ZaloPay payment order and generate payment redirect URL.
     *
     * @param array{
     *     order_id: int|string,
     *     order_code: string,
     *     amount: int|float,
     *     payment_reference?: string,
     *     app_user?: string,
     *     description?: string,
     *     items?: array,
     *     bank_code?: string,
     *     expires_in_seconds?: int
     * } $data
     * @return array<string, mixed>
     */
    public function createPaymentOrder(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'return_message' => 'ZaloPay credentials are not configured.'];
        }
        $now = now('Asia/Ho_Chi_Minh');
        $appTime = (int) round(microtime(true) * 1000);
        $transPrefix = $now->format('ymd');

        // Short, safe app_trans_id: yymmdd_<order_id>_<random6>  (max ~25 chars)
        $attemptSuffix = ! empty($data['payment_reference'])
            ? substr(hash('sha256', (string) $data['payment_reference']), 0, 6)
            : substr(uniqid(), -6);
        $appTransId = $transPrefix.'_'.((int) $data['order_id']).'_'.strtolower($attemptSuffix);
        $amount = (int) round((float) $data['amount']);
        $appUser = (string) ($data['app_user'] ?? 'MobieX_Customer');
        $description = (string) ($data['description'] ?? ('MobieX - Thanh toan don hang '.$data['order_code']));

        $embedDataArr = [
            'redirecturl' => $this->returnUrl,
            'order_id' => $data['order_id'],
            'order_code' => $data['order_code'],
        ];
        $embedData = json_encode($embedDataArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $itemsArr = $data['items'] ?? [];
        $items = json_encode($itemsArr ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // MAC = HMAC-SHA256(key1, appid|apptransid|appuser|amount|apptime|embeddata|item)
        $dataToSign = "{$this->appId}|{$appTransId}|{$appUser}|{$amount}|{$appTime}|{$embedData}|{$items}";
        $mac = hash_hmac('sha256', $dataToSign, $this->key1);

        $payload = [
            'app_id' => (int) $this->appId,
            'app_user' => $appUser,
            'app_trans_id' => $appTransId,
            'app_time' => $appTime,
            'amount' => $amount,
            'item' => $items,
            'description' => $description,
            'embed_data' => $embedData,
            'bank_code' => (string) ($data['bank_code'] ?? ''),
            'mac' => $mac,
            'callback_url' => $this->callbackUrl,
        ];
        if (isset($data['expires_in_seconds'])) {
            $payload['expire_duration_seconds'] = (int) $data['expires_in_seconds'];
        }

        try {
            $response = Http::retry(
                2,
                250,
                static fn (\Exception $exception): bool => $exception instanceof ConnectionException,
                throw: false
            )
                ->timeout(10)
                ->asForm()
                ->post($this->endpoint, $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && ($body['return_code'] ?? null) === 1) {
                    return [
                        'status' => 'success',
                        'order_url' => $body['order_url'] ?? null,
                        'zp_trans_token' => $body['zp_trans_token'] ?? null,
                        'order_token' => $body['order_token'] ?? null,
                        'app_trans_id' => $appTransId,
                        'return_code' => 1,
                        'return_message' => $body['return_message'] ?? 'Thành công',
                    ];
                }

                // Gateway returned non-1 return_code
                Log::warning('ZaloPay Create Order Gateway Returned Non-1: '.json_encode($body));

                return [
                    'status' => 'failed',
                    'app_trans_id' => $appTransId,
                    'return_code' => $body['return_code'] ?? 2,
                    'return_message' => $body['return_message'] ?? 'Không tạo được đơn hàng ZaloPay.',
                    'sub_return_code' => $body['sub_return_code'] ?? null,
                    'sub_return_message' => $body['sub_return_message'] ?? null,
                    'order_url' => null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ZaloPay Create Order Gateway Exception: '.$e->getMessage());

            return [
                'status' => 'failed',
                'order_url' => null,
                'app_trans_id' => $appTransId,
                'return_code' => 2,
                'error_code' => 'provider_unreachable',
                'return_message' => 'Backend không kết nối được ZaloPay Sandbox. Kiểm tra mạng outbound, firewall hoặc proxy trên máy chạy API.',
            ];
        }

        return [
            'status' => 'failed',
            'order_url' => null,
            'app_trans_id' => $appTransId,
            'return_code' => 2,
            'return_message' => 'Không thể kết nối hoặc tạo giao dịch ZaloPay. Vui lòng thử lại.',
        ];
    }

    /**
     * Verify Checksum received from ZaloPay Return URL.
     * Checksum formula: HMAC-SHA256(key2, appid|apptransid|pmcid|bankcode|amount|discountamount|status)
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyReturnChecksum(array $params): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $checksum = $params['checksum'] ?? null;
        if (! $checksum || ! is_string($checksum)) {
            return false;
        }

        $appId = (string) ($params['appid'] ?? $this->appId);
        $appTransId = (string) ($params['apptransid'] ?? '');
        $pmcId = (string) ($params['pmcid'] ?? '');
        $bankCode = (string) ($params['bankcode'] ?? '');
        $amount = (string) ($params['amount'] ?? '0');
        $discountAmount = (string) ($params['discountamount'] ?? '0');
        $status = (string) ($params['status'] ?? '');

        $dataToSign = "{$appId}|{$appTransId}|{$pmcId}|{$bankCode}|{$amount}|{$discountAmount}|{$status}";
        $expectedChecksum = hash_hmac('sha256', $dataToSign, $this->key2);

        return hash_equals($expectedChecksum, $checksum);
    }

    /**
     * Verify Callback (Webhook) from ZaloPay.
     * MAC formula: HMAC-SHA256(data, key2)
     */
    public function verifyCallback(string $dataJson, string $mac): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $expectedMac = hash_hmac('sha256', $dataJson, $this->key2);

        return hash_equals($expectedMac, $mac);
    }

    /**
     * Query order status directly from ZaloPay Gateway.
     * MAC formula: HMAC-SHA256(appid|apptransid|key1, key1)
     */
    public function queryOrderStatus(string $appTransId): array
    {
        $dataToSign = "{$this->appId}|{$appTransId}|{$this->key1}";
        $mac = hash_hmac('sha256', $dataToSign, $this->key1);

        try {
            $response = Http::timeout(10)->asForm()->post($this->queryEndpoint, [
                'app_id' => (int) $this->appId,
                'app_trans_id' => $appTransId,
                'mac' => $mac,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning('ZaloPay Query Order Exception: '.$e->getMessage());
        }

        return [
            'return_code' => 2,
            'return_message' => 'Khong the ket noi tuy van trang thai ZaloPay.',
        ];
    }

    /** @return array<string, mixed> */
    public function requestRefund(string $refundId, string $zpTransId, int $amount, string $description): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'ZaloPay credentials are not configured.'];
        }

        $timestamp = (int) round(microtime(true) * 1000);
        $macInput = "{$this->appId}|{$zpTransId}|{$amount}|{$description}|{$timestamp}";
        try {
            $response = Http::timeout(30)->asForm()->post($this->refundEndpoint, [
                'app_id' => (int) $this->appId,
                'm_refund_id' => $refundId,
                'zp_trans_id' => $zpTransId,
                'amount' => $amount,
                'timestamp' => $timestamp,
                'description' => $description,
                'mac' => hash_hmac('sha256', $macInput, $this->key1),
            ]);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: ['transport_error' => true, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('ZaloPay refund request failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'ZaloPay refund response could not be confirmed.'];
        }
    }

    /** @return array<string, mixed> */
    public function queryRefund(string $refundId): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'ZaloPay credentials are not configured.'];
        }

        $timestamp = (int) round(microtime(true) * 1000);
        $macInput = "{$this->appId}|{$refundId}|{$timestamp}";
        try {
            $response = Http::timeout(30)->asForm()->post($this->refundQueryEndpoint, [
                'app_id' => (int) $this->appId,
                'm_refund_id' => $refundId,
                'timestamp' => $timestamp,
                'mac' => hash_hmac('sha256', $macInput, $this->key1),
            ]);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: ['transport_error' => true, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('ZaloPay refund query failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'ZaloPay refund status could not be confirmed.'];
        }
    }
}
