<?php

class MoMoService
{
    private string $partnerCode;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;

    public function __construct()
    {
        $this->partnerCode = getenv('MOMO_PARTNER_CODE') ?: '';
        $this->accessKey = getenv('MOMO_ACCESS_KEY') ?: '';
        $this->secretKey = getenv('MOMO_SECRET_KEY') ?: '';

        $this->endpoint = getenv('MOMO_ENDPOINT')
            ?: 'https://test-payment.momo.vn/v2/gateway/api/create';
    }

    public function createPayment(
        string $transactionCode,
        int $amount,
        string $orderInfo
    ): array {
        if (
            $this->partnerCode === '' ||
            $this->accessKey === '' ||
            $this->secretKey === ''
        ) {
            throw new RuntimeException('Chưa cấu hình MoMo');
        }

        $redirectUrl = getenv('MOMO_REDIRECT_URL') ?: '';
        $ipnUrl = getenv('MOMO_IPN_URL') ?: '';

        if ($redirectUrl === '' || $ipnUrl === '') {
            throw new RuntimeException('Thiếu URL MoMo');
        }

        $requestId = $transactionCode;
        $requestType = 'payWithMethod';
        $extraData = '';

        $rawHash = "accessKey={$this->accessKey}"
            . "&amount={$amount}"
            . "&extraData={$extraData}"
            . "&ipnUrl={$ipnUrl}"
            . "&orderId={$transactionCode}"
            . "&orderInfo={$orderInfo}"
            . "&partnerCode={$this->partnerCode}"
            . "&redirectUrl={$redirectUrl}"
            . "&requestId={$requestId}"
            . "&requestType={$requestType}";

        $signature = hash_hmac(
            'sha256',
            $rawHash,
            $this->secretKey
        );

        $body = [
            'partnerCode' => $this->partnerCode,
            'accessKey' => $this->accessKey,
            'requestId' => $requestId,
            'amount' => (string)$amount,
            'orderId' => $transactionCode,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
            'lang' => 'vi'
        ];

        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 || !is_array($result)) {
            throw new RuntimeException('MoMo không trả về phản hồi hợp lệ');
        }

        return $result;
    }

    public function verify(array $data): bool
    {
        if ($this->secretKey === '') {
            return false;
        }

        $fields = [
            'accessKey',
            'amount',
            'extraData',
            'message',
            'orderId',
            'orderInfo',
            'orderType',
            'partnerCode',
            'payType',
            'requestId',
            'responseTime',
            'resultCode',
            'transId'
        ];

        $parts = [];

        foreach ($fields as $field) {
            $parts[] = $field . '=' . ($data[$field] ?? '');
        }

        $rawHash = implode('&', $parts);

        $calculated = hash_hmac(
            'sha256',
            $rawHash,
            $this->secretKey
        );

        $received = $data['signature'] ?? '';

        return $received !== '' &&
            hash_equals($calculated, $received);
    }
}