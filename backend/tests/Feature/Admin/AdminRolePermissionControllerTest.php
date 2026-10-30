<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/roles')
            ->assertUnauthorized();
    }

    public function test_permission_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/permissions')
            ->assertUnauthorized();
    }

    public function test_role_and_permission_lists_require_users_view_permission(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/roles')
            ->assertForbidden();

        $this->getJson('/api/admin/permissions')
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_access_role_or_permission_lists(): void
    {
        $this->authenticateUserWithUsersViewPermission([
            'is_active' => false,
        ]);

        $this->getJson('/api/admin/roles')
            ->assertForbidden();

        $this->getJson('/api/admin/permissions')
            ->assertForbidden();
    }

    public function test_user_with_users_view_permission_can_list_roles_and_their_permissions(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        $usersView = Permission::query()
            ->where('slug', 'users.view')
            ->firstOrFail();

        $usersManage = Permission::factory()->create([
            'name' => 'Manage users',
            'slug' => 'users.manage',
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $adminRole->permissions()->attach([
            $usersView->getKey(),
            $usersManage->getKey(),
        ]);

        Role::factory()->create([
            'name' => 'Customer',
            'slug' => 'customer',
        ]);

        $response = $this->getJson('/api/admin/roles');

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Roles retrieved successfully.',
                'data' => [
                    'roles' => [
                        [
                            'slug' => 'admin',
                            'name' => 'Admin',
                            'permissions' => [
                                'users.manage',
                                'users.view',
                            ],
                        ],
                        [
                            'slug' => 'customer',
                            'name' => 'Customer',
                            'permissions' => [],
                        ],
                        [
                            'slug' => 'reader',
                            'name' => 'Reader',
                            'permissions' => [
                                'users.view',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_with_users_view_permission_can_list_permissions(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        Permission::factory()->create([
            'name' => 'Manage brands',
            'slug' => 'brands.manage',
        ]);

        Permission::factory()->create([
            'name' => 'Manage users',
            'slug' => 'users.manage',
        ]);

        $response = $this->getJson('/api/admin/permissions');

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Permissions retrieved successfully.',
                'data' => [
                    'permissions' => [
                        [
                            'slug' => 'brands.manage',
                            'name' => 'Manage brands',
                        ],
                        [
                            'slug' => 'users.manage',
                            'name' => 'Manage users',
                        ],
                        [
                            'slug' => 'users.view',
                            'name' => 'View users',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Authenticate a user whose role grants users.view.
     *
     * @param  array<string, mixed>  $userAttributes
     */
    private function authenticateUserWithUsersViewPermission(
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::factory()->create([
            'name' => 'Reader',
            'slug' => 'reader',
        ]);

        $permission = Permission::factory()->create([
            'name' => 'View users',
            'slug' => 'users.view',
        ]);

        $role->permissions()->attach($permission->getKey());

        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
