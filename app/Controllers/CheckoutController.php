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
        $data = ['products' => [], 'catalog' => [], 'subtotal' => 0, 'error' => null, 'message' => null, 'csrf' => Csrf::token()];
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!Csrf::valid($_POST['_csrf'] ?? null)) {
                    throw new RuntimeException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
                }
                $action = (string)($_POST['action'] ?? 'place');
                if ($action === 'add' || $action === 'update' || $action === 'remove') {
                    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (!$productId) throw new InvalidArgumentException('Sản phẩm không hợp lệ.');
                    $cart = $_SESSION['cart'] ?? [];
                    if ($action === 'remove') unset($cart[$productId]);
                    elseif ($action === 'add') {
                        $next = (int)($cart[$productId] ?? 0) + 1;
                        Checkout::assertAvailableQuantity((int)$productId, $next);
                        $cart[$productId] = $next;
                    }
                    else {
                        $delta = (int)($_POST['delta'] ?? 0);
                        if (!in_array($delta, [-1, 1], true)) throw new InvalidArgumentException('Số lượng cập nhật không hợp lệ.');
                        $next = (int)($cart[$productId] ?? 0) + $delta;
                        if ($next < 1) unset($cart[$productId]); else { Checkout::assertAvailableQuantity((int)$productId, $next); $cart[$productId] = $next; }
                    }
                    $_SESSION['cart'] = $cart;
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => true]); return;
                    }
                    header('Location: index.php?page=checkout'); exit;
                }
                if ($action === 'coupon') {
                    $discount = Checkout::couponDiscount((string)($_POST['coupon'] ?? ''), (float)($_SESSION['_cart_subtotal'] ?? 0));
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['discount' => $discount, 'message' => $discount > 0 ? 'Mã hợp lệ.' : 'Mã không hợp lệ hoặc chưa đạt giá trị đơn tối thiểu.']); return;
                }
                $code = Checkout::placeCart((int)$_SESSION['user']['id'], $_SESSION['cart'] ?? [], [
                    'name' => trim((string)($_POST['shipping_name'] ?? '')), 'phone' => trim((string)($_POST['shipping_phone'] ?? '')),
                    'address' => trim((string)($_POST['shipping_address'] ?? '')), 'shipping' => (string)($_POST['shipping_method'] ?? 'standard'),
                    'payment' => (string)($_POST['payment_method'] ?? 'COD'), 'coupon' => trim((string)($_POST['coupon'] ?? '')),
                ]);
                unset($_SESSION['cart'], $_SESSION['_cart_subtotal']);
                $_SESSION['_checkout_success'] = 'Đặt hàng thành công! Mã đơn: ' . $code . '. Đơn đang chờ xác nhận.';
                header('Location: index.php?page=checkout');
                exit;
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(422); header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => $e->getMessage()]); return;
            }
            $data['error'] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('MobileX checkout SQL: ' . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                http_response_code(500); header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Không xử lý được yêu cầu do lỗi cơ sở dữ liệu.']); return;
            }
            $data['error'] = 'Lỗi kết nối cơ sở dữ liệu. Kiểm tra MySQL và cấu trúc bảng.';
        }
        $data['message'] = $_SESSION['_checkout_success'] ?? null;
        unset($_SESSION['_checkout_success']);
        try {
            $data['catalog'] = Checkout::availableProducts();
            $data['products'] = Checkout::cartProducts($_SESSION['cart'] ?? []);
            $data['subtotal'] = array_reduce($data['products'], static fn(float $sum, array $p): float => $sum + (float)$p['price'] * (int)$p['quantity'], 0.0);
            $_SESSION['_cart_subtotal'] = $data['subtotal'];
        } catch (PDOException $e) {
            error_log('MobileX checkout product loading: ' . $e->getMessage());
            $data['error'] = 'Không đọc được danh sách sản phẩm từ MySQL.';
        }
        View::render('store/checkout.php', 'MobileX | Đặt hàng', $data);
    }
}
