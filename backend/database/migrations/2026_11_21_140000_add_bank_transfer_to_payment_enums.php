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
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo', 'zalopay', 'bank_transfer') NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY COLUMN method ENUM('cod', 'vnpay', 'momo', 'zalopay', 'bank_transfer') NOT NULL");
            DB::statement("ALTER TABLE payment_webhook_events MODIFY COLUMN method ENUM('vnpay', 'momo', 'zalopay', 'bank_transfer') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $hasBankTransferData = DB::table('orders')->where('payment_method', 'bank_transfer')->exists()
                || DB::table('payments')->where('method', 'bank_transfer')->exists()
                || DB::table('payment_webhook_events')->where('method', 'bank_transfer')->exists();

            if ($hasBankTransferData) {
                throw new RuntimeException(
                    'Cannot roll back bank_transfer support while orders, payments, or webhook events still use it.'
                );
            }

            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo', 'zalopay') NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY COLUMN method ENUM('cod', 'vnpay', 'momo', 'zalopay') NOT NULL");
            DB::statement("ALTER TABLE payment_webhook_events MODIFY COLUMN method ENUM('vnpay', 'momo', 'zalopay') NOT NULL");
        }
    }
};
