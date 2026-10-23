<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Page.php';
require_once __DIR__ . '/../Core/View.php';

final class PageController
{
    public function show(string $slug): void
    {
        $page = Page::find($slug);
        if ($page === null) {
            http_response_code(404);
            $page = ['view' => 'errors/404.php', 'title' => 'Không tìm thấy trang'];
        }

        View::render($page['view'], $page['title']);
    }
}
