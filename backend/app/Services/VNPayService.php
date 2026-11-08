<?php

namespace App\Services;

class VNPayService
{
    private string $tmnCode;

    private string $hashSecret;

    private string $vnpUrl;

    private string $returnUrl;

    public function __construct()
    {
        $this->tmnCode = (string) config('vnpay.tmn_code', 'SANDBOX_TMN');
        $this->hashSecret = (string) config('vnpay.hash_secret', 'SANDBOX_HASH_SECRET');
        $this->vnpUrl = (string) config('vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->returnUrl = (string) config('vnpay.return_url', 'http://localhost:5173/payment/vnpay/return');
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
     *     bank_code?: string
     * } $data
     */
    public function createPaymentUrl(array $data): string
    {
        $vnpAmount = (int) round(((float) $data['amount']) * 100);
        $vnpTxnRef = (string) $data['order_code'];
        $vnpOrderInfo = (string) ($data['order_info'] ?? ('Thanh toán đơn hàng '.$vnpTxnRef));
        $vnpIpAddr = (string) ($data['ip_address'] ?? '127.0.0.1');
        $returnUrl = (string) ($data['return_url'] ?? $this->returnUrl);

        $createDate = date('YmdHis');
        $expireDate = date('YmdHis', strtotime('+15 minutes'));

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
}
