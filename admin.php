<?php

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/Dashboard.php';
require_once __DIR__ . '/app/Models/Reviews.php';
require_once __DIR__ . '/app/Models/Promotions.php';
require_once __DIR__ . '/app/Support/Csrf.php';
require_once __DIR__ . '/app/Support/RememberLogin.php';

try {
    RememberLogin::restore();
} catch (Throwable $error) {
    error_log('MobileX remember login: ' . $error->getMessage());
}

// Kiểm tra đăng nhập
if (empty($_SESSION['user']['id'])) {
    header('Location: index.php?page=login');
    exit;
}

try {
    // Kiểm tra quyền Admin trực tiếp trong MySQL
    $admin = User::findById(
        (int)$_SESSION['user']['id']
    );

    if (!$admin || ($admin['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('403 - Ban khong co quyen truy cap Admin.');
    }

    // BANNER & FLASH SALE - xử lý trong chính admin.php.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        strncmp((string)($_POST['admin_action'] ?? ''), 'promo_', 6) === 0) {
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Phiên bảo mật đã hết hạn. Hãy tải lại trang.');
        }
        try {
            $promoAction = (string)$_POST['admin_action'];
            $promoAdminId = (int)$admin['id'];
            if ($promoAction === 'promo_save_banner') {
                $imagePath = (string)($_POST['image_path'] ?? '');
                if (isset($_FILES['banner_image']) && ($_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $upload = $_FILES['banner_image'];
                    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 3 * 1024 * 1024 || $upload['size'] < 1 || !is_uploaded_file($upload['tmp_name'])) {
                        throw new InvalidArgumentException('Ảnh phải nhỏ hơn 3MB và được tải lên hợp lệ.');
                    }
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                    $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                    if (!isset($extensions[$mime]) || @getimagesize($upload['tmp_name']) === false) {
                        throw new InvalidArgumentException('Chỉ hỗ trợ ảnh JPG, PNG hoặc WebP.');
                    }
                    $directory = __DIR__ . '/public/uploads/banners';
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new RuntimeException('Không tạo được thư mục ảnh banner.');
                    }
                    $basename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $basename)) {
                        throw new RuntimeException('Không lưu được ảnh banner.');
                    }
                    $imagePath = 'public/uploads/banners/' . $basename;
                }
                $form = $_POST;
                $form['image_path'] = $imagePath;
                Promotions::saveBanner($form, $promoAdminId);
            } elseif ($promoAction === 'promo_toggle_banner') {
                Promotions::toggleBanner((int)($_POST['id'] ?? 0), $promoAdminId);
            } elseif ($promoAction === 'promo_save_campaign') {
                Promotions::saveCampaign($_POST, $promoAdminId);
            } elseif ($promoAction === 'promo_toggle_campaign') {
                Promotions::toggleCampaign((int)($_POST['id'] ?? 0), $promoAdminId);
            } else {
                throw new InvalidArgumentException('Thao tác không hợp lệ.');
            }
            $_SESSION['_promotion_flash'] = 'Đã lưu thay đổi Banner / Flash Sale.';
        } catch (Throwable $ex) {
            error_log('MobileX promotions: ' . $ex->getMessage());
            $_SESSION['_promotion_flash'] = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Không lưu được. Kiểm tra MySQL và PHP error log.';
        }
        header('Location: admin.php?section=promotions#promotions', true, 303);
        exit;
    }

    // Xử lý đánh giá ngay bên trong trang admin.php.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($_POST['admin_action'] ?? '') === 'moderate_review') {
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Phiên CSRF hết hạn. Tải lại trang Admin.');
        }
        $reviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
        $nextStatus = (string)($_POST['status'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!$reviewId || !in_array($nextStatus, ['approved', 'hidden'], true)
            || mb_strlen($reason, 'UTF-8') > 500
            || ($nextStatus === 'hidden' && $reason === '')) {
            $_SESSION['_review_flash'] = 'Dữ liệu kiểm duyệt không hợp lệ; khi ẩn phải ghi lý do (tối đa 500 ký tự).';
        } else {
            try {
                $saved = Reviews::moderate((int)$reviewId, $nextStatus, (int)$admin['id'], $reason);
                $_SESSION['_review_flash'] = $saved
                    ? 'Đã cập nhật trạng thái đánh giá.' : 'Không tìm thấy đánh giá cần xử lý.';
            } catch (Throwable $ex) {
                error_log('Review moderation: '.$ex->getMessage());
                $_SESSION['_review_flash'] = 'Không cập nhật được đánh giá. Xem PHP error log.';
            }
        }
        header('Location: admin.php?section=reviews#reviews', true, 303);
        exit;
    }

    $reviewFilter = (string)($_GET['review_status'] ?? 'all');
    if (!in_array($reviewFilter, ['all', 'pending', 'approved', 'hidden'], true)) $reviewFilter = 'all';
    $reviewSearch = mb_substr(trim((string)($_GET['review_q'] ?? '')), 0, 100, 'UTF-8');
    $reviewPage = max(1, min(100000, (int)($_GET['review_page'] ?? 1)));
    $reviewError = null;
    try {
        $reviewStats = Reviews::stats();
        $reviewResult = Reviews::listing($reviewFilter, $reviewSearch, $reviewPage);
        $reviewRows = $reviewResult['rows'];
        $reviewTotalPages = max(1, (int)ceil($reviewResult['total'] / Reviews::PAGE_SIZE));
    } catch (Throwable $ex) {
        error_log('Review listing: '.$ex->getMessage());
        $reviewError = 'Không tải được dữ liệu đánh giá. Chi tiết đã lưu trong PHP error log: ' . get_class($ex) . ' tại ' . basename($ex->getFile()) . ':' . $ex->getLine();
        $reviewStats = ['total'=>0,'pending'=>0,'approved'=>0,'hidden'=>0];
        $reviewRows = [];
        $reviewTotalPages = 1;
    }
    $reviewFlash = $_SESSION['_review_flash'] ?? null;
    unset($_SESSION['_review_flash']);

    $promotionError = null;
    $promotionFlash = $_SESSION['_promotion_flash'] ?? null;
    unset($_SESSION['_promotion_flash']);
    try {
        $promotionBanners = Promotions::banners();
        $promotionCampaigns = Promotions::campaigns();
        $promotionProducts = Promotions::products();
    } catch (Throwable $ex) {
        error_log('MobileX promotion listing: ' . $ex->getMessage());
        $promotionError = 'Không đọc được Banner / Flash Sale. Hãy import MobileX_promotions.sql và kiểm tra PHP error log.';
        $promotionBanners = $promotionCampaigns = $promotionProducts = [];
    }

    $data = Dashboard::summary();

} catch (Throwable $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    http_response_code(500);
    exit('Không tải được dữ liệu Dashboard. Hãy xem PHP error log (Laragon) để biết tên bảng hoặc cột bị lỗi.');
}

