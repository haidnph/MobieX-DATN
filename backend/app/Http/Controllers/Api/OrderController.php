<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Order::query()
            ->where('user_id', $userId)
            ->with([
                'items.variant.product',
                'statusHistories',
                'shippingMethod',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', strtolower((string) $request->query('status')));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $order = Order::with([
            'items.variant.product',
            'statusHistories',
            'shippingMethod',
            'payments',
            'coupon',
        ])->findOrFail($id);

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập đơn hàng này.',
            ], 403);
        }

        return response()->json([
            'data' => $order,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'shipping_address_line' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_province_code' => ['nullable', 'string', 'max:20'],
            'shipping_province_name' => ['nullable', 'string', 'max:100'],
            'shipping_district' => ['nullable', 'string', 'max:100'],
            'shipping_ward' => ['nullable', 'string', 'max:100'],
            'shipping_ward_code' => ['nullable', 'string', 'max:20'],
            'shipping_ward_name' => ['nullable', 'string', 'max:100'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'payment_method' => ['required', Rule::in(['COD', 'VNPAY', 'MOMO', 'cod', 'vnpay', 'momo'])],
            'customer_note' => ['nullable', 'string'],
        ]);

        $addressLine = $validated['shipping_address_line'] ?? $validated['shipping_address'] ?? null;
        if (! $addressLine) {
            throw ValidationException::withMessages([
                'shipping_address' => 'Địa chỉ giao hàng không được để trống.',
            ]);
        }

        $order = DB::transaction(function () use ($request, $validated, $addressLine) {
            $userId = $request->user()->id;

            $cart = Cart::where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart' => 'Không tìm thấy giỏ hàng đang hoạt động.',
                ]);
            }

            $cartItems = $cart->items()->with([
                'variant.product',
            ])->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Giỏ hàng đang trống.',
                ]);
            }

            $shippingMethod = null;
            if (! empty($validated['shipping_method_id'])) {
                $shippingMethod = ShippingMethod::find($validated['shipping_method_id']);
            }
            if (! $shippingMethod) {
                $shippingMethod = ShippingMethod::where('is_active', true)->first();
            }
            if (! $shippingMethod) {
                $shippingMethod = ShippingMethod::create([
                    'name' => 'Giao hàng tiêu chuẩn',
                    'code' => 'STANDARD',
                    'fee' => 0.00,
                    'is_active' => true,
                ]);
            }

            $preparedItems = [];
            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $variant = ProductVariant::whereKey($cartItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant || $variant->status !== 'active') {
                    throw ValidationException::withMessages([
                        'cart' => 'Một sản phẩm trong giỏ hàng không còn khả dụng.',
                    ]);
                }

                $inventory = Inventory::where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'cart' => 'Không tìm thấy tồn kho của một sản phẩm.',
                    ]);
                }

                if ($inventory->available_quantity < (int) $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Sản phẩm {$variant->sku} không đủ tồn kho.",
                    ]);
                }

                $product = $variant->product;
                if (! $product) {
                    throw ValidationException::withMessages([
                        'cart' => 'Không tìm thấy thông tin sản phẩm.',
                    ]);
                }

                $variantName = $variant->option_signature ?: null;
                if (! $variantName && method_exists($variant, 'attributeValues')) {
                    try {
                        $variantName = $variant->attributeValues
                            ->map(fn ($value) => $value->value)
                            ->filter()
                            ->implode(' / ');
                    } catch (\Throwable) {
                        $variantName = null;
                    }
                }

                $price = (float) $variant->price;
                $itemSubtotal = $price * (int) $cartItem->quantity;
                $subtotal += $itemSubtotal;

                $preparedItems[] = [
                    'variant' => $variant,
                    'inventory' => $inventory,
                    'product' => $product,
                    'product_name' => $product->name,
                    'variant_name' => $variantName ?: null,
                    'sku' => $variant->sku,
                    'price' => $price,
                    'quantity' => (int) $cartItem->quantity,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $shippingFee = (float) $shippingMethod->fee;
            $totalAmount = $subtotal + $shippingFee;

            $order = Order::create([
                'user_id' => $userId,
                'order_code' => 'MX-'.Str::upper(Str::random(12)),
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'shipping_fee' => $shippingFee,
                'total_amount' => $totalAmount,
                'currency_code' => 'VND',
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address_line' => $addressLine,
                'shipping_province_code' => $validated['shipping_province_code'] ?? 'VN-HN',
                'shipping_province_name' => $validated['shipping_province_name'] ?? $validated['shipping_city'] ?? 'Hà Nội',
                'shipping_ward_code' => $validated['shipping_ward_code'] ?? '001',
                'shipping_ward_name' => $validated['shipping_ward_name'] ?? $validated['shipping_ward'] ?? 'Dịch Vọng Hậu',
                'payment_method' => strtolower($validated['payment_method']),
                'payment_status' => 'pending',
                'status' => 'pending',
                'customer_note' => $validated['customer_note'] ?? null,
            ]);

            foreach ($preparedItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_variant_id' => $item['variant']->id,
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'image_path' => $item['variant']->image_path ?? $item['product']->thumbnail_url,
                    'price' => $item['price'],
                    'cost_price_snapshot' => $item['variant']->cost_price ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // Stock Reservation: Keep on_hand_quantity intact, increase reserved_quantity
                $inventory = $item['inventory'];
                $inventory->reserved_quantity += $item['quantity'];
                $inventory->save();

                InventoryTransaction::create([
                    'product_variant_id' => $item['variant']->id,
                    'user_id' => $userId,
                    'type' => 'reserve',
                    'quantity_delta' => 0,
                    'reserved_delta' => $item['quantity'],
                    'unit_cost' => $item['variant']->cost_price ?? null,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'idempotency_key' => "order_{$order->id}_var_{$item['variant']->id}_reserve",
                    'note' => 'Giữ chỗ cho đơn hàng '.$order->order_code,
                    'created_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => null,
                'status' => 'pending',
                'note' => 'Đơn hàng được tạo thành công.',
                'created_at' => now(),
            ]);

            $cart->items()->delete();

            return $order;
        });

        $order->load(['items.variant.product', 'statusHistories']);

        return response()->json([
            'message' => 'Đặt hàng thành công.',
            'data' => $order,
        ], 201);
    }
}
