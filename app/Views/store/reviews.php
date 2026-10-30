<?php
$h = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$product  = (int) ($product ?? 0);
$reviews  = $reviews ?? [];
$stats    = $stats ?? ['total' => 0, 'average' => 0];
$user     = (int) ($user ?? 0);
$eligible = (bool) ($eligible ?? false);
$error    = $error ?? '';
$success  = (bool) ($success ?? false);
?>

<style>
html, body {
    margin: 0;
    padding: 0;
    background: #071226 !important;
    color: #f4f7ff;
    font-family: Arial, sans-serif;
    min-height: 100%;
}

/* nếu layout tổng có thẻ main/container chung */
main,
.page,
.page-content,
.content,
.wrapper,
.site-content {
    background: #071226 !important;
}

.mx-page {
    width: 100%;
    min-height: 100vh;
    background: #071226;
    padding: 28px 0 50px;
}

.mx-reviews {
    max-width: 1050px;
    margin: 0 auto;
    padding: 0 18px;
    color: #f1f5ff;
    font-family: Arial, sans-serif;
}

.mx-card {
    background: #14233d;
    border: 1px solid #2c4164;
    border-radius: 18px;
    padding: 28px;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
}

.mx-back {
    color: #8ab9ff;
    text-decoration: none;
    font-size: 15px;
}

.mx-back:hover {
    text-decoration: underline;
}

.mx-title {
    font-size: 30px;
    line-height: 1.2;
    margin: 10px 0 20px;
    color: #ffffff;
}

.mx-product {
    font-size: 16px;
    margin-bottom: 18px;
    color: #ffffff;
}

.mx-muted {
    color: #b2c2dc;
}

.mx-rating {
    color: #ffc744;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 12px;
}

.mx-reviews h2 {
    font-size: 22px;
    margin: 0 0 20px;
    color: #ffffff;
}

.mx-form-label {
    display: block;
    margin: 18px 0 10px;
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
}

.mx-textarea {
    width: 100%;
    min-height: 145px;
    background: #09182e;
    color: #ffffff;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid #425a7f;
    font-size: 15px;
    resize: vertical;
    box-sizing: border-box;
    outline: none;
}

.mx-textarea:focus {
    border-color: #6ca6ff;
    box-shadow: 0 0 0 3px rgba(108, 166, 255, 0.15);
}

.mx-file {
    display: block;
    margin: 15px 0 10px;
    max-width: 100%;
    color: #d9e6ff;
}

.mx-preview {
    max-width: 180px;
    max-height: 180px;
    border-radius: 10px;
    margin-top: 12px;
    display: none;
    border: 1px solid #314865;
}

.mx-btn {
    background: linear-gradient(135deg, #4d91ff, #2d78ef);
    color: #ffffff;
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    margin-top: 18px;
    transition: 0.2s ease;
}

.mx-btn:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, #66a3ff, #3a84f8);
}

.mx-alert {
    padding: 15px 16px;
    border-radius: 12px;
    margin-bottom: 18px;
    line-height: 1.5;
}

.mx-error {
    background: rgba(180, 52, 73, 0.18);
    border: 1px solid rgba(255, 120, 140, 0.35);
    color: #ffb8c3;
}

.mx-success {
    background: rgba(31, 117, 72, 0.22);
    border: 1px solid rgba(111, 228, 161, 0.35);
    color: #aaf0c8;
}

.mx-warning {
    background: rgba(148, 111, 19, 0.28);
    border: 1px solid rgba(255, 199, 68, 0.28);
    color: #ffe29a;
}

.mx-warning a {
    color: #9cc3ff;
    font-weight: 700;
}

.mx-stars {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 8px;
    margin: 16px 0 22px;
}

.mx-stars input {
    position: absolute;
    opacity: 0;
}

.mx-stars label {
    cursor: pointer;
    font-size: 42px;
    color: #60738f;
    transition: .2s;
    user-select: none;
}

.mx-stars label:hover,
.mx-stars label:hover ~ label,
.mx-stars input:checked ~ label {
    color: #ffc744;
}

.mx-stars input:focus-visible + label {
    outline: 2px solid #80b4ff;
    border-radius: 4px;
}

.mx-review-item {
    padding: 20px 0;
    border-bottom: 1px solid #304662;
}

.mx-review-item:last-child {
    border-bottom: none;
}

.mx-review-name {
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 8px;
}

.mx-review-stars {
    color: #ffc744;
    font-size: 20px;
    margin-bottom: 10px;
}

.mx-review-text {
    color: #eef4ff;
    line-height: 1.6;
}

.mx-review-image {
    width: 200px;
    max-width: 100%;
    border-radius: 10px;
    margin-top: 12px;
    border: 1px solid #314865;
}

.mx-empty {
    color: #b2c2dc;
    margin: 0;
}

@media (max-width: 768px) {
    .mx-page {
        padding: 18px 0 35px;
    }

    .mx-reviews {
        padding: 0 12px;
    }

    .mx-card {
        padding: 20px;
        border-radius: 16px;
    }

    .mx-title {
        font-size: 24px;
    }

    .mx-reviews h2 {
        font-size: 20px;
    }

    .mx-stars label {
        font-size: 38px;
    }
}
</style>

