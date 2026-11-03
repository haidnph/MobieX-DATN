<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the brands table used by the Catalog and Product modules.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 255);

            // Every brand must have a globally unique slug.
            $table->string('slug', 255)->unique();

            $table->string('logo', 255)->nullable();

            $table->text('description')->nullable();

            // true = visible, false = hidden.
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Drop the brands table.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
