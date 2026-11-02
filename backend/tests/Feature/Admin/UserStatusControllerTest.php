<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_status_requires_authentication(): void
    {
        $this->patchJson('/api/admin/users/1/status', [
            'is_active' => false,
        ])->assertUnauthorized();
    }

    public function test_update_status_requires_active_account(): void
    {
        $this->authenticateUserWithUsersManagePermission([
            'is_active' => false,
        ]);

        $this->patchJson('/api/admin/users/1/status', [
            'is_active' => false,
        ])->assertForbidden();
    }

    public function test_update_status_requires_users_manage_permission(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/admin/users/1/status', [
            'is_active' => false,
        ])->assertForbidden();
    }

    public function test_user_without_users_manage_permission_receives_forbidden_even_for_non_existent_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/admin/users/999999/status', [
            'is_active' => false,
        ])->assertForbidden();
    }

    public function test_update_status_returns_not_found_when_target_user_does_not_exist(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $this->patchJson('/api/admin/users/999999/status', [
            'is_active' => false,
        ])->assertNotFound();
    }

    public function test_update_status_validates_required_is_active_field(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $this->patchJson('/api/admin/users/'.$targetUser->getKey().'/status', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_update_status_validates_is_active_must_be_boolean(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create();

        $this->patchJson('/api/admin/users/'.$targetUser->getKey().'/status', [
            'is_active' => 'not-a-boolean',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_user_with_users_manage_permission_can_deactivate_user_successfully(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $customerRole = Role::factory()->create([
            'slug' => 'customer',
            'name' => 'Customer',
        ]);
        $targetUser->assignRole($customerRole);

        $response = $this->patchJson('/api/admin/users/'.$targetUser->getKey().'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User status updated successfully.',
                'data' => [
                    'user' => [
                        'id' => $targetUser->getKey(),
                        'is_active' => false,
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

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->getKey(),
            'is_active' => false,
        ]);
    }

    public function test_user_with_users_manage_permission_can_activate_user_successfully(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->patchJson('/api/admin/users/'.$targetUser->getKey().'/status', [
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User status updated successfully.',
                'data' => [
                    'user' => [
                        'id' => $targetUser->getKey(),
                        'is_active' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->authenticateUserWithUsersManagePermission();

        $response = $this->patchJson('/api/admin/users/'.$admin->getKey().'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'You cannot deactivate your own account.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_cannot_deactivate_last_active_administrator(): void
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

        $response = $this->patchJson('/api/admin/users/'.$lastAdmin->getKey().'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot deactivate the last active administrator.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $lastAdmin->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_admin_when_another_active_administrator_exists(): void
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

        $response = $this->patchJson('/api/admin/users/'.$admin2->getKey().'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User status updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin2->getKey(),
            'is_active' => false,
        ]);
    }

    public function test_deactivated_user_cannot_access_protected_endpoints(): void
    {
        $this->authenticateUserWithUsersManagePermission();

        $targetUser = User::factory()->create(['is_active' => true]);

        $this->patchJson('/api/admin/users/'.$targetUser->getKey().'/status', [
            'is_active' => false,
        ])->assertOk();

        $targetUser->refresh();

        Sanctum::actingAs($targetUser);

        $this->getJson('/api/me')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
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
