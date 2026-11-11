<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft-delete (deleted_at) column to key admin-managed tables.
 * Enables Trash / Recycle-Bin UX: soft-delete → restore → force-delete.
 *
 * Tables covered: products, product_variants, categories, brands, banners, coupons.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'products',
            'product_variants',
            'categories',
            'brands',
            'banners',
            'coupons',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'products',
            'product_variants',
            'categories',
            'brands',
            'banners',
            'coupons',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
