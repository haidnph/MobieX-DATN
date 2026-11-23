<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Lấy danh sách tồn kho.
     */
    public function index(Request $request)
    {
        $query = Inventory::with([
            'variant.product',
            'variant.inventory',
        ]);

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        } elseif ($request->filled('variant_id')) {
            $query->where('product_variant_id', $request->variant_id);
        }

        $inventories = $query->get();
        foreach ($inventories as $inventory) {
            $inventory->variant?->makeVisible('cost_price');
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thành công.',
            'data' => $inventories,
        ]);
    }

    /**
     * Nhập kho và cập nhật giá vốn.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|max:120',
            'cost_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $variantId = $validated['product_variant_id'];
            $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->firstOrFail();

            $inventory = Inventory::where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho của biến thể này.',
                ], 404);
            }

            $existing = InventoryTransaction::query()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();
            $note = $validated['note'] ?? 'Manual inventory import';
            if ($existing) {
                $sameCost = $existing->unit_cost === null || ($validated['cost_price'] ?? null) === null
                    ? $existing->unit_cost === null && ($validated['cost_price'] ?? null) === null
                    : number_format((float) $existing->unit_cost, 2, '.', '') === number_format((float) $validated['cost_price'], 2, '.', '');
                $sameRequest = $existing->type === 'import'
                    && (int) $existing->product_variant_id === (int) $variantId
                    && (int) $existing->quantity_delta === (int) $validated['quantity']
                    && $sameCost
                    && $existing->note === $note;

                if (! $sameRequest) {
                    return response()->json(['status' => false, 'message' => 'Idempotency key has already been used for a different inventory operation.'], 409);
                }

                return response()->json(['status' => true, 'message' => 'Inventory import was already applied.', 'data' => $inventory->fresh()]);
            }

            $inventory->quantity += $validated['quantity'];
            $inventory->save();

            // Cập nhật giá vốn nếu có gửi lên.
            if (
                array_key_exists('cost_price', $validated)
                && $validated['cost_price'] !== null
            ) {
                $variant->cost_price = $validated['cost_price'];
                $variant->save();
            }

            InventoryTransaction::create([
                'product_variant_id' => $variantId,
                'user_id' => Auth::id(),
                'type' => 'import',
                'quantity_delta' => $validated['quantity'],
                'reference_type' => 'manual',
                'reference_id' => null,
                'idempotency_key' => $validated['idempotency_key'],
                'unit_cost' => $validated['cost_price'] ?? null,
                'note' => $note,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Nhập kho thành công.',
                'data' => $inventory->fresh(),
            ]);
        });
    }

    /**
     * Xuất kho thủ công.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $variantId = $validated['product_variant_id'];
            ProductVariant::query()->whereKey($variantId)->lockForUpdate()->firstOrFail();

            $inventory = Inventory::where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho.',
                ], 404);
            }

            $existing = InventoryTransaction::query()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();
            $note = $validated['note'] ?? 'Manual inventory export';
            if ($existing) {
                $sameRequest = $existing->type === 'export'
                    && (int) $existing->product_variant_id === (int) $variantId
                    && (int) $existing->quantity_delta === -((int) $validated['quantity'])
                    && $existing->note === $note;

                if (! $sameRequest) {
                    return response()->json(['status' => false, 'message' => 'Idempotency key has already been used for a different inventory operation.'], 409);
                }

                return response()->json(['status' => true, 'message' => 'Inventory export was already applied.', 'data' => $inventory->fresh()]);
            }

            if ($inventory->available_quantity < $validated['quantity']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Số lượng tồn kho khả dụng không đủ để xuất.',
                ], 422);
            }

            $inventory->quantity -= $validated['quantity'];
            $inventory->save();

            InventoryTransaction::create([
                'product_variant_id' => $variantId,
                'user_id' => Auth::id(),
                'type' => 'export',
                'quantity_delta' => -$validated['quantity'],
                'reference_type' => 'manual',
                'reference_id' => null,
                'idempotency_key' => $validated['idempotency_key'],
                'note' => $note,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Xuất kho thành công.',
                'data' => $inventory->fresh(),
            ]);
        });
    }

    /**
     * Lấy lịch sử giao dịch kho.
     */
    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with([
            'variant.product',
            'variant.inventory',
            'user',
        ])->orderByDesc('created_at');

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        } elseif ($request->filled('variant_id')) {
            $query->where('product_variant_id', $request->variant_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy lịch sử giao dịch kho thành công.',
            'data' => $query->paginate(20),
        ]);
    }

    /**
     * Lấy danh sách biến thể có tồn kho khả dụng thấp.
     */
    public function lowStock()
    {
        $inventories = Inventory::with([
            'variant.product',
            'variant.inventory',
        ])
            ->whereColumn('available_quantity', '<=', 'min_stock')
            ->get();

        foreach ($inventories as $inventory) {
            $inventory->variant?->makeVisible('cost_price');
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thấp thành công.',
            'data' => $inventories,
        ]);
    }
}
