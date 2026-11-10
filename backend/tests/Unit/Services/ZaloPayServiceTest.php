<?php

namespace Tests\Unit\Services;

use App\Services\ZaloPayService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZaloPayServiceTest extends TestCase
{
    private ZaloPayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zalopay.app_id' => '2553',
            'zalopay.key1' => 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL',
            'zalopay.key2' => 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz',
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

    public function test_verify_return_checksum_validates_correctly(): void
    {
        $appId = '2553';
        $appTransId = '261110_999_123456';
        $pmcId = '36';
        $bankCode = 'ZALOPAYAPP';
        $amount = '1250000';
        $discountAmount = '0';
        $status = '1';

        $dataToSign = "{$appId}|{$appTransId}|{$pmcId}|{$bankCode}|{$amount}|{$discountAmount}|{$status}";
        $checksum = hash_hmac('sha256', $dataToSign, 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz');

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
        $appId = '2553';
        $appTransId = '261110_999_123456';
        $pmcId = '36';
        $bankCode = 'ZALOPAYAPP';
        $amount = '1250000';
        $discountAmount = '0';
        $status = '1';

        $dataToSign = "{$appId}|{$appTransId}|{$pmcId}|{$bankCode}|{$amount}|{$discountAmount}|{$status}";
        $checksum = hash_hmac('sha256', $dataToSign, 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz');

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
            'app_id' => 2553,
            'app_trans_id' => '261110_999_123456',
            'zp_trans_id' => '261110000001234',
            'amount' => 1250000,
        ]);
        $mac = hash_hmac('sha256', $data, 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz');

        $this->assertTrue($this->service->verifyCallback($data, $mac));

        // Tampered MAC
        $this->assertFalse($this->service->verifyCallback($data, 'invalid_mac_string'));
    }
}
