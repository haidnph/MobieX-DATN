<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with([
                'user',
                'items.variant.product',
                'statusHistories',
                'shippingMethod',
                'payments',
            ])
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower((string) $request->query('status')));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', strtolower((string) $request->query('payment_status')));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $order = Order::with([
            'user',
            'items.variant.product',
            'statusHistories.user',
            'shippingMethod',
            'payments',
            'coupon',
            'emailLogs',
        ])->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['confirmed', 'processing', 'shipping', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:255'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newStatus = $validated['status'];
        $order = Order::with('items.variant')->findOrFail($id);
        $currentStatus = $order->status;

        if ($currentStatus === $newStatus) {
            return response()->json([
                'message' => "Đơn hàng hiện tại đã ở trạng thái {$currentStatus}.",
            ], 422);
        }

        // Terminal states cannot be changed
        if (in_array($currentStatus, ['delivered', 'cancelled'], true)) {
            return response()->json([
                'message' => "Không thể chuyển trạng thái cho đơn hàng đã kết thúc ({$currentStatus}).",
            ], 422);
        }

        // Strict forward state machine
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipping', 'cancelled'],
            'shipping' => ['delivered'],
        ];

        if (! isset($allowedTransitions[$currentStatus]) || ! in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
            return response()->json([
                'message' => "Không thể chuyển trạng thái từ {$currentStatus} sang {$newStatus}. Trạng thái chỉ có thể chuyển tuần tự: pending -> confirmed -> processing -> shipping -> delivered.",
            ], 422);
        }

        $userId = $request->user()->id;
        $note = $validated['note'] ?? "Cập nhật trạng thái sang {$newStatus}";

        DB::transaction(function () use ($order, $currentStatus, $newStatus, $userId, $note, $validated) {
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'shipping') {
                // Dispatch event: Goods leave warehouse
                // Decrement on_hand_quantity and decrement reserved_quantity
                foreach ($order->items as $item) {
                    $inventory = Inventory::where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory || (int) $inventory->on_hand_quantity < (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'status' => ["Sản phẩm {$item->sku} không đủ tồn kho để xuất kho."],
                        ]);
                    }

                    $inventory->on_hand_quantity = (int) $inventory->on_hand_quantity - (int) $item->quantity;
                    $inventory->reserved_quantity = max(0, (int) $inventory->reserved_quantity - (int) $item->quantity);
                    $inventory->save();

                    InventoryTransaction::create([
                        'product_variant_id' => $item->product_variant_id,
                        'user_id' => $userId,
                        'type' => 'dispatch',
                        'quantity_delta' => -((int) $item->quantity),
                        'reserved_delta' => -((int) $item->quantity),
                        'unit_cost' => $item->cost_price_snapshot ?? $item->variant?->cost_price,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'idempotency_key' => "order_{$order->id}_var_{$item->product_variant_id}_dispatch",
                        'note' => 'Xuất kho vận chuyển cho đơn hàng '.$order->order_code,
                        'created_at' => now(),
                    ]);
                }
            } elseif ($newStatus === 'delivered') {
                $updateData['delivered_at'] = now();

                // Consume coupon reservation when order is delivered successfully
                if ($order->coupon_id) {
                    $coupon = Coupon::whereKey($order->coupon_id)
                        ->lockForUpdate()
                        ->first();

                    if ($coupon) {
                        $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                        $coupon->used_count += 1;
                        $coupon->save();
                    }

                    CouponRedemption::where('order_id', $order->id)
                        ->where('status', 'reserved')
                        ->update(['status' => 'consumed']);
                }
            } elseif ($newStatus === 'cancelled') {
                $reason = $validated['cancel_reason'] ?? $note;
                $updateData['cancelled_at'] = now();
                $updateData['cancel_reason'] = $reason;

                // Release reserved stock if cancelled before dispatch
                if (in_array($currentStatus, ['pending', 'confirmed', 'processing'], true)) {
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
                                'unit_cost' => $item->cost_price_snapshot ?? $item->variant?->cost_price,
                                'reference_type' => 'order',
                                'reference_id' => $order->id,
                                'idempotency_key' => "order_{$order->id}_var_{$item->product_variant_id}_release_admin",
                                'note' => 'Hoàn trả tồn kho do đơn hàng '.$order->order_code.' bị quản trị viên hủy',
                                'created_at' => now(),
                            ]);
                        }
                    }

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
                }
            }

            $order->update($updateData);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => $currentStatus,
                'status' => $newStatus,
                'note' => $note,
                'created_at' => now(),
            ]);
        });

        $order->load(['items.variant.product', 'statusHistories.user']);

        return response()->json([
            'message' => 'Cập nhật trạng thái đơn hàng thành công.',
            'data' => $order,
        ]);
    }

    /**
     * Resend order confirmation email manually by Admin.
     */
    public function resendConfirmationEmail(Request $request, int|string $id): JsonResponse
    {
        $order = Order::with(['items.variant.product', 'shippingMethod', 'payments', 'coupon', 'user'])
            ->findOrFail($id);

        $result = app(\App\Services\OrderMailService::class)->resendConfirmationEmail($order);
        $statusCode = $result['success'] ? 200 : 422;

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'recipient' => $result['recipient'],
                'confirmation_email_status' => $order->fresh()->confirmation_email_status,
                'confirmation_email_sent_at' => $order->fresh()->confirmation_email_sent_at,
            ],
        ], $statusCode);
    }

    /**
     * Retrieve audit email logs for an order.
     */
    public function emailLogs(Request $request, int|string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $logs = app(\App\Services\OrderMailService::class)->getEmailHistory($order);

        return response()->json([
            'status' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Admin xác nhận đã nhận tiền chuyển khoản ngân hàng (VietQR).
     */
    public function confirmBankTransfer(Request $request, int|string $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn hàng này đã được xác nhận thanh toán trước đó.',
                'data' => $order,
            ]);
        }

        if ($order->payment_method !== 'bank_transfer') {
            return response()->json(['message' => 'Đơn hàng không sử dụng chuyển khoản ngân hàng.'], 422);
        }

        $userId = $request->user()->id;
        $adminName = $request->user()->name;
        $note = $request->input('note', "Quản trị viên {$adminName} xác nhận đã khớp lệnh chuyển khoản ngân hàng");
        $transNo = $request->input('transaction_no', 'VCB-ADM-'.now()->format('YmdHis'));

        DB::transaction(function () use ($order, $userId, $note, $transNo) {
            $fromStatus = $order->status;
            $payment = \App\Models\Payment::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'bank_transfer',
                ],
                [
                    'transaction_code' => 'PAY-BANK-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(14)),
                    'idempotency_key' => 'bank_order_'.$order->id.'_'.uniqid(),
                    'amount' => $order->total_amount,
                    'currency_code' => 'VND',
                ]
            );

            $payment->update([
                'status' => 'success',
                'gateway_transaction_id' => $transNo,
                'paid_at' => now(),
                'response_data' => ['note' => $note],
            ]);

            $newStatus = $fromStatus === 'pending' ? 'confirmed' : $fromStatus;
            $order->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
            ]);

            // Consume coupon
            if ($order->coupon_id) {
                $coupon = \App\Models\Coupon::whereKey($order->coupon_id)->lockForUpdate()->first();
                if ($coupon) {
                    $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                    $coupon->used_count += 1;
                    $coupon->save();
                }
                \App\Models\CouponRedemption::where('order_id', $order->id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'consumed']);
            }

            \App\Models\OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => $fromStatus,
                'status' => $newStatus,
                'note' => "Admin duyệt thanh toán VietQR: {$note} (Mã GD: {$transNo})",
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($order) {
                try {
                    app(\App\Services\OrderMailService::class)->sendOrderConfirmation($order);
                } catch (\Throwable) {}
            });
        });

        $order->load(['items.variant.product', 'statusHistories.user', 'payments']);

        return response()->json([
            'message' => 'Xác nhận thanh toán chuyển khoản ngân hàng thành công.',
            'data' => $order,
        ]);
    }
}
