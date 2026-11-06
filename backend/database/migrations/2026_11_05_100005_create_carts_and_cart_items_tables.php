<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create carts and cart_items tables.
     */
    public function up(): void
    {
        // 1. Carts
        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('session_id', 128)->nullable();
                $table->enum('status', ['active', 'converted', 'abandoned'])->default('active');
                $table->unsignedBigInteger('active_user_id')
                    ->storedAs("CASE WHEN status = 'active' THEN user_id ELSE NULL END")
                    ->nullable();
                $table->string('active_session_id', 128)
                    ->storedAs("CASE WHEN status = 'active' THEN session_id ELSE NULL END")
                    ->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->unique('active_user_id', 'uq_carts_active_user');
                $table->unique('active_session_id', 'uq_carts_active_session');
                $table->index(['user_id', 'created_at'], 'idx_carts_user_history');
                $table->index(['session_id', 'created_at'], 'idx_carts_session_history');
                $table->index(['status', 'last_activity_at'], 'idx_carts_status_activity');
            });
        }

        // 2. Cart Items
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants');
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();

                $table->unique(['cart_id', 'product_variant_id'], 'uq_cart_items_cart_variant');
                $table->index('product_variant_id', 'idx_cart_items_variant');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
