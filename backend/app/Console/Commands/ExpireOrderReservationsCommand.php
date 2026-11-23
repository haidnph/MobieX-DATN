<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\FlashSaleOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireOrderReservationsCommand extends Command
{
    protected $signature = 'orders:expire-reservations';

    protected $description = 'Cancel expired unpaid online orders and release inventory and coupon reservations.';

    public function handle(): int
    {
        $expiredOrderIds = Order::query()
            ->where('status', 'pending')
            ->where('payment_method', '!=', 'cod')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $expiredCount = 0;
        $inconsistentCount = 0;

        foreach ($expiredOrderIds as $orderId) {
            $result = DB::transaction(function () use ($orderId): string|false {
                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->with('items')
                    ->first();

                if (
                    ! $order
                    || $order->status !== 'pending'
                    || $order->payment_status === 'paid'
                    || $order->payment_method === 'cod'
                    || ! $order->reservation_expires_at
                    || $order->reservation_expires_at->isFuture()
                ) {
                    return false;
                }

                $lockedInventories = [];
                foreach ($order->items as $item) {
                    $inventory = Inventory::query()
                        ->where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory || (int) $inventory->reserved_quantity < (int) $item->quantity) {
                        Log::critical('Expired order reservation does not match inventory.', [
                            'order_id' => $order->id,
                            'product_variant_id' => $item->product_variant_id,
                            'required_quantity' => (int) $item->quantity,
                            'reserved_quantity' => $inventory?->reserved_quantity,
                        ]);

                        return 'inconsistent';
                    }

                    $lockedInventories[$item->product_variant_id] = $inventory;
                }

                foreach ($order->items as $item) {
                    $inventory = $lockedInventories[$item->product_variant_id];
                    $quantity = (int) $item->quantity;
                    $inventory->reserved_quantity -= $quantity;
                    $inventory->save();

                    InventoryTransaction::firstOrCreate(
                        ['idempotency_key' => "order_{$order->id}_var_{$item->product_variant_id}_release_expired"],
                        [
                            'product_variant_id' => $item->product_variant_id,
                            'user_id' => null,
                            'type' => 'release',
                            'quantity_delta' => 0,
                            'reserved_delta' => -$quantity,
                            'unit_cost' => $item->cost_price_snapshot,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'note' => 'Giải phóng giữ chỗ do đơn hàng hết hạn '.$order->order_code,
                            'created_at' => now(),
                        ],
                    );
                }

                app(FlashSaleOrderService::class)->releaseOrderReservations($order);

                if ($order->coupon_id) {
                    $coupon = Coupon::query()->whereKey($order->coupon_id)->lockForUpdate()->first();
                    if ($coupon) {
                        $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                        $coupon->save();
                    }

                    CouponRedemption::query()
                        ->where('order_id', $order->id)
                        ->where('status', 'reserved')
                        ->update(['status' => 'released']);
                }

                $fromStatus = $order->status;
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Thời hạn thanh toán và giữ hàng đã hết.',
                ]);
                Payment::query()
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => null,
                    'from_status' => $fromStatus,
                    'status' => 'cancelled',
                    'note' => 'Đơn hàng tự hủy do hết thời hạn thanh toán; tồn kho và coupon đã được giải phóng.',
                    'created_at' => now(),
                ]);

                return 'expired';
            });

            if ($result === 'expired') {
                $expiredCount++;
            } elseif ($result === 'inconsistent') {
                $inconsistentCount++;
                $this->error("Skipped expired order {$orderId}: reserved inventory does not match its order items.");
            }
        }

        $this->info("Expired {$expiredCount} order reservation(s).");

        if ($inconsistentCount > 0) {
            $this->error("Found {$inconsistentCount} order(s) with inconsistent inventory reservations.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
