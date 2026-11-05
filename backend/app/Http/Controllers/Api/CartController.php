<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
