<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo', 'zalopay') NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY COLUMN method ENUM('cod', 'vnpay', 'momo', 'zalopay') NOT NULL");
            DB::statement("ALTER TABLE payment_webhook_events MODIFY COLUMN method ENUM('vnpay', 'momo', 'zalopay') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo') NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY COLUMN method ENUM('cod', 'vnpay', 'momo') NOT NULL");
            DB::statement("ALTER TABLE payment_webhook_events MODIFY COLUMN method ENUM('vnpay', 'momo') NOT NULL");
        }
    }
};
