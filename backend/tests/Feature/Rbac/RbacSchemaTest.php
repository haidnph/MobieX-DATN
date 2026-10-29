<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbacSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('roles', [
            'id',
            'name',
            'slug',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('permissions', [
            'id',
            'name',
            'slug',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('role_user', [
            'user_id',
            'role_id',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('permission_role', [
            'role_id',
            'permission_id',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_role_slug_must_be_unique(): void
    {
        Role::factory()->create([
            'slug' => 'admin',
        ]);

        $this->expectException(QueryException::class);

        Role::factory()->create([
            'slug' => 'admin',
        ]);
    }

    public function test_permission_slug_must_be_unique(): void
    {
        Permission::factory()->create([
            'slug' => 'users.view',
        ]);

        $this->expectException(QueryException::class);

        Permission::factory()->create([
            'slug' => 'users.view',
        ]);
    }

    public function test_user_role_and_role_permission_relationships_work(): void
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

        $manageUsers = Permission::factory()->create([
            'slug' => 'users.manage',
        ]);

        $user->roles()->attach([
            $admin->id,
            $staff->id,
        ]);

        $admin->permissions()->attach([
            $viewUsers->id,
            $manageUsers->id,
        ]);

        $this->assertCount(2, $user->fresh()->roles);

        $this->assertTrue(
            $admin->fresh()->users->contains('id', $user->id)
        );

        $this->assertCount(2, $admin->fresh()->permissions);

        $this->assertTrue(
            $viewUsers->fresh()->roles->contains('id', $admin->id)
        );
    }

    public function test_duplicate_user_role_assignment_is_rejected(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $user->roles()->attach($role->id);

        $this->expectException(QueryException::class);

        $user->roles()->attach($role->id);
    }

    public function test_duplicate_role_permission_assignment_is_rejected(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $role->permissions()->attach($permission->id);

        $this->expectException(QueryException::class);

        $role->permissions()->attach($permission->id);
    }

    public function test_deleting_a_role_cascades_its_pivot_assignments(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $user->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        $role->delete();

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_deleting_a_user_cascades_role_assignments(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $user->roles()->attach($role->id);

        $user->delete();

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_deleting_a_permission_cascades_permission_role_assignments(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $role->permissions()->attach($permission->id);

        $permission->delete();

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }
}
