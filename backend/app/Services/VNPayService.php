<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VNPayService
{
    private string $tmnCode;

    private string $hashSecret;

    private string $vnpUrl;

    private string $transactionApiUrl;

    private string $returnUrl;

    public function __construct()
    {
        $this->tmnCode = (string) config('vnpay.tmn_code', '');
        $this->hashSecret = (string) config('vnpay.hash_secret', '');
        $this->vnpUrl = (string) config('vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->transactionApiUrl = (string) config('vnpay.transaction_api_url', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction');
        $this->returnUrl = (string) config('vnpay.return_url', 'http://localhost:5173/payment/vnpay/return');
    }

    public function isConfigured(): bool
    {
        return $this->tmnCode !== '' && $this->hashSecret !== '';
    }

    /**
     * Build the secure VNPay payment URL for redirection.
     *
     * @param array{
     *     order_code: string,
     *     amount: int|float,
     *     order_info?: string,
     *     ip_address?: string,
     *     return_url?: string,
     *     bank_code?: string,
     *     expires_at?: string|\DateTimeInterface
     * } $data
     */
    public function createPaymentUrl(array $data): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('VNPay credentials are not configured.');
        }
        $vnpAmount = (int) round(((float) $data['amount']) * 100);
        $vnpTxnRef = (string) $data['order_code'];
        $vnpOrderInfo = (string) ($data['order_info'] ?? ('Thanh toán đơn hàng '.$vnpTxnRef));
        $vnpIpAddr = (string) ($data['ip_address'] ?? '127.0.0.1');
        $returnUrl = (string) ($data['return_url'] ?? $this->returnUrl);

        $now = now('Asia/Ho_Chi_Minh');
        $createDate = (string) ($data['create_date'] ?? $now->format('YmdHis'));
        $expireAt = $now->copy()->addMinutes(20);
        if (! empty($data['expires_at'])) {
            $reservationExpiry = Carbon::parse($data['expires_at'])->setTimezone('Asia/Ho_Chi_Minh');
            if ($reservationExpiry->lt($expireAt)) {
                $expireAt = $reservationExpiry;
            }
        }
        $expireDate = $expireAt->format('YmdHis');

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_Amount' => (string) $vnpAmount,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => $createDate,
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $vnpIpAddr,
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $vnpOrderInfo,
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $vnpTxnRef,
            'vnp_ExpireDate' => $expireDate,
        ];

        if (! empty($data['bank_code'])) {
            $inputData['vnp_BankCode'] = (string) $data['bank_code'];
        }

        ksort($inputData);

        $query = '';
        $i = 0;
        $hashdata = '';
        foreach ($inputData as $key => $value) {
            if ($i === 1) {
                $hashdata .= '&'.urlencode($key).'='.urlencode((string) $value);
            } else {
                $hashdata .= urlencode($key).'='.urlencode((string) $value);
                $i = 1;
            }
            $query .= urlencode($key).'='.urlencode((string) $value).'&';
        }

        $vnpUrl = $this->vnpUrl.'?'.$query;
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->hashSecret);
        $vnpUrl .= 'vnp_SecureHash='.$vnpSecureHash;

        return $vnpUrl;
    }

    /**
     * Verify VNPay HMAC-SHA512 callback / IPN signature.
     *
     * @param  array<string, mixed>  $inputData
     */
    public function verifySignature(array $inputData): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $vnpSecureHash = $inputData['vnp_SecureHash'] ?? null;
        if (! $vnpSecureHash || ! is_string($vnpSecureHash)) {
            return false;
        }

        $params = [];
        foreach ($inputData as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
                $params[$key] = (string) $value;
            }
        }

        ksort($params);

        $i = 0;
        $hashData = '';
        foreach ($params as $key => $value) {
            if ($i === 1) {
                $hashData .= '&'.urlencode($key).'='.urlencode((string) $value);
            } else {
                $hashData .= urlencode($key).'='.urlencode((string) $value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->hashSecret);

        return hash_equals($secureHash, (string) $vnpSecureHash);
    }

    /** @return array<string, mixed> */
    public function requestRefund(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'VNPay credentials are not configured.'];
        }

        $params = [
            'vnp_RequestId' => $data['request_id'],
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_TransactionType' => '02',
            'vnp_TxnRef' => $data['transaction_reference'],
            'vnp_Amount' => (string) ((int) round(((float) $data['amount']) * 100)),
            'vnp_TransactionNo' => (string) $data['provider_transaction_id'],
            'vnp_TransactionDate' => $data['transaction_date'],
            'vnp_CreateBy' => mb_substr((string) $data['created_by'], 0, 245),
            'vnp_CreateDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_IpAddr' => (string) ($data['ip_address'] ?? '127.0.0.1'),
            'vnp_OrderInfo' => mb_substr((string) $data['description'], 0, 255),
        ];
        $signed = implode('|', [
            $params['vnp_RequestId'], $params['vnp_Version'], $params['vnp_Command'], $params['vnp_TmnCode'],
            $params['vnp_TransactionType'], $params['vnp_TxnRef'], $params['vnp_Amount'], $params['vnp_TransactionNo'],
            $params['vnp_TransactionDate'], $params['vnp_CreateBy'], $params['vnp_CreateDate'], $params['vnp_IpAddr'],
            $params['vnp_OrderInfo'],
        ]);
        $params['vnp_SecureHash'] = hash_hmac('sha512', $signed, $this->hashSecret);

        try {
            $response = Http::timeout(30)->acceptJson()->post($this->transactionApiUrl, $params);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: ['transport_error' => true, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('VNPay refund request failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'VNPay refund response could not be confirmed.'];
        }
    }

    /** @return array<string, mixed> */
    public function queryRefund(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['transport_error' => true, 'message' => 'VNPay credentials are not configured.'];
        }

        $params = [
            'vnp_RequestId' => $data['query_request_id'],
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_TxnRef' => $data['transaction_reference'],
            'vnp_TransactionDate' => $data['transaction_date'],
            'vnp_CreateDate' => now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_IpAddr' => (string) ($data['ip_address'] ?? '127.0.0.1'),
            'vnp_OrderInfo' => mb_substr((string) $data['description'], 0, 255),
        ];
        $signed = implode('|', [
            $params['vnp_RequestId'], $params['vnp_Version'], $params['vnp_Command'], $params['vnp_TmnCode'],
            $params['vnp_TxnRef'], $params['vnp_TransactionDate'], $params['vnp_CreateDate'], $params['vnp_IpAddr'],
            $params['vnp_OrderInfo'],
        ]);
        $params['vnp_SecureHash'] = hash_hmac('sha512', $signed, $this->hashSecret);

        try {
            $response = Http::timeout(30)->acceptJson()->post($this->transactionApiUrl, $params);
            if (! $response->successful()) {
                return ['transport_error' => true, 'http_status' => $response->status()];
            }

            return $response->json() ?: ['transport_error' => true, 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('VNPay refund status query failed', ['exception' => class_basename($e)]);

            return ['transport_error' => true, 'message' => 'VNPay refund status could not be confirmed.'];
        }
    }

    /**
     * Query VNPay transaction status (querydr) server-to-server for delayed IPN verification.
     *
     * @param  array{
     *     request_id: string,
     *     transaction_reference: string,
     *     transaction_date: string,
     *     ip_address?: string,
     *     description?: string,
     * }  $data
     * @return array<string, mixed>
     */
    public function queryTransactionStatus(array $data): array
    {
        return $this->queryRefund([
            'query_request_id' => $data['request_id'],
            'transaction_reference' => $data['transaction_reference'],
            'transaction_date' => $data['transaction_date'],
            'ip_address' => $data['ip_address'] ?? '127.0.0.1',
            'description' => $data['description'] ?? ('Query order '.$data['transaction_reference']),
        ]);
    }

    /** @param array<string, mixed> $response */
    public function verifyTransactionApiResponse(array $response): bool
    {
        return $this->verifyRefundApiResponse($response);
    }

    /** @param array<string, mixed> $response */
    public function verifyRefundApiResponse(array $response): bool
    {
        if (! $this->isConfigured() || ! is_string($response['vnp_SecureHash'] ?? null)) {
            return false;
        }

        $fields = [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef',
            'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
            'vnp_TransactionStatus', 'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount',
        ];
        $signed = implode('|', array_map(static fn (string $field): string => (string) ($response[$field] ?? ''), $fields));
        $expected = hash_hmac('sha512', $signed, $this->hashSecret);

        return hash_equals($expected, $response['vnp_SecureHash']);
    }
}
