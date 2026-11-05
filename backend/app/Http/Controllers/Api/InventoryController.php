<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // Lấy danh sách tồn kho
    public function index(Request $request)
    {
        $query = Inventory::with([
            'variant.product',
        ]);

        if ($request->filled('variant_id')) {
            $query->where('variant_id', $request->variant_id);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thành công',
            'data' => $query->get(),
        ]);
    }

    // Nhập kho
    public function import(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $inventory = Inventory::where(
                'variant_id',
                $validated['variant_id']
            )->lockForUpdate()->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho của biến thể này.',
                ], 404);
            }

            // Tăng số lượng tồn kho
            $inventory->quantity += $validated['quantity'];
            $inventory->updated_at = now();
            $inventory->save();

            // Cập nhật giá vốn nếu có gửi lên
            if (isset($validated['cost_price'])) {
                DB::table('product_variants')
                    ->where('id', $validated['variant_id'])
                    ->update([
                        'cost_price' => $validated['cost_price'],
                    ]);
            }

            // Ghi lịch sử nhập kho
            InventoryTransaction::create([
                'variant_id' => $validated['variant_id'],
                'user_id' => Auth::id(),
                'transaction_type' => 'import',
                'quantity' => $validated['quantity'],
                'reference_type' => 'manual',
                'reference_id' => null,
                'note' => $validated['note'] ?? 'Nhập kho thủ công',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Nhập kho thành công.',
                'data' => $inventory->fresh(),
            ]);
        });
    }

    // Xuất kho
    public function export(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $inventory = Inventory::where(
                'variant_id',
                $validated['variant_id']
            )->lockForUpdate()->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho.',
                ], 404);
            }

            if ($inventory->quantity < $validated['quantity']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Số lượng tồn kho không đủ để xuất.',
                ], 422);
            }

            $inventory->quantity -= $validated['quantity'];
            $inventory->updated_at = now();
            $inventory->save();

            InventoryTransaction::create([
                'variant_id' => $validated['variant_id'],
                'user_id' => Auth::id(),
                'transaction_type' => 'export',
                'quantity' => $validated['quantity'],
                'reference_type' => 'manual',
                'reference_id' => null,
                'note' => $validated['note'] ?? 'Xuất kho thủ công',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Xuất kho thành công.',
                'data' => $inventory->fresh(),
            ]);
        });
    }

    // Lịch sử giao dịch kho
    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with([
            'variant.product',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('variant_id')) {
            $query->where('variant_id', $request->variant_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy lịch sử giao dịch kho thành công.',
            'data' => $query->get(),
        ]);
    }

    // Cảnh báo tồn kho thấp
    public function lowStock()
    {
        $inventories = Inventory::with([
            'variant.product',
        ])
            ->whereColumn('quantity', '<=', 'min_stock')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thấp thành công.',
            'data' => $inventories,
        ]);
    }
}
