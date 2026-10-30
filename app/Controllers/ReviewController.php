
<?php


require_once __DIR__ . '/../Models/Reviews.php';
require_once __DIR__ . '/../Core/View.php';

final class ReviewController
{
    public function handle(): void
    {
        // 1. Lấy ID sản phẩm
        $product = filter_input(
            INPUT_GET,
            'product_id',
            FILTER_VALIDATE_INT
        );

        if (!$product || $product < 1) {
            http_response_code(400);
            echo 'Mã sản phẩm không hợp lệ.';
            return;
        }

        // 2. Lấy thông tin khách đăng nhập
        $user = (int) ($_SESSION['user']['id'] ?? 0);

        $error = '';
        $success = isset($_GET['sent']);

        $image = null;

        try {

            // 3. Xử lý khi khách gửi đánh giá
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                if ($user < 1) {

                    http_response_code(403);
                    $error = 'Bạn cần đăng nhập để đánh giá.';

                } elseif (!Csrf::valid(
                    $_POST['_csrf'] ?? null
                )) {

                    http_response_code(403);
                    $error = 'Phiên gửi biểu mẫu không hợp lệ.';

                } else {

                    // Lấy số sao
                    $rating = filter_var(
                        $_POST['rating'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    // Lấy nội dung bình luận
                    $comment = trim(
                        (string) ($_POST['comment'] ?? '')
                    );

                    // 4. Kiểm tra dữ liệu
                    if (
                        $rating === false ||
                        $rating === null ||
                        $rating < 1 ||
                        $rating > 5
                    ) {

                        $error = 'Vui lòng chọn từ 1 đến 5 sao.';

                    } elseif (
                        mb_strlen($comment, 'UTF-8') < 5 ||
                        mb_strlen($comment, 'UTF-8') > 2000
                    ) {

                        $error = 'Nhận xét phải từ 5 đến 2000 ký tự.';

                    } elseif (
                        !Reviews::eligible($user, $product)
                    ) {

                        $error = 'Bạn chưa mua hoặc chưa nhận sản phẩm này.';

                    } elseif (
                        Reviews::existing($user, $product)
                    ) {

                        $error = 'Bạn đã đánh giá sản phẩm này.';

                    } else {

                        // 5. Xử lý ảnh sản phẩm thực tế
                        if (
                            isset($_FILES['photo']) &&
                            (
                                $_FILES['photo']['error']
                                ?? UPLOAD_ERR_NO_FILE
                            ) !== UPLOAD_ERR_NO_FILE
                        ) {

                            $file = $_FILES['photo'];

                            if (
                                $file['error'] !== UPLOAD_ERR_OK
                            ) {

                                $error = 'Có lỗi khi tải ảnh lên.';

                            } elseif (
                                $file['size'] > 5 * 1024 * 1024
                            ) {

                                $error = 'Ảnh không được vượt quá 5 MB.';

                            } elseif (
                                !is_uploaded_file(
                                    $file['tmp_name']
                                )
                            ) {

                                $error = 'Tệp tải lên không hợp lệ.';

                            } else {

                                // Kiểm tra định dạng ảnh thật
                                $finfo = new finfo(
                                    FILEINFO_MIME_TYPE
                                );

                                $mime = $finfo->file(
                                    $file['tmp_name']
                                );

                                $types = [
                                    'image/jpeg' => 'jpg',
                                    'image/png'  => 'png',
                                    'image/webp' => 'webp'
                                ];

                                $ext = $types[$mime] ?? null;

                                if (
                                    !$ext ||
                                    !getimagesize(
                                        $file['tmp_name']
                                    )
                                ) {

                                    $error = 'Chỉ chấp nhận JPG, PNG hoặc WEBP.';

                                } else {

                                    // Thư mục lưu ảnh
                                    $dir = __DIR__
                                        . '/../../public/uploads/reviews';

                                    if (
                                        !is_dir($dir) &&
                                        !mkdir(
                                            $dir,
                                            0755,
                                            true
                                        ) &&
                                        !is_dir($dir)
                                    ) {

                                        $error = 'Không tạo được thư mục lưu ảnh.';

                                    } else {

                                        // Tên ảnh ngẫu nhiên
                                        $name = bin2hex(
                                            random_bytes(16)
                                        ) . '.' . $ext;

                                        $destination = $dir
                                            . '/' . $name;

                                        if (
                                            !move_uploaded_file(
                                                $file['tmp_name'],
                                                $destination
                                            )
                                        ) {

                                            $error = 'Không thể lưu ảnh.';

                                        } else {

                                            $image =
                                                'public/uploads/reviews/'
                                                . $name;
                                        }
                                    }
                                }
                            }
                        }

                        // 6. Lưu đánh giá
                        if ($error === '') {

                            Reviews::create(
                                $user,
                                $product,
                                (int) $rating,
                                $comment,
                                $image
                            );

                            // Chuyển trang sau khi lưu
                            header(
                                'Location: ?page=reviews'
                                . '&product_id=' . $product
                                . '&sent=1',
                                true,
                                303
                            );

                            exit;
                        }
                    }
                }
            }

            // 7. Lấy danh sách đánh giá đã duyệt
            $reviews = Reviews::all($product);

            // 8. Thống kê điểm sao trung bình
            $stats = Reviews::productStats($product);

            // 9. Kiểm tra quyền đánh giá
            $eligible = false;

            if ($user > 0) {

                $eligible =
                    Reviews::eligible($user, $product)
                    &&
                    !Reviews::existing($user, $product);
            }

            // 10. Hiển thị giao diện Client
            View::render(
                'store/reviews.php',
                'MobileX | Đánh giá sản phẩm',
                compact(
                    'product',
                    'reviews',
                    'stats',
                    'eligible',
                    'error',
                    'success',
                    'user'
                )
            );

        } catch (PDOException $e) {

            // Xóa ảnh nếu database lưu thất bại
            if ($image !== null) {

                $filePath = __DIR__
                    . '/../../' . $image;

                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            http_response_code(503);

            // Ghi lỗi vào PHP error log
            error_log(
                '[MobileX Review Database Error] '
                . $e->getMessage()
            );

            $isLocal = in_array(
                $_SERVER['REMOTE_ADDR'] ?? '',
                ['127.0.0.1', '::1'],
                true
            );

            echo '<h2>Lỗi kết nối hoặc truy vấn MySQL</h2>';

            if ($isLocal) {

                echo '<pre style="
                    padding:20px;
                    background:#171717;
                    color:#ff7777;
                    white-space:pre-wrap;
                    border-radius:10px;
                ">';

                echo htmlspecialchars(
                    $e->getMessage(),
                    ENT_QUOTES,
                    'UTF-8'
                );

                echo '</pre>';

            } else {

                echo '<p>Không thể tải đánh giá. '
                    . 'Vui lòng thử lại sau.</p>';
            }

        } catch (InvalidArgumentException $e) {

            http_response_code(400);

            echo htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            );

        } catch (RuntimeException $e) {

            if ($image !== null) {

                $filePath = __DIR__
                    . '/../../' . $image;

                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            http_response_code(400);

            echo htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            );
        }
    }
}
