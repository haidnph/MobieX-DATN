<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReturnController extends Controller
{
    /**
     * Danh sách yêu cầu đổi trả phía quản trị.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::query()->with([
            'order:id,order_code,total_amount,payment_method,payment_status',
            'user:id,name,email',
            'items.orderItem:id,product_name,variant_name,quantity,price',
        ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('return_type')) {
            $query->where('return_type', (string) $request->query('return_type'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($q) use ($term): void {
                $q->where('return_code', 'like', "%{$term}%")
                    ->orWhere('refund_transaction_id', 'like', "%{$term}%")
                    ->orWhere('return_tracking_code', 'like', "%{$term}%")
                    ->orWhere('replacement_tracking_code', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$term}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                    );
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $rows = $query->latest('id')->paginate($perPage);

        return response()->json($rows);
    }

    /**
     * Chi tiết yêu cầu đổi trả.
     */
    public function show(int $id): JsonResponse
    {
        $return = ReturnRequest::with([
            'order.items.variant.product',
            'order.payments',
            'user:id,name,email',
            'items.orderItem.variant.product',
            'histories.user:id,name',
        ])->findOrFail($id);

        return response()->json(['data' => $return]);
    }

    /**
     * Admin chấp thuận yêu cầu đổi trả.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = $validated['admin_note'] ?: 'Cửa hàng đã duyệt yêu cầu đổi/trả. Khách hàng vui lòng đóng gói và gửi sản phẩm về kho.';

        return $this->transition(
            $request,
            $id,
            'approved',
            ['pending'],
            $note,
            ['approved_at' => now(), 'admin_note' => $note]
        );
    }

    /**
     * Admin từ chối yêu cầu đổi trả.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $note = 'Cửa hàng từ chối yêu cầu: '.$validated['admin_note'];

        return $this->transition(
            $request,
            $id,
            'rejected',
            ['pending', 'approved'],
            $note,
            ['rejected_at' => now(), 'admin_note' => $validated['admin_note']]
        );
    }

    /**
     * Cửa hàng xác nhận đã nhận được kiện hàng khách gửi về kho.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = $validated['admin_note'] ?: 'Kho MobieX đã nhận được kiện hàng hoàn trả từ khách hàng. Bắt đầu thẩm định sản phẩm.';

        return $this->transition(
            $request,
            $id,
            'received',
            ['approved', 'shipping_back'],
            $note,
            ['received_at' => now()]
        );
    }

    /**
     * Lưu kết quả thẩm định / kiểm tra tình trạng từng sản phẩm hoàn trả.
     */
    public function inspect(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'exists:return_request_items,id'],
            'items.*.inspection_condition' => ['required_with:items', 'string', 'max:50'],
            'items.*.inspection_note' => ['nullable', 'string', 'max:1000'],
            'items.*.is_restocked' => ['nullable', 'boolean'],
        ]);

        $return = ReturnRequest::findOrFail($id);

        if (! in_array($return->status, ['received', 'inspecting'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Kiện hàng phải được xác nhận nhận hàng tại kho trước khi tiến hành thẩm định.',
            ]);
        }

        DB::transaction(function () use ($request, $return, $data) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            foreach ($data['items'] ?? [] as $line) {
                $item = $locked->items()->whereKey($line['id'])->firstOrFail();
                $item->update([
                    'inspection_condition' => $line['inspection_condition'],
                    'inspection_note' => $line['inspection_note'] ?? null,
                    'is_restocked' => (bool) ($line['is_restocked'] ?? false),
                ]);
            }

            $locked->update([
                'status' => 'inspecting',
                'admin_note' => $data['admin_note'],
                'inspected_at' => now(),
            ]);

            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => $from,
                'status' => 'inspecting',
                'note' => 'Thẩm định tình trạng sản phẩm: '.$data['admin_note'],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Đã lưu kết quả kiểm tra thẩm định sản phẩm.',
            'data' => $return->fresh()->load(['order', 'items.orderItem.variant.product', 'histories.user:id,name']),
        ]);
    }

    /**
     * Hoàn tất yêu cầu đổi trả (Xử lý hoàn tiền hoặc gửi đổi hàng mới).
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $return = ReturnRequest::with(['order', 'items.orderItem.variant'])->findOrFail($id);

        if (! in_array($return->status, ['inspecting', 'received'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có thể hoàn tất khi yêu cầu đã được nhận hàng và thẩm định.',
            ]);
        }

        if ($return->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Yêu cầu đổi trả này đã được hoàn tất trước đó.',
            ]);
        }

        if ($return->return_type === 'replacement') {
            $validated = $request->validate([
                'replacement_carrier' => ['required', 'string', 'max:100'],
                'replacement_tracking_code' => ['required', 'string', 'max:120'],
                'admin_note' => ['nullable', 'string', 'max:1000'],
            ]);

            DB::transaction(function () use ($request, $return, $validated): void {
                $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
                $from = $locked->status;

                // Restock inventory for items marked restockable
                $this->restockEligibleItems($locked, (int) $request->user()->id);

                $note = 'Đã gửi sản phẩm đổi mới qua '.$validated['replacement_carrier'].' (Mã vận đơn: '.$validated['replacement_tracking_code'].').';
                if (! empty($validated['admin_note'])) {
                    $note .= ' Ghi chú: '.$validated['admin_note'];
                }

                $locked->update([
                    'status' => 'completed',
                    'replacement_carrier' => $validated['replacement_carrier'],
                    'replacement_tracking_code' => $validated['replacement_tracking_code'],
                    'admin_note' => $note,
                    'completed_at' => now(),
                ]);

                $locked->histories()->create([
                    'user_id' => $request->user()->id,
                    'from_status' => $from,
                    'status' => 'completed',
                    'note' => $note,
                    'created_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Đã hoàn tất quy trình đổi hàng và cập nhật vận đơn gửi mới.',
                'data' => $return->fresh()->load(['order', 'items.orderItem', 'histories.user:id,name']),
            ]);
        }

        // Return type is 'refund'
        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0', 'max:'.(float) $return->order->total_amount],
            'refund_method' => ['required', 'string', Rule::in(['original_gateway', 'bank_transfer', 'cash', 'other'])],
            'refund_transaction_id' => ['required', 'string', 'max:100'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $return, $validated): void {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($locked->status === 'completed' || $locked->refund_status === 'refunded') {
                throw ValidationException::withMessages([
                    'status' => 'Giao dịch hoàn tiền cho yêu cầu này đã được thực hiện.',
                ]);
            }

            // Restock inventory for items marked restockable
            $this->restockEligibleItems($locked, (int) $request->user()->id);

            $refundAmount = (float) $validated['refund_amount'];
            $refundMethod = $validated['refund_method'];
            $refundTxId = $validated['refund_transaction_id'];
            $note = 'Đã xác nhận hoàn tiền '.number_format($refundAmount, 0, ',', '.')." VND qua {$refundMethod} (Mã GD: {$refundTxId}).";
            if (! empty($validated['admin_note'])) {
                $note .= ' Ghi chú: '.$validated['admin_note'];
            }

            $locked->update([
                'status' => 'completed',
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
                'refund_status' => 'refunded',
                'refund_transaction_id' => $refundTxId,
                'refunded_at' => now(),
                'completed_at' => now(),
                'admin_note' => $note,
            ]);

            // Synchronize with Order & Payment
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->first();
            if ($order) {
                // Update order payment status to refunded
                $order->update(['payment_status' => 'refunded']);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'from_status' => $order->status,
                    'status' => $order->status,
                    'note' => 'Hoàn tiền '.number_format($refundAmount, 0, ',', '.')." VND theo yêu cầu đổi trả {$locked->return_code} (Mã GD: {$refundTxId}).",
                    'created_at' => now(),
                ]);

                // Update online payment if present
                $payment = Payment::query()
                    ->where('order_id', $order->id)
                    ->whereIn('status', ['success', 'settled'])
                    ->orderByDesc('id')
                    ->first();

                if ($payment && ! $payment->refunded_at) {
                    $payment->update([
                        'status' => 'refunded',
                        'refunded_at' => now(),
                        'refund_transaction_id' => $refundTxId,
                        'refund_note' => "Hoàn tiền theo yêu cầu đổi trả {$locked->return_code}",
                        'refund_confirmed_by' => $request->user()->id,
                    ]);
                }
            }

            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => $from,
                'status' => 'completed',
                'note' => $note,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Đã hoàn tất xác nhận hoàn tiền và đồng bộ trạng thái đơn hàng.',
            'data' => $return->fresh()->load(['order', 'items.orderItem', 'histories.user:id,name']),
        ]);
    }

    /**
     * Chuyển trạng thái tổng quát kèm ghi chú và lịch sử thao tác.
     *
     * @param  array<string>  $allowedFrom
     * @param  array<string, mixed>  $extra
     */
    private function transition(
        Request $request,
        int $id,
        string $to,
        array $allowedFrom,
        string $note,
        array $extra = []
    ): JsonResponse {
        $return = ReturnRequest::findOrFail($id);

        if (! in_array($return->status, $allowedFrom, true)) {
            throw ValidationException::withMessages([
                'status' => 'Không thể chuyển yêu cầu từ trạng thái hiện tại ('.$return->status.') sang '.$to.'.',
            ]);
        }

        DB::transaction(function () use ($request, $return, $to, $note, $extra): void {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            $locked->fill(array_merge(['status' => $to], $extra))->save();

            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => $from,
                'status' => $to,
                'note' => $note,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => $note,
            'data' => $return->fresh()->load([
                'order',
                'user:id,name,email',
                'items.orderItem',
                'histories.user:id,name',
            ]),
        ]);
    }

    /**
     * Nhập kho lại đối với các sản phẩm được đánh dấu is_restocked an toàn (chống nhập trùng).
     */
    private function restockEligibleItems(ReturnRequest $returnRequest, int $adminUserId): void
    {
        $items = $returnRequest->items()->with('orderItem')->get();

        foreach ($items as $item) {
            // Only restock if explicitly marked restockable and not already restocked
            if ($item->is_restocked && ! $item->restocked_at) {
                $variantId = $item->orderItem?->product_variant_id;
                if (! $variantId) {
                    continue;
                }

                $inventory = Inventory::firstOrCreate(
                    ['product_variant_id' => $variantId],
                    ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                );

                $inventory->increment('on_hand_quantity', $item->quantity);

                $idempotencyKey = 'return_'.$returnRequest->id.'_item_'.$item->id.'_restock';
                $existingTx = InventoryTransaction::where('idempotency_key', $idempotencyKey)->first();

                if (! $existingTx) {
                    InventoryTransaction::create([
                        'product_variant_id' => $variantId,
                        'user_id' => $adminUserId,
                        'type' => 'import',
                        'quantity_delta' => $item->quantity,
                        'reserved_delta' => 0,
                        'reference_type' => 'return_request',
                        'reference_id' => $returnRequest->id,
                        'idempotency_key' => $idempotencyKey,
                        'note' => "Nhập kho lại từ đơn đổi trả {$returnRequest->return_code} (SL: {$item->quantity})",
                        'created_at' => now(),
                    ]);
                }

                $item->update(['restocked_at' => now()]);
            }
        }
    }
}
