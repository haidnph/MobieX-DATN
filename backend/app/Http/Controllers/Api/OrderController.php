<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_district' => ['nullable', 'string', 'max:100'],
            'shipping_ward' => ['nullable', 'string', 'max:100'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'payment_method' => ['required', Rule::in(['COD', 'VNPAY', 'MOMO'])],
            'customer_note' => ['nullable', 'string'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $userId = $request->user()->id;

            $cart = Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                throw ValidationException::withMessages([
                    'cart' => 'Không tìm thấy giỏ hàng đang hoạt động.',
                ]);
            }

            $cartItems = $cart->items()->with([
                'variant.product',
                'variant.attributeValues.attribute',
            ])->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Giỏ hàng đang trống.',
                ]);
            }

            $preparedItems = [];
            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $variant = ProductVariant::whereKey($cartItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if (!$variant || $variant->status !== 'active') {
                    throw ValidationException::withMessages([
                        'cart' => 'Một sản phẩm trong giỏ hàng không còn khả dụng.',
                    ]);
                }

                $inventory = Inventory::where('variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    throw ValidationException::withMessages([
                        'cart' => 'Không tìm thấy tồn kho của một sản phẩm.',
                    ]);
                }

                $available = (int) $inventory->quantity
                    - (int) $inventory->reserved_quantity;

                if ($available < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Sản phẩm {$variant->sku} không đủ tồn kho.",
                    ]);
                }

                $product = $variant->product;

                if (!$product) {
                    throw ValidationException::withMessages([
                        'cart' => 'Không tìm thấy thông tin sản phẩm.',
                    ]);
                }

                $variantName = $variant->attributeValues
                    ->map(fn($value) => $value->value)
                    ->filter()
                    ->implode(' / ');

                $price = (float) $variant->price;
                $itemSubtotal = $price * (int) $cartItem->quantity;
                $subtotal += $itemSubtotal;

                $preparedItems[] = [
                    'variant' => $variant,
                    'inventory' => $inventory,
                    'product_name' => $product->name,
                    'variant_name' => $variantName ?: null,
                    'sku' => $variant->sku,
                    'price' => $price,
                    'quantity' => (int) $cartItem->quantity,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $order = Order::create([
                'user_id' => $userId,
                'shipping_method_id' => $validated['shipping_method_id'] ?? null,
                'order_code' => 'MX-' . Str::upper(Str::random(12)),
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total_amount' => $subtotal,
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_district' => $validated['shipping_district'] ?? null,
                'shipping_ward' => $validated['shipping_ward'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'PENDING',
                'order_status' => 'PENDING',
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            foreach ($preparedItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item['variant']->id,
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                $inventory = $item['inventory'];
                $inventory->quantity -= $item['quantity'];
                $inventory->save();

                InventoryTransaction::create([
                    'variant_id' => $item['variant']->id,
                    'user_id' => $userId,
                    'transaction_type' => 'export',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => 'Xuất kho cho đơn hàng ' . $order->order_code,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        $order->load('items');

        return response()->json([
            'message' => 'Đặt hàng thành công.',
            'data' => $order,
        ], 201);
    }
}
