<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Trash (Recycle Bin) Controller
 *
 * Manages soft-deleted records for: products, categories, brands, banners, coupons.
 * Endpoints:
 *   GET    /admin/trash/{type}           → list trashed records
 *   POST   /admin/trash/{type}/{id}/restore → restore single record
 *   POST   /admin/trash/restore-all/{type} → restore all records of a type
 *   DELETE /admin/trash/{type}/{id}       → force delete (permanent)
 *   DELETE /admin/trash/empty/{type}      → empty entire trash for a type
 */
class AdminTrashController extends Controller
{
    private const ALLOWED_TYPES = [
        'products'   => Product::class,
        'categories' => Category::class,
        'brands'     => Brand::class,
        'banners'    => Banner::class,
        'coupons'    => Coupon::class,
    ];

    /**
     * Resolve model class from type string.
     */
    private function resolveModel(string $type): ?string
    {
        return self::ALLOWED_TYPES[$type] ?? null;
    }

    /**
     * List trashed (soft-deleted) items for a given type.
     * GET /admin/trash/{type}
     */
    public function index(string $type): JsonResponse
    {
        $modelClass = $this->resolveModel($type);
        if (! $modelClass) {
            return response()->json(['message' => "Invalid trash type: {$type}"], 422);
        }

        $items = $modelClass::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json([
            'data' => $items,
            'count' => $items->count(),
            'type' => $type,
        ]);
    }

    /**
     * Restore a single soft-deleted record.
     * POST /admin/trash/{type}/{id}/restore
     */
    public function restore(string $type, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModel($type);
        if (! $modelClass) {
            return response()->json(['message' => "Invalid trash type: {$type}"], 422);
        }

        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $record->restore();

        // For products also restore their soft-deleted variants
        if ($type === 'products') {
            $record->variants()->onlyTrashed()->restore();
        }

        return response()->json([
            'message' => "Khôi phục thành công. Mục này đã được đưa trở lại danh sách hoạt động.",
            'data'    => $record->fresh(),
        ]);
    }

    /**
     * Restore all soft-deleted records for a type.
     * POST /admin/trash/restore-all/{type}
     */
    public function restoreAll(string $type): JsonResponse
    {
        $modelClass = $this->resolveModel($type);
        if (! $modelClass) {
            return response()->json(['message' => "Invalid trash type: {$type}"], 422);
        }

        $count = $modelClass::onlyTrashed()->count();
        $modelClass::onlyTrashed()->restore();

        if ($type === 'products') {
            \App\Models\ProductVariant::onlyTrashed()->restore();
        }

        return response()->json([
            'message' => "Đã khôi phục {$count} mục từ thùng rác.",
            'restored_count' => $count,
        ]);
    }

    /**
     * Permanently delete a single soft-deleted record.
     * DELETE /admin/trash/{type}/{id}
     */
    public function forceDelete(string $type, int|string $id): JsonResponse
    {
        $modelClass = $this->resolveModel($type);
        if (! $modelClass) {
            return response()->json(['message' => "Invalid trash type: {$type}"], 422);
        }

        $record = $modelClass::onlyTrashed()->findOrFail($id);

        // For products: clean up child relationships before hard-delete
        if ($type === 'products') {
            $hasOrders = \Illuminate\Support\Facades\DB::table('order_items')
                ->where('product_id', $record->id)
                ->exists();

            if ($hasOrders) {
                return response()->json([
                    'message' => 'Không thể xóa vĩnh viễn sản phẩm này do đã phát sinh đơn hàng trong hệ thống (ràng buộc kiểm toán kế toán).',
                ], 422);
            }

            $record->variants()->onlyTrashed()->forceDelete();
            $record->images()->delete();
            $record->specifications()->delete();
            $record->attributeValues()->delete();
            $record->reviews()->delete();
        }

        $record->forceDelete();

        return response()->json([
            'message' => 'Đã xóa vĩnh viễn. Dữ liệu này không thể khôi phục được nữa.',
        ]);
    }

    /**
     * Empty entire trash for a type (permanently delete all trashed items).
     * DELETE /admin/trash/empty/{type}
     */
    public function emptyTrash(string $type): JsonResponse
    {
        $modelClass = $this->resolveModel($type);
        if (! $modelClass) {
            return response()->json(['message' => "Invalid trash type: {$type}"], 422);
        }

        $count = $modelClass::onlyTrashed()->count();

        if ($type === 'products') {
            // Collect trashed product IDs
            $trashedProductIds = $modelClass::onlyTrashed()->pluck('id');
            // Force-delete related trashed variants
            \App\Models\ProductVariant::onlyTrashed()
                ->whereIn('product_id', $trashedProductIds)
                ->forceDelete();
        }

        $modelClass::onlyTrashed()->forceDelete();

        return response()->json([
            'message' => "Đã dọn sạch thùng rác: {$count} mục đã bị xóa vĩnh viễn.",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Get summary counts across all trash types.
     * GET /admin/trash/summary
     */
    public function summary(): JsonResponse
    {
        $summary = [];
        foreach (self::ALLOWED_TYPES as $type => $modelClass) {
            $summary[$type] = $modelClass::onlyTrashed()->count();
        }

        $summary['total'] = array_sum($summary);

        return response()->json(['data' => $summary]);
    }
}
