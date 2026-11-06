<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_default_roles_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 35);

        $this->assertDatabaseHas('roles', [
            'name' => 'Quản trị viên',
            'slug' => 'admin',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Kế toán',
            'slug' => 'accountant',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Kho',
            'slug' => 'warehouse',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Bán hàng',
            'slug' => 'sales',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Khách hàng',
            'slug' => 'customer',
        ]);

        foreach (
            [
                'dashboard.view',
                'users.view',
                'users.manage',
                'categories.manage',
                'brands.manage',
                'attributes.manage',
                'orders.view',
            ] as $permissionSlug
        ) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $permissionSlug,
            ]);
        }
    }

    public function test_it_assigns_the_expected_permission_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $expectedMatrix = [
            'admin' => [
                'addresses.manage-own',
                'attributes.manage',
                'banners.manage',
                'banners.view',
                'brands.manage',
                'brands.view',
                'categories.manage',
                'categories.view',
                'coupons.manage',
                'coupons.view',
                'dashboard.view',
                'flash_sales.manage',
                'flash_sales.view',
                'inventory.adjust',
                'inventory.import',
                'inventory.view',
                'orders.cancel-own',
                'orders.manage',
                'orders.update-status',
                'orders.view',
                'orders.view-own',
                'payments.view',
                'products.manage',
                'products.publish',
                'products.view',
                'profile.update-own',
                'reports.view',
                'reviews.create-own',
                'reviews.moderate',
                'reviews.view',
                'roles.manage',
                'shipping_methods.manage',
                'shipping_methods.view',
                'users.manage',
                'users.view',
            ],
            'accountant' => [
                'coupons.view',
                'dashboard.view',
                'orders.view',
                'payments.view',
                'reports.view',
                'shipping_methods.view',
            ],
            'warehouse' => [
                'dashboard.view',
                'inventory.adjust',
                'inventory.import',
                'inventory.view',
                'orders.update-status',
                'orders.view',
                'products.view',
                'shipping_methods.view',
            ],
            'sales' => [
                'banners.view',
                'brands.view',
                'categories.view',
                'coupons.view',
                'dashboard.view',
                'flash_sales.manage',
                'flash_sales.view',
                'orders.manage',
                'orders.update-status',
                'orders.view',
                'products.manage',
                'products.publish',
                'products.view',
                'shipping_methods.view',
                'users.view',
            ],
            'customer' => [
                'addresses.manage-own',
                'orders.cancel-own',
                'orders.view-own',
                'profile.update-own',
                'reviews.create-own',
            ],
        ];

        foreach ($expectedMatrix as $roleSlug => $expectedPermissions) {
            $role = Role::query()
                ->where('slug', $roleSlug)
                ->firstOrFail();

            $actualPermissions = $role->permissions()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();

            sort($expectedPermissions);

            $this->assertSame(
                $expectedPermissions,
                $actualPermissions,
                "Unexpected permissions for role [{$roleSlug}]."
            );
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate_data(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $firstSnapshot = [
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'permission_role' => DB::table('permission_role')->count(),
        ];

        $this->seed(RolePermissionSeeder::class);

        $secondSnapshot = [
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'permission_role' => DB::table('permission_role')->count(),
        ];

        $this->assertSame($firstSnapshot, $secondSnapshot);

        $this->assertSame([
            'roles' => 5,
            'permissions' => 35,
            'permission_role' => 69,
        ], $secondSnapshot);
    }

    public function test_it_reconciles_stale_assignments_with_the_default_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customer = Role::query()
            ->where('slug', 'customer')
            ->firstOrFail();

        $customer->permissions()->sync(
            Permission::query()->pluck('id')->all()
        );

        $this->assertCount(35, $customer->fresh()->permissions);

        $this->seed(RolePermissionSeeder::class);

        $this->assertCount(5, $customer->fresh()->permissions);
    }
}
