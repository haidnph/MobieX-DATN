<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageAuditLog;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class HomepageService
{
    public const CACHE_KEY = 'homepage_storefront_data';

    /**
     * Canonical default sections.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultSections(): array
    {
        return [
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
                    'category_ids' => [],
                    'show_all' => true,
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
                    'product_ids' => [],
                    'filter_mode' => 'selected_or_latest',
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
                    'cta_label' => 'Mua sắm ngay',
                    'cta_url' => '/products',
                    'badge' => 'Ưu đãi thanh toán',
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
                    'brand_ids' => [],
                    'show_all' => true,
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
                        [
                            'icon' => 'verified',
                            'title' => '100% Chính hãng',
                            'description' => 'Cam kết hàng nguyên seal, bảo hành chính hãng toàn quốc',
                        ],
                        [
                            'icon' => 'local_shipping',
                            'title' => 'Miễn phí vận chuyển',
                            'description' => 'Giao hàng toàn quốc cho đơn hàng từ 500.000đ',
                        ],
                        [
                            'icon' => 'published_with_changes',
                            'title' => 'Đổi trả 30 ngày',
                            'description' => 'Lỗi 1 đổi 1 nhanh chóng nếu có lỗi từ nhà sản xuất',
                        ],
                        [
                            'icon' => 'support_agent',
                            'title' => 'Hỗ trợ 24/7',
                            'description' => 'Đội ngũ chuyên viên tư vấn kỹ thuật tận tâm chu đáo',
                        ],
                    ],
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
            ],
        ];
    }

    /**
     * Ensure default sections exist in database.
     */
    public function ensureDefaultSections(): void
    {
        if (HomepageSection::query()->count() > 0) {
            return;
        }

        foreach (self::defaultSections() as $data) {
            $data['published_settings'] = [
                'title' => $data['title'],
                'subtitle' => $data['subtitle'],
                'layout_type' => $data['layout_type'],
                'items_limit' => $data['items_limit'],
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
                'settings' => $data['settings'],
                'published_at' => now()->toIso8601String(),
            ];

            HomepageSection::query()->create($data);
        }
    }

    /**
     * Invalidate homepage public storefront cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Log audit record.
     */
    public function logAudit(
        string $action,
        string $entityType,
        ?int $entityId,
        ?string $description = null,
        ?array $changes = null,
        ?User $user = null
    ): void {
        try {
            HomepageAuditLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'changes' => $changes,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking for audit logging
        }
    }

    /**
     * Retrieve all admin sections.
     *
     * @return Collection<int, HomepageSection>
     */
    public function getAdminSections(): Collection
    {
        $this->ensureDefaultSections();

        return HomepageSection::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Update section settings in draft mode.
     */
    public function updateSection(HomepageSection $section, array $data, ?User $user = null): HomepageSection
    {
        $oldValues = $section->only([
            'title', 'subtitle', 'layout_type', 'items_limit', 'sort_order', 'is_active', 'settings',
        ]);

        $section->update($data);

        $newValues = $section->only([
            'title', 'subtitle', 'layout_type', 'items_limit', 'sort_order', 'is_active', 'settings',
        ]);

        $this->logAudit(
            'homepage.section_updated',
            'HomepageSection',
            $section->id,
            "Cập nhật cấu hình section: {$section->section_key}",
            ['old' => $oldValues, 'new' => $newValues],
            $user
        );

        return $section->fresh();
    }

    /**
     * Reorder sections.
     *
     * @param  array<int, array{id: int, sort_order: int, is_active?: bool}>  $orders
     */
    public function reorderSections(array $orders, ?User $user = null): void
    {
        DB::transaction(function () use ($orders) {
            foreach ($orders as $item) {
                $section = HomepageSection::find($item['id']);
                if ($section) {
                    $update = ['sort_order' => (int) $item['sort_order']];
                    if (isset($item['is_active'])) {
                        $update['is_active'] = (bool) $item['is_active'];
                    }
                    $section->update($update);
                }
            }
        });

        $this->logAudit(
            'homepage.reordered',
            'HomepageSection',
            null,
            'Sắp xếp lại thứ tự các section trang chủ',
            ['orders' => $orders],
            $user
        );
    }

    /**
     * Publish all current draft sections to live storefront.
     */
    public function publishAll(?User $user = null): void
    {
        $sections = HomepageSection::all();

        DB::transaction(function () use ($sections, $user) {
            $now = now()->toIso8601String();

            foreach ($sections as $section) {
                $section->published_settings = [
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'layout_type' => $section->layout_type,
                    'items_limit' => $section->items_limit,
                    'sort_order' => $section->sort_order,
                    'is_active' => $section->is_active,
                    'settings' => $section->settings,
                    'published_at' => $now,
                    'published_by' => $user?->name ?? 'Admin',
                ];
                $section->save();
            }
        });

        $this->clearCache();

        $this->logAudit(
            'homepage.published',
            'Homepage',
            null,
            'Xuất bản toàn bộ cấu hình trang chủ ra storefront',
            null,
            $user
        );
    }

    /**
     * Discard draft changes and restore to published configuration.
     */
    public function revertToPublished(?User $user = null): void
    {
        $sections = HomepageSection::all();

        DB::transaction(function () use ($sections) {
            foreach ($sections as $section) {
                if (! empty($section->published_settings)) {
                    $pub = $section->published_settings;
                    $section->update([
                        'title' => $pub['title'] ?? $section->title,
                        'subtitle' => $pub['subtitle'] ?? $section->subtitle,
                        'layout_type' => $pub['layout_type'] ?? $section->layout_type,
                        'items_limit' => $pub['items_limit'] ?? $section->items_limit,
                        'sort_order' => $pub['sort_order'] ?? $section->sort_order,
                        'is_active' => $pub['is_active'] ?? $section->is_active,
                        'settings' => $pub['settings'] ?? $section->settings,
                    ]);
                }
            }
        });

        $this->logAudit(
            'homepage.reverted',
            'Homepage',
            null,
            'Khôi phục cấu hình trang chủ về bản xuất bản gần nhất',
            null,
            $user
        );
    }

    /**
     * Reset sections to factory defaults.
     */
    public function resetDefaults(?User $user = null): void
    {
        DB::transaction(function () {
            HomepageSection::truncate();
            $this->ensureDefaultSections();
        });

        $this->clearCache();

        $this->logAudit(
            'homepage.reset_defaults',
            'Homepage',
            null,
            'Khôi phục toàn bộ cấu hình trang chủ về mặc định MobieX',
            null,
            $user
        );
    }

    /**
     * Get storefront resolved sections.
     *
     * @return array<string, mixed>
     */
    public function getStorefrontData(bool $forceFresh = false): array
    {
        $this->ensureDefaultSections();

        if ($forceFresh) {
            $this->clearCache();
        }

        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $sections = HomepageSection::all();

            $publishedSections = $sections->filter(function ($section) {
                $pub = $section->published_settings;
                if (! empty($pub)) {
                    return ! empty($pub['is_active']);
                }

                return (bool) $section->is_active;
            })->sortBy(function ($section) {
                $pub = $section->published_settings;
                if (! empty($pub) && isset($pub['sort_order'])) {
                    return (int) $pub['sort_order'];
                }

                return (int) $section->sort_order;
            })->values();

            $resolvedSections = [];

            foreach ($publishedSections as $section) {
                $resolved = $this->resolveSectionData($section, true);
                if ($resolved !== null) {
                    $resolvedSections[] = $resolved;
                }
            }

            return [
                'updated_at' => now()->toIso8601String(),
                'sections' => $resolvedSections,
            ];
        });
    }

    /**
     * Get preview resolved sections based on current draft state.
     *
     * @return array<string, mixed>
     */
    public function getPreviewData(): array
    {
        $this->ensureDefaultSections();

        $sections = HomepageSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $resolvedSections = [];

        foreach ($sections as $section) {
            $resolved = $this->resolveSectionData($section, false);
            if ($resolved !== null) {
                $resolvedSections[] = $resolved;
            }
        }

        return [
            'mode' => 'draft_preview',
            'updated_at' => now()->toIso8601String(),
            'sections' => $resolvedSections,
        ];
    }

    /**
     * Resolve a single section with hydrated data.
     *
     * @return array<string, mixed>|null
     */
    private function resolveSectionData(HomepageSection $section, bool $isPublishedSource): ?array
    {
        $source = $isPublishedSource && ! empty($section->published_settings)
            ? $section->published_settings
            : [
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'layout_type' => $section->layout_type,
                'items_limit' => $section->items_limit,
                'sort_order' => $section->sort_order,
                'is_active' => $section->is_active,
                'settings' => $section->settings ?? [],
            ];

        $settings = $source['settings'] ?? [];
        $limit = max((int) ($source['items_limit'] ?? 8), 1);
        $type = $section->section_type;

        $items = [];

        switch ($type) {
            case 'banner_slider':
                $position = $settings['position'] ?? 'home';
                $items = Banner::live()
                    ->where('position', $position)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'desc')
                    ->limit($limit)
                    ->get();
                break;

            case 'categories':
                $catIds = $settings['category_ids'] ?? [];
                $query = Category::query()->where('status', true);

                if (! empty($catIds) && is_array($catIds)) {
                    $query->whereIn('id', $catIds);
                } else {
                    $query->where(function ($q) {
                        $q->where('is_featured', true)->orWhereNull('parent_id');
                    });
                }

                $categories = $query->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->limit($limit)
                    ->get();

                if (! empty($catIds) && is_array($catIds)) {
                    $idOrder = array_flip(array_values($catIds));
                    $items = $categories->sortBy(function ($c) use ($idOrder) {
                        return $idOrder[$c->id] ?? 999;
                    })->values();
                } else {
                    $items = $categories;
                }
                break;

            case 'brands':
                $brandIds = $settings['brand_ids'] ?? [];
                $query = Brand::query()->where('status', true);

                if (! empty($brandIds) && is_array($brandIds)) {
                    $query->whereIn('id', $brandIds);
                }

                $brands = $query->orderBy('name', 'asc')->limit($limit)->get();

                if (! empty($brandIds) && is_array($brandIds)) {
                    $idOrder = array_flip(array_values($brandIds));
                    $items = $brands->sortBy(function ($b) use ($idOrder) {
                        return $idOrder[$b->id] ?? 999;
                    })->values();
                } else {
                    $items = $brands;
                }
                break;

            case 'products':
                $productIds = $settings['product_ids'] ?? [];
                $sortBy = $settings['sort_by'] ?? 'featured';

                $query = Product::with([
                    'images',
                    'variants.inventory',
                    'brand',
                    'category',
                ])->where('status', 'active');

                if (! empty($productIds) && is_array($productIds)) {
                    $query->whereIn('id', $productIds);
                    $products = $query->get();
                    $idOrder = array_flip(array_values($productIds));
                    $items = $products->sortBy(function ($p) use ($idOrder) {
                        return $idOrder[$p->id] ?? 999;
                    })->values()->take($limit);
                } else {
                    if ($sortBy === 'newest') {
                        $query->orderBy('created_at', 'desc');
                    } else {
                        $query->orderBy('id', 'desc');
                    }
                    $items = $query->limit($limit)->get();
                }
                break;

            case 'flash_sale':
                // Query active products with potential discounts
                $items = Product::with([
                    'images',
                    'variants.inventory',
                    'brand',
                    'category',
                ])
                    ->where('status', 'active')
                    ->limit($limit)
                    ->get();
                break;

            case 'banner_promo':
                $items = [
                    'cta_label' => $settings['cta_label'] ?? 'Khám phá ngay',
                    'cta_url' => $settings['cta_url'] ?? '/products',
                    'badge' => $settings['badge'] ?? null,
                    'image_url' => $settings['image_url'] ?? null,
                ];
                break;

            case 'trust_services':
                $items = $settings['items'] ?? [];
                break;

            case 'recently_viewed':
                // Client hydrates via LocalStorage
                $items = [];
                break;
        }

        return [
            'id' => $section->id,
            'section_key' => $section->section_key,
            'title' => $source['title'] ?? $section->title,
            'subtitle' => $source['subtitle'] ?? $section->subtitle,
            'section_type' => $section->section_type,
            'layout_type' => $source['layout_type'] ?? $section->layout_type,
            'items_limit' => $limit,
            'sort_order' => $source['sort_order'] ?? $section->sort_order,
            'is_active' => (bool) ($source['is_active'] ?? $section->is_active),
            'settings' => $settings,
            'items' => $items,
        ];
    }
}
