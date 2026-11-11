<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create product specifications, compatibilities, and attribute mappings tables.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Product Specification Definitions
        if (! Schema::hasTable('product_specification_definitions')) {
            Schema::create('product_specification_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique('uq_product_spec_definitions_code');
                $table->string('name', 150);
                $table->enum('value_type', ['text', 'number', 'boolean'])->default('text');
                $table->string('unit', 30)->nullable();
                $table->enum('applies_to', ['all', 'phone', 'accessory'])->default('all');
                $table->boolean('is_comparable')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'idx_product_spec_definitions_active_sort');
                $table->index(['applies_to', 'is_active', 'sort_order'], 'idx_product_spec_definitions_scope');
            });
        }

        // 2. Product Specifications
        if (! Schema::hasTable('product_specifications')) {
            Schema::create('product_specifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('specification_definition_id')->constrained('product_specification_definitions');
                $table->string('value_text', 500)->nullable();
                $table->decimal('numeric_value', 14, 4)->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'specification_definition_id'], 'uq_product_specification_product_definition');
                $table->index(['specification_definition_id', 'value_text'], 'idx_product_specifications_definition_value');
                $table->index(['specification_definition_id', 'numeric_value'], 'idx_product_specifications_definition_numeric');
            });
        }

        // 3. Product Compatibilities
        if (! Schema::hasTable('product_compatibilities')) {
            Schema::create('product_compatibilities', function (Blueprint $table) {
                $table->foreignId('accessory_product_id')->constrained('products');
                $table->foreignId('compatible_phone_product_id')->constrained('products');
                $table->string('note', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->primary(['accessory_product_id', 'compatible_phone_product_id']);
                $table->index('compatible_phone_product_id', 'idx_product_compatibilities_phone');
            });
        }

        // 4. Product Variant Attribute Values
        if (! Schema::hasTable('product_variant_attribute_values')) {
            Schema::create('product_variant_attribute_values', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained('attributes');
                $table->foreignId('attribute_value_id')->constrained('attribute_values');

                $table->unique(['product_variant_id', 'attribute_id'], 'uq_variant_attribute_once');
                $table->index(['attribute_id', 'attribute_value_id'], 'idx_pvav_attribute_value');
                $table->index(['attribute_value_id', 'attribute_id'], 'idx_pvav_value_attribute_fk');

                if ($isMysql) {
                    $table->foreign(['attribute_value_id', 'attribute_id'], 'fk_pvav_value_attribute')
                        ->references(['id', 'attribute_id'])
                        ->on('attribute_values');
                }
            });
        }

        // 5. Product Attribute Values
        if (! Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained('attributes');
                $table->foreignId('attribute_value_id')->constrained('attribute_values');
                $table->timestamps();

                $table->unique(['product_id', 'attribute_id'], 'uq_product_attribute_once');
                $table->index(['attribute_id', 'attribute_value_id'], 'idx_pav_attribute_value');
                $table->index(['attribute_value_id', 'attribute_id'], 'idx_pav_value_attribute_fk');

                if ($isMysql) {
                    $table->foreign(['attribute_value_id', 'attribute_id'], 'fk_pav_value_attribute')
                        ->references(['id', 'attribute_id'])
                        ->on('attribute_values');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_compatibilities');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('product_specification_definitions');
    }
};
