<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use Illuminate\Support\Collection;

class FlashSalePricing
{
    /**
     * @param  array<int, int>  $variantIds
     * @return Collection<int, FlashSaleItem>
     */
    public function activeItemsForVariants(array $variantIds): Collection
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
            ->filter(function (FlashSaleItem $item): bool {
                $variant = $item->variant;
                $inventory = $variant?->inventory;

                return $variant
                    && $inventory
                    && (float) $item->sale_price < (float) $variant->price
                    && $this->remainingQuantity($item) > 0;
            })
            ->unique('product_variant_id')
            ->keyBy('product_variant_id');
    }

    public function remainingQuantity(FlashSaleItem $item): int
    {
        $stockAvailable = (int) ($item->variant?->inventory?->available_quantity ?? 0);
        $saleAvailable = $item->quantity_limit === null
            ? $stockAvailable
            : (int) $item->quantity_limit - (int) $item->quantity_reserved - (int) $item->quantity_sold;

        return max(0, min($stockAvailable, $saleAvailable));
    }
}
