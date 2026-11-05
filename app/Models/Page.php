<?php
declare(strict_types=1);

final class Page
{
    private const ROUTES = [
        'home' => ['view' => 'store/home.php', 'title' => 'MobileX | Trang chủ'],
        'products' => ['view' => 'store/products.php', 'title' => 'MobileX | Sản phẩm'],
        'detail' => ['view' => 'store/detail.php', 'title' => 'MobileX | Chi tiết sản phẩm'],
        'checkout' => ['view' => 'store/checkout.php', 'title' => 'MobileX | Thanh toán'],
        'admin' => ['view' => 'admin/dashboard.php', 'title' => 'MobileX | Quản trị'],
    ];

    public static function find(string $slug): ?array
    {
        return self::ROUTES[$slug] ?? null;
    }
}
