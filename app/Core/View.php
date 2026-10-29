<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/Translator.php';
require_once __DIR__ . '/../Support/Csrf.php';

final class View
{
    public static function render(string $view, string $title, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . $view;
        if (!is_file($viewPath)) {
            throw new RuntimeException('Không tìm thấy giao diện được yêu cầu.');
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $viewPath;
        $html = Translator::render((string) ob_get_clean());
        $basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        if ($basePath === '/' || $basePath === '.') {
            $basePath = '';
        }
        $html = str_replace('/public/assets/', $basePath . '/public/assets/', $html);
        $html = str_replace('absolute right-0 top-full mt-space-sm w-56 bg-surface-container-high rounded-xl p-space-sm', 'absolute right-0 top-full mt-0 w-56 bg-surface-container-high rounded-xl p-space-sm', $html);
        if (!empty($_SESSION['user']['id'])) {
            $accountName = htmlspecialchars((string) ($_SESSION['user']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $accountEmail = htmlspecialchars((string) ($_SESSION['user']['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = str_replace('Mã tài khoản: MX-8842', 'Tài khoản: ' . $accountName, $html);
            $html = str_replace('user@mobilex.tech', $accountEmail, $html);
            $html = str_replace('minh.nguyen@techmail.com', $accountEmail, $html);
        } else {
            $html = str_replace('Mã tài khoản: MX-8842', 'Tài khoản khách', $html);
            $html = str_replace('user@mobilex.tech', 'Chưa đăng nhập', $html);
        }
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace('~<title\b[^>]*>.*?</title>~is', '<title>' . $safeTitle . '</title>', $html, 1) ?? $html;
        if (str_contains($html, '</header>') && !str_contains($view, 'auth/')) {
            if (!empty($_SESSION['user']['id'])) {
                $token = htmlspecialchars(Csrf::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $accountAction = '<form method="post" action="?page=logout" class="m-0"><input type="hidden" name="_csrf" value="' . $token . '"><button class="flex w-full items-center gap-space-sm px-space-sm py-space-xs rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors font-body-sm text-body-sm" type="submit"><span class="material-symbols-outlined text-base">logout</span>Đăng xuất</button></form>';
            } else {
                $accountAction = '<a class="flex items-center gap-space-sm px-space-sm py-space-xs rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors font-body-sm text-body-sm" href="?page=login"><span class="material-symbols-outlined text-base">login</span>Đăng nhập</a><a class="flex items-center gap-space-sm px-space-sm py-space-xs rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors font-body-sm text-body-sm" href="?page=register"><span class="material-symbols-outlined text-base">person_add</span>Tạo tài khoản</a>';
            }
            $html = preg_replace('~<a\b(?=[^>]*data-path="login")[^>]*>.*?<span[^>]*>logout</span>.*?</a>~is', $accountAction, $html, 1) ?? $html;
            $menuScript = '<script src="' . htmlspecialchars($basePath . '/public/assets/js/account-menu.js?v=3', ENT_QUOTES, 'UTF-8') . '"></script>';
            $html = str_replace('</body>', $menuScript . '</body>', $html);
        }
        echo $html;
    }
}
