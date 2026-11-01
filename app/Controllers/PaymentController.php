<?php

require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/../Services/VNPayService.php';
require_once __DIR__ . '/../Services/MoMoService.php';

class PaymentController
{
    private PDO $db;
    private Payment $paymentModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->paymentModel = new Payment($db);
    }

    // Trang chọn phương thức thanh toán
    public function index()
    {
        require __DIR__ . '/../Views/payment/index.php';
    }

    // Bắt đầu thanh toán
    public function checkout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $method = strtoupper($_POST['method'] ?? '');

        if ($orderId <= 0 ||
            !in_array($method, ['COD', 'VNPAY', 'MOMO'], true)) {
            exit('Thông tin thanh toán không hợp lệ');
        }

        /*
         * Phải kiểm tra order thuộc người dùng đang đăng nhập.
         * Thay session key bên dưới theo hệ thống đăng nhập của bạn.
         */
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            exit('Vui lòng đăng nhập');
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM orders
             WHERE id = :id AND user_id = :user_id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $orderId,
            ':user_id' => $userId
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            exit('Không tìm thấy đơn hàng');
        }

        if (($order['order_status'] ?? '') !== 'PENDING') {
            exit('Đơn hàng không ở trạng thái chờ thanh toán');
        }

        $transactionCode = 'PAY' . date('YmdHis') . random_int(1000, 9999);
        $amount = (float)$order['total_amount'];

        if ($amount <= 0 || $amount > 1000000000) {
            exit('Số tiền thanh toán không hợp lệ');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "UPDATE orders
                 SET payment_method = :method
                 WHERE id = :id AND order_status = 'PENDING'"
            );

            $stmt->execute([
                ':method' => $method,
                ':id' => $orderId
            ]);

            $this->paymentModel->create([
                'order_id' => $orderId,
                'transaction_code' => $transactionCode,
                'method' => $method,
                'amount' => $amount
            ]);

            $this->db->commit();

        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            exit('Không thể tạo giao dịch thanh toán');
        }

        // COD
        if ($method === 'COD') {
            header('Location: /payment/success');
            exit;
        }

        // VNPAY
        if ($method === 'VNPAY') {
            $service = new VNPayService();

            $url = $service->createPayment(
                $transactionCode,
                (int)round($amount),
                'Thanh toan don hang ' . $order['order_code']
            );

            header('Location: ' . $url);
            exit;
        }

        // MOMO
        if ($method === 'MOMO') {
            $service = new MoMoService();

            $result = $service->createPayment(
                $transactionCode,
                (int)round($amount),
                'Thanh toan don hang ' . $order['order_code']
            );

            if (
                (int)($result['resultCode'] ?? -1) !== 0 ||
                empty($result['payUrl'])
            ) {
                $this->paymentModel->updateResult(
                    $transactionCode,
                    'FAILED',
                    null,
                    $result
                );

                exit('Không thể tạo thanh toán MoMo');
            }

            header('Location: ' . $result['payUrl']);
            exit;
        }
    }

    // VNPAY trả khách về website
    public function vnpayReturn()
    {
        $service = new VNPayService();
        $data = $_GET;

        if (!$service->verify($data)) {
            http_response_code(400);
            exit('Chữ ký VNPAY không hợp lệ');
        }

        $code = $data['vnp_TxnRef'] ?? '';
        $payment = $this->paymentModel->findByTransactionCode($code);

        if (!$payment) {
            exit('Không tìm thấy giao dịch');
        }

        $success = (
            ($data['vnp_ResponseCode'] ?? '') === '00' &&
            ($data['vnp_TransactionStatus'] ?? '') === '00' &&
            (int)($data['vnp_Amount'] ?? 0) ===
                (int)round((float)$payment['amount'] * 100)
        );

        if ($success) {
            $this->markSuccess(
                $code,
                $data['vnp_TransactionNo'] ?? '',
                $data
            );
            header('Location: /payment/success');
        } else {
            header('Location: /payment/failed');
        }

        exit;
    }

    // VNPAY IPN
    public function vnpayIpn()
    {
        $service = new VNPayService();
        $data = $_GET;

        if (!$service->verify($data)) {
            $this->json([
                'RspCode' => '97',
                'Message' => 'Invalid signature'
            ]);
        }

        $code = $data['vnp_TxnRef'] ?? '';
        $payment = $this->paymentModel->findByTransactionCode($code);

        if (!$payment) {
            $this->json([
                'RspCode' => '01',
                'Message' => 'Order not found'
            ]);
        }

        if (
            (int)($data['vnp_Amount'] ?? 0) !==
            (int)round((float)$payment['amount'] * 100)
        ) {
            $this->json([
                'RspCode' => '04',
                'Message' => 'Invalid amount'
            ]);
        }

        if ($payment['status'] === 'SUCCESS') {
            $this->json([
                'RspCode' => '02',
                'Message' => 'Order already confirmed'
            ]);
        }

        if (
            ($data['vnp_ResponseCode'] ?? '') === '00' &&
            ($data['vnp_TransactionStatus'] ?? '') === '00'
        ) {
            $this->markSuccess(
                $code,
                $data['vnp_TransactionNo'] ?? '',
                $data
            );

            $this->json([
                'RspCode' => '00',
                'Message' => 'Confirm Success'
            ]);
        }

        $this->paymentModel->updateResult(
            $code,
            'FAILED',
            $data['vnp_TransactionNo'] ?? null,
            $data
        );

        $this->json([
            'RspCode' => '00',
            'Message' => 'Confirm Success'
        ]);
    }

    // MoMo IPN
    public function momoIpn()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->json(['message' => 'Invalid request']);
        }

        $service = new MoMoService();

        if (!$service->verify($data)) {
            $this->json(['message' => 'Invalid signature']);
        }

        $code = $data['orderId'] ?? '';
        $payment = $this->paymentModel->findByTransactionCode($code);

        if (!$payment) {
            $this->json(['message' => 'Order not found']);
        }

        if (
            (int)($data['resultCode'] ?? -1) === 0 &&
            (int)($data['amount'] ?? 0) ===
                (int)round((float)$payment['amount'])
        ) {
            $this->markSuccess(
                $code,
                (string)($data['transId'] ?? ''),
                $data
            );
        } elseif ($payment['status'] === 'PENDING') {
            $this->paymentModel->updateResult(
                $code,
                'FAILED',
                (string)($data['transId'] ?? ''),
                $data
            );
        }

        $this->json(['message' => 'Received']);
    }

    // Đánh dấu thanh toán thành công
    private function markSuccess(
        string $code,
        string $gatewayId,
        array $response
    ): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM payments
                 WHERE transaction_code = :code
                 FOR UPDATE"
            );

            $stmt->execute([':code' => $code]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment || $payment['status'] === 'SUCCESS') {
                $this->db->commit();
                return;
            }

            if ($payment['status'] !== 'PENDING') {
                $this->db->commit();
                return;
            }

            $this->paymentModel->updateResult(
                $code,
                'SUCCESS',
                $gatewayId,
                $response
            );

            $stmt = $this->db->prepare(
                "UPDATE orders
                 SET order_status = 'CONFIRMED'
                 WHERE id = :order_id
                 AND order_status = 'PENDING'"
            );

            $stmt->execute([
                ':order_id' => $payment['order_id']
            ]);

            $this->db->commit();

        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
        }
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function success()
    {
        require __DIR__ . '/../Views/payment/success.php';
    }

    public function failed()
    {
        require __DIR__ . '/../Views/payment/failed.php';
    }
}