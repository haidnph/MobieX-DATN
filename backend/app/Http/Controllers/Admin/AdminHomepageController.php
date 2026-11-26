<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageAuditLog;
use App\Models\HomepageSection;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHomepageController extends Controller
{
    public function __construct(
        protected HomepageService $homepageService
    ) {}

    /**
     * Get all homepage sections with draft and published status.
     */
    public function index(): JsonResponse
    {
        $sections = $this->homepageService->getAdminSections();

        return response()->json([
            'status' => true,
            'data' => $sections,
        ]);
    }

    /**
     * Update section settings in draft mode.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $section = HomepageSection::findOrFail($id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'layout_type' => ['sometimes', 'required', 'string', 'in:grid,carousel,split,standard'],
            'items_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.coupon_ids' => ['sometimes', 'array'],
            'settings.coupon_ids.*' => ['integer', 'distinct', 'exists:coupons,id'],
        ]);

        $updated = $this->homepageService->updateSection($section, $validated, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật cấu hình section thành công.',
            'data' => $updated,
        ]);
    }

    /**
     * Reorder sections.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'exists:homepage_sections,id'],
            'orders.*.sort_order' => ['required', 'integer', 'min:0'],
            'orders.*.is_active' => ['nullable', 'boolean'],
        ]);

        $this->homepageService->reorderSections($validated['orders'], $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật thứ tự section thành công.',
            'data' => $this->homepageService->getAdminSections(),
        ]);
    }

    /**
     * Publish draft configuration to live storefront.
     */
    public function publish(Request $request): JsonResponse
    {
        $this->homepageService->publishAll($request->user());

        return response()->json([
            'status' => true,
            'message' => 'Xuất bản cấu hình trang chủ thành công.',
            'data' => $this->homepageService->getAdminSections(),
        ]);
    }

    /**
     * Discard draft changes and restore published state.
     */
    public function revert(Request $request): JsonResponse
    {
        $this->homepageService->revertToPublished($request->user());

        return response()->json([
            'status' => true,
            'message' => 'Đã khôi phục về bản xuất bản gần nhất.',
            'data' => $this->homepageService->getAdminSections(),
        ]);
    }

    /**
     * Reset sections to MobieX factory defaults.
     */
    public function resetDefaults(Request $request): JsonResponse
    {
        $this->homepageService->resetDefaults($request->user());

        return response()->json([
            'status' => true,
            'message' => 'Đã khôi phục cấu hình trang chủ về mặc định.',
            'data' => $this->homepageService->getAdminSections(),
        ]);
    }

    /**
     * Preview resolved homepage using draft settings.
     */
    public function preview(): JsonResponse
    {
        $previewData = $this->homepageService->getPreviewData();

        return response()->json([
            'status' => true,
            'data' => $previewData,
        ]);
    }

    /**
     * Get recent audit logs for homepage actions.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $logs = HomepageAuditLog::with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $logs,
        ]);
    }
}
