<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 20);
            $table->string('request_id', 100)->unique();
            $table->string('provider_order_id', 100)->nullable();
            $table->string('provider_refund_id', 100)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->string('note', 1000);
            $table->json('provider_response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // MobieX currently supports one full refund per captured payment.
            // This also serializes simultaneous admin requests at the database level.
            $table->unique('payment_id', 'uq_payment_refunds_payment');
            $table->index(['status', 'updated_at'], 'idx_payment_refunds_status_updated');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_refunds') && DB::table('payment_refunds')->exists()) {
            throw new RuntimeException('Cannot drop payment refund history while reconciliation records exist.');
        }

        Schema::dropIfExists('payment_refunds');
    }
};
