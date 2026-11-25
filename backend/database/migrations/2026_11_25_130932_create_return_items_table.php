<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_id')
                ->constrained('returns')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->unsignedInteger('restock_quantity')->default(0);

            $table->string('condition', 30)->nullable();

            $table->text('inspection_note')->nullable();

            $table->timestamps();

            $table->unique(
                ['return_id', 'order_item_id'],
                'return_order_item_unique'
            );

            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};