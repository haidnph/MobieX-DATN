<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\ReturnStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    /**
     * Khách hàng gửi yêu cầu hoàn hàng.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],

            'reason' => [
                'required',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.order_item_id' => [
                'required',
                'integer',
                'distinct',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $userId = $request->user()->id;

        $return = DB::transaction(function () use (
            $validated,
            $userId
        ) {
            $order = Order::whereKey($validated['order_id'])
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order_id' => [
                        'Chỉ đơn hàng đã giao thành công mới được yêu cầu hoàn hàng.'
                    ],
                ]);
            }

            $return = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'return_code' => 'RTN-' . strtoupper(Str::random(12)),
                'reason' => $validated['reason'],
                'status' => 'requested',
            ]);

            foreach ($validated['items'] as $input) {
                $orderItem = $order->items()
                    ->whereKey($input['order_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Sản phẩm không thuộc đơn hàng này.'
                        ],
                    ]);
                }

                if (! $orderItem->product_variant_id) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Sản phẩm không có phiên bản hợp lệ.'
                        ],
                    ]);
                }

                // Số lượng đã được yêu cầu trả trước đó.
                $previousQuantity = ReturnItem::where(
                    'order_item_id',
                    $orderItem->id
                )
                    ->whereHas('returnRequest', function ($query) {
                        $query->whereNotIn('status', ['rejected']);
                    })
                    ->sum('quantity');

                $remainingQuantity =
                    (int) $orderItem->quantity
                    - (int) $previousQuantity;

                if ((int) $input['quantity'] > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Số lượng sản phẩm {$orderItem->product_name} còn được trả là {$remainingQuantity}."
                        ],
                    ]);
                }

                ReturnItem::create([
                    'return_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'quantity' => $input['quantity'],
                    'restock_quantity' => 0,
                ]);
            }

            ReturnStatusHistory::create([
                'return_id' => $return->id,
                'user_id' => $userId,
                'from_status' => null,
                'status' => 'requested',
                'note' => 'Khách hàng gửi yêu cầu hoàn hàng.',
            ]);

            return $return;
        });

        return response()->json([
            'message' => 'Gửi yêu cầu hoàn hàng thành công.',
            'data' => $return->load([
                'order',
                'items.orderItem',
                'histories.user',
            ]),
        ], 201);
    }

    /**
     * Danh sách yêu cầu hoàn hàng.
     */
    public function index(Request $request): JsonResponse
    {
        $returns = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'order',
                'items.orderItem',
                'histories',
            ])
            ->latest()
            ->paginate(15);

        return response()->json($returns);
    }

    /**
     * Chi tiết yêu cầu hoàn hàng.
     */
    public function show(
        Request $request,
        int|string $id
    ): JsonResponse {
        $return = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'order',
                'items.orderItem',
                'items.variant',
                'histories.user',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $return,
        ]);
    }
}