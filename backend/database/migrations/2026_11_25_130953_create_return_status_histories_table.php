<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_id')
                ->constrained('returns')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('from_status', 30)->nullable();

            $table->string('status', 30);

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['return_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_histories');
    }
};