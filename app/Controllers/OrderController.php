
<?php


require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Support/Csrf.php';

final class OrderController
{
    public function handle(string $mode): void
    {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $db = Database::connection();

        $error = null;
        $message = null;
        $orders = [];
        $order = null;

        if ($mode === 'orders' && !$uid) {
            header('Location: index.php?page=login');
            exit;
        }

        if (
            $mode === 'orders' &&
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ) {
            try {
                if (!Csrf::valid($_POST['_csrf'] ?? null)) {
                    throw new RuntimeException('Phiên không hợp lệ.');
                }

                $code = trim((string)($_POST['order_code'] ?? ''));

                $db->beginTransaction();

                $q = $db->prepare(
                    'SELECT id,order_status,payment_status
                     FROM orders
                     WHERE order_code=? AND user_id=?
                     FOR UPDATE'
                );

                $q->execute([$code, $uid]);
                $row = $q->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new InvalidArgumentException(
                        'Không tìm thấy đơn hàng.'
                    );
                }

                if (
                    $row['order_status'] !== 'PENDING' ||
                    $row['payment_status'] !== 'PENDING'
                ) {
                    throw new InvalidArgumentException(
                        'Chỉ hủy được đơn đang chờ và chưa thanh toán.'
                    );
                }

                $reason = trim((string)($_POST['reason'] ?? ''));

                if (
                    mb_strlen($reason) < 3 ||
                    mb_strlen($reason) > 500
                ) {
                    throw new InvalidArgumentException(
                        'Nhập lý do hủy từ 3 đến 500 ký tự.'
                    );
                }

                $q = $db->prepare(
                    'SELECT product_variant_id,price,
                            SUM(quantity) quantity
                     FROM order_items
                     WHERE order_id=?
                     GROUP BY product_variant_id,price'
                );

                $q->execute([$row['id']]);
                $items = $q->fetchAll(PDO::FETCH_ASSOC);

                $restore = $db->prepare(
                    'UPDATE inventories
                     SET quantity=quantity+?
                     WHERE variant_id=?'
                );

                foreach ($items as $item) {
                    $restore->execute([
                        (int)$item['quantity'],
                        (int)$item['product_variant_id']
                    ]);

                    $db->prepare(
                        'UPDATE flash_sale_items
                         SET quantity_sold=GREATEST(
                             0,quantity_sold-?
                         )
                         WHERE variant_id=? AND sale_price=?
                         ORDER BY id DESC LIMIT 1'
                    )->execute([
                        (int)$item['quantity'],
                        (int)$item['product_variant_id'],
                        (float)$item['price']
                    ]);
                }

                $db->prepare(
                    "UPDATE orders
                     SET order_status='CANCELLED',
                         cancelled_at=NOW(),
                         customer_note=?
                     WHERE id=?"
                )->execute([
                    $reason,
                    (int)$row['id']
                ]);

                $db->prepare(
                    "UPDATE payments
                     SET status='CANCELLED'
                     WHERE order_id=? AND status='PENDING'"
                )->execute([(int)$row['id']]);

                $db->commit();
                $message = 'Đã hủy đơn và hoàn tồn kho.';

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                $error = $e instanceof PDOException
                    ? 'Không xử lý được đơn hàng.'
                    : $e->getMessage();
            }
        }

        if ($mode === 'orders') {
            $q = $db->prepare(
                'SELECT *
                 FROM orders
                 WHERE user_id=?
                 ORDER BY created_at DESC'
            );

            $q->execute([$uid]);
            $orders = $q->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$o) {
                $it = $db->prepare(
                    'SELECT product_name,variant_name,
                            sku,price,quantity,subtotal
                     FROM order_items
                     WHERE order_id=?'
                );

                $it->execute([$o['id']]);
                $o['items'] = $it->fetchAll(PDO::FETCH_ASSOC);
            }

            unset($o);

        } elseif (
            isset($_GET['code'], $_GET['phone']) &&
            is_string($_GET['code']) &&
            is_string($_GET['phone']) &&
            trim($_GET['code']) !== '' &&
            trim($_GET['phone']) !== ''
        ) {
            $q = $db->prepare(
                'SELECT *
                 FROM orders
                 WHERE order_code=? AND shipping_phone=?'
            );

            $q->execute([
                trim($_GET['code']),
                trim($_GET['phone'])
            ]);

            $order = $q->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($order) {
                $it = $db->prepare(
                    'SELECT product_name,variant_name,
                            sku,quantity,subtotal
                     FROM order_items
                     WHERE order_id=?'
                );

                $it->execute([$order['id']]);
                $order['items'] = $it->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        View::render(
            $mode === 'orders'
                ? 'store/orders.php'
                : 'store/track.php',
            $mode === 'orders'
                ? 'MobileX | Đơn hàng'
                : 'MobileX | Tra cứu đơn',
            [
                'orders' => $orders,
                'order' => $order,
                'csrf' => Csrf::token(),
                'error' => $error,
                'message' => $message
            ]
        );
    }
}
