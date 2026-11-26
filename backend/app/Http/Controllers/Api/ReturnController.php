<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    /**
     * Danh sách yêu cầu đổi trả của khách hàng.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'order:id,order_code,status,total_amount,payment_method,payment_status',
                'items.orderItem:id,product_name,variant_name,image_path,price,quantity',
                'histories.user:id,name',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $rows = $query->latest('id')->paginate($perPage);

        return response()->json($rows);
    }

    /**
     * Chi tiết yêu cầu đổi trả của khách hàng.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $return = ReturnRequest::query()
            ->with([
                'order.items',
                'items.orderItem.variant.product',
                'histories.user:id,name',
            ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $return]);
    }

    /**
     * Khách hàng gửi yêu cầu đổi trả sản phẩm.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:100'],
            'return_type' => ['required', 'string', Rule::in(['refund', 'replacement'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $created = DB::transaction(function () use ($request, $validated) {
            $order = Order::query()->whereKey($validated['order_id'])->lockForUpdate()->firstOrFail();

            if ((int) $order->user_id !== (int) $request->user()->id) {
                abort(403, 'Đơn hàng không thuộc tài khoản của bạn.');
            }

            if (! in_array($order->status, ['delivered', 'received'], true)) {
                throw ValidationException::withMessages([
                    'order_id' => 'Chỉ đơn hàng đã giao thành công hoặc đã nhận hàng mới được gửi yêu cầu đổi trả.',
                ]);
            }

            // Return policy check: default within 7 days of delivery / receipt
            $deliveryDate = $order->delivered_at ?? $order->updated_at ?? $order->created_at;
            $returnWindowDays = (int) config('orders.return_window_days', 7);
            if ($deliveryDate && Carbon::parse($deliveryDate)->diffInDays(now()) > $returnWindowDays) {
                throw ValidationException::withMessages([
                    'order_id' => "Đơn hàng đã vượt quá thời hạn đổi trả cho phép ({$returnWindowDays} ngày kể từ ngày nhận hàng).",
                ]);
            }

            // Validate that requested items belong to this order
            $orderItems = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereIn('id', collect($validated['items'])->pluck('order_item_id'))
                ->get()
                ->keyBy('id');

            if ($orderItems->count() !== count($validated['items'])) {
                throw ValidationException::withMessages([
                    'items' => 'Có sản phẩm không thuộc đơn hàng đã chọn.',
                ]);
            }

            // Check unreturned available quantity for each item
            $calculatedRefundTotal = 0;
            foreach ($validated['items'] as $line) {
                $orderItem = $orderItems->get($line['order_item_id']);

                $alreadyRequested = (int) DB::table('return_request_items')
                    ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
                    ->where('return_request_items.order_item_id', $orderItem->id)
                    ->whereNotIn('return_requests.status', ['rejected', 'cancelled'])
                    ->sum('return_request_items.quantity');

                $availableQuantity = (int) $orderItem->quantity - $alreadyRequested;
                if ((int) $line['quantity'] > $availableQuantity) {
                    throw ValidationException::withMessages([
                        'items' => "Số lượng yêu cầu của sản phẩm '{$orderItem->product_name}' vượt quá số lượng còn được phép đổi trả ({$availableQuantity}).",
                    ]);
                }

                $calculatedRefundTotal += ((float) $orderItem->price) * (int) $line['quantity'];
            }

            // Handle evidence photos upload
            $attachments = [];
            foreach ($request->file('photos', []) as $photo) {
                $attachments[] = $photo->store('returns', 'public');
            }

            $returnType = $validated['return_type'];
            $estimatedRefund = ($returnType === 'refund') ? min($calculatedRefundTotal, (float) $order->total_amount) : 0;

            $return = ReturnRequest::create([
                'return_code' => 'RTN-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'reason' => $validated['reason'],
                'return_type' => $returnType,
                'refund_amount' => $estimatedRefund > 0 ? $estimatedRefund : null,
                'refund_status' => ($returnType === 'refund') ? 'pending' : null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'customer_note' => $validated['customer_note'] ?? null,
                'attachments' => $attachments,
            ]);

            foreach ($validated['items'] as $line) {
                $orderItem = $orderItems->get($line['order_item_id']);
                $itemRefund = ((float) $orderItem->price) * (int) $line['quantity'];

                $return->items()->create([
                    'order_item_id' => $line['order_item_id'],
                    'quantity' => $line['quantity'],
                    'refund_amount' => ($returnType === 'refund') ? $itemRefund : null,
                ]);
            }

            $typeLabel = $returnType === 'replacement' ? 'Đổi hàng mới' : 'Hoàn tiền';
            $return->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => null,
                'status' => 'pending',
                'note' => "Khách hàng gửi yêu cầu đổi/trả hàng. Phương án mong muốn: {$typeLabel}. Lý do: {$validated['reason']}",
                'created_at' => now(),
            ]);

            return $return;
        });

        return response()->json([
            'message' => 'Đã gửi yêu cầu đổi trả thành công. Cửa hàng sẽ thẩm định và phản hồi trong thời gian sớm nhất.',
            'data' => $created->load(['order', 'items.orderItem', 'histories']),
        ], 201);
    }

    /**
     * Khách hàng cập nhật thông tin vận chuyển hàng trả về kho.
     */
    public function shippingBack(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_carrier' => ['required', 'string', 'max:100'],
            'return_tracking_code' => ['required', 'string', 'max:120'],
        ]);

        $return = ReturnRequest::where('user_id', $request->user()->id)->findOrFail($id);

        if ($return->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ yêu cầu đã được cửa hàng duyệt mới có thể cập nhật thông tin vận chuyển trả hàng.',
            ]);
        }

        DB::transaction(function () use ($request, $return, $validated) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái yêu cầu đã thay đổi. Vui lòng tải lại trang.',
                ]);
            }

            $locked->update([
                'shipping_carrier' => $validated['shipping_carrier'],
                'return_tracking_code' => $validated['return_tracking_code'],
                'status' => 'shipping_back',
            ]);

            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => 'approved',
                'status' => 'shipping_back',
                'note' => "Khách hàng đã gửi sản phẩm về kho qua {$validated['shipping_carrier']} (Mã vận đơn: {$validated['return_tracking_code']}).",
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Đã cập nhật thông tin gửi hàng về cửa hàng.',
            'data' => $return->fresh()->load(['order', 'items.orderItem', 'histories']),
        ]);
    }

    /**
     * Khách hàng hủy yêu cầu khi đang ở trạng thái pending.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $return = ReturnRequest::where('user_id', $request->user()->id)->findOrFail($id);

        if ($return->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có thể hủy yêu cầu khi đang ở trạng thái chờ duyệt.',
            ]);
        }

        DB::transaction(function () use ($request, $return) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái yêu cầu đã thay đổi. Không thể hủy lúc này.',
                ]);
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => 'pending',
                'status' => 'cancelled',
                'note' => 'Khách hàng đã chủ động hủy yêu cầu đổi/trả hàng.',
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Đã hủy yêu cầu đổi trả thành công.',
            'data' => $return->fresh()->load(['order', 'items.orderItem', 'histories']),
        ]);
    }
}
