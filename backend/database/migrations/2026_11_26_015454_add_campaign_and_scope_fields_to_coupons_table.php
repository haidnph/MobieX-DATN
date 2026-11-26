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
            $table->string('name')->nullable()->after('code');
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('issued_quantity')->nullable()->after('usage_limit');
            $table->boolean('applies_to_sale_items')->default(false)->after('is_active');
            $table->boolean('is_stackable')->default(false)->after('applies_to_sale_items');
        });

        Schema::create('coupon_categories', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_brands', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'brand_id']);
        });

        if (Schema::hasTable('homepage_sections') && DB::table('homepage_sections')->exists()) {
            $sortOrder = ((int) DB::table('homepage_sections')->max('sort_order')) + 1;
            DB::table('homepage_sections')->insertOrIgnore([
                'section_key' => 'voucher_hunt',
                'title' => 'Săn Voucher Ưu Đãi',
                'subtitle' => 'Lưu mã giảm giá và dùng khi thanh toán',
                'section_type' => 'vouchers',
                'layout_type' => 'grid',
                'items_limit' => 6,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'settings' => json_encode(['coupon_ids' => []], JSON_UNESCAPED_UNICODE),
                'published_settings' => json_encode([
                    'title' => 'Săn Voucher Ưu Đãi',
                    'subtitle' => 'Lưu mã giảm giá và dùng khi thanh toán',
                    'layout_type' => 'grid',
                    'items_limit' => 6,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'settings' => ['coupon_ids' => []],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_brands');
        Schema::dropIfExists('coupon_categories');
        if (Schema::hasTable('homepage_sections')) {
            DB::table('homepage_sections')->where('section_key', 'voucher_hunt')->delete();
        }
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['name', 'description', 'issued_quantity', 'applies_to_sale_items', 'is_stackable']);
        });
    }
};
