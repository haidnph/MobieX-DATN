<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Omnisearch / Command Palette Controller
 *
 * Fast aggregated search across Products, Orders, Users, Categories, Coupons,
 * and quick administrative shortcuts.
 */
class AdminOmnisearchController extends Controller
{
    /**
     * Default quick shortcuts when query is empty.
     */
    private const QUICK_ACTIONS = [
        [
            'id'       => 'act_product_new',
            'title'    => 'Thêm sản phẩm mới',
            'subtitle' => 'Tạo sản phẩm, thuộc tính và biến thể mới',
            'icon'     => 'add_circle',
            'url'      => '/admin/products?action=create',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['them', 'san pham', 'tao', 'new', 'product', 'create'],
        ],
        [
            'id'       => 'act_orders_pending',
            'title'    => 'Đơn hàng chờ xác nhận',
            'subtitle' => 'Xem danh sách các đơn hàng mới cần duyệt',
            'icon'     => 'pending_actions',
            'url'      => '/admin/orders?status=pending',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['don hang', 'cho xac nhan', 'pending', 'orders'],
        ],
        [
            'id'       => 'act_trash',
            'title'    => 'Thùng rác hệ thống (Khôi phục)',
            'subtitle' => 'Xem và khôi phục các mục đã xóa mềm',
            'icon'     => 'auto_delete',
            'url'      => '/admin/trash',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['thung rac', 'khoi phuc', 'xoa', 'trash', 'recycle', 'restore'],
        ],
        [
            'id'       => 'act_inventory',
            'title'    => 'Quản lý kho hàng & Tồn kho',
            'subtitle' => 'Cập nhật tồn kho, cảnh báo sắp hết hàng',
            'icon'     => 'warehouse',
            'url'      => '/admin/inventories',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['kho', 'ton kho', 'nhap kho', 'inventory', 'stock'],
        ],
        [
            'id'       => 'act_coupons',
            'title'    => 'Mã giảm giá & Khuyến mãi',
            'subtitle' => 'Tạo mã voucher, cấu hình chiết khấu',
            'icon'     => 'local_offer',
            'url'      => '/admin/coupons',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['ma giam gia', 'voucher', 'coupon', 'khuyen mai'],
        ],
        [
            'id'       => 'act_dashboard',
            'title'    => 'Báo cáo doanh thu & Biểu đồ',
            'subtitle' => 'Tổng quan chỉ số kinh doanh toàn sàn',
            'icon'     => 'monitoring',
            'url'      => '/admin',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['bao cao', 'doanh thu', 'bieu do', 'thong ke', 'dashboard'],
        ],
        [
            'id'       => 'act_banners',
            'title'    => 'Quản lý Banner quảng cáo',
            'subtitle' => 'Cấu hình banner trang chủ và các vị trí',
            'icon'     => 'image',
            'url'      => '/admin/banners',
            'group'    => 'Thao tác nhanh',
            'keywords' => ['banner', 'hinh anh', 'quang cao'],
        ],
    ];

