<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class Reviews
{
    public const PAGE_SIZE = 15;
    private const TERMS = ['địt', 'đụ má', 'đéo', 'con cặc', 'vãi lồn', 'fuck', 'shit'];

    public static function flagged(string $content): bool
    {
        $text = mb_strtolower($content, 'UTF-8');
        foreach (self::TERMS as $term) {
            if (mb_strpos($text, $term, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    // Admin: số lượng theo trạng thái.
    public static function stats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'approved'), 0) AS approved,
                COALESCE(SUM(status = 'hidden'), 0) AS hidden
             FROM product_reviews"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'pending' => 0, 'approved' => 0, 'hidden' => 0
        ];
    }

    // Admin: danh sách có phân trang, tìm kiếm và lọc.
    public static function listing(string $status = 'all', string $search = '', int $page = 1): array
    {
        $db = Database::connection();
        $where = [];
        $values = [];

        if (in_array($status, ['pending', 'approved', 'hidden'], true)) {
            $where[] = 'r.status = :status';
            $values['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(r.content LIKE :q1 OR p.name LIKE :q2 OR u.name LIKE :q3 OR u.email LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
                $values[$key] = '%' . $search . '%';
            }
        }

        $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $from = ' FROM product_reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id ';
        $countStmt = $db->prepare('SELECT COUNT(*) ' . $from . $filter);
        $countStmt->execute($values);
        $total = (int) $countStmt->fetchColumn();
        $page = max(1, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $sql = 'SELECT r.id, r.rating, r.content, r.status, r.created_at, r.moderation_reason, '
            . 'u.name AS reviewer, p.name AS product_name' . $from . $filter
            . " ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.created_at DESC, r.id DESC LIMIT :lim OFFSET :off";
        $stmt = $db->prepare($sql);
        foreach ($values as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', self::PAGE_SIZE, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['total' => $total, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    // Admin: phê duyệt hoặc ẩn đánh giá.
    public static function moderate(int $reviewId, string $status, int $adminId, string $reason): bool
    {
        if ($reviewId < 1 || $adminId < 1 || !in_array($status, ['approved', 'hidden'], true)) {
            throw new InvalidArgumentException('Thông số kiểm duyệt không hợp lệ.');
        }
        $reason = trim($reason);
        if ($status === 'hidden' && ($reason === '' || mb_strlen($reason, 'UTF-8') > 500)) {
            throw new InvalidArgumentException('Lý do ẩn phải từ 1 đến 500 ký tự.');
        }
        $db = Database::connection();
        $role = $db->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $role->execute(['id' => $adminId]);
        if ($role->fetchColumn() !== 'admin') {
            throw new RuntimeException('Tài khoản không có quyền Admin.');
        }
        $stmt = $db->prepare(
            'UPDATE product_reviews SET status = :status, moderation_reason = :reason, '
            . 'moderated_by = :admin, moderated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'reason' => $status === 'hidden' ? $reason : null,
            'admin' => $adminId,
            'id' => $reviewId
        ]);
        return $stmt->rowCount() > 0;
    }

    // Client: khách đã mua sản phẩm trong đơn giao thành công.
    public static function eligible(int $userId, int $productId): bool
    {
        if ($userId < 1 || $productId < 1) return false;
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :user AND oi.product_id = :product
               AND o.status IN ('completed', 'delivered') LIMIT 1"
        );
        $stmt->execute(['user' => $userId, 'product' => $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function existing(int $userId, int $productId): bool
    {
        if ($userId < 1 || $productId < 1) return false;
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    // Client: luôn kiểm tra quyền mua tại Model và đưa vào hàng chờ duyệt.
    public static function create(int $userId, int $productId, int $rating, string $comment, ?string $imagePath = null): void
    {
        $comment = trim($comment);
        if ($userId < 1 || $productId < 1 || $rating < 1 || $rating > 5
            || mb_strlen($comment, 'UTF-8') < 5 || mb_strlen($comment, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('Dữ liệu đánh giá không hợp lệ.');
        }
        if ($imagePath !== null && (!preg_match('~^public/uploads/reviews/[a-f0-9]{32}\.(jpg|png|webp)$~', $imagePath))) {
            throw new InvalidArgumentException('Đường dẫn ảnh không hợp lệ.');
        }
        if (!self::eligible($userId, $productId)) {
            throw new RuntimeException('Bạn chưa có đơn hàng đã hoàn tất cho sản phẩm này.');
        }
        if (self::existing($userId, $productId)) {
            throw new RuntimeException('Bạn đã đánh giá sản phẩm này.');
        }
        $stmt = Database::connection()->prepare(
            "INSERT INTO product_reviews (user_id, product_id, rating, content, image_path, status)
             VALUES (:user, :product, :rating, :content, :image, 'pending')"
        );
        $stmt->execute([
            'user' => $userId, 'product' => $productId, 'rating' => $rating,
            'content' => $comment, 'image' => $imagePath
        ]);
    }

    // Client: chỉ cho khách xem các đánh giá được duyệt; alias comment cho view cũ.
    public static function all(int $productId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.id, r.rating, r.content, r.content AS comment,
                    r.image_path, r.created_at, u.name
             FROM product_reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.product_id = :product AND r.status = 'approved'
             ORDER BY r.created_at DESC, r.id DESC LIMIT 100"
        );
        $stmt->execute(['product' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function productStats(int $productId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average
             FROM product_reviews WHERE product_id = ? AND status = 'approved'"
        );
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'average' => 0];
    }
}
