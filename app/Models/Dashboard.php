<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class Dashboard
{
    private static function columns(PDO $db, string $table): array
    {
        $query = $db->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $query->execute(['table_name' => $table]);
        return array_fill_keys($query->fetchAll(PDO::FETCH_COLUMN), true);
    }

    public static function summary(): array
    {
        $db = Database::connection();
        $userColumns = self::columns($db, 'users');
        $orderColumns = self::columns($db, 'orders');
        $productColumns = self::columns($db, 'products');

        $missingUsers = array_diff(['id', 'name'], array_keys($userColumns));
        if ($missingUsers) {
            throw new RuntimeException('The users table is missing: ' . implode(', ', $missingUsers));
        }

        $statusColumn = isset($orderColumns['status'])
            ? 'status'
            : (isset($orderColumns['order_status']) ? 'order_status' : null);
        $missingOrders = array_diff(
            ['id', 'order_code', 'user_id', 'total_amount', 'created_at'],
            array_keys($orderColumns)
        );
        if ($statusColumn === null) {
            $missingOrders[] = 'status/order_status';
        }
        if ($missingOrders) {
            throw new RuntimeException('The orders table is missing: ' . implode(', ', $missingOrders));
        }

        $directInventory = array_diff(
            ['sku', 'price', 'stock_quantity'],
            array_keys($productColumns)
        ) === [];
        if (!$directInventory) {
            $variantColumns = self::columns($db, 'product_variants');
            $inventoryColumns = self::columns($db, 'inventories');
            $validVariantSchema = array_diff(
                ['id', 'product_id', 'sku', 'price'],
                array_keys($variantColumns)
            ) === [];
            $validInventorySchema = array_diff(
                ['variant_id', 'available_quantity'],
                array_keys($inventoryColumns)
            ) === [];
            if (!$validVariantSchema || !$validInventorySchema) {
                throw new RuntimeException(
                    'Stock columns were not found on products or on product_variants/inventories.'
                );
            }
        }

        $completionDate = isset($orderColumns['completed_at'])
            ? (isset($orderColumns['updated_at'])
                ? 'COALESCE(completed_at, updated_at, created_at)'
                : 'COALESCE(completed_at, created_at)')
            : (isset($orderColumns['updated_at'])
                ? 'COALESCE(updated_at, created_at)'
                : 'created_at');
        $status = 'LOWER(`' . $statusColumn . '`)';

        $stats = $db->query("SELECT
            COALESCE(SUM(CASE WHEN $status IN ('completed', 'delivered') AND DATE($completionDate) = CURDATE() THEN total_amount ELSE 0 END), 0) AS day_revenue,
            COALESCE(SUM(CASE WHEN $status IN ('completed', 'delivered') AND YEAR($completionDate) = YEAR(CURDATE()) AND MONTH($completionDate) = MONTH(CURDATE()) THEN total_amount ELSE 0 END), 0) AS month_revenue,
            COALESCE(SUM(CASE WHEN $status IN ('completed', 'delivered') AND YEAR($completionDate) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) AS year_revenue,
            COALESCE(SUM(CASE WHEN $status = 'pending' THEN 1 ELSE 0 END), 0) AS new_orders,
            COALESCE(SUM(CASE WHEN $status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
            COUNT(*) AS total_orders
            FROM orders")->fetch(PDO::FETCH_ASSOC);

        $recent = $db->query("SELECT o.id, o.order_code, o.total_amount,
            CASE LOWER(o.`$statusColumn`)
                WHEN 'delivered' THEN 'completed'
                ELSE LOWER(o.`$statusColumn`)
            END AS status,
            o.created_at, COALESCE(u.name, 'Guest') AS customer
            FROM orders o LEFT JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC, o.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

        if ($directInventory) {
            $lowStock = $db->query("SELECT id, sku, name, price, stock_quantity
                FROM products WHERE stock_quantity < 5
                ORDER BY stock_quantity ASC, name ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $lowStock = $db->query("SELECT p.id, COALESCE(MIN(v.sku), p.slug) AS sku,
                    p.name, COALESCE(MIN(v.price), 0) AS price,
                    COALESCE(SUM(i.available_quantity), 0) AS stock_quantity
                FROM products p
                LEFT JOIN product_variants v ON v.product_id = p.id
                LEFT JOIN inventories i ON i.variant_id = v.id
                GROUP BY p.id, p.slug, p.name
                HAVING stock_quantity < 5
                ORDER BY stock_quantity ASC, p.name ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        }

        $monthly = $db->query("SELECT MONTH($completionDate) AS month, SUM(total_amount) AS revenue
            FROM orders WHERE $status IN ('completed', 'delivered')
              AND $completionDate IS NOT NULL AND YEAR($completionDate) = YEAR(CURDATE())
            GROUP BY MONTH($completionDate) ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'stats' => $stats ?: [
                'day_revenue' => 0,
                'month_revenue' => 0,
                'year_revenue' => 0,
                'new_orders' => 0,
                'cancelled_orders' => 0,
                'total_orders' => 0,
            ],
            'recent' => $recent,
            'low_stock' => $lowStock,
            'monthly' => $monthly,
        ];
    }
}
