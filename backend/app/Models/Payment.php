<?php

class Payment
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Tạo giao dịch thanh toán
    public function create(array $data): int
    {
        $sql = "INSERT INTO payments
                (
                    order_id,
                    transaction_code,
                    method,
                    amount,
                    status
                )
                VALUES
                (
                    :order_id,
                    :transaction_code,
                    :method,
                    :amount,
                    'PENDING'
                )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':transaction_code' => $data['transaction_code'],
            ':method' => $data['method'],
            ':amount' => $data['amount']
        ]);

        return (int)$this->db->lastInsertId();
    }

    // Tìm giao dịch theo mã
    public function findByTransactionCode(string $code)
    {
        $sql = "SELECT * FROM payments
                WHERE transaction_code = :code
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Cập nhật kết quả giao dịch
    public function updateResult(
        string $code,
        string $status,
        ?string $gatewayId,
        array $response
    ): bool {
        $sql = "UPDATE payments
                SET status = :status,
                    gateway_transaction_id = :gateway_id,
                    response_data = :response,
                    paid_at = CASE
                        WHEN :paid_status = 'SUCCESS'
                        THEN NOW()
                        ELSE paid_at
                    END
                WHERE transaction_code = :code
                AND status = 'PENDING'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':status' => $status,
            ':gateway_id' => $gatewayId,
            ':response' => json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ),
            ':paid_status' => $status,
            ':code' => $code
        ]);
    }
}   