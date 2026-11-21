<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('import', 'export', 'dispatch', 'adjustment', 'reserve', 'release') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('import', 'dispatch', 'adjustment', 'reserve', 'release') NOT NULL");
    }
};
