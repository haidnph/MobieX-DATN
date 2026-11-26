<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CouponRules;
use App\Services\FlashSalePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private const MAX_PRODUCT_QUANTITY = 4;

    public function __construct(private readonly CouponRules $couponRules) {}

    /**
     * Lấy giỏ hàng của người dùng đang đăng nhập.
     */
    public function index(Request $request): JsonResponse
    {
        [$cart, $wasAdjusted] = DB::transaction(function () use ($request): array {
            $cart = $this->lockActiveCart((int) $request->user()->id, true);
            $items = $cart->items()->with('variant')->orderBy('id')->lockForUpdate()->get();
            $variants = ProductVariant::with('inventory')->whereIn('id', $items->pluck('product_variant_id'))->get()->keyBy('id');
            $groups = $items->groupBy(fn (CartItem $item) => $variants->get($item->product_variant_id)?->product_id ?? 'missing');
            $wasAdjusted = false;

            foreach ($groups as $productItems) {
                $remaining = self::MAX_PRODUCT_QUANTITY;
                foreach ($productItems as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $stock = (int) ($variant?->inventory?->available_quantity ?? 0);
                    $quantity = min((int) $item->quantity, $stock, $remaining);
                    if ($quantity < 1) {
                        $item->delete();
                        $wasAdjusted = true;

                        continue;
                    }
                    if ($quantity !== (int) $item->quantity) {
                        $item->quantity = $quantity;
                        $item->save();
                        $wasAdjusted = true;
                    }
                    $remaining -= $quantity;
                }
            }

            return [$cart, $wasAdjusted];
        });

        $cart->load([
            'items.variant.product',
            'items.variant.attributeValues.attribute',
            'items.variant.inventory',
        ]);

        $userId = (int) $request->user()->id;
        $saleItems = app(FlashSalePricing::class)->activeItemsForVariants(
            $cart->items->pluck('variant_id')->map(static fn ($id): int => (int) $id)->all(), $userId,
        );

        $items = $cart->items->map(function ($item) use ($saleItems, $userId) {
            $variant = $item->variant;
            $product = $variant?->product;
            $saleItem = $variant ? $saleItems->get($variant->id) : null;
            $quantity = (int) $item->quantity;
            $saleAvailable = $saleItem ? app(FlashSalePricing::class)->remainingQuantity($saleItem, $userId) : 0;
            $flashSale = $saleItem && $saleAvailable >= $quantity ? [
                'id' => $saleItem->flash_sale_id,
                'end_at' => $saleItem->flashSale->end_at->toIso8601String(),
                'sale_price' => (float) $saleItem->sale_price,
                'quantity_remaining' => $saleAvailable,
            ] : null;
            $unitPrice = $flashSale ? (float) $saleItem->sale_price : (float) ($variant?->price ?? 0);

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
                    'current_price' => $unitPrice,
                    'flash_sale' => $flashSale,
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
                'price' => $unitPrice,
                'quantity' => $item->quantity,
                'flash_sale' => $flashSale,
                'stock_quantity' => $variant?->inventory?->available_quantity ?? $variant?->inventory?->quantity ?? 0,
                'subtotal' => $unitPrice * $quantity,
            ];
        })->values();

        $totalAmount = $items->sum('subtotal');

        $discount = 0.0;
        $couponCode = null;
        $couponId = null;
        $couponInvalidReason = null;

        if ($request->filled('coupon_code') && $totalAmount > 0) {
            $inputCode = strtoupper(trim((string) $request->input('coupon_code')));
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
            } elseif ($this->couponRules->customerLimitReached($coupon, (int) $request->user()->id)) {
                $couponInvalidReason = 'Bạn đã sử dụng hết lượt áp dụng mã giảm giá này.';
            } else {
                $eligibleSubtotal = $this->couponRules->eligibleSubtotal(
                    $coupon,
                    $items,
                    static fn (array $item): int => (int) ($item['product_id'] ?? 0),
                    static fn (array $item): float => ! empty($item['flash_sale']) ? 0.0 : (float) ($item['subtotal'] ?? 0),
                );

                if ($eligibleSubtotal <= 0) {
                    $couponInvalidReason = 'Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng.';
                } elseif ($eligibleSubtotal < (float) $coupon->min_order_amount) {
                    $couponInvalidReason = 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.';
                } else {
                    $discount = $this->couponRules->discountAmount($coupon, $eligibleSubtotal);
                    $couponCode = $coupon->code;
                    $couponId = $coupon->id;
                }
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
                'quantity_limit_adjusted' => $wasAdjusted,
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
            $cart = $this->lockActiveCart((int) $request->user()->id, true);
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

            $productQuantity = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereHas('variant', fn ($query) => $query->where('product_id', $variant->product_id))
                ->sum('quantity');
            if ((int) $productQuantity - (int) $currentQuantity + (int) $validated['quantity'] > self::MAX_PRODUCT_QUANTITY) {
                return ['error' => 'Bạn chỉ có thể mua tối đa 4 chiếc cho mỗi sản phẩm trong một đơn hàng.', 'code' => 422];
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
            $cart = $this->lockActiveCart((int) $request->user()->id);

            if (! $cart) {
                return [
                    'error' => 'Không tìm thấy giỏ hàng.',
                    'code' => 404,
                ];
            }

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $id)
                ->lockForUpdate()
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

            $productQuantity = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereHas('variant', fn ($query) => $query->where('product_id', $variant->product_id))
                ->sum('quantity');
            $newProductQuantity = (int) $productQuantity - (int) $cartItem->quantity + (int) $validated['quantity'];
            if ($newProductQuantity > self::MAX_PRODUCT_QUANTITY) {
                return ['error' => 'Bạn chỉ có thể mua tối đa 4 chiếc cho mỗi sản phẩm trong một đơn hàng.', 'code' => 422];
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
        $deleted = DB::transaction(function () use ($request, $id): bool {
            $cart = $this->lockActiveCart((int) $request->user()->id);
            if (! $cart) {
                return false;
            }

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $cartItem) {
                return false;
            }

            $cartItem->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Xóa sản phẩm khỏi giỏ hàng thành công.',
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $cart = $this->lockActiveCart((int) $request->user()->id);

            if ($cart) {
                $items = $cart->items()->lockForUpdate()->get();
                foreach ($items as $item) {
                    $item->delete();
                }
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Đã xóa toàn bộ sản phẩm khỏi giỏ hàng.',
        ]);
    }

    private function lockActiveCart(int $userId, bool $create = false): ?Cart
    {
        User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

        $cart = Cart::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (! $cart && $create) {
            $cart = Cart::create([
                'user_id' => $userId,
                'status' => 'active',
            ]);
        }

        return $cart;
    }
}
