<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create orders and coupon_redemptions tables.
     */
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Orders
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code', 50)->unique('uq_orders_order_code');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->foreignId('coupon_id')->nullable()->constrained('coupons');
                $table->string('coupon_code', 50)->nullable();
                $table->foreignId('shipping_method_id')->constrained('shipping_methods');
                $table->string('shipping_method_code', 50);
                $table->string('shipping_method_name', 100);
                $table->decimal('subtotal', 14, 2);
                $table->decimal('discount_amount', 14, 2)->default(0.00);
                $table->decimal('shipping_fee', 14, 2)->default(0.00);
                $table->decimal('total_amount', 14, 2);
                $table->char('currency_code', 3)->default('VND');
                $table->string('shipping_name', 255);
                $table->string('shipping_phone', 20);
                $table->string('shipping_province_code', 20);
                $table->string('shipping_province_name', 100);
                $table->string('shipping_ward_code', 20);
                $table->string('shipping_ward_name', 100);
                $table->string('shipping_address_line', 255);
                $table->enum('payment_method', ['cod', 'vnpay', 'momo', 'zalopay']);
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
                $table->enum('status', ['pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled'])->default('pending');
                $table->text('customer_note')->nullable();
                $table->string('cancel_reason', 255)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('reservation_expires_at')->nullable();
                $table->timestamps();

                $table->unique(['id', 'coupon_id'], 'uq_orders_id_coupon');
                $table->index(['user_id', 'created_at'], 'idx_orders_user_created');
                $table->index(['status', 'created_at'], 'idx_orders_status_created');
                $table->index(['payment_status', 'created_at'], 'idx_orders_payment_status_created');
                $table->index('coupon_id', 'idx_orders_coupon');
                $table->index('shipping_method_id', 'idx_orders_shipping_method');
                $table->index(['status', 'payment_status', 'reservation_expires_at'], 'idx_orders_reservation_expiry');
                $table->index(['status', 'delivered_at'], 'idx_orders_status_delivered');
            });
        }

        // 2. Coupon Redemptions
        if (! Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) use ($isMysql) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons');
                $table->foreignId('order_id')->unique('uq_coupon_redemptions_order')->constrained('orders');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->decimal('discount_amount', 14, 2);
                $table->enum('status', ['reserved', 'consumed', 'released'])->default('reserved');
                $table->timestamps();

                $table->index(['order_id', 'coupon_id'], 'idx_coupon_redemptions_order_coupon');
                $table->index(['coupon_id', 'status'], 'idx_coupon_redemptions_coupon_status');
                $table->index('user_id', 'idx_coupon_redemptions_user');

                if ($isMysql) {
                    $table->foreign(['order_id', 'coupon_id'], 'fk_coupon_redemptions_order_coupon')
                        ->references(['id', 'coupon_id'])
                        ->on('orders');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('orders');
    }
};