function e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function vnd($value): string {
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

$stats = $data['stats'];
$recent = $data['recent'];
$lowStock = $data['low_stock'];

$monthly = array_fill(0, 12, 0);

foreach ($data['monthly'] as $row) {
    $index = (int)$row['month'] - 1;

    if ($index >= 0 && $index < 12) {
        $monthly[$index] = (float)$row['revenue'];
    }
}

$statusNames = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>MobileX | Admin Dashboard</title>

    <link rel="stylesheet"
          href="public/assets/css/admin.css">
    <link rel="stylesheet"
          href="public/assets/css/admin-reviews-inline.css">
    <link rel="stylesheet" href="public/assets/css/admin-promotions.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="logo">
            <div class="logo-icon">M</div>
            <div>
                <h2>Mobile<span>X</span></h2>
                <small>ADMIN CONTROL</small>
            </div>
        </div>

        <p class="menu-label">TỔNG QUAN</p>

        <nav class="sidebar-menu">

            <a href="#dashboard" class="active">
                <span>▦</span> Dashboard
            </a>

            <a href="#revenue">
                <span>▥</span> Thống kê doanh thu
            </a>

            <a href="#orders">
                <span>▤</span> Quản lý đơn hàng
            </a>

            <a href="admin.php?section=reviews#reviews" class="review-nav">
                <span>★</span> Quản lý Đánh giá
            </a>

            <a href="admin.php?section=promotions" class="promotion-nav"><span>▣</span> Quản lý Banner & Khuyến mãi</a>

            <a href="#inventory">
                <span>◫</span> Kho & Tồn kho
            </a>

        </nav>

        <p class="menu-label">HỆ THỐNG</p>

        <nav class="sidebar-menu">

            <a href="index.php?page=home">
                <span>↗</span> Website bán hàng
            </a>

        </nav>

        <div class="sidebar-user">
            <div class="avatar">A</div>
            <div>
                <strong><?= e($admin['name']) ?></strong>
                <small>Administrator</small>
            </div>
        </div>

    </aside>

    <!-- MAIN -->
    <div class="main">

        <header class="topbar">

            <div>
                <small>MobileX / Admin</small>
                <h3>Dashboard & Thống kê</h3>
            </div>

            <div class="top-actions">

                <span class="today">
                    <?= date('d/m/Y') ?>
                </span>

                <a href="index.php?page=home"
                   class="website-btn">
                    Xem cửa hàng ↗
                </a>

                <form method="POST"
                      action="index.php?page=logout">

                    <input type="hidden"
                           name="_csrf"
                           value="<?= e(Csrf::token()) ?>">

                    <button type="submit" class="logout-btn">
                        Đăng xuất
                    </button>
                </form>

            </div>

        </header>

        <main class="content" id="dashboard">

            <div class="heading">

                <div>
                    <span class="eyebrow">
                        ● MOBILEX ANALYTICS
                    </span>

                    <h1>Tổng quan kinh doanh</h1>

                    <p>
                        Theo dõi doanh thu, đơn hàng
                        và tình trạng tồn kho.
                    </p>
                </div>

                <span class="period">
                    Năm <?= date('Y') ?>
                </span>

            </div>

            <!-- KPI CARDS -->
            <section class="stats-grid">

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Doanh thu hôm nay</span>
                        <div class="stat-icon blue">↗</div>
                    </div>

                    <h2><?= vnd($stats['day_revenue']) ?></h2>
                    <p>Đơn hàng đã hoàn thành</p>
                    <div class="line blue-line"></div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Doanh thu tháng</span>
                        <div class="stat-icon purple">▥</div>
                    </div>

                    <h2><?= vnd($stats['month_revenue']) ?></h2>
                    <p>Tháng <?= date('m/Y') ?></p>
                    <div class="line purple-line"></div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Doanh thu năm</span>
                        <div class="stat-icon green">◈</div>
                    </div>

                    <h2><?= vnd($stats['year_revenue']) ?></h2>
                    <p>Năm <?= date('Y') ?></p>
                    <div class="line green-line"></div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Đơn hàng mới</span>
                        <div class="stat-icon orange">▤</div>
                    </div>

                    <h2><?= (int)$stats['new_orders'] ?></h2>
                    <p>Đang chờ xác nhận</p>
                    <div class="line orange-line"></div>
                </article>

            </section>

            <!-- CHART & ORDER STATUS -->
            <section class="dashboard-grid" id="revenue">

                <div class="panel chart-panel">

                    <div class="panel-title">
                        <div>
                            <h3>Biểu đồ doanh thu</h3>
                            <p>Doanh thu 12 tháng của năm hiện tại</p>
                        </div>
                        <span class="panel-tag">12 tháng</span>
                    </div>

                    <div class="chart-box">
                        <canvas id="revenueChart"></canvas>
                    </div>

                </div>

                <div class="panel">

                    <div class="panel-title">
                        <div>
                            <h3>Trạng thái đơn hàng</h3>
                            <p>Thống kê đơn hàng thực tế</p>
                        </div>
                    </div>

                    <div class="chart-box donut-box">
                        <canvas id="orderChart"></canvas>
                    </div>

                    <div class="order-summary">
                        <div>
                            <span>Đơn hàng mới</span>
                            <strong>
                                <?= (int)$stats['new_orders'] ?>
                            </strong>
                        </div>

                        <div>
                            <span>Đơn hàng hủy</span>
                            <strong class="danger">
                                <?= (int)$stats['cancelled_orders'] ?>
                            </strong>
                        </div>

                        <div>
                            <span>Tổng đơn hàng</span>
                            <strong>
                                <?= (int)$stats['total_orders'] ?>
                            </strong>
                        </div>
                    </div>

                </div>

            </section>

            <!-- RECENT ORDERS -->
            <section class="panel" id="orders">

                <div class="panel-title">
                    <div>
                        <h3>Đơn hàng gần đây</h3>
                        <p>Danh sách đơn hàng mới nhất</p>
                    </div>
                </div>

                <div class="table-scroll">

                    <table>
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Ngày đặt</th>
                                <th>Giá trị</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($recent as $order): ?>
                            <tr>
                                <td class="code">
                                    <?= e($order['order_code']) ?>
                                </td>

                                <td><?= e($order['customer']) ?></td>

                                <td><?= e($order['created_at']) ?></td>

                                <td class="amount">
                                    <?= vnd($order['total_amount']) ?>
                                </td>

                                <td>
                                    <span class="status
                                        status-<?= e($order['status']) ?>">
                                        <?= e(
                                            $statusNames[$order['status']]
                                            ?? $order['status']
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($recent)): ?>
                            <tr>
                                <td colspan="5" class="empty">
                                    Chưa có đơn hàng nào.
                                </td>
                            </tr>
                        <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </section>

            <!-- LOW STOCK -->
            <section class="panel" id="inventory">

                <div class="panel-title">
                    <div>
                        <h3>Cảnh báo tồn kho</h3>
                        <p>Sản phẩm có số lượng dưới 5 chiếc</p>
                    </div>

                    <span class="warning-tag">
                        <?= count($lowStock) ?> sản phẩm
                    </span>
                </div>

                <div class="table-scroll">

                    <table>
                        <thead>
                            <tr>
                                <th>Mã SKU</th>
                                <th>Sản phẩm</th>
                                <th>Giá bán</th>
                                <th>Tồn kho</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($lowStock as $product): ?>
                            <tr>
                                <td class="code">
                                    <?= e($product['sku']) ?>
                                </td>

                                <td><?= e($product['name']) ?></td>

                                <td><?= vnd($product['price']) ?></td>

                                <td>
                                    <span class="stock-warning">
                                        <?= (int)$product['stock_quantity'] ?>
                                        chiếc
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($lowStock)): ?>
                            <tr>
                                <td colspan="4" class="empty">
                                    Không có sản phẩm sắp hết.
                                </td>
                            </tr>
                        <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </section>


            <!-- QUẢN LÝ ĐÁNH GIÁ: nằm trong cùng admin.php -->
            <section class="panel inline-reviews" id="reviews">
                <div class="panel-title"><div>
                    <h3>★ Quản lý Đánh giá</h3>
                    <p>Duyệt hoặc ẩn nội dung vi phạm. Chỉ đánh giá được duyệt mới nên xuất hiện trên cửa hàng.</p>
                </div></div>
                <?php if ($reviewFlash): ?><p class="review-notice" role="status"><?= e($reviewFlash) ?></p><?php endif; ?>
                <?php if ($reviewError): ?><p class="review-error" role="alert"><?= e($reviewError) ?></p><?php endif; ?>
                <div class="review-kpis">
                    <div><span>Tổng đánh giá</span><strong><?= (int)$reviewStats['total'] ?></strong></div>
                    <div><span>Chờ duyệt</span><strong><?= (int)$reviewStats['pending'] ?></strong></div>
                    <div><span>Đã duyệt</span><strong><?= (int)$reviewStats['approved'] ?></strong></div>
                    <div><span>Đã ẩn</span><strong><?= (int)$reviewStats['hidden'] ?></strong></div>
                </div>
                <form method="GET" action="admin.php" class="review-filters">
                    <input type="hidden" name="section" value="reviews">
                    <input type="search" name="review_q" value="<?= e($reviewSearch) ?>" placeholder="Tìm nội dung, sản phẩm hoặc khách hàng...">
                    <select name="review_status">
                        <option value="all">Tất cả trạng thái</option>
                        <?php foreach (['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','hidden'=>'Đã ẩn'] as $v => $label): ?>
                        <option value="<?= e($v) ?>" <?= $reviewFilter === $v ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Lọc</button>
                    <a href="admin.php?section=reviews">Xóa lọc</a>
                </form>
                <div class="table-scroll"><table class="review-table">
                    <thead><tr><th>ID</th><th>Khách hàng / Sản phẩm</th><th>Sao</th><th>Nội dung</th><th>Trạng thái</th><th>Duyệt / Ẩn</th></tr></thead>
                    <tbody>
                    <?php foreach ($reviewRows as $review): ?>
                        <tr>
                          <td>#<?= (int)$review['id'] ?></td>
                          <td><strong><?= e($review['reviewer']) ?></strong><small><?= e($review['product_name']) ?></small></td>
                          <td><span class="review-stars"><?= str_repeat('★', max(0, min(5, (int)$review['rating']))) ?></span></td>
                          <td class="review-comment"><?= nl2br(e($review['content'])) ?>
                          <?php if (Reviews::flagged((string)$review['content'])): ?><small class="review-warning">⚠ Nghi chứa từ ngữ thô tục — cần kiểm tra</small><?php endif; ?>
                          <?php if (!empty($review['moderation_reason'])): ?><small>Lý do ẩn: <?= e($review['moderation_reason']) ?></small><?php endif; ?>
                          </td>
                          <td><span class="review-status review-status-<?= e($review['status']) ?>"><?= e(['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','hidden'=>'Đã ẩn'][$review['status']] ?? $review['status']) ?></span></td>
                          <td><div class="review-action-stack">
                              <form method="POST" action="admin.php?section=reviews">
                                  <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                  <input type="hidden" name="admin_action" value="moderate_review">
                                  <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                                  <input type="hidden" name="status" value="approved">
                                  <button class="review-approve" type="submit" <?= $review['status']==='approved'?'disabled':'' ?>>Duyệt</button>
                              </form>
                              <form method="POST" action="admin.php?section=reviews">
                                  <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                  <input type="hidden" name="admin_action" value="moderate_review">
                                  <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                                  <input type="hidden" name="status" value="hidden">
                                  <input type="text" name="reason" maxlength="500" placeholder="Lý do ẩn..." required <?= $review['status']==='hidden'?'disabled':'' ?>>
                                  <button class="review-hide" type="submit" <?= $review['status']==='hidden'?'disabled':'' ?>>Ẩn</button>
                              </form>
                          </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$reviewRows): ?><tr><td colspan="6" class="empty">Chưa có đánh giá phù hợp.</td></tr><?php endif; ?>
                    </tbody></table></div>
                <div class="review-pagination">
                  <span>Trang <?= $reviewPage ?> / <?= $reviewTotalPages ?></span>
                  <div>
                    <?php if ($reviewPage > 1): ?><a href="<?= e('admin.php?' . http_build_query(['section'=>'reviews','review_status'=>$reviewFilter,'review_q'=>$reviewSearch,'review_page'=>$reviewPage-1])) ?>">← Trước</a><?php endif; ?>
                    <?php if ($reviewPage < $reviewTotalPages): ?><a href="<?= e('admin.php?' . http_build_query(['section'=>'reviews','review_status'=>$reviewFilter,'review_q'=>$reviewSearch,'review_page'=>$reviewPage+1])) ?>">Tiếp →</a><?php endif; ?>
                  </div>
                </div>
            </section>

            <!-- QUẢN LÝ BANNER & FLASH SALE: ĐÃ GỘP TRỰC TIẾP VÀO ADMIN.PHP -->
