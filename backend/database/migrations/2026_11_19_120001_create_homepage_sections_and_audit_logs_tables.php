<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('homepage_sections')) {
            Schema::create('homepage_sections', function (Blueprint $table) {
                $table->id();
                $table->string('section_key', 50)->unique();
                $table->string('title', 255)->nullable();
                $table->text('subtitle')->nullable();
                $table->string('section_type', 50); // banner_slider, categories, flash_sale, products, brands, banner_promo, trust_services, recently_viewed
                $table->string('layout_type', 50)->default('grid'); // grid, carousel, split, standard
                $table->unsignedInteger('items_limit')->default(8);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->json('published_settings')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('homepage_audit_logs')) {
            Schema::create('homepage_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100);
                $table->string('entity_type', 100);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description')->nullable();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['entity_type', 'entity_id']);
                $table->index('action');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_audit_logs');
        Schema::dropIfExists('homepage_sections');
    }
};
