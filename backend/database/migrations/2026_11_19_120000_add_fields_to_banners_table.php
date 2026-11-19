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
        Schema::table('banners', function (Blueprint $table) {
            if (! Schema::hasColumn('banners', 'name')) {
                $table->string('name', 255)->nullable()->after('title');
            }
            if (! Schema::hasColumn('banners', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('banners', 'mobile_image_path')) {
                $table->string('mobile_image_path', 255)->nullable()->after('image_path');
            }
            if (! Schema::hasColumn('banners', 'alt_text')) {
                $table->string('alt_text', 255)->nullable()->after('mobile_image_path');
            }
            if (! Schema::hasColumn('banners', 'cta_label')) {
                $table->string('cta_label', 100)->nullable()->after('target_url');
            }
            if (! Schema::hasColumn('banners', 'status')) {
                $table->string('status', 50)->default('published')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $columns = [
                'name',
                'description',
                'mobile_image_path',
                'alt_text',
                'cta_label',
                'status',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('banners', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