<?php
// Include from admin.php AFTER checking administrator role and loading $promotionBanners, $promotionCampaigns, $promotionProducts.
$promoEsc = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); };
$promoMoney = static function ($v): string { return number_format((float)$v,0,',','.') . ' ₫'; };
?>
<section class="panel promotion-panel" id="promotions">
 <div class="panel-title"><div><h3>▣ Quản lý Banner & Khuyến mãi</h3><p>Thêm/sửa, bật/tắt slider trang chủ và thiết lập Flash Sale theo khung giờ.</p></div></div>
 <?php if (!empty($promotionFlash)): ?><p class="promotion-flash" role="status"><?= $promoEsc($promotionFlash) ?></p><?php endif; ?>
 <?php if (!empty($promotionError)): ?><p class="promotion-error" role="alert"><?= $promoEsc($promotionError) ?></p><?php endif; ?>
 <div class="promotion-columns">
  <div class="promotion-block">
   <h4>Banner trang chủ</h4>
   <details class="promotion-details"><summary>+ Thêm banner</summary>
    <form method="post" action="admin.php?section=promotions#promotions" enctype="multipart/form-data" class="promotion-form">
     <input type="hidden" name="_csrf" value="<?= $promoEsc(Csrf::token()) ?>"><input type="hidden" name="admin_action" value="promo_save_banner">
     <label>Tiêu đề<input name="title" maxlength="180" required></label>
     <label>Ảnh banner (JPG, PNG, WebP; tối đa 3MB)<input name="banner_image" type="file" accept="image/jpeg,image/png,image/webp" required></label>
     <label>Đích liên kết (tùy chọn)<input name="target_url" placeholder="index.php?page=products"></label>
     <label>Thứ tự<input type="number" name="sort_order" value="0"></label>
     <button type="submit" class="promo-primary">Lưu banner</button>
    </form>
   </details>
   <?php foreach ($promotionBanners as $b): ?>
    <div class="promotion-item">
     <img class="promotion-thumb" src="<?= $promoEsc($b['image_path']) ?>" alt="<?= $promoEsc($b['title']) ?>" loading="lazy">
     <div class="promotion-info"><strong><?= $promoEsc($b['title']) ?></strong><small>Thứ tự <?= (int)$b['sort_order'] ?> · <?= (int)$b['is_active'] ? 'Đang bật' : 'Đã tắt' ?></small></div>
     <form method="post" action="admin.php?section=promotions#promotions"><input type="hidden" name="_csrf" value="<?= $promoEsc(Csrf::token()) ?>"><input type="hidden" name="admin_action" value="promo_toggle_banner"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="promo-secondary" type="submit"><?= (int)$b['is_active'] ? 'Tắt' : 'Bật' ?></button></form>
    </div>
    <details class="promotion-details promotion-edit"><summary>Sửa banner #<?= (int)$b['id'] ?></summary>
     <form method="post" enctype="multipart/form-data" action="admin.php?section=promotions#promotions" class="promotion-form">
      <input type="hidden" name="_csrf" value="<?= $promoEsc(Csrf::token()) ?>"><input type="hidden" name="admin_action" value="promo_save_banner"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="image_path" value="<?= $promoEsc($b['image_path']) ?>">
      <label>Tiêu đề<input name="title" required maxlength="180" value="<?= $promoEsc($b['title']) ?>"></label>
      <label>Thay ảnh (để trống nếu giữ ảnh hiện tại)<input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp"></label>
      <label>Liên kết<input name="target_url" value="<?= $promoEsc($b['target_url']) ?>"></label>
      <label>Thứ tự<input type="number" name="sort_order" value="<?= (int)$b['sort_order'] ?>"></label>
      <button class="promo-primary" type="submit">Lưu thay đổi</button>
     </form>
    </details>
   <?php endforeach; ?>
   <?php if (!$promotionBanners): ?><p class="promo-empty">Chưa có banner nào.</p><?php endif; ?>
  </div>
  <div class="promotion-block">
   <h4>Flash Sale theo khung giờ</h4>
   <?php
   $renderCampaignForm = static function(?array $campaign) use ($promotionProducts,$promoEsc): void {
       $id=(int)($campaign['id']??0); $selected=[];
       if ($id) foreach (Promotions::campaignProducts($id) as $item) $selected[(int)$item['product_id']]=$item['sale_price'];
       $formatDate=static function($v):string {return $v ? date('Y-m-d\TH:i',strtotime((string)$v)):'';};
   ?>
    <form method="post" action="admin.php?section=promotions#promotions" class="promotion-form">
     <input type="hidden" name="_csrf" value="<?= $promoEsc(Csrf::token()) ?>"><input type="hidden" name="admin_action" value="promo_save_campaign"><input type="hidden" name="id" value="<?= $id ?>">
     <label>Tên chiến dịch<input name="name" maxlength="180" required value="<?= $promoEsc($campaign['name']??'') ?>" placeholder="Flash Sale cuối tuần"></label>
     <div class="promo-date-row"><label>Bắt đầu<input type="datetime-local" name="starts_at" required value="<?= $promoEsc($formatDate($campaign['starts_at']??null)) ?>"></label>
     <label>Kết thúc<input type="datetime-local" name="ends_at" required value="<?= $promoEsc($formatDate($campaign['ends_at']??null)) ?>"></label></div>
     <div class="promo-products-caption">Chọn sản phẩm và nhập giá Flash Sale (thấp hơn giá gốc)</div>
     <div class="promo-product-list">
      <?php foreach ($promotionProducts as $product): $pid=(int)$product['id']; ?>
       <label class="promo-product-row"><span><input type="checkbox" name="product_ids[]" value="<?= $pid ?>" <?= array_key_exists($pid,$selected)?'checked':'' ?>> <?= $promoEsc($product['name']) ?><small>Gốc: <?= number_format((float)$product['price'],0,',','.') ?> ₫ · Tồn: <?= (int)$product['stock_quantity'] ?></small></span>
       <input type="number" name="sale_prices[<?= $pid ?>]" min="0.01" step="0.01" placeholder="Giá ưu đãi" value="<?= $promoEsc($selected[$pid]??'') ?>"></label>
      <?php endforeach; ?>
     </div>
     <button type="submit" class="promo-primary"><?= $id?'Cập nhật Flash Sale':'Tạo Flash Sale' ?></button>
    </form>
   <?php }; ?>
   <details class="promotion-details"><summary>+ Tạo Flash Sale</summary><?php $renderCampaignForm(null); ?></details>
   <?php foreach ($promotionCampaigns as $c): ?>
    <div class="promotion-item">
     <div class="promotion-info"><strong><?= $promoEsc($c['name']) ?></strong><small><?= $promoEsc($c['starts_at']) ?> → <?= $promoEsc($c['ends_at']) ?></small><small><?= (int)$c['product_count'] ?> sản phẩm · <?= $promoEsc(['running'=>'Đang chạy','upcoming'=>'Sắp diễn ra','ended'=>'Đã kết thúc','disabled'=>'Đang tắt'][$c['live_status']]??$c['live_status']) ?></small></div>
     <form method="post" action="admin.php?section=promotions#promotions"><input type="hidden" name="_csrf" value="<?= $promoEsc(Csrf::token()) ?>"><input type="hidden" name="admin_action" value="promo_toggle_campaign"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="promo-secondary" type="submit"><?= (int)$c['is_active']?'Tắt':'Bật' ?></button></form>
    </div>
    <details class="promotion-details promotion-edit"><summary>Sửa chiến dịch #<?= (int)$c['id'] ?></summary><?php $renderCampaignForm($c); ?></details>
   <?php endforeach; ?>
   <?php if (!$promotionCampaigns): ?><p class="promo-empty">Chưa có chương trình Flash Sale.</p><?php endif; ?>
  </div>
 </div>
