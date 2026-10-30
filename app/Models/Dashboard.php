<?php
// MobileX Dashboard: đọc đúng các bảng, không tạo dữ liệu giả.
require_once __DIR__ . '/../Core/Database.php';

final class Dashboard
{
    private static function assertColumns(PDO $db, string $table, array $columns): void
    {
        $allowedTables = ['users', 'orders', 'products'];
        if (!in_array($table, $allowedTables, true)) {
            throw new RuntimeException('Tên bảng không hợp lệ.');
        }
        $check = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
        $check->execute(['table_name' => $table]);
        $existing = $check->fetchAll(PDO::FETCH_COLUMN);
        if (!$existing) {
            throw new RuntimeException("Không tìm thấy bảng '$table' trong database đang kết nối.");
        }
        $missing = array_diff($columns, $existing);
        if ($missing) {
            throw new RuntimeException("Bảng '$table' thiếu cột: " . implode(', ', $missing) . '. Hãy kiểm tra SQL trước khi thay đổi cấu trúc.');
        }
    }

    public static function summary(): array
    {
        $db = Database::connection();
        self::assertColumns($db, 'users', ['id', 'name']);
        self::assertColumns($db, 'orders', ['id','order_code','user_id','status','total_amount','created_at','completed_at']);
        self::assertColumns($db, 'products', ['sku','name','price','stock_quantity']);

        $stats = $db->query("SELECT
            COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN total_amount ELSE 0 END), 0) AS day_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' AND YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END), 0) AS month_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' AND YEAR(completed_at) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) AS year_revenue,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS new_orders,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
            COUNT(*) AS total_orders
            FROM orders")->fetch(PDO::FETCH_ASSOC);

        $recent = $db->query("SELECT o.order_code, o.total_amount, o.status, o.created_at,
            COALESCE(u.name, 'Khách vãng lai') AS customer
            FROM orders o LEFT JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC, o.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

        $lowStock = $db->query("SELECT sku, name, price, stock_quantity
            FROM products WHERE stock_quantity < 5
            ORDER BY stock_quantity ASC, name ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        $monthly = $db->query("SELECT MONTH(completed_at) AS month, SUM(total_amount) AS revenue
            FROM orders WHERE status = 'completed' AND completed_at IS NOT NULL
              AND YEAR(completed_at) = YEAR(CURDATE())
            GROUP BY MONTH(completed_at) ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'stats' => $stats ?: [
                'day_revenue' => 0, 'month_revenue' => 0, 'year_revenue' => 0,
                'new_orders' => 0, 'cancelled_orders' => 0, 'total_orders' => 0
            ],
            'recent' => $recent,
            'low_stock' => $lowStock,
            'monthly' => $monthly
        ];
    }
}
