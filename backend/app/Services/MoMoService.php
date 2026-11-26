<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoMoService
{
    private string $partnerCode;

    private string $accessKey;

    private string $secretKey;

    private string $endpoint;

    private string $queryEndpoint;

    private string $refundEndpoint;

    private string $refundQueryEndpoint;

    private string $returnUrl;

    private string $callbackUrl;

    public function __construct()
    {
        $this->partnerCode = (string) config('momo.partner_code', '');
        $this->accessKey = (string) config('momo.access_key', '');
        $this->secretKey = (string) config('momo.secret_key', '');
        $this->endpoint = (string) config('momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');
        $this->queryEndpoint = (string) config('momo.query_endpoint', 'https://test-payment.momo.vn/v2/gateway/api/query');
        $this->refundEndpoint = (string) config('momo.refund_endpoint', 'https://test-payment.momo.vn/v2/gateway/api/refund');
        $this->refundQueryEndpoint = (string) config('momo.refund_query_endpoint', 'https://test-payment.momo.vn/v2/gateway/api/refund/query');
        $this->returnUrl = (string) config('momo.return_url', 'http://localhost:5173/payment/momo/return');
        $this->callbackUrl = (string) config('momo.callback_url', 'http://localhost:8000/api/payments/momo/callback');
    }

    public function isConfigured(): bool
    {
        return $this->partnerCode !== '' && $this->accessKey !== '' && $this->secretKey !== '';
    }

    /**
     * Create MoMo payment order and generate payment redirect URL.
     *
     * @param array{
     *     order_id: int|string,
     *     order_code: string,
     *     amount: int|float,
     *     payment_reference?: string,
     *     order_info?: string,
     *     extra_data?: string,
     *     expires_in_minutes?: int
     * } $data
     * @return array<string, mixed>
     */
    public function createPaymentOrder(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'message' => 'MoMo credentials are not configured.'];
        }
        $amount = (string) ((int) round((float) $data['amount']));
        $attemptSuffix = ! empty($data['payment_reference'])
            ? substr(hash('sha256', (string) $data['payment_reference']), 0, 6)
            : substr(uniqid(), -6);
        $orderId = 'MX_MM_'.((int) $data['order_id']).'_'.$attemptSuffix;
        $requestId = 'REQ_MM_'.((int) $data['order_id']).'_'.$attemptSuffix;
        $orderInfo = (string) ($data['order_info'] ?? ('MobieX - Thanh toan don hang '.$data['order_code']));
        $extraData = (string) ($data['extra_data'] ?? '');
        $requestType = 'captureWallet';

        // Raw signature order: accessKey, amount, extraData, ipnUrl, orderId, orderInfo, partnerCode, redirectUrl, requestId, requestType
        $rawHash = "accessKey={$this->accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$this->callbackUrl}&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$this->partnerCode}&redirectUrl={$this->returnUrl}&requestId={$requestId}&requestType={$requestType}";
        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);

        $payload = [
            'partnerCode' => $this->partnerCode,
            'partnerName' => 'MobieX Store',
            'storeId' => 'MobieX_Store',
            'requestId' => $requestId,
            'amount' => (int) $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->returnUrl,
            'ipnUrl' => $this->callbackUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
        ];
        if (isset($data['expires_in_minutes'])) {
            $payload['orderExpireTime'] = (int) $data['expires_in_minutes'];
        }

        try {
            $response = Http::retry(
                2,
                250,
                static fn (\Exception $exception): bool => $exception instanceof ConnectionException,
                throw: false
            )
                ->timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Accept' => 'application/json',
                ])
                ->post($this->endpoint, $payload);

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && ($body['resultCode'] ?? null) === 0 && ! empty($body['payUrl'])) {
                    return [
                        'status' => 'success',
                        'order_url' => $body['payUrl'],
                        'pay_url' => $body['payUrl'],
                        'qr_code_url' => $body['qrCodeUrl'] ?? null,
                        'deeplink' => $body['deeplink'] ?? null,
                        'order_id' => $orderId,
                        'request_id' => $requestId,
                        'result_code' => 0,
                        'message' => $body['message'] ?? 'Thành công',
                    ];
                }

                $resultCode = is_array($body) ? ($body['resultCode'] ?? 'unknown') : 'invalid_response';
                $gatewayMessage = is_array($body) ? ($body['message'] ?? 'MoMo không trả về thông tin thanh toán hợp lệ.') : 'MoMo trả về dữ liệu không hợp lệ.';
                Log::warning('MoMo create payment rejected', [
                    'http_status' => $response->status(),
                    'result_code' => $resultCode,
                    'message' => $gatewayMessage,
                ]);

                return [
                    'status' => 'failed',
                    'message' => "MoMo từ chối tạo giao dịch (mã {$resultCode}): {$gatewayMessage}",
                ];
            }

            $body = $response->json();
            $resultCode = is_array($body) ? ($body['resultCode'] ?? 'unknown') : 'no_result_code';
            $gatewayMessage = is_array($body) ? ($body['message'] ?? $response->body()) : $response->body();
            Log::warning('MoMo create payment HTTP error', [
                'http_status' => $response->status(),
                'result_code' => $resultCode,
                'message' => mb_substr((string) $gatewayMessage, 0, 500),
            ]);

            return [
                'status' => 'failed',
                'message' => "Cổng thanh toán MoMo từ chối yêu cầu (HTTP {$response->status()} · mã {$resultCode}). Vui lòng kiểm tra cấu hình merchant MoMo hoặc thử phương thức khác.",
            ];
        } catch (\Throwable $e) {
            Log::warning('MoMo create payment connection exception', [
                'exception' => class_basename($e),
                'message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            return [
                'status' => 'failed',
                'error_code' => 'provider_unreachable',
                'message' => 'Backend không kết nối được MoMo Sandbox. Kiểm tra mạng outbound, firewall hoặc proxy trên máy chạy API.',
            ];
        }

        return [
            'status' => 'failed',
            'message' => 'MoMo Sandbox không khả dụng. Kiểm tra thông tin merchant sandbox và callback HTTPS công khai.',
        ];
    }

    /**
     * Verify Return Redirect Signature from MoMo.
     * Raw hash sequence: accessKey, amount, extraData, message, orderId, orderInfo, orderType, partnerCode, payType, requestId, responseTime, resultCode, transId
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyReturnSignature(array $params): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $signature = $params['signature'] ?? null;
        if (! $signature || ! is_string($signature)) {
            return false;
        }

        $partnerCode = (string) ($params['partnerCode'] ?? $this->partnerCode);
        $orderId = (string) ($params['orderId'] ?? '');
        $requestId = (string) ($params['requestId'] ?? '');
        $amount = (string) ($params['amount'] ?? '0');
        $orderInfo = (string) ($params['orderInfo'] ?? '');
        $orderType = (string) ($params['orderType'] ?? '');
        $transId = (string) ($params['transId'] ?? '');
        $resultCode = (string) ($params['resultCode'] ?? '');
        $message = (string) ($params['message'] ?? '');
        $payType = (string) ($params['payType'] ?? '');
        $responseTime = (string) ($params['responseTime'] ?? '');
        $extraData = (string) ($params['extraData'] ?? '');

        $rawHash = "accessKey={$this->accessKey}&amount={$amount}&extraData={$extraData}&message={$message}&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}&transId={$transId}";
        $expected = hash_hmac('sha256', $rawHash, $this->secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify IPN Webhook Callback Signature from MoMo.
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyCallbackSignature(array $params): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->verifyReturnSignature($params);
    }

    /**
     * Query order status from MoMo Gateway.
     * Raw hash sequence: accessKey, orderId, partnerCode, requestId
     */
    public function queryOrderStatus(string $orderId, string $requestId): array
    {
        if (! $this->isConfigured()) {
            return ['resultCode' => 99, 'transport_error' => true, 'message' => 'MoMo credentials are not configured.'];
        }

        $rawHash = "accessKey={$this->accessKey}&orderId={$orderId}&partnerCode={$this->partnerCode}&requestId={$requestId}";
        $signature = hash_hmac('sha256', $rawHash, $this->secretKey);

        try {
            $response = Http::timeout(10)->acceptJson()->post($this->queryEndpoint, [
                'partnerCode' => $this->partnerCode,
                'requestId' => $requestId,
                'orderId' => $orderId,
                'signature' => $signature,
                'lang' => 'vi',
            ]);

            if ($response->successful()) {
                return $response->json() ?: ['resultCode' => 99, 'transport_error' => true, 'message' => 'Empty response from MoMo.'];
            }
        } catch (\Throwable $e) {
            Log::warning('MoMo Query Order Exception: '.$e->getMessage());
        }

        return [
            'resultCode' => 99,
            'transport_error' => true,
            'message' => 'Không thể kết nối truy vấn trạng thái MoMo.',
        ];
    }

    /** @return array<string, mixed> */
    public function requestRefund(string $orderId, string $requestId, string $transId, int $amount, string $description): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'MoMo credentials are not configured.'];
        }

        $rawHash = "accessKey={$this->accessKey}&amount={$amount}&description={$description}&orderId={$orderId}&partnerCode={$this->partnerCode}&requestId={$requestId}&transId={$transId}";
        $payload = [
            'partnerCode' => $this->partnerCode,
            'orderId' => $orderId,
            'requestId' => $requestId,
            'amount' => $amount,
            'transId' => (int) $transId,
            'lang' => 'vi',
            'description' => $description,
            'signature' => hash_hmac('sha256', $rawHash, $this->secretKey),
        ];

        try {
            $response = Http::timeout(30)->acceptJson()->post($this->refundEndpoint, $payload);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: [
                'transport_error' => true,
                'http_status' => $response->status(),
                'message' => 'MoMo returned an invalid refund response.',
            ];
        } catch (\Throwable $e) {
            Log::warning('MoMo refund request failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'MoMo refund response could not be confirmed.'];
        }
    }

    /** @return array<string, mixed> */
    public function queryRefund(string $orderId, string $requestId): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'MoMo credentials are not configured.'];
        }

        $rawHash = "accessKey={$this->accessKey}&orderId={$orderId}&partnerCode={$this->partnerCode}&requestId={$requestId}";
        try {
            $response = Http::timeout(30)->acceptJson()->post($this->refundQueryEndpoint, [
                'partnerCode' => $this->partnerCode,
                'orderId' => $orderId,
                'requestId' => $requestId,
                'lang' => 'vi',
                'signature' => hash_hmac('sha256', $rawHash, $this->secretKey),
            ]);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: ['transport_error' => true, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('MoMo refund query failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'MoMo refund status could not be confirmed.'];
        }
    }
}
