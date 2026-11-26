<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\FlashSaleItem;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\CouponRules;
use App\Services\FlashSaleOrderService;
use App\Services\FlashSalePricing;
use App\Services\OrderMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly CouponRules $couponRules) {}

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

    /**
     * Tra cứu đơn hàng công khai dành cho khách vãng lai (bằng mã đơn + SĐT nhận hàng).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_code' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $orderCode = trim($validated['order_code']);
        $phone = trim($validated['phone']);

        $order = Order::with([
            'items.variant.product',
            'statusHistories',
            'shippingMethod',
        ])
            ->where('order_code', $orderCode)
            ->where('shipping_phone', $phone)
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy đơn hàng với mã đơn và số điện thoại đã cung cấp.',
            ], 404);
        }

        return response()->json([
            'status' => true,
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

        if ($order->status !== 'pending' || $order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Chỉ có thể hủy đơn hàng khi đơn đang ở trạng thái chờ xử lý (pending).',
            ], 422);
        }

        $userId = $request->user()->id;
        $reason = $validated['cancel_reason'] ?? 'Khách hàng yêu cầu hủy đơn';

        DB::transaction(function () use ($order, $userId, $reason) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($order->status !== 'pending' || $order->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Đơn hàng không còn ở trạng thái có thể hủy.',
                ]);
            }

            $fromStatus = $order->status;

            app(FlashSaleOrderService::class)->releaseOrderReservations($order);

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            Payment::query()
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            foreach ($order->items as $item) {
                $inventory = Inventory::where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || (int) $inventory->reserved_quantity < (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'inventory' => 'Không thể hủy đơn vì số lượng giữ chỗ kho không khớp. Vui lòng liên hệ hỗ trợ.',
                    ]);
                }

                $inventory->reserved_quantity -= (int) $item->quantity;
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

        $order->refresh()->load(['items.variant.product', 'statusHistories']);

        return response()->json([
            'message' => 'Hủy đơn hàng thành công.',
            'data' => $order,
        ]);
    }

    public function confirmReceived(Request $request, int|string $id): JsonResponse
    {
        $order = DB::transaction(function () use ($request, $id) {
            $order = Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ((int) $order->user_id !== (int) $request->user()->id) {
                abort(403, 'Bạn không có quyền xác nhận đơn hàng này.');
            }
            if ($order->status !== 'shipping') {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể xác nhận đã nhận hàng khi đơn đang được giao.',
                ]);
            }

            $order->update(['status' => 'received']);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'from_status' => 'shipping',
                'status' => 'received',
                'note' => 'Khách hàng xác nhận đã nhận hàng',
                'created_at' => now(),
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Đã xác nhận nhận hàng. Quản trị viên có thể chốt giao hàng thành công.',
            'data' => $order->refresh()->load('statusHistories.user'),
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
        if (! $request->has('customer_email')) {
            $email = $request->input('email') ?? $request->input('recipient_email');
            if ($email) {
                $request->merge(['customer_email' => $email]);
            }
        }

        $validated = $request->validate([
            'customer_email' => ['nullable', 'email', 'max:150'],
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
            'payment_method' => ['required', Rule::in(['COD', 'VNPAY', 'MOMO', 'ZALOPAY', 'BANK_TRANSFER', 'cod', 'vnpay', 'momo', 'zalopay', 'bank_transfer'])],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'buy_now_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'buy_now_quantity' => ['required_with:buy_now_variant_id', 'integer', 'min:1'],
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
            $customerEmail = $validated['customer_email'] ?? $request->user()?->email;

            $cart = null;
            if (! empty($validated['buy_now_variant_id'])) {
                $cartItems = collect([(object) ['variant_id' => $validated['buy_now_variant_id'], 'quantity' => $validated['buy_now_quantity']]]);
            } else {
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
                ])->orderBy('product_variant_id')->get();

            }
            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Giỏ hàng đang trống.',
                ]);
            }

            $shippingMethod = null;
            if (! empty($validated['shipping_method_id'])) {
                $shippingMethod = ShippingMethod::query()
                    ->whereKey($validated['shipping_method_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $shippingMethod) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'Phương thức giao hàng này hiện không khả dụng.',
                    ]);
                }
            }
            if (! $shippingMethod) {
                $shippingMethod = ShippingMethod::where('is_active', true)->first();
            }
            if (! $shippingMethod) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => 'Hiện chưa có phương thức giao hàng khả dụng. Vui lòng liên hệ cửa hàng.',
                ]);
            }

            $preparedItems = [];
            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $variant = ProductVariant::whereKey($cartItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant || $variant->status !== 'active' || ! $variant->is_active) {
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
                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'cart' => 'Một sản phẩm trong giỏ hàng không còn khả dụng hoặc đã ngừng kinh doanh.',
                    ]);
                }

                $flashSaleItems = FlashSaleItem::query()
                    ->where('product_variant_id', $variant->id)
                    ->whereHas('flashSale', function ($query): void {
                        $query->whereIn('status', ['scheduled', 'active'])
                            ->where('start_at', '<=', now())
                            ->where('end_at', '>', now());
                    })
                    ->orderBy('flash_sale_id')
                    ->lockForUpdate()
                    ->get();

                $flashSaleItem = $flashSaleItems->first(fn (FlashSaleItem $item): bool => (float) $item->sale_price > 0
                    && (float) $item->sale_price < (float) $variant->price
                    && $item->variant?->is_active
                    && $item->variant?->product?->status === 'active'
                );

                if ($flashSaleItem) {
                    $remainingSaleQuantity = $flashSaleItem->quantity_limit === null
                        ? (int) $inventory->available_quantity
                        : max(0, (int) $flashSaleItem->quantity_limit
                            - (int) $flashSaleItem->quantity_reserved
                            - (int) $flashSaleItem->quantity_sold);
                    $remainingCustomerQuantity = app(FlashSalePricing::class)
                        ->customerRemaining($flashSaleItem, (int) $userId);
                    $remainingQuantity = min((int) $inventory->available_quantity, $remainingSaleQuantity, $remainingCustomerQuantity);

                    if ((int) $cartItem->quantity > $remainingQuantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Sản phẩm {$variant->sku} chỉ còn {$remainingQuantity} suất Flash Sale phù hợp với giới hạn mua của bạn. Vui lòng điều chỉnh giỏ hàng rồi thử lại.",
                        ]);
                    }
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

                $price = (float) ($flashSaleItem?->sale_price ?? $variant->price);
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
                    'flash_sale_item' => $flashSaleItem,
                ];
            }

            $shippingFee = (float) $shippingMethod->fee;

            $coupon = null;
            $discountAmount = 0.0;
            if (! empty($validated['coupon_code'])) {
                $couponCode = strtoupper(trim($validated['coupon_code']));
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

                if ($this->couponRules->customerLimitReached($coupon, (int) $userId)) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Bạn đã sử dụng hết lượt áp dụng mã giảm giá này.',
                    ]);
                }

                $eligibleSubtotal = $this->couponRules->eligibleSubtotal(
                    $coupon,
                    $preparedItems,
                    static fn (array $item): int => (int) $item['product']->id,
                    fn (array $item): float => $item['flash_sale_item'] && ! ($coupon->applies_to_sale_items && $coupon->is_stackable)
                        ? 0.0
                        : (float) $item['subtotal'],
                );

                if ($eligibleSubtotal <= 0) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng.',
                    ]);
                }

                if ($eligibleSubtotal < (float) $coupon->min_order_amount) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Giá trị đơn hàng chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount).' VND để áp dụng mã.',
                    ]);
                }

                $discountAmount = $this->couponRules->discountAmount($coupon, $eligibleSubtotal);

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
                'customer_email' => $customerEmail,
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address_line' => $addressLine,
                // Address codes must come from a verified address source. Do not
                // silently map unstructured customer input to Hanoi codes.
                'shipping_province_code' => $validated['shipping_province_code'] ?? '',
                'shipping_province_name' => $validated['shipping_province_name'] ?? $validated['shipping_city'] ?? '',
                'shipping_ward_code' => $validated['shipping_ward_code'] ?? '',
                'shipping_ward_name' => $validated['shipping_ward_name'] ?? $validated['shipping_ward'] ?? '',
                'payment_method' => strtolower($validated['payment_method']),
                'payment_status' => 'pending',
                'flash_sale_reservation_status' => collect($preparedItems)->contains(fn (array $item): bool => $item['flash_sale_item'] !== null)
                    ? 'reserved'
                    : null,
                'status' => 'pending',
                'customer_note' => $validated['customer_note'] ?? null,
                'reservation_expires_at' => strtolower($validated['payment_method']) === 'cod'
                    ? null
                    : now()->addMinutes((int) config('orders.reservation_expiration_minutes')),
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
                    'flash_sale_id' => $item['flash_sale_item']?->flash_sale_id,
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'image_path' => $item['variant']->thumbnail
                        ?? $item['product']->thumbnail
                        ?? $item['product']->primary_image_url
                        ?? null,
                    'price' => $item['price'],
                    'cost_price_snapshot' => $item['variant']->cost_price ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // Stock Reservation: Keep on_hand_quantity intact, increase reserved_quantity
                $inventory = $item['inventory'];
                $inventory->reserved_quantity += $item['quantity'];
                $inventory->save();

                if ($item['flash_sale_item']) {
                    $item['flash_sale_item']->quantity_reserved += $item['quantity'];
                    $item['flash_sale_item']->save();
                }

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

            $cart?->items()->delete();

            DB::afterCommit(function () use ($order) {
                app(OrderMailService::class)->sendOrderConfirmation($order);
            });

            return $order;
        });

        $order->load(['items.variant.product', 'statusHistories']);

        return response()->json([
            'message' => 'Đặt hàng thành công.',
            'data' => $order,
        ], 201);
    }
}
