
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the hierarchical categories table.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();

            // NULL means this is a root category.
            $table->foreignId('parent_id')->nullable();

            $table->string('name', 255);

            // Every category must have a unique slug.
            $table->string('slug', 255)->unique();

            $table->text('description')->nullable();

            $table->string('image', 255)->nullable();

            $table->integer('sort_order')->default(0);

            // 1 = visible, 0 = hidden.
            // Keep the existing ERD/model contract.
            $table->boolean('status')->default(true);

            $table->timestamps();

            // Index for parent-child queries.
            $table->index('parent_id', 'idx_categories_parent');

            // Self-referencing foreign key.
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Drop the categories table.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
