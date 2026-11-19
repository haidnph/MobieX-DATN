
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZaloPayService
{
    private string $appId;

    private string $key1;

    private string $key2;

    private string $zalopayUrl;

    private string $returnUrl;

    private string $callbackUrl;

    public function __construct()
    {
        $this->appId = (string) config('zalopay.app_id', '');
        $this->key1 = (string) config('zalopay.key1', '');
        $this->key2 = (string) config('zalopay.key2', '');
        $this->zalopayUrl = (string) config(
            'zalopay.endpoint',
            'https://sb-openapi.zalopay.vn/v2/create'
        );
        $this->returnUrl = (string) config(
            'zalopay.redirect_url',
            'http://localhost:5173/payment/zalopay/return'
        );
        $this->callbackUrl = (string) config(
            'zalopay.callback_url',
            'http://localhost:8000/api/payment/zalopay/callback'
        );
    }

    /**
     * Build the secure ZaloPay payment URL for redirection.
     *
     * @param array{
     *     order_code: string,
     *     amount: int|float,
     *     order_info?: string,
     *     app_user?: string
     * } $data
     */
    public function createPaymentUrl(array $data): string
    {
        $appId = (int) $this->appId;
        $appTransId = date('ymd').'_'.uniqid();
        $appUser = (string) ($data['app_user'] ?? 'customer');
        $amount = (int) round((float) $data['amount']);
        $appTime = (int) round(microtime(true) * 1000);

        $description = (string) (
            $data['order_info'] ?? 'Thanh toán đơn hàng '.$data['order_code']
        );

        $embedData = json_encode([
            'redirecturl' => $this->returnUrl,
        ], JSON_UNESCAPED_SLASHES);

        $item = json_encode([]);

        $inputData = [
            'app_id' => $appId,
            'app_trans_id' => $appTransId,
            'app_user' => $appUser,
            'app_time' => $appTime,
            'amount' => $amount,
            'item' => $item,
            'description' => $description,
            'embed_data' => $embedData,
            'callback_url' => $this->callbackUrl,
        ];

        $inputData['mac'] = hash_hmac(
            'sha256',
            $appId.'|'.$appTransId.'|'.$appUser.'|'.$amount
                .'|'.$appTime.'|'.$embedData.'|'.$item,
            $this->key1
        );

        $response = Http::asForm()
            ->timeout(30)
            ->post($this->zalopayUrl, $inputData);

        if (! $response->successful()) {
            throw new \RuntimeException('Không thể kết nối ZaloPay.');
        }

        $result = $response->json();

        if (
            ! is_array($result) ||
            ($result['return_code'] ?? 0) !== 1 ||
            empty($result['order_url'])
        ) {
            throw new \RuntimeException(
                $result['return_message'] ?? 'Tạo thanh toán ZaloPay thất bại.'
            );
        }

        return $result['order_url'];
    }

    /**
     * Verify ZaloPay HMAC-SHA256 callback signature.
     *
     * @param array<string, mixed> $inputData
     */
    public function verifySignature(array $inputData): bool
    {
        $data = $inputData['data'] ?? null;
        $mac = $inputData['mac'] ?? null;

        if (
            ! is_string($data) ||
            ! is_string($mac) ||
            $data === '' ||
            $mac === ''
        ) {
            return false;
        }

        $secureHash = hash_hmac(
            'sha256',
            $data,
            $this->key2
        );

        return hash_equals($secureHash, $mac);
    }
}
