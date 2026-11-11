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
    /**
     * Lấy danh sách tồn kho.
     */
    public function index(Request $request)
    {
        $query = Inventory::with('variant.product');

        if ($request->filled('product_variant_id')) {
            $query->where('variant_id', $request->product_variant_id);
        } elseif ($request->filled('variant_id')) {
            $query->where('variant_id', $request->variant_id);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thành công.',
            'data' => $query->get(),
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
            'cost_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $variantId = $validated['product_variant_id'];

            $inventory = Inventory::where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho của biến thể này.',
                ], 404);
            }

            $inventory->quantity += $validated['quantity'];
            $inventory->save();

            // Cập nhật giá vốn nếu có gửi lên.
            if (
                array_key_exists('cost_price', $validated)
                && $validated['cost_price'] !== null
            ) {
                DB::table('product_variants')
                    ->where('id', $variantId)
                    ->update([
                        'cost_price' => $validated['cost_price'],
                    ]);
            }

            InventoryTransaction::create([
                'variant_id' => $variantId,
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

    /**
     * Xuất kho thủ công.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $variantId = $validated['product_variant_id'];

            $inventory = Inventory::where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Không tìm thấy bản ghi tồn kho.',
                ], 404);
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
                'variant_id' => $variantId,
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

    /**
     * Lấy lịch sử giao dịch kho.
     */
    public function transactions(Request $request)
    {
        $query = InventoryTransaction::with([
            'variant.product',
            'user',
        ])->orderByDesc('created_at');

        if ($request->filled('product_variant_id')) {
            $query->where('variant_id', $request->product_variant_id);
        } elseif ($request->filled('variant_id')) {
            $query->where('variant_id', $request->variant_id);
        }

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
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
        $inventories = Inventory::with('variant.product')
            ->whereColumn('available_quantity', '<=', 'min_stock')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Lấy danh sách tồn kho thấp thành công.',
            'data' => $inventories,
        ]);
    }
}
