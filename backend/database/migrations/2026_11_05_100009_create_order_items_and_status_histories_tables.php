<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create order_items and order_status_histories tables.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Order Items
        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders');
                $table->foreignId('product_id')->constrained('products');
                $table->unsignedBigInteger('product_variant_id');
                $table->unsignedBigInteger('flash_sale_id')->nullable();
                $table->string('product_name', 255);
                $table->string('variant_name', 255)->nullable();
                $table->string('sku', 100);
                $table->string('image_path', 255)->nullable();
                $table->decimal('price', 14, 2);
                $table->decimal('cost_price_snapshot', 14, 2)->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('line_total', 24, 2)->storedAs('price * quantity');
                $table->timestamps();

                $table->unique(['order_id', 'product_variant_id'], 'uq_order_items_order_variant');
                $table->unique(['id', 'product_id'], 'uq_order_items_id_product');
                $table->index('product_variant_id', 'idx_order_items_variant');
                $table->index(['product_variant_id', 'product_id'], 'idx_order_items_variant_product_fk');
                $table->index(['product_id', 'created_at'], 'idx_order_items_product_created');
                $table->index(['flash_sale_id', 'product_variant_id'], 'idx_order_items_flash_sale_variant');

                if ($isMysql) {
                    $table->foreign(['product_variant_id', 'product_id'], 'fk_order_items_variant_product')
                        ->references(['id', 'product_id'])
                        ->on('product_variants');
                    $table->foreign(['flash_sale_id', 'product_variant_id'], 'fk_order_items_flash_sale_variant')
                        ->references(['flash_sale_id', 'product_variant_id'])
                        ->on('flash_sale_items');
                }
            });
        }

        // 2. Order Status Histories
        if (! Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('from_status', ['pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled'])->nullable();
                $table->enum('status', ['pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled']);
                $table->string('note', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['order_id', 'created_at', 'id'], 'idx_order_status_histories_order_created');
                $table->index('user_id', 'idx_order_status_histories_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
    }
};
