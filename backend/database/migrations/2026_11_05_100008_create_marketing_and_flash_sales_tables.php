<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create banners, flash_sales, and flash_sale_items tables.
     */
    public function up(): void
    {
        // 1. Banners
        if (! Schema::hasTable('banners')) {
            Schema::create('banners', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->string('image_path', 255);
                $table->string('target_url', 255)->nullable();
                $table->string('position', 100)->default('home');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['position', 'is_active', 'start_at', 'end_at'], 'idx_banners_position_active_window');
            });
        }

        // 2. Flash Sales
        if (! Schema::hasTable('flash_sales')) {
            Schema::create('flash_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 255);
                $table->string('slug', 255)->unique('uq_flash_sales_slug');
                $table->text('description')->nullable();
                $table->timestamp('start_at');
                $table->timestamp('end_at');
                $table->enum('status', ['draft', 'scheduled', 'active', 'ended', 'cancelled'])->default('draft');
                $table->timestamps();

                $table->index(['status', 'start_at', 'end_at'], 'idx_flash_sales_status_window');
                $table->index('created_by_user_id', 'idx_flash_sales_creator');
            });
        }

        // 3. Flash Sale Items
        if (! Schema::hasTable('flash_sale_items')) {
            Schema::create('flash_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants');
                $table->decimal('sale_price', 14, 2);
                $table->unsignedInteger('quantity_limit')->nullable();
                $table->unsignedInteger('quantity_reserved')->default(0);
                $table->unsignedInteger('quantity_sold')->default(0);
                $table->timestamps();

                $table->unique(['flash_sale_id', 'product_variant_id'], 'uq_flash_sale_items_sale_variant');
                $table->index('product_variant_id', 'idx_flash_sale_items_variant');
                $table->index(['flash_sale_id', 'quantity_reserved', 'quantity_sold'], 'idx_flash_sale_items_capacity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('banners');
    }
};
