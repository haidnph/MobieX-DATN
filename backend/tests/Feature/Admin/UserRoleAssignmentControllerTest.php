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
