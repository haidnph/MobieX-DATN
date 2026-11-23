<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','processing','shipping','received','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE order_status_histories MODIFY from_status ENUM('pending','confirmed','processing','shipping','received','delivered','cancelled') NULL");
        DB::statement("ALTER TABLE order_status_histories MODIFY status ENUM('pending','confirmed','processing','shipping','received','delivered','cancelled') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('orders')->where('status', 'received')->update(['status' => 'shipping']);
        DB::table('order_status_histories')->where('status', 'received')->update(['status' => 'shipping']);
        DB::table('order_status_histories')->where('from_status', 'received')->update(['from_status' => 'shipping']);
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','processing','shipping','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE order_status_histories MODIFY from_status ENUM('pending','confirmed','processing','shipping','delivered','cancelled') NULL");
        DB::statement("ALTER TABLE order_status_histories MODIFY status ENUM('pending','confirmed','processing','shipping','delivered','cancelled') NOT NULL");
    }
};
