<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_list_requires_authentication(): void
    {
        $this->getJson('/api/admin/users')
            ->assertUnauthorized();
    }

    public function test_admin_user_detail_requires_authentication(): void
    {
        $this->getJson('/api/admin/users/1')
            ->assertUnauthorized();
    }

    public function test_user_with_users_view_permission_can_get_paginated_user_list(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        User::factory()->count(15)->create();

        $response = $this->getJson('/api/admin/users');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Admin users retrieved successfully.',
            )
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'is_active',
                        'roles' => [
                            '*' => [
                                'slug',
                                'name',
                            ],
                        ],
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ])
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonMissingPath('data.0.tokens');
    }

    public function test_user_list_supports_page_query_parameter(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        User::factory()->count(15)->create();

        $response = $this->getJson('/api/admin/users?page=2');

        $response
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_with_users_view_permission_can_get_user_detail_with_roles(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        $targetUser = User::factory()->create([
            'name' => 'Customer Example',
            'email' => 'customer@example.com',
            'phone' => '0900000000',
            'is_active' => true,
        ]);

        $customerRole = Role::factory()->create([
            'name' => 'Customer',
            'slug' => 'customer',
        ]);

        $targetUser->assignRole($customerRole);

        $response = $this->getJson(
            '/api/admin/users/'.$targetUser->getKey(),
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Admin user retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $targetUser->getKey(),
                        'name' => 'Customer Example',
                        'email' => 'customer@example.com',
                        'phone' => '0900000000',
                        'is_active' => true,
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
    }

    public function test_user_without_users_view_permission_receives_forbidden(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/users')
            ->assertForbidden();

        $this->getJson('/api/admin/users/1')
            ->assertForbidden();

        $this->getJson('/api/admin/users/999999')
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_access_admin_user_endpoints(): void
    {
        $this->authenticateUserWithUsersViewPermission([
            'is_active' => false,
        ]);

        $this->getJson('/api/admin/users')
            ->assertForbidden();

        $this->getJson('/api/admin/users/1')
            ->assertForbidden();

        $this->getJson('/api/admin/users/999999')
            ->assertForbidden();
    }

    public function test_missing_user_detail_returns_not_found(): void
    {
        $this->authenticateUserWithUsersViewPermission();

        $this->getJson('/api/admin/users/999999')
            ->assertNotFound();
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

        $role = Role::factory()->create();

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
