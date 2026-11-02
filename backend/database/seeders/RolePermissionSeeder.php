<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use LogicException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Canonical system roles keyed by immutable slug.
     *
     * @var array<string, string>
     */
    private const ROLES = [
        'admin' => 'Admin',
        'staff' => 'Staff',
        'customer' => 'Customer',
    ];

    /**
     * Canonical permissions keyed by immutable slug.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'users.view' => 'View users',
        'users.manage' => 'Manage users',
        'categories.manage' => 'Manage categories',
        'brands.manage' => 'Manage brands',
        'attributes.manage' => 'Manage attributes',
    ];

    /**
     * Authoritative permission assignment for each system role.
     *
     * Running this seeder reconciles these assignments to this matrix.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSION_MAP = [
        'admin' => [
            'users.view',
            'users.manage',
            'categories.manage',
            'brands.manage',
            'attributes.manage',
        ],
        'staff' => [
            'categories.manage',
            'brands.manage',
            'attributes.manage',
        ],
        'customer' => [],
    ];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $slug => $name) {
            $permissions[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        $roles = [];

        foreach (self::ROLES as $slug => $name) {
            $roles[$slug] = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        foreach (self::ROLE_PERMISSION_MAP as $roleSlug => $permissionSlugs) {
            if (! isset($roles[$roleSlug])) {
                throw new LogicException(
                    "RBAC permission matrix references an undefined role: {$roleSlug}"
                );
            }

            $permissionIds = [];

            foreach ($permissionSlugs as $permissionSlug) {
                if (! isset($permissions[$permissionSlug])) {
                    throw new LogicException(
                        "RBAC permission matrix references an undefined permission: {$permissionSlug}"
                    );
                }

                $permissionIds[] = $permissions[$permissionSlug]->getKey();
            }

            /*
             * The seeder owns the canonical permissions assigned to these
             * system roles. sync() removes stale assignments and inserts
             * missing ones without creating duplicate pivot records.
             *
             * Existing user-to-role assignments are not affected.
             */
            $roles[$roleSlug]
                ->permissions()
                ->sync($permissionIds);
        }
    }
}
