<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_role_is_idempotent_and_accepts_model_or_slug(): void
    {
        $user = User::factory()->create();

        $role = Role::factory()->create([
            'slug' => 'customer',
        ]);

        $user->assignRole($role);
        $user->assignRole('customer');

        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue($user->hasRole($role));

        $this->assertSame(
            1,
            DB::table('role_user')
                ->where('user_id', $user->getKey())
                ->where('role_id', $role->getKey())
                ->count(),
        );
    }

    public function test_removing_role_only_detaches_that_assignment(): void
    {
        $user = User::factory()->create();

        $admin = Role::factory()->create([
            'slug' => 'admin',
        ]);

        $staff = Role::factory()->create([
            'slug' => 'staff',
        ]);

        $user->assignRole($admin);
        $user->assignRole($staff);

        $user->removeRole('staff');
        $user->removeRole($staff);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('staff'));
        $this->assertSame(1, $user->roles()->count());

        $this->assertDatabaseHas('roles', [
            'id' => $staff->getKey(),
            'slug' => 'staff',
        ]);
    }

    public function test_has_role_accepts_a_slug_or_persisted_role_model(): void
    {
        $user = User::factory()->create();

        $customer = Role::factory()->create([
            'slug' => 'customer',
        ]);

        $staff = Role::factory()->create([
            'slug' => 'staff',
        ]);

        $user->assignRole($customer);

        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue($user->hasRole($customer));
        $this->assertFalse($user->hasRole('staff'));
        $this->assertFalse($user->hasRole($staff));
        $this->assertFalse($user->hasRole('unknown-role'));
        $this->assertFalse($user->hasRole('   '));
    }

    public function test_effective_permissions_are_the_union_of_all_roles_without_duplicates(): void
    {
        $user = User::factory()->create();

        $admin = Role::factory()->create([
            'slug' => 'admin',
        ]);

        $staff = Role::factory()->create([
            'slug' => 'staff',
        ]);

        $viewUsers = Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $viewCatalog = Permission::factory()->create([
            'slug' => 'catalog.view',
        ]);

        $manageBrands = Permission::factory()->create([
            'slug' => 'brands.manage',
        ]);

        $admin->permissions()->attach([
            $viewUsers->getKey(),
            $viewCatalog->getKey(),
        ]);

        $staff->permissions()->attach([
            $viewCatalog->getKey(),
            $manageBrands->getKey(),
        ]);

        $user->assignRole($admin);
        $user->assignRole($staff);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertTrue($user->hasPermission('catalog.view'));
        $this->assertTrue($user->hasPermission('brands.manage'));

        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertFalse($user->hasPermission(''));

        $effectivePermissions = $user->getAllPermissions();

        $this->assertCount(3, $effectivePermissions);

        $this->assertSame([
            'brands.manage',
            'catalog.view',
            'users.view',
        ], $effectivePermissions->pluck('slug')->all());

        $this->assertSame(
            $effectivePermissions->pluck('id')->unique()->count(),
            $effectivePermissions->count(),
        );
    }

    public function test_user_without_roles_has_no_effective_permissions(): void
    {
        $user = User::factory()->create();

        Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $this->assertCount(0, $user->getAllPermissions());
        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertFalse($user->hasRole('customer'));
    }

    public function test_unsaved_user_has_no_roles_or_effective_permissions(): void
    {
        $user = new User;

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_assigning_unknown_role_slug_throws_model_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $user->assignRole('role-that-does-not-exist');
    }

    public function test_removing_unknown_role_slug_throws_model_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $user->removeRole('role-that-does-not-exist');
    }

    public function test_unsaved_user_cannot_be_assigned_a_role(): void
    {
        $user = new User;

        $role = Role::factory()->create([
            'slug' => 'customer',
        ]);

        $this->expectException(LogicException::class);

        $user->assignRole($role);
    }

    public function test_unsaved_role_cannot_be_assigned_to_a_user(): void
    {
        $user = User::factory()->create();

        $role = Role::make([
            'name' => 'Customer',
            'slug' => 'customer',
        ]);

        $this->expectException(LogicException::class);

        $user->assignRole($role);
    }

    public function test_blank_role_slug_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $user->assignRole('   ');
    }
}
