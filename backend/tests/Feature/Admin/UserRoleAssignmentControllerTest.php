<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRoleAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_role_requires_authentication(): void
    {
        $this->postJson('/api/admin/users/1/roles', [
            'role' => 'staff',
        ])->assertUnauthorized();
    }

    public function test_assign_role_requires_active_account(): void
    {
        $this->authenticateUserWithUsersManagePermission([
            'is_active' => false,
        ]);

        $this->postJson('/api/admin/users/1/roles', [
            'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_assign_role_requires_users_manage_permission(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/admin/users/1/roles', [
            'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_user_without_users_manage_permission_receives_forbidden_even_for_non_existent_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/admin/users/999999/roles', [
            'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_assign_role_returns_not_found_when_target_user_does_not_exist(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $role = Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $this->postJson('/api/admin/users/999999/roles', [
            'role' => $role->slug,
        ])->assertNotFound();
    }

    public function test_assign_role_validates_required_role_field(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $this->postJson('/api/admin/users/'.$targetUser->getKey().'/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_assign_role_validates_role_exists(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $this->postJson('/api/admin/users/'.$targetUser->getKey().'/roles', [
            'role' => 'nonexistent-role-slug',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_user_with_users_manage_permission_can_assign_role_successfully(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create([
            'name' => 'Target Customer',
            'email' => 'target@example.com',
            'phone' => '0901234567',
            'is_active' => true,
        ]);

        $role = Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $response = $this->postJson('/api/admin/users/'.$targetUser->getKey().'/roles', [
            'role' => 'staff',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User role assigned successfully.',
                'data' => [
                    'user' => [
                        'id' => $targetUser->getKey(),
                        'name' => 'Target Customer',
                        'email' => 'target@example.com',
                        'phone' => '0901234567',
                        'is_active' => true,
                        'roles' => [
                            [
                                'slug' => 'staff',
                                'name' => 'Staff',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.user.tokens');

        $this->assertDatabaseHas('role_user', [
            'user_id' => $targetUser->getKey(),
            'role_id' => $role->getKey(),
        ]);
    }

    public function test_assigning_already_assigned_role_is_idempotent(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $role = Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $targetUser->assignRole($role);

        $response = $this->postJson('/api/admin/users/'.$targetUser->getKey().'/roles', [
            'role' => 'staff',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User role assigned successfully.',
            ]);

        $count = DB::table('role_user')
            ->where('user_id', $targetUser->getKey())
            ->where('role_id', $role->getKey())
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_remove_role_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/users/1/roles/staff')
            ->assertUnauthorized();
    }

    public function test_remove_role_requires_active_account(): void
    {
        $this->authenticateUserWithUsersManagePermission([
            'is_active' => false,
        ]);

        $this->deleteJson('/api/admin/users/1/roles/staff')
            ->assertForbidden();
    }

    public function test_remove_role_requires_users_manage_permission(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/admin/users/1/roles/staff')
            ->assertForbidden();
    }

    public function test_user_without_users_manage_permission_receives_forbidden_even_for_non_existent_user_or_role(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/admin/users/999999/roles/nonexistent-role')
            ->assertForbidden();
    }

    public function test_remove_role_returns_not_found_when_target_user_does_not_exist(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $this->deleteJson('/api/admin/users/999999/roles/staff')
            ->assertNotFound();
    }

    public function test_remove_role_returns_not_found_when_role_does_not_exist(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $this->deleteJson('/api/admin/users/'.$targetUser->getKey().'/roles/nonexistent-role')
            ->assertNotFound();
    }

    public function test_user_with_users_manage_permission_can_remove_role_successfully(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $customerRole = Role::factory()->create([
            'slug' => 'customer',
            'name' => 'Customer',
        ]);

        $staffRole = Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $targetUser->assignRole($customerRole);
        $targetUser->assignRole($staffRole);

        $response = $this->deleteJson(
            '/api/admin/users/'.$targetUser->getKey().'/roles/staff'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User role removed successfully.',
                'data' => [
                    'user' => [
                        'id' => $targetUser->getKey(),
                        'roles' => [
                            [
                                'slug' => 'customer',
                                'name' => 'Customer',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.user.tokens');

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $targetUser->getKey(),
            'role_id' => $staffRole->getKey(),
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $targetUser->getKey(),
            'role_id' => $customerRole->getKey(),
        ]);
    }

    public function test_remove_role_is_idempotent_when_role_was_not_assigned_to_user(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        Role::factory()->create([
            'slug' => 'staff',
            'name' => 'Staff',
        ]);

        $response = $this->deleteJson(
            '/api/admin/users/'.$targetUser->getKey().'/roles/staff'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User role removed successfully.',
            ]);
    }

    public function test_administrator_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->authenticateUserWithUsersManagePermission();

        $adminRole = Role::factory()->create([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $admin->assignRole($adminRole);

        $response = $this->deleteJson(
            '/api/admin/users/'.$admin->getKey().'/roles/admin'
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'You cannot remove your own admin role.',
            ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->getKey(),
            'role_id' => $adminRole->getKey(),
        ]);
    }

    public function test_cannot_remove_admin_role_from_last_active_administrator(): void
    {
        $operator = $this->authenticateUserWithUsersManagePermission();

        $adminRole = Role::factory()->create([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $lastAdmin = User::factory()->create([
            'is_active' => true,
        ]);
        $lastAdmin->assignRole($adminRole);

        $response = $this->deleteJson(
            '/api/admin/users/'.$lastAdmin->getKey().'/roles/admin'
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot remove the admin role from the last active administrator.',
            ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $lastAdmin->getKey(),
            'role_id' => $adminRole->getKey(),
        ]);
    }

    public function test_can_remove_admin_role_when_another_active_administrator_exists(): void
    {
        $adminRole = Role::factory()->create([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $managePermission = Permission::factory()->create([
            'name' => 'Manage users',
            'slug' => 'users.manage',
        ]);
        $adminRole->permissions()->attach($managePermission->getKey());

        $admin1 = User::factory()->create(['is_active' => true]);
        $admin1->assignRole($adminRole);

        $admin2 = User::factory()->create(['is_active' => true]);
        $admin2->assignRole($adminRole);

        Sanctum::actingAs($admin1);

        $response = $this->deleteJson(
            '/api/admin/users/'.$admin2->getKey().'/roles/admin'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User role removed successfully.',
            ]);

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $admin2->getKey(),
            'role_id' => $adminRole->getKey(),
        ]);
    }

    /**
     * Authenticate a user whose role grants users.manage.
     *
     * @param  array<string, mixed>  $userAttributes
     */
    private function authenticateUserWithUsersManagePermission(
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::factory()->create();

        $permission = Permission::factory()->create([
            'name' => 'Manage users',
            'slug' => 'users.manage',
        ]);

        $role->permissions()->attach($permission->getKey());

        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