<div class="mx-page">
    <div class="mx-reviews">

        <!-- THÔNG TIN CHUNG -->
        <div class="mx-card">
            <a class="mx-back" href="index.php?page=detail">
                ← Quay lại sản phẩm
            </a>

            <h1 class="mx-title">Đánh giá & Bình luận</h1>

            <div class="mx-product">
                Sản phẩm #<?= $h($product) ?>
            </div>

            <div class="mx-rating">
                ★ <?= $h($stats['average']) ?>/5
            </div>

            <p class="mx-muted">
                <?= $h($stats['total']) ?> đánh giá đã được duyệt
            </p>
        </div>

        <!-- FORM ĐÁNH GIÁ -->
        <div class="mx-card">
            <h2>Viết đánh giá của bạn</h2>

            <?php if ($error): ?>
                <div class="mx-alert mx-error">
                    <?= $h($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mx-alert mx-success">
                    ✓ Đã gửi đánh giá thành công. Đánh giá của bạn đang chờ Admin phê duyệt.
                </div>
            <?php endif; ?>

            <?php if (!$user): ?>
                <div class="mx-alert mx-warning">
                    Bạn cần đăng nhập trước khi gửi đánh giá.
                    <a href="?page=login">Đăng nhập</a>
                </div>
            <?php elseif (!$eligible): ?>
                <div class="mx-alert mx-warning">
                    Tài khoản hiện chưa đủ điều kiện gửi đánh giá mới.
                    Bạn cần có đơn hàng đã hoàn tất và chưa đánh giá sản phẩm này.
                </div>
            <?php endif; ?>

            <form
                method="POST"
                enctype="multipart/form-data"
                action="index.php?page=reviews&amp;product_id=<?= $product ?>"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= $h(Csrf::token()) ?>"
                >

                <label class="mx-form-label">
                    1. Chấm sao cho sản phẩm
                </label>

                <div class="mx-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input
                            type="radio"
                            id="mx-star-<?= $i ?>"
                            name="rating"
                            value="<?= $i ?>"
                            required
                        >
                        <label
                            for="mx-star-<?= $i ?>"
                            title="<?= $i ?> sao"
                        >★</label>
                    <?php endfor; ?>
                </div>

                <label class="mx-form-label" for="mx-comment">
                    2. Viết nhận xét
                </label>

                <textarea
                    class="mx-textarea"
                    name="comment"
                    id="mx-comment"
                    minlength="5"
                    maxlength="2000"
                    required
                    placeholder="Hãy chia sẻ trải nghiệm thực tế của bạn về sản phẩm..."
                ><?= $h($_POST['comment'] ?? '') ?></textarea>

                <label class="mx-form-label" for="mx-photo">
                    3. Thêm ảnh sản phẩm thực tế
                </label>

                <input
                    class="mx-file"
                    type="file"
                    name="photo"
                    id="mx-photo"
                    accept="image/jpeg,image/png,image/webp"
                >

                <img
                    id="mx-preview"
                    class="mx-preview"
                    alt="Xem trước ảnh đánh giá"
                >

                <p class="mx-muted">
                    Hỗ trợ JPG, PNG, WEBP. Tối đa 5 MB.
                </p>

                <button class="mx-btn" type="submit">
                    ★ Gửi đánh giá 
                </button>
            </form>
        </div>

        <!-- DANH SÁCH ĐÁNH GIÁ -->
        <div class="mx-card">
            <h2>Đánh giá từ khách hàng</h2>

            <?php if (empty($reviews)): ?>
                <p class="mx-empty">
                    Chưa có đánh giá được duyệt cho sản phẩm này.
                </p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="mx-review-item">
                        <div class="mx-review-name">
                            <?= $h($review['name']) ?>
                        </div>

                        <div class="mx-review-stars">
                            <?= str_repeat('★', (int) $review['rating']) ?>
                        </div>

                        <p class="mx-muted">
                            Đã mua hàng · <?= $h($review['created_at']) ?>
                        </p>

                        <div class="mx-review-text">
                            <?= nl2br($h($review['comment'] ?? $review['content'] ?? '')) ?>
                        </div>

                        <?php if (!empty($review['image_path'])): ?>
                            <img
                                class="mx-review-image"
                                src="<?= $h($review['image_path']) ?>"
                                alt="Ảnh khách hàng"
                                loading="lazy"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
(function () {
    const input = document.getElementById('mx-photo');
    const preview = document.getElementById('mx-preview');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = this.files && this.files[0];

        if (!file) {
            preview.removeAttribute('src');
            preview.style.display = 'none';
            return;
        }

        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 5 * 1024 * 1024
        ) {
            alert('Chọn ảnh JPG, PNG hoặc WEBP tối đa 5 MB.');
            this.value = '';
            preview.style.display = 'none';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.style.display = 'block';
        };

        reader.readAsDataURL(file);
    });
})();
</script>