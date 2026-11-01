<?php

class VNPayService
{
    private string $tmnCode;
    private string $hashSecret;
    private string $url;
    private string $returnUrl;

    public function __construct()
    {
        $this->tmnCode = getenv('VNPAY_TMN_CODE') ?: '';
        $this->hashSecret = getenv('VNPAY_HASH_SECRET') ?: '';

        $this->url = getenv('VNPAY_URL')
            ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

        $this->returnUrl = getenv('VNPAY_RETURN_URL') ?: '';
    }

    public function createPayment(
        string $transactionCode,
        int $amount,
        string $orderInfo
    ): string {
        if (
            $this->tmnCode === '' ||
            $this->hashSecret === '' ||
            $this->returnUrl === ''
        ) {
            throw new RuntimeException('Chưa cấu hình VNPAY');
        }

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_Amount' => $amount * 100,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $transactionCode,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $this->returnUrl,
            'vnp_IpAddr' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'vnp_CreateDate' => date('YmdHis')
        ];

        ksort($params);

        $query = [];
        $hashData = [];

        foreach ($params as $key => $value) {
            $hashData[] = urlencode($key) . '=' . urlencode((string)$value);
            $query[] = urlencode($key) . '=' . urlencode((string)$value);
        }

        $hashString = implode('&', $hashData);

        $secureHash = hash_hmac(
            'sha512',
            $hashString,
            $this->hashSecret
        );

        return $this->url . '?' .
            implode('&', $query) .
            '&vnp_SecureHash=' . $secureHash;
    }

    public function verify(array $data): bool
    {
        if ($this->hashSecret === '') {
            return false;
        }

        $receivedHash = $data['vnp_SecureHash'] ?? '';

        unset(
            $data['vnp_SecureHash'],
            $data['vnp_SecureHashType']
        );

        ksort($data);

        $hashData = [];

        foreach ($data as $key => $value) {
            $hashData[] = urlencode($key) . '=' . urlencode((string)$value);
        }

        $calculatedHash = hash_hmac(
            'sha512',
            implode('&', $hashData),
            $this->hashSecret
        );

        return $receivedHash !== '' &&
            hash_equals($calculatedHash, $receivedHash);
    }
}