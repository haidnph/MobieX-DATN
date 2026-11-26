<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class FlashSalePricing
{
    /**
     * @param  array<int, int>  $variantIds
     * @return Collection<int, FlashSaleItem>
     */
    public function activeItemsForVariants(array $variantIds, ?int $userId = null): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        return FlashSaleItem::query()
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('flashSale', function ($query): void {
                $query->whereIn('status', ['scheduled', 'active'])
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>', now());
            })
            ->with(['flashSale', 'variant.inventory'])
            ->orderBy('flash_sale_id')
            ->get()
            ->filter(function (FlashSaleItem $item) use ($userId): bool {
                $variant = $item->variant;
                $inventory = $variant?->inventory;

                return $variant
                    && $inventory
                    && (float) $item->sale_price < (float) $variant->price
                    && $this->remainingQuantity($item, $userId) > 0;
            })
            ->unique('product_variant_id')
            ->keyBy('product_variant_id');
    }

    public function customerRemaining(FlashSaleItem $item, ?int $userId): int
    {
        if ($userId === null || $item->max_per_customer === null) {
            return PHP_INT_MAX;
        }
        $used = OrderItem::query()
            ->where('flash_sale_id', $item->flash_sale_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->whereHas('order', fn ($query) => $query->where('user_id', $userId)->where('status', '!=', 'cancelled'))
            ->sum('quantity');
        return max(0, (int) $item->max_per_customer - (int) $used);
    }

    public function remainingQuantity(FlashSaleItem $item, ?int $userId = null): int
    {
        $stockAvailable = (int) ($item->variant?->inventory?->available_quantity ?? 0);
        $saleAvailable = $item->quantity_limit === null
            ? $stockAvailable
            : (int) $item->quantity_limit - (int) $item->quantity_reserved - (int) $item->quantity_sold;

        return max(0, min($stockAvailable, $saleAvailable, $this->customerRemaining($item, $userId)));
    }
}
