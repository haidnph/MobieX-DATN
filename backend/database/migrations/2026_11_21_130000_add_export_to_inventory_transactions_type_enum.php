<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('import', 'export', 'dispatch', 'adjustment', 'reserve', 'release') NOT NULL");
        }
    }

    public function down(): void
    {
        // Keep the additive enum value on rollback so existing manual export
        // transactions remain valid and no inventory history is rewritten.
    }
};
