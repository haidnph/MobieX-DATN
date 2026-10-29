<?php

namespace Tests\Feature\Rbac;

use App\Http\Middleware\EnsurePermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsurePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function registerProtectedRoute(string $requiredPermissions = 'users.view'): void
    {
        Route::get(
            '/__test/rbac/permissions',
            static fn () => response()->json([
                'message' => 'Access granted.',
            ]),
        )->middleware([
            'auth:sanctum',
            'active',
            EnsurePermission::class.':'.$requiredPermissions,
        ]);
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $this->registerProtectedRoute();

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_inactive_user_receives_403(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->registerProtectedRoute();

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);
    }

    public function test_authenticated_user_without_permission_receives_403(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->registerProtectedRoute();

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to perform this action.',
            ]);
    }

    public function test_user_with_required_permission_receives_200(): void
    {
        $user = User::factory()->create();

        $role = Role::factory()->create([
            'slug' => 'admin',
        ]);

        $permission = Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $role->permissions()->attach($permission->getKey());

        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->registerProtectedRoute();

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Access granted.',
            ]);
    }

    public function test_permissions_from_multiple_roles_are_combined(): void
    {
        $user = User::factory()->create();

        $userRole = Role::factory()->create([
            'slug' => 'user-manager',
        ]);

        $brandRole = Role::factory()->create([
            'slug' => 'brand-manager',
        ]);

        $viewUsers = Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $manageBrands = Permission::factory()->create([
            'slug' => 'brands.manage',
        ]);

        $userRole->permissions()->attach($viewUsers->getKey());
        $brandRole->permissions()->attach($manageBrands->getKey());

        $user->assignRole($userRole);
        $user->assignRole($brandRole);

        Sanctum::actingAs($user);

        $this->registerProtectedRoute('users.view,brands.manage');

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Access granted.',
            ]);
    }

    public function test_user_missing_one_of_multiple_permissions_receives_403(): void
    {
        $user = User::factory()->create();

        $role = Role::factory()->create([
            'slug' => 'user-viewer',
        ]);

        $permission = Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $role->permissions()->attach($permission->getKey());
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->registerProtectedRoute('users.view,brands.manage');

        $response = $this->getJson('/__test/rbac/permissions');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to perform this action.',
            ]);
    }

    public function test_inactive_account_is_rejected_even_without_active_alias(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        Route::get(
            '/__test/rbac/permission-defense',
            static fn () => response()->json([
                'message' => 'Access granted.',
            ]),
        )->middleware([
            'auth:sanctum',
            EnsurePermission::class.':users.view',
        ]);

        $response = $this->getJson('/__test/rbac/permission-defense');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);
    }
}
