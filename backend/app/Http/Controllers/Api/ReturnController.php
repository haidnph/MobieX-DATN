<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnHistory;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = ReturnRequest::query()->where('user_id', $request->user()->id)
            ->with(['order:id,order_code,status', 'items.orderItem:id,product_name,variant_name,image_path,price,quantity', 'histories.user:id,name'])
            ->latest()->paginate(min(max((int) $request->query('per_page', 10), 1), 50));
        return response()->json($rows);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $return = ReturnRequest::with(['order', 'items.orderItem', 'histories.user:id,name'])
            ->where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json(['data' => $return]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $created = DB::transaction(function () use ($request, $validated) {
            $order = Order::query()->whereKey($validated['order_id'])->lockForUpdate()->firstOrFail();
            if ((int) $order->user_id !== (int) $request->user()->id) abort(403, 'Đơn hàng không thuộc tài khoản của bạn.');
            if (! in_array($order->status, ['delivered', 'received'], true)) {
                throw ValidationException::withMessages(['order_id' => 'Chỉ đơn hàng đã giao thành công hoặc đã nhận hàng mới được yêu cầu trả hàng.']);
            }

            $orderItems = OrderItem::query()->where('order_id', $order->id)
                ->whereIn('id', collect($validated['items'])->pluck('order_item_id'))->get()->keyBy('id');
            if ($orderItems->count() !== count($validated['items'])) {
                throw ValidationException::withMessages(['items' => 'Có sản phẩm không thuộc đơn hàng đã chọn.']);
            }

            foreach ($validated['items'] as $line) {
                $orderItem = $orderItems->get($line['order_item_id']);

$alreadyRequested = DB::table('return_request_items')
    ->join(
        'return_requests',
        'return_requests.id',
        '=',
        'return_request_items.return_request_id'
    )
    ->where('return_request_items.order_item_id', $orderItem->id)
    ->whereNotIn('return_requests.status', ['rejected', 'cancelled'])
    ->sum('return_request_items.quantity');
                if ((int) $line['quantity'] + (int) $alreadyRequested > (int) $orderItem->quantity) {
                    throw ValidationException::withMessages(['items' => "Số lượng trả của sản phẩm {$orderItem->product_name} vượt quá số lượng còn được phép trả."]);
                }
            }

            $attachments = [];
            foreach ($request->file('photos', []) as $photo) {
                $attachments[] = $photo->store('returns', 'public');
            }

            $return = ReturnRequest::create([
                'return_code' => 'RTN-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'reason' => $validated['reason'],
                'description' => $validated['description'] ?? null,
                'customer_note' => $validated['customer_note'] ?? null,
                'attachments' => $attachments,
            ]);

            foreach ($validated['items'] as $line) {
                $return->items()->create([
                    'order_item_id' => $line['order_item_id'],
                    'quantity' => $line['quantity'],
                ]);
            }
            $return->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => null,
                'status' => 'pending',
                'note' => 'Khách hàng gửi yêu cầu trả hàng.',
            ]);
            return $return;
        });

        return response()->json(['message' => 'Đã gửi yêu cầu trả hàng. Vui lòng chờ cửa hàng xác nhận.', 'data' => $created->load(['order', 'items.orderItem', 'histories'])], 201);
    }

    public function shippingBack(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_carrier' => ['required', 'string', 'max:100'],
            'return_tracking_code' => ['required', 'string', 'max:120'],
        ]);
        $return = ReturnRequest::where('user_id', $request->user()->id)->findOrFail($id);
        if ($return->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Chỉ yêu cầu đã được duyệt mới có thể cập nhật vận đơn.']);
        }
        DB::transaction(function () use ($request, $return, $validated) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Trạng thái yêu cầu đã thay đổi. Vui lòng tải lại trang.']);
            }
            $locked->update([...$validated, 'status' => 'shipping_back']);
            $locked->histories()->create([
                'user_id' => $request->user()->id,
                'from_status' => 'approved',
                'status' => 'shipping_back',
                'note' => 'Khách hàng đã cập nhật thông tin vận chuyển hàng trả về.',
            ]);
        });
        return response()->json(['message' => 'Đã cập nhật thông tin gửi hàng về cửa hàng.', 'data' => $return->fresh()->load(['order', 'items.orderItem', 'histories'])]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $return = ReturnRequest::where('user_id', $request->user()->id)->findOrFail($id);
        if ($return->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể hủy yêu cầu khi đang chờ duyệt.']);
        }
        $this->setStatus($return, 'cancelled', $request->user()->id, 'Khách hàng hủy yêu cầu trả hàng.', ['cancelled_at' => now()]);
        return response()->json(['message' => 'Đã hủy yêu cầu trả hàng.', 'data' => $return->fresh()]);
    }

    private function setStatus(ReturnRequest $return, string $status, int $userId, ?string $note, array $extra = []): void
    {
        DB::transaction(function () use ($return, $status, $userId, $note, $extra) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $locked->fill(['status' => $status, ...$extra])->save();
            $locked->histories()->create(['user_id' => $userId, 'from_status' => $from, 'status' => $status, 'note' => $note]);
        });
    }
}
