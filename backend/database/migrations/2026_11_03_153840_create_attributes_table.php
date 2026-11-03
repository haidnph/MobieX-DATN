<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 100);
            $table->string('code', 100);
            $table->string('type', 50)->default('select');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique('code', 'uq_attributes_code');

            $table->index(
                ['status', 'sort_order', 'id'],
                'idx_attributes_status_sort_id',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
