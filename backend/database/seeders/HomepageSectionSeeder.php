<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    /**
     * Seed canonical homepage CMS sections.
     */
    public function run(): void
    {
        $sections = [
            [
                'section_key' => 'hero_banner',
                'title' => 'Hero Banner',
                'subtitle' => 'Banner trượt quảng cáo chính trên đầu trang chủ',
                'section_type' => 'banner_slider',
                'layout_type' => 'carousel',
                'items_limit' => 5,
                'sort_order' => 1,
                'is_active' => true,
                'settings' => [
                    'position' => 'home',
                    'autoplay_interval' => 4000,
                ],
                'published_settings' => [
                    'title' => 'Hero Banner',
                    'settings' => ['position' => 'home', 'autoplay_interval' => 4000],
                    'subtitle' => 'Banner trượt quảng cáo chính trên đầu trang chủ',
                    'is_active' => true,
                    'sort_order' => 1,
                    'items_limit' => 5,
                    'layout_type' => 'carousel',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'categories',
                'title' => 'Danh mục sản phẩm',
                'subtitle' => 'Khám phá các dòng sản phẩm công nghệ hàng đầu tại MobieX',
                'section_type' => 'categories',
                'layout_type' => 'grid',
                'items_limit' => 6,
                'sort_order' => 2,
                'is_active' => true,
                'settings' => [
                    'show_all' => true,
                    'category_ids' => [],
                ],
                'published_settings' => [
                    'title' => 'Danh mục sản phẩm',
                    'settings' => ['show_all' => true, 'category_ids' => []],
                    'subtitle' => 'Khám phá các dòng sản phẩm công nghệ hàng đầu tại MobieX',
                    'is_active' => true,
                    'sort_order' => 2,
                    'items_limit' => 6,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'flash_sale',
                'title' => 'Flash Sale Giờ Vàng',
                'subtitle' => 'Ưu đãi giảm giá sốc có hạn trong ngày',
                'section_type' => 'flash_sale',
                'layout_type' => 'grid',
                'items_limit' => 4,
                'sort_order' => 3,
                'is_active' => true,
                'settings' => [
                    'badge_text' => 'HOT',
                    'discount_threshold' => 5,
                ],
                'published_settings' => [
                    'title' => 'Flash Sale Giờ Vàng',
                    'settings' => ['badge_text' => 'HOT', 'discount_threshold' => 5],
                    'subtitle' => 'Ưu đãi giảm giá sốc có hạn trong ngày',
                    'is_active' => true,
                    'sort_order' => 3,
                    'items_limit' => 4,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'featured_products',
                'title' => 'Sản phẩm nổi bật',
                'subtitle' => 'Các siêu phẩm công nghệ được yêu thích nhất',
                'section_type' => 'products',
                'layout_type' => 'grid',
                'items_limit' => 8,
                'sort_order' => 4,
                'is_active' => true,
                'settings' => [
                    'filter_mode' => 'selected_or_latest',
                    'product_ids' => [],
                ],
                'published_settings' => [
                    'title' => 'Sản phẩm nổi bật',
                    'settings' => ['filter_mode' => 'selected_or_latest', 'product_ids' => []],
                    'subtitle' => 'Các siêu phẩm công nghệ được yêu thích nhất',
                    'is_active' => true,
                    'sort_order' => 4,
                    'items_limit' => 8,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'promotional_banner',
                'title' => 'Thanh toán nhanh với VNPay, MoMo & ZaloPay',
                'subtitle' => 'Hỗ trợ đa dạng phương thức thanh toán. Giao hàng toàn quốc siêu tốc, trả góp 0% lãi suất.',
                'section_type' => 'banner_promo',
                'layout_type' => 'split',
                'items_limit' => 1,
                'sort_order' => 5,
                'is_active' => true,
                'settings' => [
                    'badge' => 'Ưu đãi thanh toán',
                    'cta_url' => '/products',
                    'cta_label' => 'Mua sắm ngay',
                ],
                'published_settings' => [
                    'title' => 'Thanh toán nhanh với VNPay, MoMo & ZaloPay',
                    'settings' => ['badge' => 'Ưu đãi thanh toán', 'cta_url' => '/products', 'cta_label' => 'Mua sắm ngay'],
                    'subtitle' => 'Hỗ trợ đa dạng phương thức thanh toán. Giao hàng toàn quốc siêu tốc, trả góp 0% lãi suất.',
                    'is_active' => true,
                    'sort_order' => 5,
                    'items_limit' => 1,
                    'layout_type' => 'split',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'featured_brands',
                'title' => 'Thương hiệu hàng đầu',
                'subtitle' => 'Đối tác ủy quyền và phân phối chính hãng',
                'section_type' => 'brands',
                'layout_type' => 'grid',
                'items_limit' => 8,
                'sort_order' => 6,
                'is_active' => true,
                'settings' => [
                    'show_all' => true,
                    'brand_ids' => [],
                ],
                'published_settings' => [
                    'title' => 'Thương hiệu hàng đầu',
                    'settings' => ['show_all' => true, 'brand_ids' => []],
                    'subtitle' => 'Đối tác ủy quyền và phân phối chính hãng',
                    'is_active' => true,
                    'sort_order' => 6,
                    'items_limit' => 8,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'new_arrivals',
                'title' => 'Hàng mới về',
                'subtitle' => 'Đón đầu xu hướng với các sản phẩm công nghệ vừa ra mắt',
                'section_type' => 'products',
                'layout_type' => 'grid',
                'items_limit' => 4,
                'sort_order' => 7,
                'is_active' => true,
                'settings' => [
                    'sort_by' => 'newest',
                    'product_ids' => [],
                ],
                'published_settings' => [
                    'title' => 'Hàng mới về',
                    'settings' => ['sort_by' => 'newest', 'product_ids' => []],
                    'subtitle' => 'Đón đầu xu hướng với các sản phẩm công nghệ vừa ra mắt',
                    'is_active' => true,
                    'sort_order' => 7,
                    'items_limit' => 4,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'trust_services',
                'title' => 'Cam kết từ MobieX',
                'subtitle' => 'An tâm tuyệt đối với dịch vụ hàng đầu',
                'section_type' => 'trust_services',
                'layout_type' => 'standard',
                'items_limit' => 4,
                'sort_order' => 8,
                'is_active' => true,
                'settings' => [
                    'items' => [
                        ['icon' => 'verified', 'title' => '100% Chính hãng', 'description' => 'Cam kết hàng nguyên seal, bảo hành chính hãng toàn quốc'],
                        ['icon' => 'local_shipping', 'title' => 'Miễn phí vận chuyển', 'description' => 'Giao hàng toàn quốc cho đơn hàng từ 500.000đ'],
                        ['icon' => 'published_with_changes', 'title' => 'Đổi trả 30 ngày', 'description' => 'Lỗi 1 đổi 1 nhanh chóng nếu có lỗi từ nhà sản xuất'],
                        ['icon' => 'support_agent', 'title' => 'Hỗ trợ 24/7', 'description' => 'Đội ngũ chuyên viên tư vấn kỹ thuật tận tâm chu đáo'],
                    ],
                ],
                'published_settings' => [
                    'title' => 'Cam kết từ MobieX',
                    'settings' => [
                        'items' => [
                            ['icon' => 'verified', 'title' => '100% Chính hãng', 'description' => 'Cam kết hàng nguyên seal, bảo hành chính hãng toàn quốc'],
                            ['icon' => 'local_shipping', 'title' => 'Miễn phí vận chuyển', 'description' => 'Giao hàng toàn quốc cho đơn hàng từ 500.000đ'],
                            ['icon' => 'published_with_changes', 'title' => 'Đổi trả 30 ngày', 'description' => 'Lỗi 1 đổi 1 nhanh chóng nếu có lỗi từ nhà sản xuất'],
                            ['icon' => 'support_agent', 'title' => 'Hỗ trợ 24/7', 'description' => 'Đội ngũ chuyên viên tư vấn kỹ thuật tận tâm chu đáo'],
                        ],
                    ],
                    'subtitle' => 'An tâm tuyệt đối với dịch vụ hàng đầu',
                    'is_active' => true,
                    'sort_order' => 8,
                    'items_limit' => 4,
                    'layout_type' => 'standard',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
            [
                'section_key' => 'recently_viewed',
                'title' => 'Sản phẩm bạn vừa xem',
                'subtitle' => 'Dễ dàng xem lại các thiết bị bạn đã quan tâm',
                'section_type' => 'recently_viewed',
                'layout_type' => 'grid',
                'items_limit' => 6,
                'sort_order' => 9,
                'is_active' => true,
                'settings' => [
                    'items_limit' => 6,
                ],
                'published_settings' => [
                    'title' => 'Sản phẩm bạn vừa xem',
                    'settings' => ['items_limit' => 6],
                    'subtitle' => 'Dễ dàng xem lại các thiết bị bạn đã quan tâm',
                    'is_active' => true,
                    'sort_order' => 9,
                    'items_limit' => 6,
                    'layout_type' => 'grid',
                    'published_at' => '2026-11-19T04:58:05+00:00',
                ],
            ],
        ];

        foreach ($sections as $section) {
            HomepageSection::updateOrCreate(
                ['section_key' => $section['section_key']],
                $section
            );
        }
    }
}
