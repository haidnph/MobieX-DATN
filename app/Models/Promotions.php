
<?php

require_once __DIR__ . '/../Core/Database.php';

final class Promotions
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    // Kiểm tra quyền quản trị
    private static function checkAdmin(int $adminId): void
    {
        $stmt = self::db()->prepare(
            "SELECT role FROM users WHERE id = ?"
        );
        $stmt->execute([$adminId]);

        if ($stmt->fetchColumn() !== 'admin') {
            throw new RuntimeException(
                'Bạn không có quyền quản trị.'
            );
        }
    }

    // ==============================
    // QUẢN LÝ BANNER
    // ==============================

    public static function banners(): array
    {
        return self::db()->query("
            SELECT *
            FROM home_banners
            ORDER BY sort_order ASC, id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveBanner(
        array $form,
        int $adminId
    ): void {
        self::checkAdmin($adminId);

        $id = max(0, (int)($form['id'] ?? 0));
        $title = trim((string)($form['title'] ?? ''));
        $image = trim((string)($form['image_path'] ?? ''));
        $url = trim((string)($form['target_url'] ?? ''));
        $sort = (int)($form['sort_order'] ?? 0);

        if ($title === '' ||
            mb_strlen($title, 'UTF-8') > 180) {
            throw new InvalidArgumentException(
                'Tiêu đề banner không hợp lệ.'
            );
        }

        if (!preg_match(
            '~^public/uploads/banners/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~i',
            $image
        )) {
            throw new InvalidArgumentException(
                'Đường dẫn ảnh không hợp lệ.'
            );
        }

        if (
            strlen($url) > 500 ||
            ($url !== '' &&
             !preg_match(
                 '~^(https?://[^\s]+|(?:index\.php|\?page=)[^\s]*)$~i',
                 $url
             ))
        ) {
            throw new InvalidArgumentException(
                'Liên kết banner không hợp lệ.'
            );
        }

        $db = self::db();

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE home_banners
                SET title = ?,
                    image_path = ?,
                    target_url = ?,
                    sort_order = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $title, $image, $url, $sort, $id
            ]);

            $check = $db->prepare(
                "SELECT COUNT(*) FROM home_banners WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException(
                    'Không tìm thấy banner.'
                );
            }
        } else {
            $stmt = $db->prepare("
                INSERT INTO home_banners
                    (title, image_path, target_url, sort_order)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $title, $image, $url, $sort
            ]);
        }
    }

    public static function toggleBanner(
        int $id,
        int $adminId
    ): void {
        self::checkAdmin($adminId);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID banner không hợp lệ.'
            );
        }

        $stmt = self::db()->prepare("
            UPDATE home_banners
            SET is_active = 1 - is_active
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    public static function activeBanners(): array
    {
        return self::db()->query("
            SELECT id, title, image_url AS image_path, target_url
            FROM banners
            WHERE is_active = 1
              AND (start_at IS NULL OR start_at <= NOW())
              AND (end_at IS NULL OR end_at >= NOW())
            ORDER BY sort_order ASC, id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==============================
    // QUẢN LÝ FLASH SALE
    // ==============================

    public static function campaigns(): array
    {
        return self::db()->query("
            SELECT
                f.*,
                COUNT(fp.product_id) AS product_count,
                CASE
                    WHEN f.is_active = 0 THEN 'disabled'
                    WHEN NOW() < f.starts_at THEN 'upcoming'
                    WHEN NOW() >= f.ends_at THEN 'ended'
                    ELSE 'running'
                END AS live_status
            FROM flash_sales f
            LEFT JOIN flash_sale_products fp
                ON fp.flash_sale_id = f.id
            GROUP BY f.id
            ORDER BY f.starts_at DESC, f.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function products(): array
    {
        return self::db()->query("
            SELECT id, sku, name, price, stock_quantity
            FROM products
            ORDER BY name ASC
            LIMIT 500
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function campaignProducts(
        int $id
    ): array {
        $stmt = self::db()->prepare("
            SELECT product_id, sale_price
            FROM flash_sale_products
            WHERE flash_sale_id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveCampaign(
        array $form,
        int $adminId
    ): void {
        self::checkAdmin($adminId);

        $id = max(0, (int)($form['id'] ?? 0));
        $name = trim((string)($form['name'] ?? ''));

        if ($name === '' ||
            mb_strlen($name, 'UTF-8') > 180) {
            throw new InvalidArgumentException(
                'Tên Flash Sale không hợp lệ.'
            );
        }

        $start = (string)($form['starts_at'] ?? '');
        $end = (string)($form['ends_at'] ?? '');

        $startDt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $start
        );

        $endDt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $end
        );

        if (
            !$startDt ||
            !$endDt ||
            $startDt->format('Y-m-d\TH:i') !== $start ||
            $endDt->format('Y-m-d\TH:i') !== $end ||
            $endDt <= $startDt
        ) {
            throw new InvalidArgumentException(
                'Thời gian Flash Sale không hợp lệ.'
            );
        }

        $productIds = $form['product_ids'] ?? [];
        $prices = $form['sale_prices'] ?? [];

        if (
            !is_array($productIds) ||
            !is_array($prices) ||
            count($productIds) > 100
        ) {
            throw new InvalidArgumentException(
                'Danh sách sản phẩm không hợp lệ.'
            );
        }

        $items = [];

        foreach ($productIds as $rawId) {
            $pid = filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$pid) {
                throw new InvalidArgumentException(
                    'ID sản phẩm không hợp lệ.'
                );
            }

            $price = $prices[(int)$pid] ?? null;

            if (
                !is_scalar($price) ||
                !preg_match(
                    '/^\d{1,13}(\.\d{1,2})?$/',
                    (string)$price
                ) ||
                (float)$price <= 0
            ) {
                throw new InvalidArgumentException(
                    'Giá Flash Sale phải lớn hơn 0.'
                );
            }

            $items[(int)$pid] = (string)$price;
        }

        if (!$items) {
            throw new InvalidArgumentException(
                'Hãy chọn ít nhất một sản phẩm.'
            );
        }

        $db = self::db();
        $db->beginTransaction();

        try {
            // Khóa dữ liệu sản phẩm khi kiểm tra giá
            $ids = array_keys($items);
            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            $stmt = $db->prepare("
                SELECT id, price
                FROM products
                WHERE id IN ($placeholders)
                FOR UPDATE
            ");

            $stmt->execute($ids);
            $originalPrices = $stmt->fetchAll(
                PDO::FETCH_KEY_PAIR
            );

            if (count($originalPrices) !== count($ids)) {
                throw new InvalidArgumentException(
                    'Có sản phẩm không tồn tại.'
                );
            }

            foreach ($items as $pid => $price) {
                if ((float)$price >=
                    (float)$originalPrices[$pid]) {
                    throw new InvalidArgumentException(
                        'Giá khuyến mãi phải thấp hơn giá gốc.'
                    );
                }
            }

            if ($id > 0) {
                $check = $db->prepare("
                    SELECT id
                    FROM flash_sales
                    WHERE id = ?
                    FOR UPDATE
                ");

                $check->execute([$id]);

                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException(
                        'Không tìm thấy chiến dịch.'
                    );
                }

                $stmt = $db->prepare("
                    UPDATE flash_sales
                    SET name = ?,
                        starts_at = ?,
                        ends_at = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $startDt->format('Y-m-d H:i:s'),
                    $endDt->format('Y-m-d H:i:s'),
                    $id
                ]);

                $db->prepare("
                    DELETE FROM flash_sale_products
                    WHERE flash_sale_id = ?
                ")->execute([$id]);

            } else {
                $stmt = $db->prepare("
                    INSERT INTO flash_sales
                        (name, starts_at, ends_at)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $startDt->format('Y-m-d H:i:s'),
                    $endDt->format('Y-m-d H:i:s')
                ]);

                $id = (int)$db->lastInsertId();
            }

            $insert = $db->prepare("
                INSERT INTO flash_sale_products
                    (flash_sale_id, product_id, sale_price)
                VALUES (?, ?, ?)
            ");

            foreach ($items as $pid => $price) {
                $insert->execute([
                    $id, $pid, $price
                ]);
            }

            $db->commit();

        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function toggleCampaign(
        int $id,
        int $adminId
    ): void {
        self::checkAdmin($adminId);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID chiến dịch không hợp lệ.'
            );
        }

        $stmt = self::db()->prepare("
            UPDATE flash_sales
            SET is_active = 1 - is_active
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    public static function liveFlashProducts(): array
    {
        $rows = self::db()->query("
            SELECT
                p.id,
                v.id AS variant_id,
                p.name,
                v.sku,
                i.available_quantity AS stock_quantity,
                v.price AS original_price,
                fi.sale_price,
                f.name AS campaign_name,
                f.end_at AS ends_at,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), f.end_at)) AS remaining_seconds
            FROM flash_sales f
            INNER JOIN flash_sale_items fi ON fi.flash_sale_id=f.id
            INNER JOIN product_variants v ON v.id=fi.variant_id
            INNER JOIN products p ON p.id=v.product_id
            INNER JOIN inventories i ON i.variant_id=v.id
            WHERE f.status='active'
              AND NOW() >= f.start_at
              AND NOW() < f.end_at
              AND i.available_quantity > 0
              AND p.status='active' AND v.status='active'
              AND fi.sale_price > 0
              AND fi.sale_price < v.price
            ORDER BY fi.sale_price ASC, f.end_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        // Nếu các campaign trùng một sản phẩm, chọn giá sale thấp nhất.
        $unique = [];
        foreach ($rows as $row) {
            $pid = (int)$row['id'];
            if (!isset($unique[$pid])) {
                $unique[$pid] = $row;
            }
        }
        return array_values($unique);
    }
}
