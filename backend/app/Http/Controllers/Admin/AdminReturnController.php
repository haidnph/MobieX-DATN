<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::query()->with(['order:id,order_code,total_amount', 'user:id,name,email', 'items.orderItem:id,product_name,variant_name,quantity,price']);
        if ($request->filled('status')) $query->where('status', $request->query('status'));
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn ($q) => $q->where('return_code', 'like', "%{$term}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$term}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }
        return response()->json($query->latest()->paginate(min(max((int) $request->query('per_page', 15), 1), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ReturnRequest::with(['order.items', 'user:id,name,email', 'items.orderItem', 'histories.user:id,name'])->findOrFail($id)]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'approved', ['pending'], 'Yêu cầu đã được duyệt.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'min:3', 'max:1000']]);
        return $this->transition($request, $id, 'rejected', ['pending', 'approved'], $data['admin_note'], ['admin_note' => $data['admin_note'], 'rejected_at' => now()]);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'received', ['approved', 'shipping_back'], 'Cửa hàng đã xác nhận nhận sản phẩm.', ['received_at' => now()]);
    }

    public function inspect(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'exists:return_items,id'],
            'items.*.inspection_condition' => ['required_with:items', 'string', 'max:50'],
            'items.*.inspection_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $return = ReturnRequest::findOrFail($id);
        if (! in_array($return->status, ['received', 'inspecting'], true)) throw ValidationException::withMessages(['status' => 'Yêu cầu phải được nhận hàng trước khi kiểm tra.']);
        DB::transaction(function () use ($request, $return, $data) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            foreach ($data['items'] ?? [] as $line) {
                $item = $locked->items()->whereKey($line['id'])->firstOrFail();
                $item->update(['inspection_condition' => $line['inspection_condition'], 'inspection_note' => $line['inspection_note'] ?? null]);
            }
            $locked->update(['status' => 'inspecting', 'admin_note' => $data['admin_note'], 'inspected_at' => now()]);
            $locked->histories()->create(['user_id' => $request->user()->id, 'from_status' => $from, 'status' => 'inspecting', 'note' => $data['admin_note']]);
        });
        return response()->json(['message' => 'Đã lưu kết quả kiểm tra sản phẩm.', 'data' => $return->fresh()->load(['items.orderItem', 'histories'])]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        return $this->transition($request, $id, 'completed', ['inspecting'], $data['admin_note'] ?? 'Đã hoàn tất quy trình trả hàng; không phát sinh hoàn tiền.', ['completed_at' => now(), 'admin_note' => $data['admin_note'] ?? 'Đã hoàn tất trả hàng. Không hoàn tiền.']);
    }

    private function transition(Request $request, int $id, string $to, array $allowed, string $note, array $extra = []): JsonResponse
    {
        $return = ReturnRequest::findOrFail($id);
        if (! in_array($return->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Không thể chuyển yêu cầu từ trạng thái hiện tại sang trạng thái này.']);
        }
        DB::transaction(function () use ($request, $return, $to, $note, $extra) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $locked->fill(['status' => $to, ...$extra])->save();
            $locked->histories()->create(['user_id' => $request->user()->id, 'from_status' => $from, 'status' => $to, 'note' => $note]);
        });
        return response()->json(['message' => $note, 'data' => $return->fresh()->load(['order', 'user:id,name,email', 'items.orderItem', 'histories.user:id,name'])]);
    }
}
