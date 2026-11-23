<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        'products' => Product::class,
        'categories' => Category::class,
        'brands' => Brand::class,
        'banners' => Banner::class,
        'coupons' => Coupon::class,
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

        $record = DB::transaction(function () use ($modelClass, $type, $id) {
            $record = $modelClass::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            $record->restore();

            if ($type === 'products') {
                $record->variants()->onlyTrashed()->lockForUpdate()->restore();
            }

            return $record->fresh();
        });

        return response()->json([
            'message' => 'Khôi phục thành công. Mục này đã được đưa trở lại danh sách hoạt động.',
            'data' => $record,
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

        $count = DB::transaction(function () use ($modelClass, $type): int {
            $trashed = $modelClass::onlyTrashed()->lockForUpdate()->get(['id']);
            $ids = $trashed->modelKeys();

            if ($ids === []) {
                return 0;
            }

            $modelClass::onlyTrashed()->whereKey($ids)->restore();

            if ($type === 'products') {
                ProductVariant::onlyTrashed()
                    ->whereIn('product_id', $ids)
                    ->lockForUpdate()
                    ->restore();
            }

            return count($ids);
        });

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

        $deleted = DB::transaction(function () use ($modelClass, $type, $id): bool {
            $record = $modelClass::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($type === 'products') {
                $hasOrders = DB::table('order_items')
                    ->where('product_id', $record->id)
                    ->exists();

                if ($hasOrders) {
                    return false;
                }

                $record->variants()->onlyTrashed()->lockForUpdate()->forceDelete();
                $record->images()->delete();
                $record->specifications()->delete();
                $record->attributeValues()->delete();
                $record->reviews()->delete();
            }

            $record->forceDelete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Không thể xóa vĩnh viễn sản phẩm này do đã phát sinh đơn hàng trong hệ thống (ràng buộc kiểm toán kế toán).',
            ], 422);
        }

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

        $deletedCount = DB::transaction(function () use ($modelClass, $type): int|false {
            $trashed = $modelClass::onlyTrashed()->lockForUpdate()->get(['id']);
            $ids = $trashed->modelKeys();

            if ($ids === []) {
                return 0;
            }

            if ($type === 'products') {
                $hasOrders = DB::table('order_items')
                    ->whereIn('product_id', $ids)
                    ->exists();

                if ($hasOrders) {
                    return false;
                }

                ProductVariant::onlyTrashed()
                    ->whereIn('product_id', $ids)
                    ->lockForUpdate()
                    ->forceDelete();
            }

            $modelClass::onlyTrashed()->whereKey($ids)->forceDelete();

            return count($ids);
        });

        if ($deletedCount === false) {
            return response()->json([
                'message' => 'Không thể dọn thùng rác sản phẩm vì có sản phẩm đã phát sinh đơn hàng. Hãy xóa riêng các sản phẩm không được đơn hàng tham chiếu.',
            ], 422);
        }

        return response()->json([
            'message' => "Đã dọn sạch thùng rác: {$deletedCount} mục đã bị xóa vĩnh viễn.",
            'deleted_count' => $deletedCount,
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
