<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('position')) {
            $query->where('position', (string) $request->query('position'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $banners = $query->paginate($perPage);

        return response()->json($banners);
    }

    public function show(int|string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        return response()->json([
            'data' => $banner,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'image_path' => ['required', 'string', 'max:255'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['position'] = $validated['position'] ?? 'home';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $banner = Banner::create($validated);

        return response()->json([
            'message' => 'Tạo banner thành công.',
            'data' => $banner,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'image_path' => ['sometimes', 'required', 'string', 'max:255'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $banner->update($validated);

        return response()->json([
            'message' => 'Cập nhật banner thành công.',
            'data' => $banner->fresh(),
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return response()->json([
            'message' => 'Xóa banner thành công.',
        ]);
    }
}
