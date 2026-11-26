<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;

class CouponRules
{
    public function customerLimitReached(Coupon $coupon, int $userId): bool
    {
        if ($coupon->max_uses_per_customer === null) {
            return false;
        }

        $usageCount = Order::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->count();

        return $usageCount >= $coupon->max_uses_per_customer;
    }

    /**
     * Calculate the eligible portion of a cart/order. A coupon with no product
     * rows applies to the complete subtotal for backward compatibility.
     *
     * @param  iterable<mixed>  $items
     */
    public function eligibleSubtotal(
        Coupon $coupon,
        iterable $items,
        callable $productId,
        callable $lineSubtotal,
    ): float {
        $productIds = $coupon->products()->pluck('products.id')->map(static fn ($id): int => (int) $id)->all();
        $categoryIds = $coupon->categories()->pluck('categories.id')->map(static fn ($id): int => (int) $id)->all();
        $brandIds = $coupon->brands()->pluck('brands.id')->map(static fn ($id): int => (int) $id)->all();
        $candidateProductIds = collect($items)->map(static fn ($item): int => (int) $productId($item))->filter()->unique();
        $products = Product::query()->whereIn('id', $candidateProductIds)->get(['id', 'category_id', 'brand_id'])->keyBy('id');
        $subtotal = 0.0;

        foreach ($items as $item) {
            $id = (int) $productId($item);
            $product = $products->get($id);
            if (! $product
                || ($productIds !== [] && ! in_array($id, $productIds, true))
                || ($categoryIds !== [] && ! in_array((int) $product->category_id, $categoryIds, true))
                || ($brandIds !== [] && ! in_array((int) $product->brand_id, $brandIds, true))) {
                continue;
            }

            $subtotal += (float) $lineSubtotal($item);
        }

        return round($subtotal, 2);
    }

    public function discountAmount(Coupon $coupon, float $eligibleSubtotal): float
    {
        if ($eligibleSubtotal <= 0) {
            return 0.0;
        }

        if ($coupon->discount_type === 'percent') {
            $discount = round(($eligibleSubtotal * (float) $coupon->discount_value) / 100, 2);

            if ($coupon->max_discount !== null) {
                $discount = min($discount, (float) $coupon->max_discount);
            }

            return min($discount, $eligibleSubtotal);
        }

        if ($coupon->discount_type === 'fixed') {
            return min((float) $coupon->discount_value, $eligibleSubtotal);
        }

        return 0.0;
    }
}
