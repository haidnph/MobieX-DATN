
<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Page.php';
require_once __DIR__ . '/../Models/Promotions.php';
require_once __DIR__ . '/../Core/View.php';

final class PageController
{
    public function show(string $slug): void
    {
        $page = Page::find($slug);

        if ($page === null) {
            http_response_code(404);
            $page = [
                'view' => 'errors/404.php',
                'title' => 'Không tìm thấy trang'
            ];
        }

        $data = [];

        if ($slug === 'home') {
            try {
                $data['homeBanners'] =
                    Promotions::activeBanners();

                $data['liveFlashProducts'] =
                    Promotions::liveFlashProducts();

            } catch (Throwable $error) {
                error_log(
                    'MobileX homepage promotions: ' .
                    $error->getMessage()
                );

                $data['promotionLoadError'] = true;
                $data['homeBanners'] = [];
                $data['liveFlashProducts'] = [];
            }
        }

        View::render($page['view'], $page['title'], $data);
    }
}
