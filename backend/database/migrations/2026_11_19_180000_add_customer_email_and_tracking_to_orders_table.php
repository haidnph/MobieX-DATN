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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email', 150)->nullable()->after('currency_code');
                $table->index('customer_email', 'idx_orders_customer_email');
            }

            if (! Schema::hasColumn('orders', 'confirmation_email_sent_at')) {
                $table->timestamp('confirmation_email_sent_at')->nullable()->after('reservation_expires_at');
            }

            if (! Schema::hasColumn('orders', 'confirmation_email_status')) {
                $table->string('confirmation_email_status', 30)->nullable()->default('pending')->after('confirmation_email_sent_at');
                $table->index('confirmation_email_status', 'idx_orders_confirmation_email_status');
            }

            if (! Schema::hasColumn('orders', 'confirmation_email_error')) {
                $table->text('confirmation_email_error')->nullable()->after('confirmation_email_status');
            }
        });

        if (! Schema::hasTable('order_email_logs')) {
            Schema::create('order_email_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('recipient_email', 150);
                $table->string('email_type', 50)->default('order_confirmation');
                $table->string('subject', 255);
                $table->string('payment_status_snapshot', 30)->nullable();
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'email_type'], 'idx_order_email_logs_order_type');
                $table->index(['recipient_email', 'status'], 'idx_order_email_logs_recipient_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_email_logs');

        Schema::table('orders', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('orders', 'customer_email')) {
                $table->dropIndex('idx_orders_customer_email');
                $columnsToDrop[] = 'customer_email';
            }
            if (Schema::hasColumn('orders', 'confirmation_email_status')) {
                $table->dropIndex('idx_orders_confirmation_email_status');
                $columnsToDrop[] = 'confirmation_email_status';
            }
            if (Schema::hasColumn('orders', 'confirmation_email_sent_at')) {
                $columnsToDrop[] = 'confirmation_email_sent_at';
            }
            if (Schema::hasColumn('orders', 'confirmation_email_error')) {
                $columnsToDrop[] = 'confirmation_email_error';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
