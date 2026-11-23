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
            if (! Schema::hasColumn('payments', 'refund_required')) {
                $table->boolean('refund_required')->default(false);
            }
            if (! Schema::hasColumn('payments', 'gateway_succeeded_at')) {
                $table->timestamp('gateway_succeeded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'refund_required') && DB::table('payments')->where('refund_required', true)->exists()) {
            throw new RuntimeException('Cannot remove refund reconciliation fields while unresolved duplicate captures exist.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'gateway_succeeded_at')) {
                $table->dropColumn('gateway_succeeded_at');
            }
            if (Schema::hasColumn('payments', 'refund_required')) {
                $table->dropColumn('refund_required');
            }
        });
    }
};
