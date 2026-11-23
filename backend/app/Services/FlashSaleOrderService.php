<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class FlashSaleOrderService
{
    public function releaseOrderReservations(Order $order): void
    {
        $items = $order->items()
            ->whereNotNull('flash_sale_id')
            ->orderBy('flash_sale_id')
            ->orderBy('product_variant_id')
            ->get();

        $locked = [];
        foreach ($items as $orderItem) {
            $key = $orderItem->flash_sale_id.':'.$orderItem->product_variant_id;
            if (isset($locked[$key])) {
                continue;
            }

            $saleItem = FlashSaleItem::query()
                ->where('flash_sale_id', $orderItem->flash_sale_id)
                ->where('product_variant_id', $orderItem->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (! $saleItem) {
                throw ValidationException::withMessages([
                    'flash_sale' => 'Không tìm thấy lượt giữ chỗ Flash Sale của đơn hàng.',
                ]);
            }

            $required = (int) $items
                ->where('flash_sale_id', $orderItem->flash_sale_id)
                ->where('product_variant_id', $orderItem->product_variant_id)
                ->sum('quantity');

            if ((int) $saleItem->quantity_reserved < $required) {
                throw ValidationException::withMessages([
                    'flash_sale' => 'Số lượng giữ chỗ Flash Sale không khớp với đơn hàng.',
                ]);
            }

            $locked[$key] = [$saleItem, $required];
        }

        foreach ($locked as [$saleItem, $quantity]) {
            $saleItem->quantity_reserved -= $quantity;
            $saleItem->save();
        }
    }

    public function consumeOrderReservations(Order $order): void
    {
        $items = $order->items()
            ->whereNotNull('flash_sale_id')
            ->orderBy('flash_sale_id')
            ->orderBy('product_variant_id')
            ->get();

        $locked = [];
        foreach ($items as $orderItem) {
            $key = $orderItem->flash_sale_id.':'.$orderItem->product_variant_id;
            if (isset($locked[$key])) {
                continue;
            }

            $saleItem = FlashSaleItem::query()
                ->where('flash_sale_id', $orderItem->flash_sale_id)
                ->where('product_variant_id', $orderItem->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (! $saleItem) {
                throw ValidationException::withMessages([
                    'flash_sale' => 'Không tìm thấy lượt giữ chỗ Flash Sale của đơn hàng.',
                ]);
            }

            $required = (int) $items
                ->where('flash_sale_id', $orderItem->flash_sale_id)
                ->where('product_variant_id', $orderItem->product_variant_id)
                ->sum('quantity');

            if ((int) $saleItem->quantity_reserved < $required) {
                throw ValidationException::withMessages([
                    'flash_sale' => 'Số lượng giữ chỗ Flash Sale không khớp với đơn hàng.',
                ]);
            }

            $locked[$key] = [$saleItem, $required];
        }

        foreach ($locked as [$saleItem, $quantity]) {
            $saleItem->quantity_reserved -= $quantity;
            $saleItem->quantity_sold += $quantity;
            $saleItem->save();
        }
    }
}
