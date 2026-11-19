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
        if (Schema::hasTable('banners')) {
            Schema::table('banners', function (Blueprint $table) {
                if (! Schema::hasColumn('banners', 'storage_provider')) {
                    $table->string('storage_provider', 50)->default('local')->nullable()->after('image_path');
                }
                if (! Schema::hasColumn('banners', 'public_id')) {
                    $table->string('public_id', 255)->nullable()->after('storage_provider');
                }
                if (! Schema::hasColumn('banners', 'mobile_public_id')) {
                    $table->string('mobile_public_id', 255)->nullable()->after('mobile_image_path');
                }
            });
        }

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                if (! Schema::hasColumn('product_images', 'storage_provider')) {
                    $table->string('storage_provider', 50)->default('local')->nullable()->after('image_path');
                }
                if (! Schema::hasColumn('product_images', 'public_id')) {
                    $table->string('public_id', 255)->nullable()->after('storage_provider');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('banners')) {
            Schema::table('banners', function (Blueprint $table) {
                $dropColumns = [];
                if (Schema::hasColumn('banners', 'mobile_public_id')) {
                    $dropColumns[] = 'mobile_public_id';
                }
                if (Schema::hasColumn('banners', 'public_id')) {
                    $dropColumns[] = 'public_id';
                }
                if (Schema::hasColumn('banners', 'storage_provider')) {
                    $dropColumns[] = 'storage_provider';
                }
                if (! empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $dropColumns = [];
                if (Schema::hasColumn('product_images', 'public_id')) {
                    $dropColumns[] = 'public_id';
                }
                if (Schema::hasColumn('product_images', 'storage_provider')) {
                    $dropColumns[] = 'storage_provider';
                }
                if (! empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }
};
