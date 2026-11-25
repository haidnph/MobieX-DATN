<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ReturnRequest;
use App\Models\ReturnStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReturnController extends Controller
{
    /**
     * Danh sách hoàn hàng.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::with([
            'order',
            'user',
            'items.orderItem',
            'histories.user',
        ])->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->where(function ($q) use ($search) {
                $q->where('return_code', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($order) use ($search) {
                        $order->where(
                            'order_code',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                strtolower((string) $request->query('status'))
            );
        }

        $perPage = min(
            max((int) $request->query('per_page', 15), 1),
            100
        );

        return response()->json(
            $query->paginate($perPage)
        );
    }

    /**
     * Chi tiết hoàn hàng.
     */
    public function show(int|string $id): JsonResponse
    {
        $return = ReturnRequest::with([
            'order.user',
            'items.orderItem',
            'items.variant.product',
            'histories.user',
        ])->findOrFail($id);

        return response()->json([
            'data' => $return,
        ]);
    }

    /**
     * Duyệt yêu cầu.
     */
    public function approve(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = $this->changeStatus(
            $id,
            'requested',
            'approved',
            $request->user()->id,
            $validated['admin_note']
                ?? 'Quản trị viên đã duyệt yêu cầu.'
        );

        return response()->json([
            'message' => 'Duyệt yêu cầu hoàn hàng thành công.',
            'data' => $return,
        ]);
    }

    /**
     * Từ chối yêu cầu.
     */
    public function reject(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $return = $this->changeStatus(
            $id,
            'requested',
            'rejected',
            $request->user()->id,
            $validated['reason']
        );

        return response()->json([
            'message' => 'Đã từ chối yêu cầu hoàn hàng.',
            'data' => $return,
        ]);
    }

    /**
     * Cập nhật khách đang gửi hàng.
     */
    public function shippingBack(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = $this->changeStatus(
            $id,
            'approved',
            'shipping_back',
            $request->user()->id,
            $validated['note'] ?? 'Khách đang gửi hàng trả.'
        );

        return response()->json([
            'message' => 'Cập nhật trạng thái gửi hàng thành công.',
            'data' => $return,
        ]);
    }

    /**
     * Cửa hàng xác nhận nhận hàng.
     */
    public function receive(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = DB::transaction(function () use (
            $id,
            $request,
            $validated
        ) {
            $return = ReturnRequest::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array(
                $return->status,
                ['approved', 'shipping_back'],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Yêu cầu chưa đủ điều kiện xác nhận nhận hàng.'
                    ],
                ]);
            }

            $oldStatus = $return->status;

            $return->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            $this->writeHistory(
                $return,
                $oldStatus,
                'received',
                $request->user()->id,
                $validated['note'] ?? 'Cửa hàng đã nhận hàng trả.'
            );

            return $return;
        });

        return response()->json([
            'message' => 'Xác nhận nhận hàng thành công.',
            'data' => $return,
        ]);
    }

    /**
     * Kiểm tra tình trạng sản phẩm.
     */
    public function inspect(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.return_item_id' => [
                'required',
                'integer',
                'distinct',
            ],

            'items.*.condition' => [
                'required',
                'in:sellable,damaged,incomplete',
            ],

            'items.*.restock_quantity' => [
                'required',
                'integer',
                'min:0',
            ],

            'items.*.inspection_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $return = DB::transaction(function () use (
            $id,
            $request,
            $validated
        ) {
            $return = ReturnRequest::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->status !== 'received') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Sản phẩm phải được tiếp nhận trước khi kiểm tra.'
                    ],
                ]);
            }

            $items = $return->items()
                ->lockForUpdate()
                ->get();

            $inputs = collect($validated['items'])
                ->keyBy('return_item_id');

            if ($inputs->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Phải kiểm tra đầy đủ tất cả sản phẩm.'
                    ],
                ]);
            }

            foreach ($items as $item) {
                $input = $inputs->get($item->id);

                if (! $input) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Thiếu kết quả kiểm tra sản phẩm.'
                        ],
                    ]);
                }

                $quantity = (int) $input['restock_quantity'];

                if ($quantity > (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Số lượng nhập kho vượt số lượng khách trả.'
                        ],
                    ]);
                }

                if (
                    $input['condition'] !== 'sellable'
                    && $quantity > 0
                ) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Hàng hỏng hoặc thiếu phụ kiện không được nhập kho bán được.'
                        ],
                    ]);
                }

                $item->update([
                    'condition' => $input['condition'],
                    'restock_quantity' => $quantity,
                    'inspection_note' => $input['inspection_note'] ?? null,
                ]);
            }

            $return->update([
                'status' => 'inspecting',
            ]);

            $this->writeHistory(
                $return,
                'received',
                'inspecting',
                $request->user()->id,
                $validated['note'] ?? 'Đã kiểm tra tình trạng hàng trả.'
            );

            return $return;
        });

        return response()->json([
            'message' => 'Kiểm tra sản phẩm thành công.',
            'data' => $return->load('items.orderItem'),
        ]);
    }

    /**
     * Hoàn tất hoàn hàng và nhập kho sản phẩm đạt điều kiện.
     *
     * Không hoàn tiền.
     */
    public function complete(
        Request $request,
        int|string $id
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = DB::transaction(function () use (
            $id,
            $request,
            $validated
        ) {
            $return = ReturnRequest::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->status !== 'inspecting') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Yêu cầu phải được kiểm tra trước khi hoàn tất.'
                    ],
                ]);
            }

            $items = $return->items()
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Không có sản phẩm để xử lý.'
                    ],
                ]);
            }

            foreach ($items as $item) {
                if ($item->condition === null) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Chưa kiểm tra đầy đủ sản phẩm.'
                        ],
                    ]);
                }

                $quantity = (int) $item->restock_quantity;

                if ($quantity === 0) {
                    continue;
                }

                if ($item->condition !== 'sellable') {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Sản phẩm không đủ điều kiện nhập kho.'
                        ],
                    ]);
                }

                $key = "return_{$return->id}_item_{$item->id}";

                // Kiểm tra giao dịch nhập kho đã tồn tại.
                $exists = InventoryTransaction::where(
                    'idempotency_key',
                    $key
                )->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'inventory' => [
                            'Giao dịch nhập kho đã được xử lý trước đó.'
                        ],
                    ]);
                }

                $inventory = Inventory::where(
                    'product_variant_id',
                    $item->product_variant_id
                )
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'inventory' => [
                            'Không tìm thấy tồn kho sản phẩm.'
                        ],
                    ]);
                }

                // Cộng lại tồn kho bán được.
                $inventory->on_hand_quantity =
                    (int) $inventory->on_hand_quantity + $quantity;

                $inventory->save();

                InventoryTransaction::create([
                    'product_variant_id' => $item->product_variant_id,
                    'user_id' => $request->user()->id,
                    'type' => 'return',
                    'quantity_delta' => $quantity,
                    'reserved_delta' => 0,
                    'unit_cost' => $item->orderItem?->cost_price_snapshot,
                    'reference_type' => 'return',
                    'reference_id' => $return->id,
                    'idempotency_key' => $key,
                    'note' => 'Nhập kho từ hàng khách trả: '
                        . $return->return_code,
                ]);
            }

            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->writeHistory(
                $return,
                'inspecting',
                'completed',
                $request->user()->id,
                $validated['note']
                    ?? 'Hoàn tất xử lý hàng trả. Không thực hiện hoàn tiền.'
            );

            return $return;
        });

        return response()->json([
            'message' => 'Hoàn tất xử lý hàng trả thành công. Không phát sinh giao dịch hoàn tiền.',
            'data' => $return->load([
                'items.orderItem',
                'histories.user',
            ]),
        ]);
    }

    /**
     * Hàm chuyển trạng thái dùng chung.
     */
    private function changeStatus(
        int|string $id,
        string $expectedStatus,
        string $newStatus,
        int $userId,
        string $note
    ): ReturnRequest {
        return DB::transaction(function () use (
            $id,
            $expectedStatus,
            $newStatus,
            $userId,
            $note
        ) {
            $return = ReturnRequest::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->status !== $expectedStatus) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Không thể chuyển từ {$return->status} sang {$newStatus}."
                    ],
                ]);
            }

            $return->update([
                'status' => $newStatus,
                'admin_note' => $note,
            ]);

            $this->writeHistory(
                $return,
                $expectedStatus,
                $newStatus,
                $userId,
                $note
            );

            return $return;
        });
    }

    /**
     * Lưu lịch sử hoàn hàng.
     */
    private function writeHistory(
        ReturnRequest $return,
        ?string $fromStatus,
        string $status,
        ?int $userId,
        ?string $note
    ): void {
        ReturnStatusHistory::create([
            'return_id' => $return->id,
            'user_id' => $userId,
            'from_status' => $fromStatus,
            'status' => $status,
            'note' => $note,
        ]);
    }
}