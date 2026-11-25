
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_request_id')
                ->constrained('return_requests')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->string('inspection_condition', 50)->nullable();
            $table->text('inspection_note')->nullable();

            $table->timestamps();

            $table->unique(
                ['return_request_id', 'order_item_id'],
                'uq_return_request_items_order_item'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
