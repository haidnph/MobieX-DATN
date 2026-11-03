<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('attribute_id');
            $table->string('value', 255);
            $table->string('color_code', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();

            // A value must be unique within its owning attribute.
            $table->unique(
                ['attribute_id', 'value'],
                'uq_attribute_values_attribute_value',
            );

            // Supports active value lookup and ordering.
            $table->index(
                ['attribute_id', 'status', 'sort_order', 'id'],
                'idx_attribute_values_visibility_sort',
            );

            $table->foreign('attribute_id', 'fk_attribute_values_attribute')
                ->references('id')
                ->on('attributes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
