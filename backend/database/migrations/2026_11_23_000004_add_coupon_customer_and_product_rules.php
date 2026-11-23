<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupons', 'max_uses_per_customer')) {
                $table->unsignedInteger('max_uses_per_customer')->nullable();
            }
        });

        if (! Schema::hasTable('coupon_products')) {
            Schema::create('coupon_products', function (Blueprint $table): void {
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->primary(['coupon_id', 'product_id']);
                $table->index('product_id', 'idx_coupon_products_product');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('coupons', 'max_uses_per_customer')
            && DB::table('coupons')->whereNotNull('max_uses_per_customer')->exists()
        ) {
            throw new RuntimeException('Cannot roll back per-customer coupon limits while coupons use them.');
        }

        if (Schema::hasTable('coupon_products') && DB::table('coupon_products')->exists()) {
            throw new RuntimeException('Cannot roll back product-specific coupon rules while they are configured.');
        }

        Schema::dropIfExists('coupon_products');
        Schema::table('coupons', function (Blueprint $table): void {
            if (Schema::hasColumn('coupons', 'max_uses_per_customer')) {
                $table->dropColumn('max_uses_per_customer');
            }
        });
    }
};
