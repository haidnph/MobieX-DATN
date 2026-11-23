<?php

namespace Tests\Unit\Services;

use App\Services\MoMoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoMoServiceTest extends TestCase
{
    private MoMoService $service;

    public function test_create_payment_order_reports_provider_unreachable_separately(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => function () {
                throw new ConnectionException('Gateway unavailable');
            },
        ]);

        $result = $this->service->createPaymentOrder([
            'order_id' => 999,
            'order_code' => 'MX-MM-TEST-001',
            'amount' => 1250000,
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('provider_unreachable', $result['error_code']);
        $this->assertStringContainsString('mạng outbound', $result['message']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'momo.partner_code' => 'MOMOBKUN20180529',
            'momo.access_key' => 'klm0567shj9qRrA',
            'momo.secret_key' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'momo.query_endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/query',
            'momo.return_url' => 'http://localhost:5173/payment/momo/return',
            'momo.callback_url' => 'http://localhost:8000/api/payments/momo/callback',
        ]);

        $this->service = new MoMoService;
    }

    public function test_create_payment_order_generates_valid_structure_and_signature(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => Http::response([
                'partnerCode' => 'MOMOBKUN20180529',
                'orderId' => 'MX_MM_999_123456',
                'requestId' => 'REQ_MM_999_123456',
                'amount' => 1250000,
                'responseTime' => 1794300000000,
                'message' => 'Thành công.',
                'resultCode' => 0,
                'payUrl' => 'https://test-payment.momo.vn/v2/gateway/pay?token=TEST_MOMO_TOKEN',
            ], 200),
        ]);

        $result = $this->service->createPaymentOrder([
            'order_id' => 999,
            'order_code' => 'MX-MM-TEST-001',
            'amount' => 1250000,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['result_code']);
        $this->assertNotEmpty($result['order_url']);
        $this->assertNotEmpty($result['order_id']);
        $this->assertStringContainsString('MX_MM_999_', $result['order_id']);
    }

    public function test_create_payment_order_reuses_provider_reference_for_same_payment_attempt(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => function ($request) {
                return Http::response([
                    'resultCode' => 0,
                    'orderId' => $request['orderId'],
                    'requestId' => $request['requestId'],
                    'payUrl' => 'https://test-payment.momo.vn/pay',
                ]);
            },
        ]);
        $attempt = [
            'order_id' => 999,
            'order_code' => 'MX-MM-TEST-001',
            'amount' => 1250000,
            'payment_reference' => 'PAY-MM-STABLE-ATTEMPT',
        ];

        $first = $this->service->createPaymentOrder($attempt);
        $second = $this->service->createPaymentOrder($attempt);

        $this->assertSame($first['order_id'], $second['order_id']);
        $this->assertSame($first['request_id'], $second['request_id']);
    }

    public function test_verify_return_signature_validates_correctly(): void
    {
        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm0567shj9qRrA';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderId = 'MX_MM_999_123456';
        $requestId = 'REQ_MM_999_123456';
        $amount = '1250000';
        $orderInfo = 'Thanh toan don hang';
        $orderType = 'momo_wallet';
        $transId = '2345678901';
        $resultCode = '0';
        $message = 'Giao dich thanh cong.';
        $payType = 'qr';
        $responseTime = '1794300000000';
        $extraData = '';

        $rawHash = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&message={$message}&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}&transId={$transId}";
        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $params = [
            'partnerCode' => $partnerCode,
            'orderId' => $orderId,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderInfo' => $orderInfo,
            'orderType' => $orderType,
            'transId' => $transId,
            'resultCode' => $resultCode,
            'message' => $message,
            'payType' => $payType,
            'responseTime' => $responseTime,
            'extraData' => $extraData,
            'signature' => $signature,
        ];

        $this->assertTrue($this->service->verifyReturnSignature($params));
    }

    public function test_verify_return_signature_rejects_tampered_amount(): void
    {
        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm0567shj9qRrA';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderId = 'MX_MM_999_123456';
        $requestId = 'REQ_MM_999_123456';
        $amount = '1250000';
        $orderInfo = 'Thanh toan don hang';
        $orderType = 'momo_wallet';
        $transId = '2345678901';
        $resultCode = '0';
        $message = 'Giao dich thanh cong.';
        $payType = 'qr';
        $responseTime = '1794300000000';
        $extraData = '';

        $rawHash = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&message={$message}&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}&transId={$transId}";
        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $params = [
            'partnerCode' => $partnerCode,
            'orderId' => $orderId,
            'requestId' => $requestId,
            'amount' => '999999999', // Tampered!
            'orderInfo' => $orderInfo,
            'orderType' => $orderType,
            'transId' => $transId,
            'resultCode' => $resultCode,
            'message' => $message,
            'payType' => $payType,
            'responseTime' => $responseTime,
            'extraData' => $extraData,
            'signature' => $signature,
        ];

        $this->assertFalse($this->service->verifyReturnSignature($params));
    }

    public function test_verify_callback_signature_validates_correctly(): void
    {
        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm0567shj9qRrA';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderId = 'MX_MM_999_123456';
        $requestId = 'REQ_MM_999_123456';
        $amount = '1250000';
        $orderInfo = 'Thanh toan don hang';
        $orderType = 'momo_wallet';
        $transId = '2345678901';
        $resultCode = '0';
        $message = 'Giao dich thanh cong.';
        $payType = 'qr';
        $responseTime = '1794300000000';
        $extraData = '';

        $rawHash = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&message={$message}&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}&transId={$transId}";
        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $payload = [
            'partnerCode' => $partnerCode,
            'orderId' => $orderId,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderInfo' => $orderInfo,
            'orderType' => $orderType,
            'transId' => $transId,
            'resultCode' => $resultCode,
            'message' => $message,
            'payType' => $payType,
            'responseTime' => $responseTime,
            'extraData' => $extraData,
            'signature' => $signature,
        ];

        $this->assertTrue($this->service->verifyCallbackSignature($payload));

        $payload['signature'] = 'invalid_momo_signature';
        $this->assertFalse($this->service->verifyCallbackSignature($payload));
    }

    public function test_missing_credentials_reject_callback_and_payment_creation(): void
    {
        config(['momo.partner_code' => '', 'momo.access_key' => '', 'momo.secret_key' => '']);
        $service = new MoMoService;

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->verifyCallbackSignature(['signature' => hash_hmac('sha256', '', '')]));
        $result = $service->createPaymentOrder([
            'order_id' => 1,
            'order_code' => 'MX-TEST',
            'amount' => 1000,
        ]);
        $this->assertSame('failed', $result['status']);
    }
}