</section>

            <footer class="footer">
                © <?= date('Y') ?> MobileX Admin System
            </footer>

        </main>

    </div>
</div>

<!-- JAVASCRIPT CHART -->
<script>

const monthlyRevenue = <?= json_encode(
    $monthly,
    JSON_NUMERIC_CHECK |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

const totalOrders = <?= (int)$stats['total_orders'] ?>;
const newOrders = <?= (int)$stats['new_orders'] ?>;
const cancelledOrders = <?= (int)$stats['cancelled_orders'] ?>;

if (typeof Chart !== 'undefined') {
Chart.defaults.color = '#93a4c1';

new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: [
            'T1','T2','T3','T4','T5','T6',
            'T7','T8','T9','T10','T11','T12'
        ],
        datasets: [{
            label: 'Doanh thu',
            data: monthlyRevenue,
            borderColor: '#6b9aff',
            backgroundColor: 'rgba(107,154,255,.12)',
            borderWidth: 3,
            pointRadius: 4,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN', {
                            notation: 'compact'
                        }).format(value);
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('orderChart'), {
    type: 'doughnut',
    data: {
        labels: ['Đơn mới', 'Đơn hủy', 'Khác'],
        datasets: [{
            data: [
                newOrders,
                cancelledOrders,
                Math.max(
                    0,
                    totalOrders - newOrders - cancelledOrders
                )
            ],
            backgroundColor: [
                '#f6bb58',
                '#ff6686',
                '#4dd3ae'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

}
</script>

<script>
if (new URLSearchParams(location.search).get('section') === 'promotions') {
    document.getElementById('promotions')?.scrollIntoView({block:'start'});
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.toggle('active', a.classList.contains('promotion-nav')));
}
if (new URLSearchParams(location.search).get('section') === 'reviews') {
    document.getElementById('reviews')?.scrollIntoView({block:'start'});
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.toggle('active', a.classList.contains('review-nav')));
}
</script>
</body>
</html>