    /**
     * Handle global omnisearch query.
     * GET /api/admin/omnisearch?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'query'         => '',
                'total_results' => count(self::QUICK_ACTIONS),
                'results'       => [
                    'actions'    => self::QUICK_ACTIONS,
                    'products'   => [],
                    'orders'     => [],
                    'users'      => [],
                    'categories' => [],
                    'coupons'    => [],
                ],
            ]);
        }

        $normalizedQ = mb_strtolower($q, 'UTF-8');

        // 1. Matching quick actions
        $matchingActions = array_values(array_filter(self::QUICK_ACTIONS, function (array $action) use ($normalizedQ): bool {
            if (str_contains(mb_strtolower($action['title'], 'UTF-8'), $normalizedQ)) {
                return true;
            }
            if (str_contains(mb_strtolower($action['subtitle'], 'UTF-8'), $normalizedQ)) {
                return true;
            }
            foreach ($action['keywords'] as $keyword) {
                if (str_contains($keyword, $normalizedQ) || str_contains($normalizedQ, $keyword)) {
                    return true;
                }
            }
            return false;
        }));

        // 2. Search Products (Limit 5)
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhereHas('variants', function ($vQuery) use ($q) {
                        $vQuery->where('sku', 'like', "%{$q}%");
                    });
            })
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'category_id', 'brand_id', 'base_price', 'status', 'product_type'])
            ->map(function ($p) {
                return [
                    'id'           => $p->id,
                    'title'        => $p->name,
                    'subtitle'     => ($p->brand?->name ?? 'No brand') . ' • ' . ($p->category?->name ?? 'No category'),
                    'badge'        => $p->status,
                    'badge_label'  => match ($p->status) {
                        'active'       => 'Đang bán',
                        'draft'        => 'Bản nháp',
                        'inactive'     => 'Ngừng kinh doanh',
                        'out_of_stock' => 'Hết hàng',
                        default        => $p->status,
                    },
                    'price'        => $p->base_price,
                    'url'          => "/admin/products?q=" . urlencode($p->name),
                    'type'         => 'product',
                    'icon'         => 'inventory_2',
                ];
            });

        // 3. Search Orders (Limit 5)
        $orders = Order::query()
            ->where('order_code', 'like', "%{$q}%")
            ->orWhere('shipping_phone', 'like', "%{$q}%")
            ->orWhere('shipping_name', 'like', "%{$q}%")
            ->orWhereHas('user', function ($uQuery) use ($q) {
                $uQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->latest('id')
            ->limit(5)
            ->get(['id', 'order_code', 'shipping_name', 'shipping_phone', 'total_amount', 'status', 'payment_status', 'created_at'])
            ->map(function ($o) {
                return [
                    'id'          => $o->id,
                    'title'       => $o->order_code,
                    'subtitle'    => ($o->shipping_name ?: 'Khách lẻ') . ' • ' . ($o->shipping_phone ?: ''),
                    'badge'       => $o->status,
                    'badge_label' => match ($o->status) {
                        'pending'    => 'Chờ xác nhận',
                        'confirmed'  => 'Đã xác nhận',
                        'processing' => 'Đang chuẩn bị',
                        'shipping'   => 'Đang giao',
                        'delivered'  => 'Hoàn thành',
                        'cancelled'  => 'Đã hủy',
                        default      => $o->status,
                    },
                    'price'       => $o->total_amount,
                    'url'         => "/admin/orders/{$o->id}",
                    'type'        => 'order',
                    'icon'        => 'receipt_long',
                    'created_at'  => $o->created_at?->format('d/m/Y H:i'),
                ];
            });

        // 4. Search Users (Limit 5)
        $users = User::query()
            ->with('roles:id,name,slug')
            ->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'email', 'phone', 'is_active'])
            ->map(function ($u) {
                $roleNames = $u->roles->pluck('name')->join(', ');
                return [
                    'id'          => $u->id,
                    'title'       => $u->name,
                    'subtitle'    => $u->email . ($u->phone ? " • {$u->phone}" : ''),
                    'badge'       => $u->is_active ? 'active' : 'inactive',
                    'badge_label' => $roleNames ?: ($u->is_active ? 'Hoạt động' : 'Bị khóa'),
                    'url'         => "/admin/users?q=" . urlencode($u->email),
                    'type'        => 'user',
                    'icon'        => 'person',
                ];
            });

        // 5. Search Categories (Limit 3)
        $categories = Category::query()
            ->where('name', 'like', "%{$q}%")
            ->orWhere('slug', 'like', "%{$q}%")
            ->limit(3)
            ->get(['id', 'name', 'slug', 'status'])
            ->map(function ($c) {
                return [
                    'id'          => $c->id,
                    'title'       => $c->name,
                    'subtitle'    => "Slug: {$c->slug}",
                    'badge'       => $c->status ? 'active' : 'inactive',
                    'badge_label' => $c->status ? 'Đang hiện' : 'Đang ẩn',
                    'url'         => "/admin/categories?q=" . urlencode($c->name),
                    'type'        => 'category',
                    'icon'        => 'category',
                ];
            });

        // 6. Search Coupons (Limit 3)
        $coupons = Coupon::query()
            ->where('code', 'like', "%{$q}%")
            ->limit(3)
            ->get(['id', 'code', 'discount_type', 'discount_value', 'is_active'])
            ->map(function ($cp) {
                $discountStr = $cp->discount_type === 'percent'
                    ? "Giảm {$cp->discount_value}%"
                    : "Giảm " . number_format($cp->discount_value, 0, ',', '.') . " ₫";
                return [
                    'id'          => $cp->id,
                    'title'       => $cp->code,
                    'subtitle'    => $discountStr,
                    'badge'       => $cp->is_active ? 'active' : 'inactive',
                    'badge_label' => $cp->is_active ? 'Khả dụng' : 'Hết hạn',
                    'url'         => "/admin/coupons?q=" . urlencode($cp->code),
                    'type'        => 'coupon',
                    'icon'        => 'local_offer',
                ];
            });

        $totalResults = count($matchingActions) + $products->count() + $orders->count()
            + $users->count() + $categories->count() + $coupons->count();

        return response()->json([
            'query'         => $q,
            'total_results' => $totalResults,
            'results'       => [
                'actions'    => $matchingActions,
                'products'   => $products,
                'orders'     => $orders,
                'users'      => $users,
                'categories' => $categories,
                'coupons'    => $coupons,
            ],
        ]);
    }
}
