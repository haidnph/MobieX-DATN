<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/User.php';

final class RememberLogin
{
    private const COOKIE = 'mobilex_remember';

    public static function restore(): void
    {
        if (!empty($_SESSION['user']['id']) || empty($_COOKIE[self::COOKIE])) {
            return;
        }

        $parts = explode(':', (string) $_COOKIE[self::COOKIE], 2);
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
            self::clearCookie();
            return;
        }

        try {
            $saved = User::findRememberToken($parts[0]);
            if (!$saved || !hash_equals($saved['token_hash'], hash('sha256', $parts[1]))) {
                self::clearCookie();
                return;
            }
            $user = User::findById((int) $saved['user_id']);
            if (!$user) {
                User::deleteRememberToken($parts[0]);
                self::clearCookie();
                return;
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']];
        } catch (Throwable $error) {
            error_log('MobileX remember-login restore failed: ' . $error->getMessage());
            self::clearCookie();
        }
    }

    public static function remember(int $userId): void
    {
        self::forget();
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + 60 * 60 * 24 * 30;
        User::saveRememberToken($userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires));
        setcookie(self::COOKIE, $selector . ':' . $validator, [
            'expires' => $expires,
            'path' => self::cookiePath(),
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $selector . ':' . $validator;
    }

    public static function forget(): void
    {
        $value = (string) ($_COOKIE[self::COOKIE] ?? '');
        $selector = explode(':', $value, 2)[0] ?? '';
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            try {
                User::deleteRememberToken($selector);
            } catch (Throwable $error) {
                error_log('MobileX remember-login revoke failed: ' . $error->getMessage());
            }
        }
        self::clearCookie();
    }

    private static function clearCookie(): void
    {
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => self::cookiePath(),
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function cookiePath(): string
    {
        $path = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        return $path === '/' || $path === '.' ? '/' : $path;
    }

    private static function isSecure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
