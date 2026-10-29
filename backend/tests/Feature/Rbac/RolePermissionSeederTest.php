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

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 5);

        $this->assertDatabaseHas('roles', [
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Staff',
            'slug' => 'staff',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Customer',
            'slug' => 'customer',
        ]);

        foreach (
            [
                'users.view',
                'users.manage',
                'categories.manage',
                'brands.manage',
                'attributes.manage',
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
                'attributes.manage',
                'brands.manage',
                'categories.manage',
                'users.manage',
                'users.view',
            ],
            'staff' => [
                'attributes.manage',
                'brands.manage',
                'categories.manage',
            ],
            'customer' => [],
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
            'roles' => 3,
            'permissions' => 5,
            'permission_role' => 8,
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

        $this->assertCount(5, $customer->fresh()->permissions);

        $this->seed(RolePermissionSeeder::class);

        $this->assertCount(0, $customer->fresh()->permissions);
    }
}
