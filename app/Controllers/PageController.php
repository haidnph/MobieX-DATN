
<?php


require_once __DIR__ . '/../Models/Page.php';
require_once __DIR__ . '/../Models/Promotions.php';
require_once __DIR__ . '/../Models/Catalog.php';
require_once __DIR__ . '/../Support/Csrf.php';
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
        if($slug==='detail') $data['csrf']=Csrf::token();

        if ($slug === 'home') {
            try {
                $data['homeBanners'] =
                    Promotions::activeBanners();

                $data['liveFlashProducts'] =
                    Promotions::liveFlashProducts();
                $data['featuredProducts']=Catalog::products(['sort'=>'newest']);
                $data['topProducts']=Catalog::bestSellers();
                $data['accessoryProducts']=Catalog::accessories();

            } catch (Throwable $error) {
                error_log(
                    'MobileX homepage promotions: ' .
                    $error->getMessage()
                );

                $data['promotionLoadError'] = true;
                $data['homeBanners'] = [];
                $data['liveFlashProducts'] = [];
                $data['featuredProducts'] = [];
                $data['topProducts'] = [];
                $data['accessoryProducts'] = [];
            }
        }
        if ($slug === 'products') {
            $data['products']=Catalog::products($_GET);
            $data['brands']=Catalog::brands(); $data['categories']=Catalog::categories();
        } elseif ($slug === 'detail') {
            $data['product']=Catalog::detail((int)($_GET['product_id']??$_GET['id']??0));
            if(!$data['product']){http_response_code(404);$page=['view'=>'errors/404.php','title'=>'Không tìm thấy sản phẩm'];}
        } elseif ($slug === 'compare') {
            $data['products']=Catalog::compare((array)($_GET['id']??[]));
            $page=['view'=>'store/compare.php','title'=>'MobileX | So sánh sản phẩm'];
        }

        View::render($page['view'], $page['title'], $data);
    }
}
