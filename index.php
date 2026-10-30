
<?php


session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);

session_start();

// Controllers
require_once __DIR__
    . '/app/Controllers/PageController.php';

require_once __DIR__
    . '/app/Controllers/AuthController.php';

require_once __DIR__
    . '/app/Controllers/ReviewController.php';

// Remember login
require_once __DIR__
    . '/app/Support/RememberLogin.php';

RememberLogin::restore();

// Xác định trang hiện tại
$slug = filter_input(
    INPUT_GET,
    'page',
    FILTER_UNSAFE_RAW
) ?: 'home';

$slug = strtolower(trim($slug));

// Trang đánh giá
if ($slug === 'reviews') {

    (new ReviewController())->handle();

// Trang xác thực
} elseif (in_array(
    $slug,
    [
        'register',
        'login',
        'logout',
        'forgot-password',
        'reset-password'
    ],
    true
)) {

    (new AuthController())->show($slug);

// Các trang Client khác
} else {

    (new PageController())->show($slug);
}
