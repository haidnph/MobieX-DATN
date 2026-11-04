
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
require_once __DIR__ . '/app/Controllers/CheckoutController.php';
require_once __DIR__ . '/app/Models/Catalog.php';
require_once __DIR__ . '/app/Controllers/ProfileController.php';
require_once __DIR__ . '/app/Controllers/OrderController.php';
require_once __DIR__ . '/app/Controllers/PaymentController.php';

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

// Trang đặt hàng: xử lý POST và lưu đơn vào cùng DB với Admin
if ($slug === 'checkout') {
    (new CheckoutController())->handle();
} elseif ($slug === 'profile') {
    (new ProfileController())->handle();
} elseif ($slug === 'orders' || $slug === 'track-orders') {
    (new OrderController())->handle($slug);
} elseif ($slug === 'payment' || $slug === 'payment-return' || $slug === 'payment-ipn') {
    if($slug==='payment-return')$_GET['mode']='return';
    if($slug==='payment-ipn')$_GET['mode']='ipn';
    (new PaymentController())->handle();
} elseif ($slug === 'search-suggestions') {
    header('Content-Type: application/json; charset=utf-8');
    $rows=Catalog::products(['q'=>trim((string)($_GET['q']??''))]);
    echo json_encode(array_map(static fn($p)=>['id'=>(int)$p['id'],'name'=>$p['name']],array_slice($rows,0,8)),JSON_UNESCAPED_UNICODE);
} elseif ($slug === 'variant') {
    header('Content-Type: application/json; charset=utf-8');
    $variant=Catalog::variant((int)($_GET['id']??0));
    if(!$variant){http_response_code(404);echo json_encode(['error'=>'Phiên bản không tồn tại.'],JSON_UNESCAPED_UNICODE);}else echo json_encode($variant,JSON_UNESCAPED_UNICODE);
} elseif ($slug === 'reviews') {

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
