<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'name' => 'Quản lý thùng rác',
                'slug' => 'trash.manage',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')
                ->where('slug', 'trash.manage')
                ->value('id');

            $adminRoleIds = DB::table('roles')
                ->whereIn('slug', ['admin', 'super-admin'])
                ->pluck('id');

            foreach ($adminRoleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('slug', 'trash.manage')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $adminRoleIds = DB::table('roles')
            ->whereIn('slug', ['admin', 'super-admin'])
            ->pluck('id');

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $adminRoleIds)
            ->delete();

        if (! DB::table('permission_role')->where('permission_id', $permissionId)->exists()) {
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
