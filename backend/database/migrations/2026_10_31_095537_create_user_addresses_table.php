<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the user addresses table.
     */
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id');

            $table->string('label', 50)->nullable();
            $table->string('full_name', 255);
            $table->string('phone', 20);
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('ward', 100)->nullable();
            $table->string('address_line', 255);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Create the user_id index before adding the foreign key.
            $table->index('user_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Drop the user addresses table.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
