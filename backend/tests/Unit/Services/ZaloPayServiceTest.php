<?php

namespace Tests\Unit\Services;

use App\Services\ZaloPayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZaloPayServiceTest extends TestCase
{
    private ZaloPayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zalopay.app_id' => '2554',
            'zalopay.key1' => 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn',
            'zalopay.key2' => 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf',
            'zalopay.endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
            'zalopay.query_endpoint' => 'https://sb-openapi.zalopay.vn/v2/query',
            'zalopay.return_url' => 'http://localhost:5173/payment/zalopay/return',
            'zalopay.callback_url' => 'http://localhost:8000/api/payments/zalopay/callback',
        ]);

        $this->service = new ZaloPayService;
    }

    public function test_create_payment_order_generates_valid_structure_and_app_trans_id(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response([
                'return_code' => 1,
                'return_message' => 'Success',
                'order_url' => 'https://sb-openapi.zalopay.vn/gateway?token=TEST_ZALOPAY_TOKEN',
                'zp_trans_token' => 'TEST_ZP_TOKEN_123',
            ], 200),
        ]);

        $result = $this->service->createPaymentOrder([
            'order_id' => 999,
            'order_code' => 'MX-TEST-001',
            'amount' => 1250000,
            'app_user' => 'customer_test',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['return_code']);
        $this->assertNotEmpty($result['app_trans_id']);
        // app_trans_id format: yymmdd_<order_id>_<random>
        $this->assertStringContainsString('_999_', $result['app_trans_id']);
        $this->assertNotEmpty($result['order_url']);
    }

    public function test_create_payment_order_reuses_provider_reference_for_same_payment_attempt(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => function ($request) {
                return Http::response([
                    'return_code' => 1,
                    'order_url' => 'https://sb-openapi.zalopay.vn/pay',
                    'app_trans_id' => $request['app_trans_id'],
                ]);
            },
        ]);
        $attempt = [
            'order_id' => 999,
            'order_code' => 'MX-TEST-001',
            'amount' => 1250000,
            'payment_reference' => 'PAY-ZP-STABLE-ATTEMPT',
        ];

        $first = $this->service->createPaymentOrder($attempt);
        $second = $this->service->createPaymentOrder($attempt);

        $this->assertSame($first['app_trans_id'], $second['app_trans_id']);
    }

    public function test_create_payment_order_fails_when_gateway_rejects_request(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response([
                'return_code' => 2,
                'return_message' => 'Rejected',
            ], 503),
        ]);

        $result = $this->service->createPaymentOrder([
            'order_id' => 999,
            'order_code' => 'MX-TEST-001',
            'amount' => 1250000,
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['order_url']);
    }

    public function test_create_payment_order_does_not_return_a_fake_success_when_gateway_is_unreachable(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => function () {
                throw new ConnectionException('Gateway unavailable');
            },
        ]);

        $result = $this->service->createPaymentOrder([
            'order_id' => 999,
            'order_code' => 'MX-TEST-001',
            'amount' => 1250000,
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['order_url']);
        $this->assertSame('provider_unreachable', $result['error_code']);
        $this->assertArrayNotHasKey('zp_trans_token', $result);
    }

    public function test_verify_return_checksum_validates_correctly(): void
    {
        $appId = '2554';
        $appTransId = '261110_999_123456';
        $pmcId = '36';
        $bankCode = 'ZALOPAYAPP';
        $amount = '1250000';
        $discountAmount = '0';
        $status = '1';

        $dataToSign = "{$appId}|{$appTransId}|{$pmcId}|{$bankCode}|{$amount}|{$discountAmount}|{$status}";
        $checksum = hash_hmac('sha256', $dataToSign, 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf');

        $params = [
            'appid' => $appId,
            'apptransid' => $appTransId,
            'pmcid' => $pmcId,
            'bankcode' => $bankCode,
            'amount' => $amount,
            'discountamount' => $discountAmount,
            'status' => $status,
            'checksum' => $checksum,
        ];

        $this->assertTrue($this->service->verifyReturnChecksum($params));
    }

    public function test_verify_return_checksum_rejects_tampered_amount(): void
    {
        $appId = '2554';
        $appTransId = '261110_999_123456';
        $pmcId = '36';
        $bankCode = 'ZALOPAYAPP';
        $amount = '1250000';
        $discountAmount = '0';
        $status = '1';

        $dataToSign = "{$appId}|{$appTransId}|{$pmcId}|{$bankCode}|{$amount}|{$discountAmount}|{$status}";
        $checksum = hash_hmac('sha256', $dataToSign, 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf');

        $params = [
            'appid' => $appId,
            'apptransid' => $appTransId,
            'pmcid' => $pmcId,
            'bankcode' => $bankCode,
            'amount' => '999999999', // Tampered!
            'discountamount' => $discountAmount,
            'status' => $status,
            'checksum' => $checksum,
        ];

        $this->assertFalse($this->service->verifyReturnChecksum($params));
    }

    public function test_verify_callback_signature_validates_correctly(): void
    {
        $data = json_encode([
            'app_id' => 2554,
            'app_trans_id' => '261110_999_123456',
            'zp_trans_id' => '261110000001234',
            'amount' => 1250000,
        ]);
        $mac = hash_hmac('sha256', $data, 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf');

        $this->assertTrue($this->service->verifyCallback($data, $mac));

        // Tampered MAC
        $this->assertFalse($this->service->verifyCallback($data, 'invalid_mac_string'));
    }

    public function test_missing_credentials_reject_callback_and_payment_creation(): void
    {
        config(['zalopay.app_id' => '', 'zalopay.key1' => '', 'zalopay.key2' => '']);
        $service = new ZaloPayService;

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->verifyCallback('{}', hash_hmac('sha256', '{}', '')));
        $result = $service->createPaymentOrder([
            'order_id' => 1,
            'order_code' => 'MX-TEST',
            'amount' => 1000,
        ]);
        $this->assertSame('failed', $result['status']);
    }
}
