<?php

namespace Tests\Unit\Services;

use App\Services\VNPayService;
use Tests\TestCase;

class VNPayServiceTest extends TestCase
{
    private VNPayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vnpay.tmn_code' => 'TEST_TMN',
            'vnpay.hash_secret' => 'TEST_SECRET_KEY_1234567890',
            'vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'vnpay.return_url' => 'http://localhost:5173/payment/vnpay/return',
        ]);

        $this->service = new VNPayService;
    }

    public function test_create_payment_url_contains_valid_structure_and_hash(): void
    {
        $url = $this->service->createPaymentUrl([
            'order_code' => 'MX-ORD-TEST-123',
            'amount' => 500000, // 500,000 VND
            'order_info' => 'Thanh toan don hang MX-ORD-TEST-123',
        ]);

        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $url);
        $this->assertStringContainsString('vnp_Amount=50000000', $url); // 500k * 100
        $this->assertStringContainsString('vnp_TxnRef=MX-ORD-TEST-123', $url);
        $this->assertStringContainsString('vnp_SecureHash=', $url);

        // Parse query params to verify signature
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $params);

        $this->assertTrue($this->service->verifySignature($params));
    }

    public function test_verify_signature_rejects_tampered_amount(): void
    {
        $url = $this->service->createPaymentUrl([
            'order_code' => 'MX-ORD-TEST-456',
            'amount' => 1000000,
        ]);

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $params);

        // Tamper amount
        $params['vnp_Amount'] = '200000000';

        $this->assertFalse($this->service->verifySignature($params));
    }
}
