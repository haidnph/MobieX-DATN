<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create inventories and inventory_transactions tables.
     */
    public function up(): void
    {
        // 1. Inventories
        if (! Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_variant_id')->unique('uq_inventories_variant')->constrained('product_variants');
                $table->unsignedInteger('on_hand_quantity')->default(0);
                $table->unsignedInteger('reserved_quantity')->default(0);
                $table->unsignedInteger('available_quantity')->storedAs('on_hand_quantity - reserved_quantity');
                $table->unsignedInteger('min_stock')->default(5);
                $table->timestamps();

                $table->index(['available_quantity', 'product_variant_id'], 'idx_inventories_available');
                $table->index(['min_stock', 'available_quantity'], 'idx_inventories_low_stock');
            });
        }

        // 2. Inventory Transactions
        if (! Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_variant_id')->constrained('product_variants');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['import', 'dispatch', 'adjustment', 'reserve', 'release']);
                $table->integer('quantity_delta');
                $table->integer('reserved_delta')->default(0);
                $table->decimal('unit_cost', 14, 2)->nullable();
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('idempotency_key', 120)->unique('uq_inventory_transactions_idempotency');
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['product_variant_id', 'created_at'], 'idx_inventory_transactions_variant_created');
                $table->index(['reference_type', 'reference_id'], 'idx_inventory_transactions_reference');
                $table->index('user_id', 'idx_inventory_transactions_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
    }
};
