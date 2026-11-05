<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\CartItem;
use App\Models\ProductVariant;
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
                'cart_item_id' => $item->id,
                'variant_id' => $item->variant_id,
                'sku' => $variant?->sku,
                'product_name' => $product?->name,
                'variant_attributes' => $variant?->attributeValues
                    ->map(fn($value) => [
                        'attribute' => $value->attribute?->name,
                        'value' => $value->value,
                    ])
                    ->values(),
                'price' => $variant?->price,
                'quantity' => $item->quantity,
                'stock_quantity' => $variant?->inventory?->quantity ?? 0,
                'subtotal' => (float) ($variant?->price ?? 0)
                    * $item->quantity,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Lấy giỏ hàng thành công',
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items,
                'total_quantity' => $items->sum('quantity'),
                'total_amount' => $items->sum('subtotal'),
            ],
        ]);
    }

    /**
     * Thêm biến thể sản phẩm vào giỏ hàng.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $variant = ProductVariant::whereKey($validated['variant_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->status !== 'active') {
                return [
                    'error' => 'Biến thể sản phẩm không hoạt động.',
                    'code' => 422,
                ];
            }

            $inventory = $variant->inventory()
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
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
                ->where('variant_id', $variant->id)
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
                    'variant_id' => $variant->id,
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
}
