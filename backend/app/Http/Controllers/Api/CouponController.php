<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
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
            ->with(['items.variant'])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Giỏ hàng đang trống.',
            ], 422);
        }

        $subtotal = 0;
        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if ($variant && $variant->is_active) {
                $subtotal += (float) $variant->price * (int) $item->quantity;
            }
        }

        if ($subtotal <= 0) {
            return response()->json([
                'message' => 'Giỏ hàng không có sản phẩm hợp lệ.',
            ], 422);
        }

        $code = trim($validated['coupon_code']);
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

        if ($subtotal < (float) $coupon->min_order_amount) {
            return response()->json([
                'message' => 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.',
            ], 422);
        }

        // Calculate discount
        $discountAmount = 0.0;
        if ($coupon->discount_type === 'percent') {
            $discountAmount = round(($subtotal * (float) $coupon->discount_value) / 100, 2);
            if ($coupon->max_discount !== null && $discountAmount > (float) $coupon->max_discount) {
                $discountAmount = (float) $coupon->max_discount;
            }
        } elseif ($coupon->discount_type === 'fixed') {
            $discountAmount = min((float) $coupon->discount_value, $subtotal);
        }

        $finalTotal = max(0.0, round($subtotal - $discountAmount, 2));

        return response()->json([
            'message' => 'Áp dụng mã giảm giá thành công.',
            'data' => [
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_total' => $finalTotal,
            ],
        ]);
    }
}
