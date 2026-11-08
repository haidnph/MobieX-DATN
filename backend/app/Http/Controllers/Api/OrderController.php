<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
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

    public function cancel(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::with('items')->findOrFail($id);

        if ((int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền hủy đơn hàng này.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Chỉ có thể hủy đơn hàng khi đơn đang ở trạng thái chờ xử lý (pending).',
            ], 422);
        }

        $userId = $request->user()->id;
        $reason = $validated['cancel_reason'] ?? 'Khách hàng yêu cầu hủy đơn';

        DB::transaction(function () use ($order, $userId, $reason) {
            $fromStatus = $order->status;

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            foreach ($order->items as $item) {
                $inventory = Inventory::where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->reserved_quantity = max(0, (int) $inventory->reserved_quantity - (int) $item->quantity);
                    $inventory->save();

                    InventoryTransaction::create([
                        'product_variant_id' => $item->product_variant_id,
                        'user_id' => $userId,
                        'type' => 'release',
                        'quantity_delta' => 0,
                        'reserved_delta' => -((int) $item->quantity),
                        'unit_cost' => $item->cost_price_snapshot,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'idempotency_key' => "order_{$order->id}_var_{$item->product_variant_id}_release",
                        'note' => 'Hoàn trả tồn kho do đơn hàng '.$order->order_code.' bị hủy',
                        'created_at' => now(),
                    ]);
                }
            }

            // Release coupon reservation if any
            if ($order->coupon_id) {
                $coupon = Coupon::whereKey($order->coupon_id)
                    ->lockForUpdate()
                    ->first();

                if ($coupon) {
                    $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                    $coupon->save();
                }

                CouponRedemption::where('order_id', $order->id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'released']);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => $fromStatus,
                'status' => 'cancelled',
                'note' => $reason,
                'created_at' => now(),
            ]);
        });

        $order->load(['items.variant.product', 'statusHistories']);

        return response()->json([
            'message' => 'Hủy đơn hàng thành công.',
            'data' => $order,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('shipping_name') && $request->has('recipient_name')) {
            $request->merge(['shipping_name' => $request->input('recipient_name')]);
        }
        if (! $request->has('shipping_phone')) {
            $phone = $request->input('recipient_phone') ?? $request->input('phone');
            if ($phone) {
                $request->merge(['shipping_phone' => $phone]);
            }
        }
        if (! $request->has('customer_note') && $request->has('note')) {
            $request->merge(['customer_note' => $request->input('note')]);
        }

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
            'coupon_code' => ['nullable', 'string', 'max:50'],
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

            $coupon = null;
            $discountAmount = 0.0;
            if (! empty($validated['coupon_code'])) {
                $couponCode = trim($validated['coupon_code']);
                $coupon = Coupon::where('code', $couponCode)
                    ->lockForUpdate()
                    ->first();

                if (! $coupon) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá không tồn tại.',
                    ]);
                }

                if (! $coupon->is_active) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá hiện đang bị vô hiệu hóa.',
                    ]);
                }

                $now = now();
                if ($coupon->start_at && $now->lt($coupon->start_at)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá chưa đến thời gian áp dụng.',
                    ]);
                }

                if ($coupon->end_at && $now->gt($coupon->end_at)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá đã hết hạn sử dụng.',
                    ]);
                }

                if ($coupon->usage_limit !== null && (($coupon->used_count + $coupon->reserved_count) >= $coupon->usage_limit)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá đã hết lượt sử dụng.',
                    ]);
                }

                if ($subtotal < (float) $coupon->min_order_amount) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.',
                    ]);
                }

                if ($coupon->discount_type === 'percent') {
                    $discountAmount = round(($subtotal * (float) $coupon->discount_value) / 100, 2);
                    if ($coupon->max_discount !== null && $discountAmount > (float) $coupon->max_discount) {
                        $discountAmount = (float) $coupon->max_discount;
                    }
                } elseif ($coupon->discount_type === 'fixed') {
                    $discountAmount = min((float) $coupon->discount_value, $subtotal);
                }

                $coupon->reserved_count += 1;
                $coupon->save();
            }

            $totalAmount = max(0.0, round($subtotal - $discountAmount + $shippingFee, 2));

            $order = Order::create([
                'user_id' => $userId,
                'order_code' => 'MX-'.Str::upper(Str::random(12)),
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_method_code' => $shippingMethod->code,
                'shipping_method_name' => $shippingMethod->name,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
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

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'discount_amount' => $discountAmount,
                    'status' => 'reserved',
                ]);
            }

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
