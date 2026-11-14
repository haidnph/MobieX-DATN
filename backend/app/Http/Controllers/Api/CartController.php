<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Lấy giỏ hàng của người dùng đang đăng nhập.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = Cart::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'status' => 'active',
            ]
        );

        $cart->load([
            'items.variant.product',
            'items.variant.attributeValues.attribute',
            'items.variant.inventory',
        ]);

        $items = $cart->items->map(function ($item) {
            $variant = $item->variant;
            $product = $variant?->product;

            return [
                'id' => $item->id,
                'cart_item_id' => $item->id,
                'variant_id' => $item->variant_id,
                'sku' => $variant?->sku,
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'product' => [
                    'id' => $product?->id,
                    'name' => $product?->name,
                    'slug' => $product?->slug,
                    'thumbnail' => $product?->thumbnail,
                ],
                'variant' => [
                    'id' => $variant?->id,
                    'sku' => $variant?->sku,
                    'price' => $variant?->price,
                    'name' => $variant?->option_signature,
                    'available_quantity' => $variant?->inventory?->available_quantity ?? $variant?->inventory?->quantity ?? 0,
                    'image_url' => $product?->thumbnail,
                ],
                'variant_attributes' => $variant?->attributeValues
                    ->map(fn ($value) => [
                        'attribute' => $value->attribute?->name,
                        'value' => $value->value,
                    ])
                    ->values(),
                'price' => (float) ($variant?->price ?? 0),
                'quantity' => $item->quantity,
                'stock_quantity' => $variant?->inventory?->available_quantity ?? $variant?->inventory?->quantity ?? 0,
                'subtotal' => (float) ($variant?->price ?? 0)
                    * $item->quantity,
            ];
        })->values();

        $totalAmount = $items->sum('subtotal');

        $discount = 0.0;
        $couponCode = null;
        $couponId = null;
        $couponInvalidReason = null;

        if ($request->filled('coupon_code') && $totalAmount > 0) {
            $inputCode = trim((string) $request->input('coupon_code'));
            $coupon = Coupon::where('code', $inputCode)->first();

            $now = now();
            if (! $coupon) {
                $couponInvalidReason = 'Mã giảm giá không tồn tại.';
            } elseif (! $coupon->is_active) {
                $couponInvalidReason = 'Mã giảm giá hiện đang bị vô hiệu hóa.';
            } elseif ($coupon->start_at && $now->lt($coupon->start_at)) {
                $couponInvalidReason = 'Mã giảm giá chưa đến thời gian áp dụng.';
            } elseif ($coupon->end_at && $now->gt($coupon->end_at)) {
                $couponInvalidReason = 'Mã giảm giá đã hết hạn sử dụng.';
            } elseif ($coupon->usage_limit !== null && (($coupon->used_count + $coupon->reserved_count) >= $coupon->usage_limit)) {
                $couponInvalidReason = 'Mã giảm giá đã hết lượt sử dụng.';
            } elseif ($totalAmount < (float) $coupon->min_order_amount) {
                $couponInvalidReason = 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.';
            } else {
                if ($coupon->discount_type === 'percent') {
                    $discount = round(($totalAmount * (float) $coupon->discount_value) / 100, 2);
                    if ($coupon->max_discount !== null && $discount > (float) $coupon->max_discount) {
                        $discount = (float) $coupon->max_discount;
                    }
                } elseif ($coupon->discount_type === 'fixed') {
                    $discount = min((float) $coupon->discount_value, $totalAmount);
                }
                $couponCode = $coupon->code;
                $couponId = $coupon->id;
            }
        }

        $finalTotal = max(0.0, round($totalAmount - $discount, 2));

        return response()->json([
            'status' => true,
            'message' => 'Lấy giỏ hàng thành công',
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items,
                'total_quantity' => $items->sum('quantity'),
                'total_amount' => $finalTotal,
                'subtotal' => $totalAmount,
                'discount' => $discount,
                'total' => $finalTotal,
                'coupon_code' => $couponCode,
                'coupon_id' => $couponId,
                'coupon_invalid_reason' => $couponInvalidReason,
            ],
        ]);
    }

    /**
     * Thêm biến thể sản phẩm vào giỏ hàng.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->has('variant_id') && $request->has('product_variant_id')) {
            $request->merge(['variant_id' => $request->input('product_variant_id')]);
        }

        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $variant = ProductVariant::with('product')
                ->whereKey($validated['variant_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->status !== 'active' || ! $variant->product || $variant->product->status !== 'active') {
                return [
                    'error' => 'Sản phẩm hoặc biến thể này hiện đã ngừng kinh doanh.',
                    'code' => 422,
                ];
            }

            $inventory = $variant->inventory()
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return [
                    'error' => 'Biến thể sản phẩm chưa có thông tin tồn kho.',
                    'code' => 422,
                ];
            }

            $cart = Cart::firstOrCreate([
                'user_id' => $request->user()->id,
                'status' => 'active',
            ]);

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            $currentQuantity = $cartItem?->quantity ?? 0;
            $newQuantity = $currentQuantity + $validated['quantity'];
            $availableQuantity = (int) $inventory->available_quantity;

            if ($newQuantity > $availableQuantity) {
                return [
                    'error' => "Tồn kho không đủ. Hiện còn {$availableQuantity} sản phẩm.",
                    'code' => 422,
                ];
            }

            if ($cartItem) {
                $cartItem->quantity = $newQuantity;
                $cartItem->save();
            } else {
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $newQuantity,
                ]);
            }

            return [
                'cart_item' => $cartItem->load('variant.product'),
                'code' => 200,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['error'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Thêm sản phẩm vào giỏ hàng thành công.',
            'data' => $result['cart_item'],
        ]);
    }

    /**
     * Cập nhật số lượng sản phẩm trong giỏ hàng.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($request, $id, $validated) {
            $cart = Cart::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();

            if (! $cart) {
                return [
                    'error' => 'Không tìm thấy giỏ hàng.',
                    'code' => 404,
                ];
            }

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $id)
                ->first();

            if (! $cartItem) {
                return [
                    'error' => 'Không tìm thấy sản phẩm trong giỏ hàng.',
                    'code' => 404,
                ];
            }

            $variant = ProductVariant::with('product')
                ->whereKey($cartItem->variant_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || $variant->status !== 'active' || ! $variant->product || $variant->product->status !== 'active') {
                return [
                    'error' => 'Sản phẩm hoặc biến thể này không còn khả dụng.',
                    'code' => 422,
                ];
            }

            $inventory = $variant->inventory()
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return [
                    'error' => 'Biến thể sản phẩm chưa có thông tin tồn kho.',
                    'code' => 422,
                ];
            }

            $availableQuantity = (int) $inventory->available_quantity;

            if ($validated['quantity'] > $availableQuantity) {
                return [
                    'error' => "Tồn kho không đủ. Hiện còn {$availableQuantity} sản phẩm.",
                    'code' => 422,
                ];
            }

            $cartItem->quantity = $validated['quantity'];
            $cartItem->save();

            return [
                'cart_item' => $cartItem->load('variant.product'),
                'code' => 200,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['error'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật số lượng sản phẩm thành công.',
            'data' => $result['cart_item'],
        ]);
    }

    /**
     * Xóa sản phẩm khỏi giỏ hàng.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $cart) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy giỏ hàng.',
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->first();

        if (! $cartItem) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.',
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'status' => true,
            'message' => 'Xóa sản phẩm khỏi giỏ hàng thành công.',
        ]);
    }

    /**
     * Xóa toàn bộ sản phẩm khỏi giỏ hàng.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Đã xóa toàn bộ sản phẩm khỏi giỏ hàng.',
        ]);
    }
}
