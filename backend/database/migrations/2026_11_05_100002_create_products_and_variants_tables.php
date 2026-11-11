<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create products, product_variants, and product_images tables.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Products
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands');
                $table->foreignId('category_id')->constrained('categories');
                $table->enum('product_type', ['phone', 'accessory']);
                $table->string('name', 255);
                $table->string('slug', 255)->unique('uq_products_slug');
                $table->longText('description')->nullable();
                $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
                $table->timestamps();

                $table->index(['category_id', 'status', 'created_at'], 'idx_products_category_status_created');
                $table->index(['brand_id', 'status'], 'idx_products_brand_status');
                $table->index('name', 'idx_products_name');

                if ($isMysql) {
                    $table->fullText(['name', 'description'], 'ft_products_search');
                }
            });
        }

        // 2. Product Variants
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products');
                $table->string('sku', 100)->unique('uq_product_variants_sku');
                $table->string('option_signature', 500)->default('');
                $table->decimal('price', 14, 2);
                $table->decimal('compare_price', 14, 2)->nullable();
                $table->decimal('cost_price', 14, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['product_id', 'option_signature'], 'uq_product_variants_product_signature');
                $table->unique(['id', 'product_id'], 'uq_product_variants_id_product');
                $table->index(['product_id', 'is_active'], 'idx_product_variants_product_active');
                $table->index('price', 'idx_product_variants_price');
            });
        }

        // 3. Product Images
        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->foreignId('attribute_value_id')->nullable()->constrained('attribute_values');
                $table->string('image_path', 255);
                $table->string('alt_text', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);

                $table->string('primary_scope_type', 20)->storedAs(
                    "CASE WHEN is_primary = 1 THEN CASE WHEN product_variant_id IS NOT NULL THEN 'variant' WHEN attribute_value_id IS NOT NULL THEN 'attribute_value' ELSE 'product' END ELSE NULL END"
                )->nullable();

                $table->unsignedBigInteger('primary_scope_id')->storedAs(
                    'CASE WHEN is_primary = 1 THEN COALESCE(product_variant_id, attribute_value_id, 0) ELSE NULL END'
                )->nullable();

                $table->timestamps();

                $table->unique(['product_id', 'primary_scope_type', 'primary_scope_id'], 'uq_product_images_primary_scope');
                $table->index(['product_id', 'attribute_value_id', 'product_variant_id', 'sort_order'], 'idx_product_images_product_scope_sort');
                $table->index('attribute_value_id', 'idx_product_images_attribute_value');
                $table->index(['product_variant_id', 'product_id'], 'idx_product_images_variant_product_fk');

                if ($isMysql) {
                    $table->foreign(['product_variant_id', 'product_id'], 'fk_product_images_variant_product')
                        ->references(['id', 'product_id'])
                        ->on('product_variants');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
