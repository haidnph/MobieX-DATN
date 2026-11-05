
<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class Checkout
{
    public static function availableProducts(): array
    {
        return Database::connection()->query(
            'SELECT p.id, p.name, p.sku, p.price, p.stock_quantity,
                (SELECT MIN(fp.sale_price)
                 FROM flash_sale_products fp
                 JOIN flash_sales fs ON fs.id = fp.flash_sale_id
                 WHERE fp.product_id = p.id AND fs.is_active = 1
                   AND NOW() >= fs.starts_at AND NOW() < fs.ends_at
                   AND fp.sale_price > 0 AND fp.sale_price < p.price) AS sale_price
            FROM products p WHERE p.is_active = 1 AND p.stock_quantity > 0
            ORDER BY p.name LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Order history always filters by the signed-in customer's ID. */
    public static function recentOrders(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT order_code, status, total_amount, created_at
             FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Create a pending COD order atomically, reserving stock immediately. */
    public static function place(int $userId, int $productId, int $quantity): string
    {
        if ($userId < 1 || $productId < 1 || $quantity < 1 || $quantity > 10) {
            throw new InvalidArgumentException('Thông tin sản phẩm hoặc số lượng không hợp lệ.');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT id, price, stock_quantity, is_active FROM products WHERE id = ? FOR UPDATE');
            $q->execute([$productId]);
            $product = $q->fetch(PDO::FETCH_ASSOC);
            if (!$product || (int)$product['is_active'] !== 1 || (int)$product['stock_quantity'] < $quantity) {
                throw new RuntimeException('Sản phẩm đã hết hàng hoặc số lượng không đủ.');
            }
            // Never trust a price sent by the browser.
            $unitPrice = (string)$product['price'];
            $sale = $db->prepare('SELECT MIN(fp.sale_price) FROM flash_sale_products fp
                JOIN flash_sales fs ON fs.id = fp.flash_sale_id
                WHERE fp.product_id = ? AND fs.is_active = 1
                  AND NOW() >= fs.starts_at AND NOW() < fs.ends_at
                  AND fp.sale_price > 0 AND fp.sale_price < ?');
            $sale->execute([$productId, $unitPrice]);
            $activeSale = $sale->fetchColumn();
            if ($activeSale !== false && $activeSale !== null) {
                $unitPrice = (string)$activeSale;
            }
            if ((float)$unitPrice <= 0) {
                throw new RuntimeException('Giá sản phẩm chưa hợp lệ.');
            }
            $total = number_format(round((float)$unitPrice * $quantity, 2), 2, '.', '');
            $code = 'MX-' . strtoupper(bin2hex(random_bytes(8)));
            $q = $db->prepare("INSERT INTO orders (order_code, user_id, status, total_amount) VALUES (?, ?, 'pending', ?)");
            $q->execute([$code, $userId, $total]);
            $orderId = (int)$db->lastInsertId();
            $q = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $q->execute([$orderId, $productId, $quantity, $unitPrice]);
            $q = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
            $q->execute([$quantity, $productId]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('Không thể giữ hàng; vui lòng thử lại.');
            }
            $db->commit();
            return $code;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
