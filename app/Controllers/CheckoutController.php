<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Checkout.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Support/Csrf.php';

final class CheckoutController
{
    public function handle(): void
    {
        if (empty($_SESSION['user']['id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        $data = ['products' => [], 'error' => null, 'message' => null, 'csrf' => Csrf::token()];
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::valid($_POST['_csrf'] ?? null)) {
                    throw new RuntimeException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
                }
                $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
                if (!$productId || !$quantity) {
                    throw new InvalidArgumentException('Hãy chọn sản phẩm và số lượng hợp lệ.');
                }
                $code = Checkout::place((int)$_SESSION['user']['id'], (int)$productId, (int)$quantity);
                $_SESSION['_checkout_success'] = 'Đặt hàng thành công! Mã đơn: ' . $code . '. Admin đã nhận được đơn hàng chờ xác nhận.';
                header('Location: index.php?page=checkout');
                exit;
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            $data['error'] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('MobileX checkout SQL: ' . $e->getMessage());
            $data['error'] = 'Lỗi kết nối cơ sở dữ liệu. Kiểm tra MySQL và cấu trúc bảng.';
        }
        $data['message'] = $_SESSION['_checkout_success'] ?? null;
        unset($_SESSION['_checkout_success']);
        try {
            $data['products'] = Checkout::availableProducts();
        } catch (PDOException $e) {
            error_log('MobileX checkout product loading: ' . $e->getMessage());
            $data['error'] = 'Không đọc được danh sách sản phẩm từ MySQL.';
        }
        View::render('store/checkout.php', 'MobileX | Đặt hàng', $data);
    }
}
