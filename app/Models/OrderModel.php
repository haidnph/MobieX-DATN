<?php

class OrderModel
{
    private $conn;

    public function __construct()
    {
        $this->conn = new PDO(
            "mysql:host=localhost;dbname=datn;charset=utf8mb4",
            "root",
            ""
        );

        $this->conn->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    // =========================
    // LẤY DANH SÁCH ĐƠN HÀNG
    // =========================
    public function getAll($status = '')
    {
        $sql = "
            SELECT 
                o.*,
                u.fullname,
                u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
        ";

        if ($status != '') {
            $sql .= " WHERE o.order_status = :status ";
        }

        $sql .= " ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($sql);

        if ($status != '') {
            $stmt->bindValue(':status', $status);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // =========================
    // LẤY CHI TIẾT ĐƠN
    // =========================
    public function getById($id)
    {
        $sql = "
            SELECT 
                o.*,
                u.fullname,
                u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = :id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // =========================
    // LẤY SẢN PHẨM TRONG ĐƠN
    // =========================
    public function getItems($orderId)
    {
        $sql = "
            SELECT *
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':order_id', $orderId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // =========================
    // CẬP NHẬT TRẠNG THÁI
    // =========================
    public function updateStatus($id, $status)
    {
        $sql = "
            UPDATE orders
            SET 
                order_status = :status,
                updated_at = NOW()
            WHERE id = :id
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id);

        return $stmt->execute();
    }
}