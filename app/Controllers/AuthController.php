<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Support/Csrf.php';
require_once __DIR__ . '/../Support/RememberLogin.php';

final class AuthController
{
    public function show(string $action): void
    {
        $routes = [
            'register' => ['auth/form.php', 'Tạo tài khoản'],
            'login' => ['auth/form.php', 'Đăng nhập'],
            'forgot-password' => ['auth/form.php', 'Quên mật khẩu'],
            'reset-password' => ['auth/form.php', 'Đặt lại mật khẩu'],
        ];
        if ($action === 'logout') {
            $this->logout();
            return;
        }
        if (!isset($routes[$action])) {
            http_response_code(404);
            View::render('errors/404.php', 'Không tìm thấy trang');
            return;
        }

        $data = ['action' => $action, 'csrf' => Csrf::token(), 'error' => null, 'message' => $this->takeFlash(), 'old' => []];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::valid($_POST['_csrf'] ?? null)) {
                http_response_code(419);
                $data['error'] = 'Phiên làm việc hết hạn. Vui lòng tải lại trang và thử lại.';
            } elseif ($action === 'register' || $action === 'login') {
                $data = array_merge($data, $this->authenticate($action));
                if (isset($data['redirect'])) {
                    header('Location: ' . $data['redirect']);
                    exit;
                }
            } else {
                $data['error'] = 'Chức năng gửi email đặt lại mật khẩu chưa được cấu hình.';
            }
        } elseif ($action === 'forgot-password' || $action === 'reset-password') {
            $data['message'] = 'Chức năng đặt lại mật khẩu cần cấu hình dịch vụ email trước khi sử dụng.';
        }
        View::render($routes[$action][0], 'MobileX | ' . $routes[$action][1], $data);
    }

    private function authenticate(string $action): array
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $old = ['email' => $email, 'remember' => isset($_POST['remember'])];
        try {
            if ($action === 'register') {
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                    return ['error' => 'Vui lòng nhập tên, email hợp lệ và mật khẩu có ít nhất 8 ký tự.', 'old' => $old];
                }
                if (User::findByEmail($email)) {
                    return ['error' => 'Email này đã được đăng ký. Hãy đăng nhập hoặc dùng email khác.', 'old' => $old];
                }
                $id = User::create($name, $email, $password);
                $account = ['id' => $id, 'name' => $name, 'email' => $email];
                $_SESSION['_flash'] = 'Tạo tài khoản thành công. Bạn đã đăng nhập.';
            } else {
                $user = User::findByEmail($email);
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    return ['error' => 'Email hoặc mật khẩu không chính xác.', 'old' => $old];
                }
                $id = (int) $user['id'];
                $account = ['id' => $id, 'name' => $user['name'], 'email' => $user['email']];
                if (isset($_POST['remember'])) {
                    RememberLogin::remember($id);
                } else {
                    RememberLogin::forget();
                }
                $_SESSION['_flash'] = 'Đăng nhập thành công.';
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $account;
            return ['redirect' => '?page=home'];
        } catch (PDOException $error) {
            error_log('MobileX authentication database error: ' . $error->getMessage());
            return ['error' => 'Chưa kết nối được cơ sở dữ liệu. Hãy kiểm tra cấu hình và bảng tài khoản.', 'old' => $old];
        }
    }

    private function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::valid($_POST['_csrf'] ?? null)) {
            http_response_code(405);
            return;
        }
        RememberLogin::forget();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
        }
        session_destroy();
        header('Location: ?page=home');
    }

    private function takeFlash(): ?string
    {
        $message = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_string($message) ? $message : null;
    }
}
