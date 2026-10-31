<?php

require_once 'models/OrderModel.php';

class OrderController
{
    private $model;

    public function __construct()
    {
        $this->model = new OrderModel();
    }


    // =========================
    // DANH SÁCH ĐƠN HÀNG
    // =========================
    public function index()
    {
        $status = isset($_GET['status'])
            ? trim($_GET['status'])
            : '';

        $orders = $this->model->getAll($status);

        require 'views/admin/orders/index.php';
    }


    // =========================
    // CHI TIẾT ĐƠN HÀNG
    // =========================
    public function detail()
    {
        if (!isset($_GET['id'])) {
            header("Location: index.php?url=admin/orders");
            exit;
        }

        $id = (int) $_GET['id'];

        $order = $this->model->getById($id);

        if (!$order) {
            die("Không tìm thấy đơn hàng");
        }

        $items = $this->model->getItems($id);

        require 'views/admin/orders/detail.php';
    }


    // =========================
    // CẬP NHẬT TRẠNG THÁI
    // =========================
    public function updateStatus()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?url=admin/orders");
            exit;
        }

        $id = isset($_POST['id'])
            ? (int) $_POST['id']
            : 0;

        $newStatus = isset($_POST['status'])
            ? $_POST['status']
            : '';

        if ($id <= 0) {
            die("ID đơn hàng không hợp lệ");
        }

        $order = $this->model->getById($id);

        if (!$order) {
            die("Không tìm thấy đơn hàng");
        }

        $currentStatus = $order['order_status'];


        // Trạng thái tiếp theo
        $nextStatus = [
            'PENDING'    => 'CONFIRMED',
            'CONFIRMED'  => 'PROCESSING',
            'PROCESSING' => 'SHIPPING',
            'SHIPPING'   => 'DELIVERED'
        ];


        // Không cho quay ngược trạng thái
        if (
            !isset($nextStatus[$currentStatus]) ||
            $nextStatus[$currentStatus] !== $newStatus
        ) {
            die("Không thể chuyển trạng thái đơn hàng theo bước này.");
        }


        $this->model->updateStatus($id, $newStatus);

        header(
            "Location: index.php?url=admin/order-detail&id=" . $id
        );

        exit;
    }
}