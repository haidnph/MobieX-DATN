<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class AdminOperations
{
    // Valid status transitions. Prevent reversing completed orders.
    private const NEXT = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public static function changeOrderStatus(int $id, string $status): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Mã đơn hàng không hợp lệ.');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $q->execute([$id]);
            $current = $q->fetchColumn();
            if ($current === false) {
                throw new InvalidArgumentException('Không tìm thấy đơn hàng.');
            }
            if (!in_array($status, self::NEXT[$current] ?? [], true)) {
                throw new InvalidArgumentException('Không thể chuyển đơn từ ' . $current . ' sang ' . $status . '.');
            }
            $stmt = $db->prepare("UPDATE orders SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END, cancelled_at = CASE WHEN ? = 'cancelled' THEN NOW() ELSE cancelled_at END WHERE id = ?");
            $stmt->execute([$status, $status, $status, $id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function restock(int $productId, int $quantity): void
    {
        if ($productId < 1 || $quantity < 1 || $quantity > 100000) {
            throw new InvalidArgumentException('ID sản phẩm hoặc số lượng nhập không hợp lệ (1–100000).');
        }
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND stock_quantity <= 4294867295');
        $stmt->execute([$quantity, $productId]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Không tìm thấy sản phẩm hoặc tồn kho vượt giới hạn.');
        }
    }
}