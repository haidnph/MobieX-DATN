
<?php


require_once __DIR__ . '/../Core/Database.php';

final class AdminOperations
{
    private const NEXT = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping'  => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    private const MAX_STOCK = 4294967295;

    public static function changeOrderStatus(
        int $id,
        string $status
    ): void {
        if ($id < 1) {
            throw new InvalidArgumentException(
                'Mã đơn hàng không hợp lệ.'
            );
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            // Khóa đơn hàng để tránh xử lý đồng thời
            $stmt = $db->prepare(
                'SELECT status FROM orders
                 WHERE id = ? FOR UPDATE'
            );

            $stmt->execute([$id]);
            $current = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($current === false) {
                throw new InvalidArgumentException(
                    'Không tìm thấy đơn hàng.'
                );
            }

            if (!in_array(
                $status,
                self::NEXT[(string)$current] ?? [],
                true
            )) {
                throw new InvalidArgumentException(
                    "Không thể chuyển đơn từ {$current} sang {$status}."
                );
            }

            // Checkout đã trừ kho khi khách đặt hàng.
            // Khi hủy đơn cần hoàn lại số lượng đã trừ.
            if ($status === 'cancelled') {

                $items = $db->prepare(
                    'SELECT product_id,
                            SUM(quantity) AS qty
                     FROM order_items
                     WHERE order_id = ?
                     GROUP BY product_id
                     ORDER BY product_id'
                );

                $items->execute([$id]);
                $rows = $items->fetchAll(PDO::FETCH_ASSOC);
                $items->closeCursor();

                if (!$rows) {
                    throw new RuntimeException(
                        'Đơn hàng không có sản phẩm, không thể tự động hoàn kho.'
                    );
                }

                $restore = $db->prepare(
                    'UPDATE products
                     SET stock_quantity = stock_quantity + :quantity
                     WHERE id = :product_id
                     AND stock_quantity <= :maximum_before_add'
                );

                foreach ($rows as $row) {
                    $productId = (int)$row['product_id'];
                    $quantity = (int)$row['qty'];

                    if (
                        $productId < 1 ||
                        $quantity < 1 ||
                        $quantity > self::MAX_STOCK
                    ) {
                        throw new RuntimeException(
                            'Dữ liệu chi tiết đơn hàng không hợp lệ.'
                        );
                    }

                    $restore->execute([
                        'quantity' => $quantity,
                        'product_id' => $productId,
                        'maximum_before_add' =>
                            self::MAX_STOCK - $quantity
                    ]);

                    if ($restore->rowCount() !== 1) {
                        throw new RuntimeException(
                            "Không hoàn được kho sản phẩm #{$productId}. Đã hoàn tác thao tác hủy."
                        );
                    }
                }
            }

            // Cập nhật trạng thái đơn hàng
            $update = $db->prepare(
                "UPDATE orders
                 SET status = ?,
                     completed_at = CASE
                         WHEN ? = 'completed' THEN NOW()
                         ELSE completed_at
                     END,
                     cancelled_at = CASE
                         WHEN ? = 'cancelled' THEN NOW()
                         ELSE cancelled_at
                     END
                 WHERE id = ?"
            );

            $update->execute([
                $status,
                $status,
                $status,
                $id
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Không cập nhật được trạng thái đơn hàng.'
                );
            }

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public static function restock(
        int $productId,
        int $quantity
    ): void {
        if (
            $productId < 1 ||
            $quantity < 1 ||
            $quantity > 100000
        ) {
            throw new InvalidArgumentException(
                'ID sản phẩm hoặc số lượng nhập không hợp lệ (1–100000).'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + :quantity
             WHERE id = :product_id
             AND stock_quantity <= :maximum_before_add'
        );

        $stmt->execute([
            'quantity' => $quantity,
            'product_id' => $productId,
            'maximum_before_add' =>
                self::MAX_STOCK - $quantity
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException(
                'Không tìm thấy sản phẩm hoặc tồn kho vượt giới hạn.'
            );
        }
    }
}
