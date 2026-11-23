<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUserWithUsersManagePermission(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $role = Role::factory()->create([
            'name' => 'Admin Role',
            'slug' => 'admin-test',
        ]);

        $permissionView = Permission::factory()->create([
            'name' => 'View users',
            'slug' => 'users.view',
        ]);

        $permissionManage = Permission::factory()->create([
            'name' => 'Manage users',
            'slug' => 'users.manage',
        ]);

        $role->permissions()->attach([$permissionView->getKey(), $permissionManage->getKey()]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_create_user_requires_users_manage_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_can_create_user_with_role_successfully(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $role = Role::factory()->create([
            'slug' => 'sales',
            'name' => 'Bán hàng',
        ]);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Nguyễn Văn Test',
            'email' => 'nvtest@example.com',
            'phone' => '0912345678',
            'password' => 'SecurePass123!',
            'role' => 'sales',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Nguyễn Văn Test')
            ->assertJsonPath('data.user.email', 'nvtest@example.com')
            ->assertJsonPath('data.user.phone', '0912345678')
            ->assertJsonPath('data.user.is_active', true);

        $createdUser = User::where('email', 'nvtest@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('SecurePass123!', $createdUser->password));
        $this->assertTrue($createdUser->hasRole('sales'));
    }

    public function test_users_manager_cannot_create_super_admin_account(): void
    {
        $this->authenticateUserWithUsersManagePermission();
        Role::factory()->create([
            'slug' => 'super-admin',
            'name' => 'Super Admin',
        ]);

        $this->postJson('/api/admin/users', [
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.com',
            'password' => 'SecurePass123!',
            'role' => 'super-admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalation@example.com']);
    }

    public function test_create_user_validates_unique_email_and_password(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/admin/users', [
            'name' => 'Test',
            'email' => 'existing@example.com',
            'password' => '123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_can_update_user_details(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $target = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->patchJson('/api/admin/users/'.$target->id, [
            'name' => 'Updated Name',
            'phone' => '0988888888',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.phone', '0988888888');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
            'phone' => '0988888888',
        ]);
    }

    public function test_can_reset_user_password(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $target = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->patchJson('/api/admin/users/'.$target->id.'/password', [
            'password' => 'NewStrongPass123!',
        ]);

        $response->assertOk();

        $target->refresh();
        $this->assertTrue(Hash::check('NewStrongPass123!', $target->password));
    }

    public function test_user_list_filters_by_search_role_and_status(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $roleStaff = Role::factory()->create(['slug' => 'warehouse', 'name' => 'Kho']);

        $user1 = User::factory()->create(['name' => 'Hoàng Nam', 'email' => 'nam@test.com', 'is_active' => true]);
        $user1->assignRole($roleStaff);

        $user2 = User::factory()->create(['name' => 'Lê Hoa', 'email' => 'hoa@test.com', 'is_active' => false]);

        // Search by keyword
        $resSearch = $this->getJson('/api/admin/users?search=Hoàng');
        $resSearch->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Hoàng Nam', $resSearch->json('data.0.name'));

        // Filter by role
        $resRole = $this->getJson('/api/admin/users?role=warehouse');
        $resRole->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Hoàng Nam', $resRole->json('data.0.name'));

        // Filter by status
        $resBlocked = $this->getJson('/api/admin/users?status=blocked');
        $resBlocked->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Lê Hoa', $resBlocked->json('data.0.name'));

        // Stats in meta
        $this->assertNotNull($resSearch->json('stats'));
        $this->assertGreaterThanOrEqual(3, $resSearch->json('stats.total'));
    }
}
