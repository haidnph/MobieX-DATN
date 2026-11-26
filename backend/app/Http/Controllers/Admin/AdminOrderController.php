<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\FlashSaleOrderService;
use App\Services\OrderMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function confirmRefund(Request $request, int|string $paymentId): JsonResponse
    {
        $validated = $request->validate([
            'refund_transaction_id' => ['required', 'string', 'max:100'],
            'refund_amount' => ['required', 'numeric', 'gt:0'],
            'refund_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $orderId = Payment::query()->whereKey($paymentId)->value('order_id');
        if (! $orderId) {
            abort(404);
        }

        $payment = DB::transaction(function () use ($request, $paymentId, $validated, $orderId): Payment {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ((int) $payment->order_id !== (int) $order->id) {
                abort(409, 'Payment order changed during refund reconciliation.');
            }

            if (round((float) $validated['refund_amount'], 2) !== round((float) $payment->amount, 2)) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Chỉ hỗ trợ đối soát hoàn toàn bộ số tiền giao dịch đã capture.',
                ]);
            }

            if ($payment->status === 'refunded') {
                if ($payment->refund_transaction_id === $validated['refund_transaction_id']) {
                    return $payment;
                }

                throw ValidationException::withMessages([
                    'refund_transaction_id' => 'Giao dịch này đã được đối soát hoàn tiền với mã khác.',
                ]);
            }

            if (PaymentRefund::query()->where('payment_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'unknown'])->exists()) {
                throw ValidationException::withMessages([
                    'payment' => 'Giao dịch đang có yêu cầu hoàn qua cổng. Hãy truy vấn kết quả trước khi đối soát thủ công.',
                ]);
            }

            $isDuplicateCaptureAwaitingReconciliation = $payment->refund_required
                && $payment->gateway_succeeded_at !== null;
            $isNormalSettledPayment = $payment->status === 'success'
                && $order->payment_status === 'paid';

            if (! $isDuplicateCaptureAwaitingReconciliation && ! $isNormalSettledPayment) {
                throw ValidationException::withMessages([
                    'payment' => 'Chỉ giao dịch đã thu tiền hoặc capture trùng đang chờ đối soát mới được xác nhận hoàn tiền.',
                ]);
            }

            $duplicateReference = Payment::query()
                ->where('method', $payment->method)
                ->where('refund_transaction_id', $validated['refund_transaction_id'])
                ->whereKeyNot($payment->getKey())
                ->exists();

            if ($duplicateReference) {
                throw ValidationException::withMessages([
                    'refund_transaction_id' => 'Mã giao dịch hoàn tiền đã được ghi nhận cho một giao dịch khác.',
                ]);
            }

            $responseData = $payment->response_data ?? [];
            $responseData['refund_reconciliation'] = [
                'transaction_id' => $validated['refund_transaction_id'],
                'amount' => number_format((float) $validated['refund_amount'], 2, '.', ''),
                'note' => $validated['refund_note'],
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now()->toISOString(),
                'confirmation_type' => 'manual_after_external_refund',
            ];

            $payment->update([
                'status' => 'refunded',
                'refund_required' => false,
                'refunded_at' => now(),
                'refund_transaction_id' => $validated['refund_transaction_id'],
                'refund_note' => $validated['refund_note'],
                'refund_confirmed_by' => $request->user()->id,
                'response_data' => $responseData,
            ]);

            $hasOtherUnrefundedCapture = Payment::query()
                ->where('order_id', $order->id)
                ->whereKeyNot($payment->getKey())
                ->where(function ($query): void {
                    $query->where('status', 'success')
                        ->orWhere('refund_required', true);
                })
                ->exists();

            if (! $hasOtherUnrefundedCapture && $order->payment_status === 'paid') {
                $order->update(['payment_status' => 'refunded']);
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'from_status' => $order->status,
                    'status' => $order->status,
                    'note' => 'Payment refund reconciled: '.$validated['refund_note'],
                    'created_at' => now(),
                ]);
            }

            return $payment->fresh();
        });

        $payment->makeVisible([
            'idempotency_key',
            'response_data',
            'refund_required',
            'gateway_succeeded_at',
            'refund_note',
            'refund_confirmed_by',
        ]);

        return response()->json([
            'message' => 'Đã ghi nhận đối soát hoàn tiền. Trạng thái này chỉ được cập nhật sau khi hoàn tiền thật trên cổng thanh toán.',
            'data' => $payment,
        ]);
    }

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
            'payments.refunds',
            'payments.webhookEvents',
            'coupon',
            'emailLogs',
            'returnRequests.items.orderItem',
        ])->findOrFail($id);

        $order->payments->each(
            static fn (Payment $payment) => $payment->makeVisible([
                'idempotency_key',
                'response_data',
                'refund_required',
                'gateway_succeeded_at',
                'refund_note',
                'refund_confirmed_by',
            ])
        );

        return response()->json([
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['confirmed', 'processing', 'shipping', 'received', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:255'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newStatus = $validated['status'];
        $order = Order::with('items.variant')->findOrFail($id);
        $currentStatus = $order->status;

        if ($newStatus === 'cancelled' && $order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Không thể hủy đơn đã thanh toán trước khi xử lý hoàn tiền.',
            ], 422);
        }

        if (
            $newStatus !== 'cancelled'
            && $order->payment_method !== 'cod'
            && $order->payment_status !== 'paid'
        ) {
            return response()->json([
                'message' => 'Chỉ có thể xử lý giao hàng sau khi đơn thanh toán online được xác nhận đã thanh toán.',
            ], 422);
        }

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
            'shipping' => [],
            'received' => ['delivered'],
        ];

        if (! isset($allowedTransitions[$currentStatus]) || ! in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
            return response()->json([
                'message' => "Cannot move {$currentStatus} to {$newStatus}; customer must confirm receipt before delivery can be completed.",
            ], 422);
        }

        $userId = $request->user()->id;
        $note = $validated['note'] ?? "Cập nhật trạng thái sang {$newStatus}";

        DB::transaction(function () use ($order, $currentStatus, $newStatus, $userId, $note, $validated) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with('items.variant')
                ->firstOrFail();

            if ($order->status !== $currentStatus) {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái đơn hàng vừa được cập nhật. Hãy tải lại và thử lại.',
                ]);
            }

            if ($newStatus === 'cancelled' && $order->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hủy đơn đã thanh toán trước khi xử lý hoàn tiền.',
                ]);
            }

            if (
                $newStatus !== 'cancelled'
                && $order->payment_method !== 'cod'
                && $order->payment_status !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Đơn thanh toán online chưa được xác nhận đã thanh toán.',
                ]);
            }

            $updateData = ['status' => $newStatus];

            if ($newStatus === 'shipping') {
                // Dispatch event: Goods leave warehouse
                // Decrement on_hand_quantity and decrement reserved_quantity
                foreach ($order->items as $item) {
                    $inventory = Inventory::where('product_variant_id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $inventory
                        || (int) $inventory->on_hand_quantity < (int) $item->quantity
                        || (int) $inventory->reserved_quantity < (int) $item->quantity
                    ) {
                        throw ValidationException::withMessages([
                            'status' => ["Sản phẩm {$item->sku} không đủ tồn kho hoặc số lượng giữ chỗ để xuất kho."],
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

                if ($order->payment_method === 'cod' && $order->payment_status !== 'paid') {
                    $codPayment = Payment::firstOrCreate(
                        ['order_id' => $order->id, 'method' => 'cod'],
                        [
                            'transaction_code' => 'PAY-COD-ORDER-'.$order->id,
                            'idempotency_key' => 'cod_order_'.$order->id,
                            'amount' => $order->total_amount,
                            'currency_code' => $order->currency_code,
                        ],
                    );
                    $codPayment->update([
                        'status' => 'success',
                        'paid_at' => now(),
                    ]);
                    $updateData['payment_status'] = 'paid';
                    app(FlashSaleOrderService::class)->consumeOrderReservations($order);
                }

                // Consume coupon reservation when order is delivered successfully
                if ($order->coupon_id) {
                    $redemption = CouponRedemption::query()
                        ->where('order_id', $order->id)
                        ->where('status', 'reserved')
                        ->lockForUpdate()
                        ->first();

                    if ($redemption) {
                        $redemption->update(['status' => 'consumed']);

                        $coupon = Coupon::whereKey($order->coupon_id)
                            ->lockForUpdate()
                            ->first();

                        if ($coupon) {
                            $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                            $coupon->used_count += 1;
                            $coupon->save();
                        }
                    }
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

                        if (! $inventory || (int) $inventory->reserved_quantity < (int) $item->quantity) {
                            throw ValidationException::withMessages([
                                'status' => ["Không thể hủy đơn vì số lượng giữ chỗ kho cho {$item->sku} không khớp."],
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
                            'unit_cost' => $item->cost_price_snapshot ?? $item->variant?->cost_price,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'idempotency_key' => "order_{$order->id}_var_{$item->product_variant_id}_release_admin",
                            'note' => 'Hoàn trả tồn kho do đơn hàng '.$order->order_code.' bị quản trị viên hủy',
                            'created_at' => now(),
                        ]);
                    }

                    app(FlashSaleOrderService::class)->releaseOrderReservations($order);

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

            if ($newStatus === 'cancelled') {
                Payment::query()
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => $currentStatus,
                'status' => $newStatus,
                'note' => $note,
                'created_at' => now(),
            ]);
        });

        $order->refresh()->load(['items.variant.product', 'statusHistories.user']);

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

        $result = app(OrderMailService::class)->resendConfirmationEmail($order);
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
        $logs = app(OrderMailService::class)->getEmailHistory($order);

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
        $userId = $request->user()->id;
        $adminName = $request->user()->name;
        $note = $request->input('note', "Quản trị viên {$adminName} xác nhận đã khớp lệnh chuyển khoản ngân hàng");
        $transNo = $request->input('transaction_no', 'VCB-ADM-'.now()->format('YmdHis'));

        $result = DB::transaction(function () use ($id, $userId, $note, $transNo) {
            $order = Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $order->load('items');

            if ($order->payment_status === 'paid') {
                return ['order' => $order, 'already_paid' => true];
            }

            if ($order->payment_method !== 'bank_transfer') {
                throw ValidationException::withMessages([
                    'payment_method' => 'Đơn hàng không sử dụng chuyển khoản ngân hàng.',
                ]);
            }

            if (in_array($order->status, ['cancelled', 'delivered'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể xác nhận thanh toán cho đơn hàng đã kết thúc.',
                ]);
            }

            $fromStatus = $order->status;
            $payment = Payment::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'bank_transfer',
                ],
                [
                    'transaction_code' => 'PAY-BANK-'.Str::upper(Str::random(14)),
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

            app(FlashSaleOrderService::class)->consumeOrderReservations($order);

            // Consume coupon
            if ($order->coupon_id) {
                $coupon = Coupon::whereKey($order->coupon_id)->lockForUpdate()->first();
                if ($coupon) {
                    $coupon->reserved_count = max(0, (int) $coupon->reserved_count - 1);
                    $coupon->used_count += 1;
                    $coupon->save();
                }
                CouponRedemption::where('order_id', $order->id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'consumed']);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => $fromStatus,
                'status' => $newStatus,
                'note' => "Admin duyệt thanh toán VietQR: {$note} (Mã GD: {$transNo})",
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($order) {
                try {
                    app(OrderMailService::class)->sendOrderConfirmation($order);
                } catch (\Throwable) {
                }
            });

            return ['order' => $order->fresh(['items', 'payments']), 'already_paid' => false];
        });

        if ($result['already_paid']) {
            return response()->json([
                'message' => 'Đơn hàng này đã được xác nhận thanh toán trước đó.',
                'data' => $result['order'],
            ]);
        }

        $order = $result['order'];
        $order->load(['items.variant.product', 'statusHistories.user', 'payments']);

        return response()->json([
            'message' => 'Xác nhận thanh toán chuyển khoản ngân hàng thành công.',
            'data' => $order,
        ]);
    }
}
