<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'refund_transaction_id')) {
                $table->string('refund_transaction_id', 100)->nullable();
            }
            if (! Schema::hasColumn('payments', 'refund_note')) {
                $table->text('refund_note')->nullable();
            }
            if (! Schema::hasColumn('payments', 'refund_confirmed_by')) {
                $table->foreignId('refund_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(['method', 'refund_transaction_id'], 'uq_payments_method_refund_tx');
        });

        // A refunded duplicate capture is not a second settled sale. Keep the
        // unique guard on successful payments while allowing its refund record.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments DROP INDEX uq_payments_one_settled_attempt_per_order');
            DB::statement('ALTER TABLE payments DROP COLUMN settled_order_id');
            DB::statement("ALTER TABLE payments ADD settled_order_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'success' THEN order_id ELSE NULL END) STORED NULL");
            DB::statement('ALTER TABLE payments ADD UNIQUE KEY uq_payments_one_settled_attempt_per_order (settled_order_id)');
        } else {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('uq_payments_one_settled_attempt_per_order');
                $table->dropColumn('settled_order_id');
            });
            Schema::table('payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('settled_order_id')
                    ->storedAs("CASE WHEN status = 'success' THEN order_id ELSE NULL END")
                    ->nullable();
                $table->unique('settled_order_id', 'uq_payments_one_settled_attempt_per_order');
            });
        }
    }

    public function down(): void
    {
        $duplicateSettlements = DB::table('payments')
            ->select('order_id')
            ->whereIn('status', ['success', 'refunded'])
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateSettlements) {
            throw new RuntimeException('Cannot restore the old settled-payment constraint while an order has multiple successful/refunded payment attempts.');
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments DROP INDEX uq_payments_one_settled_attempt_per_order');
            DB::statement('ALTER TABLE payments DROP COLUMN settled_order_id');
            DB::statement("ALTER TABLE payments ADD settled_order_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('success', 'refunded') THEN order_id ELSE NULL END) STORED NULL");
            DB::statement('ALTER TABLE payments ADD UNIQUE KEY uq_payments_one_settled_attempt_per_order (settled_order_id)');
        } else {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('uq_payments_one_settled_attempt_per_order');
                $table->dropColumn('settled_order_id');
            });
            Schema::table('payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('settled_order_id')
                    ->storedAs("CASE WHEN status IN ('success', 'refunded') THEN order_id ELSE NULL END")
                    ->nullable();
                $table->unique('settled_order_id', 'uq_payments_one_settled_attempt_per_order');
            });
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('uq_payments_method_refund_tx');
            $table->dropForeign(['refund_confirmed_by']);
            $table->dropColumn(['refunded_at', 'refund_transaction_id', 'refund_note', 'refund_confirmed_by']);
        });
    }
};
