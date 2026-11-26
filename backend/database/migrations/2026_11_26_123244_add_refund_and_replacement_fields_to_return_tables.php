<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('return_requests', 'return_type')) {
                $table->string('return_type', 30)->default('refund')->after('reason');
            }
            if (! Schema::hasColumn('return_requests', 'refund_amount')) {
                $table->decimal('refund_amount', 14, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('return_requests', 'refund_method')) {
                $table->string('refund_method', 50)->nullable()->after('refund_amount');
            }
            if (! Schema::hasColumn('return_requests', 'refund_status')) {
                $table->string('refund_status', 30)->nullable()->after('refund_method');
            }
            if (! Schema::hasColumn('return_requests', 'refund_transaction_id')) {
                $table->string('refund_transaction_id', 100)->nullable()->after('refund_status');
            }
            if (! Schema::hasColumn('return_requests', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_transaction_id');
            }
            if (! Schema::hasColumn('return_requests', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('refunded_at');
            }
            if (! Schema::hasColumn('return_requests', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('return_requests', 'bank_account_name')) {
                $table->string('bank_account_name', 150)->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('return_requests', 'replacement_carrier')) {
                $table->string('replacement_carrier', 100)->nullable()->after('return_tracking_code');
            }
            if (! Schema::hasColumn('return_requests', 'replacement_tracking_code')) {
                $table->string('replacement_tracking_code', 100)->nullable()->after('replacement_carrier');
            }
        });

        Schema::table('return_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('return_request_items', 'is_restocked')) {
                $table->boolean('is_restocked')->default(false)->after('inspection_note');
            }
            if (! Schema::hasColumn('return_request_items', 'restocked_at')) {
                $table->timestamp('restocked_at')->nullable()->after('is_restocked');
            }
            if (! Schema::hasColumn('return_request_items', 'refund_amount')) {
                $table->decimal('refund_amount', 14, 2)->nullable()->after('restocked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $cols = [
                'return_type', 'refund_amount', 'refund_method', 'refund_status',
                'refund_transaction_id', 'refunded_at', 'bank_name',
                'bank_account_number', 'bank_account_name',
                'replacement_carrier', 'replacement_tracking_code',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('return_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('return_request_items', function (Blueprint $table) {
            $cols = ['is_restocked', 'restocked_at', 'refund_amount'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('return_request_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
