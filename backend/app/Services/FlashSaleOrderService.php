<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlashSaleOrderService
{
    public function releaseOrderReservations(Order $order): void
    {
        $this->transitionReservations($order, 'released');
    }

    public function consumeOrderReservations(Order $order): void
    {
        $this->transitionReservations($order, 'consumed');
    }

    private function transitionReservations(Order $order, string $targetStatus): void
    {
        DB::transaction(function () use ($order, $targetStatus): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->flash_sale_reservation_status !== null
                && $lockedOrder->flash_sale_reservation_status !== 'reserved') {
                return;
            }

            $items = $lockedOrder->items()
                ->whereNotNull('flash_sale_id')
                ->orderBy('flash_sale_id')
                ->orderBy('product_variant_id')
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                $lockedOrder->flash_sale_reservation_status = null;
                $lockedOrder->save();

                return;
            }

            $quantities = $items->groupBy(fn ($item): string => $item->flash_sale_id.':'.$item->product_variant_id);
            $saleItems = [];

            foreach ($quantities as $key => $orderItems) {
                [$saleId, $variantId] = array_map('intval', explode(':', $key));
                $saleItem = FlashSaleItem::query()
                    ->where('flash_sale_id', $saleId)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();
                $quantity = (int) $orderItems->sum('quantity');

                if (! $saleItem || (int) $saleItem->quantity_reserved < $quantity) {
                    throw ValidationException::withMessages([
                        'flash_sale' => 'Số lượng giữ chỗ Flash Sale không khớp với đơn hàng.',
                    ]);
                }

                $saleItems[] = [$saleItem, $quantity];
            }

            foreach ($saleItems as [$saleItem, $quantity]) {
                $saleItem->quantity_reserved -= $quantity;
                if ($targetStatus === 'consumed') {
                    $saleItem->quantity_sold += $quantity;
                }
                $saleItem->save();
            }

            $lockedOrder->flash_sale_reservation_status = $targetStatus;
            $lockedOrder->save();
        });
    }
}
