
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MoMoService
{
    private string $partnerCode;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;
    private string $returnUrl;
    private string $notifyUrl;

    public function __construct()
    {
        $this->partnerCode = (string) config('momo.partner_code');
        $this->accessKey = (string) config('momo.access_key');
        $this->secretKey = (string) config('momo.secret_key');

        $this->endpoint = (string) config(
            'momo.endpoint',
            'https://test-payment.momo.vn/v2/gateway/api/create'
        );

        $this->returnUrl = (string) config('momo.return_url');
        $this->notifyUrl = (string) config('momo.notify_url');
    }

    /**
     * Create MoMo payment URL.
     */
    public function createPaymentUrl(array $data): array
    {
        $orderId = (string) $data['order_code'];
        $amount = (string) (int) $data['amount'];
        $requestId = (string) ($data['request_id'] ?? uniqid('momo_'));

        $orderInfo = (string) (
            $data['order_info'] ?? 'Thanh toan don hang '.$orderId
        );

        $requestType = 'captureWallet';
        $extraData = '';

        $rawHash = "accessKey={$this->accessKey}"
            ."&amount={$amount}"
            ."&extraData={$extraData}"
            ."&ipnUrl={$this->notifyUrl}"
            ."&orderId={$orderId}"
            ."&orderInfo={$orderInfo}"
            ."&partnerCode={$this->partnerCode}"
            ."&redirectUrl={$this->returnUrl}"
            ."&requestId={$requestId}"
            ."&requestType={$requestType}";

        $signature = hash_hmac(
            'sha256',
            $rawHash,
            $this->secretKey
        );

        $response = Http::timeout(30)->post($this->endpoint, [
            'partnerCode' => $this->partnerCode,
            'accessKey' => $this->accessKey,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->returnUrl,
            'ipnUrl' => $this->notifyUrl,
            'requestType' => $requestType,
            'extraData' => $extraData,
            'lang' => 'vi',
            'signature' => $signature,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Không thể kết nối MoMo.');
        }

        $result = $response->json();

        if (!isset($result['payUrl']) || ($result['resultCode'] ?? -1) != 0) {
            throw new \RuntimeException(
                $result['message'] ?? 'Tạo thanh toán MoMo thất bại.'
            );
        }

        return $result;
    }

    /**
     * Verify MoMo IPN signature.
     */
    public function verifySignature(array $data): bool
    {
        $required = [
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
            'transId',
            'signature',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                return false;
            }
        }

        $rawHash = 'accessKey='.$this->accessKey
            .'&amount='.$data['amount']
            .'&extraData='.$data['extraData']
            .'&message='.$data['message']
            .'&orderId='.$data['orderId']
            .'&orderInfo='.$data['orderInfo']
            .'&orderType='.$data['orderType']
            .'&partnerCode='.$data['partnerCode']
            .'&payType='.$data['payType']
            .'&requestId='.$data['requestId']
            .'&responseTime='.$data['responseTime']
            .'&resultCode='.$data['resultCode']
            .'&transId='.$data['transId'];

        $signature = hash_hmac(
            'sha256',
            $rawHash,
            $this->secretKey
        );

        return hash_equals(
            $signature,
            (string) $data['signature']
        );
    }
}
