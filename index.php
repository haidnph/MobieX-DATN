<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/app/Controllers/PageController.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Support/RememberLogin.php';
RememberLogin::restore();

$slug = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW) ?: 'home';
$slug = strtolower(trim($slug));
if (in_array($slug, ['register', 'login', 'logout', 'forgot-password', 'reset-password'], true)) {
    (new AuthController())->show($slug);
} else {
    (new PageController())->show($slug);
}
