<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('flash_sale_reservation_status', 16)->nullable()->index();
        });

        DB::table('orders')
            ->whereIn('id', DB::table('order_items')->whereNotNull('flash_sale_id')->select('order_id'))
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $reservationStatus = $order->status === 'cancelled'
                        ? 'released'
                        : ($order->payment_status === 'paid' ? 'consumed' : 'reserved');

                    DB::table('orders')->where('id', $order->id)->update([
                        'flash_sale_reservation_status' => $reservationStatus,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['flash_sale_reservation_status']);
            $table->dropColumn('flash_sale_reservation_status');
        });
    }
};
