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

        // Parse query params to verify signature and Vietnam timezone
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $params);

        $this->assertTrue($this->service->verifySignature($params));
        $this->assertEquals(
            now('Asia/Ho_Chi_Minh')->format('YmdH'),
            substr((string) $params['vnp_CreateDate'], 0, 10)
        );
        $this->assertTrue((string) $params['vnp_ExpireDate'] > (string) $params['vnp_CreateDate']);
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

    public function test_missing_credentials_reject_payment_creation_and_signatures(): void
    {
        config(['vnpay.tmn_code' => '', 'vnpay.hash_secret' => '']);
        $service = new VNPayService;

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->verifySignature([
            'vnp_SecureHash' => hash_hmac('sha512', '', ''),
        ]));
        $this->expectException(\RuntimeException::class);
        $service->createPaymentUrl(['order_code' => 'MX-TEST', 'amount' => 1000]);
    }

    public function test_payment_url_expires_at_the_order_reservation_deadline(): void
    {
        $deadline = now('Asia/Ho_Chi_Minh')->addMinutes(5);
        $url = $this->service->createPaymentUrl([
            'order_code' => 'MX-ORD-DEADLINE',
            'amount' => 1000,
            'expires_at' => $deadline,
        ]);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertSame($deadline->format('YmdHis'), $params['vnp_ExpireDate']);
    }

    public function test_refund_api_response_signature_is_verified_before_use(): void
    {
        $response = [
            'vnp_ResponseId' => 'RESP-123',
            'vnp_Command' => 'querydr',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'TEST_TMN',
            'vnp_TxnRef' => 'PAY-123',
            'vnp_Amount' => '25000000',
            'vnp_TransactionNo' => '99887766',
            'vnp_TransactionType' => '02',
            'vnp_TransactionStatus' => '00',
        ];
        $fields = [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef',
            'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
            'vnp_TransactionStatus', 'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount',
        ];
        $signed = implode('|', array_map(static fn (string $field): string => $response[$field] ?? '', $fields));
        $response['vnp_SecureHash'] = hash_hmac('sha512', $signed, 'TEST_SECRET_KEY_1234567890');

        $this->assertTrue($this->service->verifyRefundApiResponse($response));
        $response['vnp_TransactionStatus'] = '09';
        $this->assertFalse($this->service->verifyRefundApiResponse($response));
        $this->assertFalse($this->service->verifyRefundApiResponse(['vnp_TransactionStatus' => '00']));
    }
}
