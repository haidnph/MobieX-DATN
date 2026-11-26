<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Services\CouponRules;
use App\Services\FlashSalePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private readonly CouponRules $couponRules) {}

    /**
     * Validate and calculate coupon discount for the current active cart.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['items.variant.inventory'])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Giỏ hàng đang trống.',
            ], 422);
        }

        $saleItems = app(FlashSalePricing::class)->activeItemsForVariants(
            $cart->items->pluck('variant_id')->map(static fn ($id): int => (int) $id)->all(),
        );
        $subtotal = 0;
        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if ($variant && $variant->is_active) {
                $saleItem = $saleItems->get($variant->id);
                $saleApplies = $saleItem
                    && app(FlashSalePricing::class)->remainingQuantity($saleItem) >= (int) $item->quantity;
                $unitPrice = $saleApplies ? (float) $saleItem->sale_price : (float) $variant->price;
                $subtotal += $unitPrice * (int) $item->quantity;
            }
        }

        if ($subtotal <= 0) {
            return response()->json([
                'message' => 'Giỏ hàng không có sản phẩm hợp lệ.',
            ], 422);
        }

        $code = strtoupper(trim($validated['coupon_code']));
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return response()->json([
                'message' => 'Mã giảm giá không tồn tại.',
            ], 422);
        }

        if (! $coupon->is_active) {
            return response()->json([
                'message' => 'Mã giảm giá hiện đang bị vô hiệu hóa.',
            ], 422);
        }

        $now = now();
        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            return response()->json([
                'message' => 'Mã giảm giá chưa đến thời gian áp dụng.',
            ], 422);
        }

        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            return response()->json([
                'message' => 'Mã giảm giá đã hết hạn sử dụng.',
            ], 422);
        }

        if ($coupon->usage_limit !== null && (($coupon->used_count + $coupon->reserved_count) >= $coupon->usage_limit)) {
            return response()->json([
                'message' => 'Mã giảm giá đã hết lượt sử dụng.',
            ], 422);
        }

        if ($this->couponRules->customerLimitReached($coupon, (int) $userId)) {
            return response()->json([
                'message' => 'Bạn đã sử dụng hết lượt áp dụng mã giảm giá này.',
            ], 422);
        }

        $eligibleSubtotal = $this->couponRules->eligibleSubtotal(
            $coupon,
            $cart->items,
            static fn ($item): int => (int) ($item->variant?->product_id ?? 0),
            function ($item) use ($saleItems, $coupon): float {
                $variant = $item->variant;
                if (! $variant || ! $variant->is_active) {
                    return 0.0;
                }

                $saleItem = $saleItems->get($variant->id);
                if ($saleItem && app(FlashSalePricing::class)->remainingQuantity($saleItem) >= (int) $item->quantity) {
                    return $coupon->applies_to_sale_items && $coupon->is_stackable
                        ? (float) $saleItem->sale_price * (int) $item->quantity
                        : 0.0;
                }

                return (float) $variant->price * (int) $item->quantity;
            },
        );

        if ($eligibleSubtotal <= 0) {
            return response()->json([
                'message' => 'Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng.',
            ], 422);
        }

        if ($eligibleSubtotal < (float) $coupon->min_order_amount) {
            return response()->json([
                'message' => 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.',
            ], 422);
        }

        $discountAmount = $this->couponRules->discountAmount($coupon, $eligibleSubtotal);

        $finalTotal = max(0.0, round($subtotal - $discountAmount, 2));

        return response()->json([
            'status' => true,
            'message' => 'Áp dụng mã giảm giá thành công.',
            'data' => [
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'subtotal' => $subtotal,
                'eligible_subtotal' => $eligibleSubtotal,
                'discount_amount' => $discountAmount,
                'final_total' => $finalTotal,
            ],
        ]);
    }

    public function buyNowQuote(Request $request): JsonResponse
    {
        $v = $request->validate(['variant_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'coupon_code' => ['nullable', 'string', 'max:50']]);
        $variant = ProductVariant::with(['product', 'inventory'])->findOrFail($v['variant_id']);
        if (! $variant->is_active || $variant->product?->status !== 'active' || $variant->available_quantity < $v['quantity']) {
            return response()->json(['message' => 'Sản phẩm không còn đủ tồn kho.'], 422);
        }
        $sale = app(FlashSalePricing::class)->activeItemsForVariants([$variant->id])->get($variant->id);
        $onSale = $sale && app(FlashSalePricing::class)->remainingQuantity($sale) >= $v['quantity'];
        $price = (float) ($onSale ? $sale->sale_price : $variant->price);
        $subtotal = $price * $v['quantity'];
        $discount = 0;
        $code = null;
        if (! empty($v['coupon_code'])) {
            $coupon = Coupon::where('code', strtoupper(trim($v['coupon_code'])))->first();
            if (! $coupon || ! $coupon->is_active || ($coupon->start_at && now()->lt($coupon->start_at)) || ($coupon->end_at && now()->gt($coupon->end_at)) || ($coupon->usage_limit !== null && $coupon->used_count + $coupon->reserved_count >= $coupon->usage_limit) || $this->couponRules->customerLimitReached($coupon, $request->user()->id)) {
                return response()->json(['message' => 'Voucher không còn khả dụng.'], 422);
            }
            $eligible = $this->couponRules->eligibleSubtotal($coupon, [$variant], fn ($x) => $x->product_id, fn ($x) => $onSale && ! ($coupon->applies_to_sale_items && $coupon->is_stackable) ? 0 : $subtotal);
            if ($eligible <= 0 || $eligible < (float) $coupon->min_order_amount) {
                return response()->json(['message' => 'Sản phẩm hoặc giá trị đơn chưa đủ điều kiện Voucher.'], 422);
            }
            $discount = $this->couponRules->discountAmount($coupon, $eligible);
            $code = $coupon->code;
        }

        return response()->json(['data' => ['items' => [['id' => $variant->id, 'variant' => $variant, 'quantity' => $v['quantity'], 'price' => $price]], 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount, 'couponCode' => $code]]);
    }
}
