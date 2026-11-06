<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create payments and payment_webhook_events tables.
     */
    public function up(): void
    {
        // 1. Payments
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders');
                $table->string('transaction_code', 100)->unique('uq_payments_transaction_code');
                $table->string('idempotency_key', 120)->unique('uq_payments_idempotency');
                $table->enum('method', ['cod', 'vnpay', 'momo']);
                $table->decimal('amount', 14, 2);
                $table->char('currency_code', 3)->default('VND');
                $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'refunded'])->default('pending');
                $table->string('gateway_transaction_id', 100)->nullable();
                $table->unsignedBigInteger('settled_order_id')
                    ->storedAs("CASE WHEN status IN ('success', 'refunded') THEN order_id ELSE NULL END")
                    ->nullable();
                $table->json('response_data')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique(['method', 'gateway_transaction_id'], 'uq_payments_method_gateway_tx');
                $table->unique('settled_order_id', 'uq_payments_one_settled_attempt_per_order');
                $table->index(['order_id', 'status'], 'idx_payments_order_status');
            });
        }

        // 2. Payment Webhook Events
        if (! Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->nullable()->constrained('payments');
                $table->enum('method', ['vnpay', 'momo']);
                $table->string('event_key', 150);
                $table->string('provider_reference', 120)->nullable();
                $table->boolean('signature_verified')->default(false);
                $table->enum('processing_status', ['received', 'verified', 'processed', 'ignored', 'failed'])->default('received');
                $table->json('event_payload')->nullable();
                $table->string('error_message', 500)->nullable();
                $table->timestamp('received_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();

                $table->unique(['method', 'event_key'], 'uq_payment_webhook_method_event');
                $table->index(['payment_id', 'processing_status'], 'idx_payment_webhook_payment_status');
                $table->index('received_at', 'idx_payment_webhook_received');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};
