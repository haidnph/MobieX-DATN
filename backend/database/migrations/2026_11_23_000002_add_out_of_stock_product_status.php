<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('draft', 'active', 'inactive', 'out_of_stock') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (DB::table('products')->where('status', 'out_of_stock')->exists()) {
                throw new RuntimeException(
                    'Cannot remove out_of_stock while products still use that status.'
                );
            }

            DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'draft'");
        }
    }
};
