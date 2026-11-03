<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_category', function (Blueprint $table): void {
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('category_id');

            // Prevent duplicate attribute-category assignments.
            $table->primary(
                ['attribute_id', 'category_id'],
                'pk_attribute_category',
            );

            // Supports reverse lookup by category.
            $table->index(
                'category_id',
                'idx_attribute_category_category_id',
            );

            $table->foreign('attribute_id', 'fk_attribute_category_attribute')
                ->references('id')
                ->on('attributes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('category_id', 'fk_attribute_category_category')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_category');
    }
};
