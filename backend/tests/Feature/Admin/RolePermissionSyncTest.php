<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();

        $role = Role::factory()->create([
            'name' => 'Quản trị cấp cao',
            'slug' => 'super-admin',
        ]);

        $perm = Permission::factory()->create([
            'name' => 'Quản lý vai trò',
            'slug' => 'roles.manage',
        ]);

        $permUsers = Permission::factory()->create([
            'name' => 'Quản lý người dùng',
            'slug' => 'users.manage',
        ]);

        $permDash = Permission::factory()->create([
            'name' => 'Xem dashboard',
            'slug' => 'dashboard.view',
        ]);

        $role->permissions()->attach([$perm->id, $permUsers->id, $permDash->id]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_sync_permissions_requires_authentication(): void
    {
        $role = Role::factory()->create();

        $this->putJson("/api/admin/roles/{$role->id}/permissions", [
            'permissions' => [],
        ])->assertUnauthorized();
    }

    public function test_sync_permissions_requires_roles_manage_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $role = Role::factory()->create();

        $this->putJson("/api/admin/roles/{$role->id}/permissions", [
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_super_admin_can_sync_role_permissions_successfully(): void
    {
        $this->createSuperAdmin();

        $targetRole = Role::factory()->create([
            'name' => 'Nhân viên bán hàng',
            'slug' => 'sales',
        ]);

        $p1 = Permission::factory()->create(['slug' => 'products.view', 'name' => 'Xem sản phẩm']);
        $p2 = Permission::factory()->create(['slug' => 'orders.view', 'name' => 'Xem đơn hàng']);

        $response = $this->putJson("/api/admin/roles/{$targetRole->id}/permissions", [
            'permissions' => ['products.view', 'orders.view'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role.slug', 'sales')
            ->assertJsonPath('data.role.permissions', ['orders.view', 'products.view']);

        $this->assertEquals(2, $targetRole->fresh()->permissions()->count());
    }

    public function test_super_admin_can_sync_role_permissions_by_slug(): void
    {
        $this->createSuperAdmin();

        $targetRole = Role::factory()->create([
            'name' => 'Thủ kho',
            'slug' => 'warehouse',
        ]);

        $p1 = Permission::factory()->create(['slug' => 'inventory.view', 'name' => 'Xem kho']);

        $response = $this->putJson("/api/admin/roles/{$targetRole->slug}/permissions", [
            'permissions' => ['inventory.view'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role.slug', 'warehouse')
            ->assertJsonPath('data.role.permissions', ['inventory.view']);

        $this->assertEquals(1, $targetRole->fresh()->permissions()->count());
    }

    public function test_cannot_strip_core_permissions_from_super_admin(): void
    {
        $this->createSuperAdmin();

        $superRole = Role::where('slug', 'super-admin')->firstOrFail();

        $response = $this->putJson("/api/admin/roles/{$superRole->id}/permissions", [
            'permissions' => ['products.view'], // Missing roles.manage, users.manage, dashboard.view
        ]);

        $response->assertUnprocessable();
    }
}
