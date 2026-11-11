<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use LogicException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Canonical system roles.
     *
     * @var array<string, array{name: string, description: string, is_system: bool}>
     */
    private const ROLES = [
        'admin' => [
            'name' => 'Quản trị viên',
            'description' => 'Toàn quyền quản trị hệ thống',
            'is_system' => true,
        ],
        'accountant' => [
            'name' => 'Kế toán',
            'description' => 'Theo dõi thanh toán và báo cáo tài chính',
            'is_system' => true,
        ],
        'warehouse' => [
            'name' => 'Kho',
            'description' => 'Quản lý tồn kho và bàn giao đơn hàng',
            'is_system' => true,
        ],
        'sales' => [
            'name' => 'Bán hàng',
            'description' => 'Quản lý sản phẩm, khách hàng và đơn hàng',
            'is_system' => true,
        ],
        'customer' => [
            'name' => 'Khách hàng',
            'description' => 'Mua hàng và quản lý tài khoản cá nhân',
            'is_system' => true,
        ],
    ];

    /**
     * Canonical permissions.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const PERMISSIONS = [
        'dashboard.view' => ['name' => 'Xem dashboard', 'description' => 'Xem dashboard quản trị'],
        'users.view' => ['name' => 'Xem người dùng', 'description' => 'Xem danh sách người dùng'],
        'users.manage' => ['name' => 'Quản lý người dùng', 'description' => 'Tạo, cập nhật và khóa tài khoản'],
        'roles.manage' => ['name' => 'Quản lý vai trò', 'description' => 'Quản lý roles và permissions'],
        'categories.view' => ['name' => 'Xem danh mục', 'description' => 'Xem danh mục sản phẩm'],
        'categories.manage' => ['name' => 'Quản lý danh mục', 'description' => 'Tạo, cập nhật và ẩn danh mục'],
        'brands.view' => ['name' => 'Xem thương hiệu', 'description' => 'Xem thương hiệu'],
        'brands.manage' => ['name' => 'Quản lý thương hiệu', 'description' => 'Tạo, cập nhật và ẩn thương hiệu'],
        'attributes.manage' => ['name' => 'Quản lý thuộc tính', 'description' => 'Quản lý thuộc tính và giá trị'],
        'products.view' => ['name' => 'Xem sản phẩm', 'description' => 'Xem sản phẩm và biến thể'],
        'products.manage' => ['name' => 'Quản lý sản phẩm', 'description' => 'Tạo và cập nhật sản phẩm, biến thể'],
        'products.publish' => ['name' => 'Xuất bản sản phẩm', 'description' => 'Xuất bản hoặc ẩn sản phẩm'],
        'inventory.view' => ['name' => 'Xem tồn kho', 'description' => 'Xem số dư tồn kho'],
        'inventory.import' => ['name' => 'Nhập kho', 'description' => 'Ghi nhận nhập kho'],
        'inventory.adjust' => ['name' => 'Điều chỉnh kho', 'description' => 'Điều chỉnh tồn kho có lý do'],
        'orders.view' => ['name' => 'Xem đơn hàng', 'description' => 'Xem đơn hàng'],
        'orders.manage' => ['name' => 'Quản lý đơn hàng', 'description' => 'Xử lý và cập nhật đơn hàng'],
        'orders.update-status' => ['name' => 'Cập nhật trạng thái đơn', 'description' => 'Chuyển trạng thái fulfillment'],
        'payments.view' => ['name' => 'Xem thanh toán', 'description' => 'Xem các lần thanh toán'],
        'coupons.view' => ['name' => 'Xem coupon', 'description' => 'Xem mã giảm giá'],
        'coupons.manage' => ['name' => 'Quản lý coupon', 'description' => 'Tạo và cập nhật mã giảm giá'],
        'banners.manage' => ['name' => 'Quản lý banner', 'description' => 'Quản lý banner'],
        'shipping_methods.view' => ['name' => 'Xem phương thức vận chuyển', 'description' => 'Xem cấu hình vận chuyển'],
        'shipping_methods.manage' => ['name' => 'Quản lý phương thức vận chuyển', 'description' => 'Tạo và cập nhật phương thức vận chuyển'],
        'flash_sales.view' => ['name' => 'Xem Flash Sale', 'description' => 'Xem chiến dịch Flash Sale'],
        'flash_sales.manage' => ['name' => 'Quản lý Flash Sale', 'description' => 'Quản lý chiến dịch Flash Sale'],
        'banners.view' => ['name' => 'Xem banner', 'description' => 'Xem banner'],
        'reviews.view' => ['name' => 'Xem đánh giá', 'description' => 'Xem đánh giá sản phẩm'],
        'reviews.moderate' => ['name' => 'Kiểm duyệt đánh giá', 'description' => 'Duyệt hoặc từ chối đánh giá'],
        'reports.view' => ['name' => 'Xem báo cáo', 'description' => 'Xem báo cáo quản trị'],
        'profile.update-own' => ['name' => 'Cập nhật hồ sơ cá nhân', 'description' => 'Khách hàng cập nhật hồ sơ của mình'],
        'addresses.manage-own' => ['name' => 'Quản lý địa chỉ cá nhân', 'description' => 'Khách hàng quản lý sổ địa chỉ'],
        'orders.view-own' => ['name' => 'Xem đơn hàng cá nhân', 'description' => 'Khách hàng xem đơn hàng của mình'],
        'orders.cancel-own' => ['name' => 'Hủy đơn cá nhân', 'description' => 'Khách hàng tự hủy đơn hợp lệ'],
        'reviews.create-own' => ['name' => 'Tạo đánh giá cá nhân', 'description' => 'Khách hàng tạo đánh giá đơn hàng đã giao'],
    ];

    /**
     * Authoritative permission assignment for each system role.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSION_MAP = [
        'admin' => [
            'dashboard.view', 'users.view', 'users.manage', 'roles.manage',
            'categories.view', 'categories.manage', 'brands.view', 'brands.manage',
            'attributes.manage', 'products.view', 'products.manage', 'products.publish',
            'inventory.view', 'inventory.import', 'inventory.adjust',
            'orders.view', 'orders.manage', 'orders.update-status',
            'payments.view', 'coupons.view', 'coupons.manage',
            'banners.manage', 'shipping_methods.view', 'shipping_methods.manage',
            'flash_sales.view', 'flash_sales.manage', 'banners.view',
            'reviews.view', 'reviews.moderate', 'reports.view',
            'profile.update-own', 'addresses.manage-own', 'orders.view-own',
            'orders.cancel-own', 'reviews.create-own',
        ],
        'accountant' => [
            'dashboard.view', 'orders.view', 'payments.view', 'reports.view',
            'coupons.view', 'shipping_methods.view',
        ],
        'warehouse' => [
            'dashboard.view', 'products.view', 'inventory.view', 'inventory.import',
            'inventory.adjust', 'orders.view', 'orders.update-status', 'shipping_methods.view',
        ],
        'sales' => [
            'dashboard.view', 'users.view', 'categories.view', 'brands.view',
            'products.view', 'products.manage', 'products.publish',
            'orders.view', 'orders.manage', 'orders.update-status',
            'coupons.view', 'flash_sales.view', 'flash_sales.manage',
            'banners.view', 'shipping_methods.view',
        ],
        'customer' => [
            'profile.update-own', 'addresses.manage-own', 'orders.view-own',
            'orders.cancel-own', 'reviews.create-own',
        ],
    ];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $slug => $data) {
            $permissions[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                ],
            );
        }

        $roles = [];

        foreach (self::ROLES as $slug => $data) {
            $roles[$slug] = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_system' => $data['is_system'],
                ],
            );
        }

        foreach (self::ROLE_PERMISSION_MAP as $roleSlug => $permissionSlugs) {
            if (! isset($roles[$roleSlug])) {
                throw new LogicException(
                    "RBAC permission matrix references an undefined role: {$roleSlug}"
                );
            }

            $permissionIds = [];

            foreach ($permissionSlugs as $permissionSlug) {
                if (! isset($permissions[$permissionSlug])) {
                    throw new LogicException(
                        "RBAC permission matrix references an undefined permission: {$permissionSlug}"
                    );
                }

                $permissionIds[] = $permissions[$permissionSlug]->getKey();
            }

            $roles[$roleSlug]->permissions()->sync($permissionIds);
        }
    }
}
