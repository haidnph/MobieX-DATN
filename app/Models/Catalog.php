
<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class Catalog
{
    public static function products(array $filters = []): array
    {
        $where = [
            "p.status='active'",
            "v.status='active'"
        ];

        $args = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR v.sku LIKE ?)';

            $q = '%' . mb_substr(
                trim((string)$filters['q']),
                0,
                120
            ) . '%';

            array_push($args, $q, $q, $q);
        }

        if (!empty($filters['brand'])) {
            $where[] = 'b.slug=?';
            $args[] = $filters['brand'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'c.slug=?';
            $args[] = $filters['category'];
        }

        if (
            ($filters['min'] ?? '') !== '' &&
            is_numeric($filters['min'])
        ) {
            $where[] = 'v.price>=?';
            $args[] = (float)$filters['min'];
        }

        if (
            ($filters['max'] ?? '') !== '' &&
            is_numeric($filters['max'])
        ) {
            $where[] = 'v.price<=?';
            $args[] = (float)$filters['max'];
        }

        if (!empty($filters['attribute'])) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM variant_attribute_values x
                JOIN attribute_values y
                  ON y.id=x.attribute_value_id
                WHERE x.variant_id=v.id AND y.value=?
            )';

            $args[] = $filters['attribute'];
        }

        $sort = match ($filters['sort'] ?? '') {
            'price_asc' => 'MIN(v.price) ASC',
            'price_desc' => 'MIN(v.price) DESC',
            'name' => 'p.name ASC',
            default => 'p.created_at DESC,p.id DESC'
        };

        $sql = "
            SELECT
                p.id, p.name, p.slug, p.description,
                b.name brand,
                c.name category,
                MIN(v.price) price,
                MAX(v.compare_price) compare_price,
                SUM(COALESCE(i.available_quantity,0)) stock,
                MIN(pi.image_url) image,
                COUNT(DISTINCT v.id) variant_count
            FROM products p
            JOIN product_variants v ON v.product_id=p.id
            LEFT JOIN inventories i ON i.variant_id=v.id
            LEFT JOIN brands b ON b.id=p.brand_id
            LEFT JOIN categories c ON c.id=p.category_id
            LEFT JOIN product_images pi
              ON pi.product_id=p.id AND pi.is_primary=1
            WHERE " . implode(' AND ', $where) . "
            GROUP BY
                p.id,p.name,p.slug,p.description,b.name,c.name
            ORDER BY $sort
            LIMIT 100
        ";

        $st = Database::connection()->prepare($sql);
        $st->execute($args);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function detail(int $id): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT p.*, b.name brand, c.name category
             FROM products p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.id=? AND p.status='active'"
        );

        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            return null;
        }

        $st = Database::connection()->prepare(
            "SELECT
                v.id,
                v.sku,
                COALESCE(
                    (SELECT MIN(fi.sale_price)
                     FROM flash_sale_items fi
                     JOIN flash_sales fs
                       ON fs.id=fi.flash_sale_id
                     WHERE fi.variant_id=v.id
                       AND fs.status='active'
                       AND fs.start_at<=NOW()
                       AND fs.end_at>NOW()
                       AND (
                           fi.quantity_limit IS NULL
                           OR fi.quantity_sold<fi.quantity_limit
                       )
                       AND fi.sale_price<v.price),
                    v.price
                ) price,
                v.compare_price,
                v.image_url,
                COALESCE(i.available_quantity,0) stock,
                GROUP_CONCAT(
                    CONCAT(a.name,': ',av.value)
                    ORDER BY a.name SEPARATOR ', '
                ) attributes
             FROM product_variants v
             LEFT JOIN inventories i ON i.variant_id=v.id
             LEFT JOIN variant_attribute_values x
               ON x.variant_id=v.id
             LEFT JOIN attribute_values av
               ON av.id=x.attribute_value_id
             LEFT JOIN attributes a ON a.id=av.attribute_id
             WHERE v.product_id=? AND v.status='active'
             GROUP BY
                v.id,v.sku,v.price,v.compare_price,
                v.image_url,i.available_quantity
             ORDER BY v.id"
        );

        $st->execute([$id]);
        $p['variants'] = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = Database::connection()->prepare(
            'SELECT image_url, alt_text
             FROM product_images
             WHERE product_id=?
             ORDER BY is_primary DESC,sort_order,id'
        );

        $st->execute([$id]);
        $p['images'] = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = Database::connection()->prepare(
            "SELECT p.id,p.name,p.slug,pc.note
             FROM product_compatibilities pc
             JOIN products p ON p.id=pc.compatible_product_id
             WHERE pc.product_id=? AND p.status='active'"
        );

        $st->execute([$id]);
        $p['compatible'] = $st->fetchAll(PDO::FETCH_ASSOC);

        return $p;
    }

    public static function brands(): array
    {
        return Database::connection()->query(
            "SELECT name,slug FROM brands
             WHERE status='active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function categories(): array
    {
        return Database::connection()->query(
            "SELECT name,slug FROM categories
             WHERE status='active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function compare(array $ids): array
    {
        $ids = array_slice(
            array_values(
                array_unique(
                    array_filter(
                        array_map('intval', $ids),
                        static fn(int $id): bool => $id > 0
                    )
                )
            ),
            0,
            3
        );

        if (!$ids) {
            return [];
        }

        $mark = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $q = Database::connection()->prepare(
            "SELECT
                p.id,p.name,
                b.name brand,
                c.name category,
                MIN(v.price) price,
                MAX(v.compare_price) compare_price,
                SUM(COALESCE(i.available_quantity,0)) stock,
                GROUP_CONCAT(
                    DISTINCT av.value
                    ORDER BY av.value SEPARATOR ', '
                ) attributes
             FROM products p
             JOIN product_variants v ON v.product_id=p.id
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             LEFT JOIN inventories i ON i.variant_id=v.id
             LEFT JOIN variant_attribute_values x
               ON x.variant_id=v.id
             LEFT JOIN attribute_values av
               ON av.id=x.attribute_value_id
             WHERE p.id IN ($mark) AND p.status='active'
             GROUP BY p.id,p.name,b.name,c.name"
        );

        $q->execute($ids);

        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function variant(int $id): ?array
    {
        $q = Database::connection()->prepare(
            "SELECT
                v.id,
                COALESCE(
                    (SELECT MIN(fi.sale_price)
                     FROM flash_sale_items fi
                     JOIN flash_sales fs
                       ON fs.id=fi.flash_sale_id
                     WHERE fi.variant_id=v.id
                       AND fs.status='active'
                       AND fs.start_at<=NOW()
                       AND fs.end_at>NOW()
                       AND (
                           fi.quantity_limit IS NULL
                           OR fi.quantity_sold<fi.quantity_limit
                       )
                       AND fi.sale_price<v.price),
                    v.price
                ) price,
                v.image_url,
                COALESCE(i.available_quantity,0) stock
             FROM product_variants v
             JOIN products p ON p.id=v.product_id
             LEFT JOIN inventories i ON i.variant_id=v.id
             WHERE v.id=?
               AND v.status='active'
               AND p.status='active'"
        );

        $q->execute([$id]);

        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function bestSellers(): array
    {
        return Database::connection()->query(
            "SELECT
                p.id,p.name,
                MIN(v.price) price,
                SUM(oi.quantity) sold
             FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             JOIN product_variants v
               ON v.id=oi.product_variant_id
             JOIN products p ON p.id=v.product_id
             WHERE p.status='active'
               AND o.order_status='DELIVERED'
             GROUP BY p.id,p.name
             ORDER BY sold DESC LIMIT 4"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function accessories(): array
    {
        return Database::connection()->query(
            "SELECT p.id,p.name,MIN(v.price) price
             FROM products p
             JOIN categories c ON c.id=p.category_id
             JOIN product_variants v ON v.product_id=p.id
             WHERE p.status='active'
               AND (
                   c.slug LIKE '%accessor%'
                   OR c.slug LIKE '%phu-kien%'
                   OR c.name LIKE '%phụ kiện%'
               )
             GROUP BY p.id,p.name
             ORDER BY p.created_at DESC LIMIT 4"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
