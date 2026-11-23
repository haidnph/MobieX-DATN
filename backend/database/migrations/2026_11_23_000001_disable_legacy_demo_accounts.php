<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable the fixed demo identities that older seeders created with
     * publicly documented passwords, and revoke their existing API tokens.
     */
    public function up(): void
    {
        $userIds = DB::table('users')
            ->whereIn('email', [
                'admin@mobilex.tech',
                'superadmin@gmail.com',
                'customer@mobilex.tech',
            ])
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        DB::table('users')->whereIn('id', $userIds)->update(['is_active' => false]);

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }

    /**
     * Intentionally irreversible: reactivating a fixed demo identity would
     * restore a known credential. An administrator can create a replacement.
     */
    public function down(): void
    {
        // Keep legacy demo accounts disabled on rollback.
    }
};
