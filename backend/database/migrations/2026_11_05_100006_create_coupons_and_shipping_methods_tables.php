<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create coupons and shipping_methods tables.
     */
    public function up(): void
    {
        // 1. Coupons
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique('uq_coupons_code');
                $table->enum('discount_type', ['fixed', 'percent']);
                $table->decimal('discount_value', 14, 2);
                $table->decimal('min_order_amount', 14, 2)->default(0.00);
                $table->decimal('max_discount', 14, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('reserved_count')->default(0);
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'start_at', 'end_at'], 'idx_coupons_active_window');
            });
        }

        // 2. Shipping Methods
        if (! Schema::hasTable('shipping_methods')) {
            Schema::create('shipping_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 50)->unique('uq_shipping_methods_code');
                $table->text('description')->nullable();
                $table->decimal('fee', 14, 2)->default(0.00);
                $table->string('estimated_days', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('coupons');
    }
};
