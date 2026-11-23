<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\HomepageService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    public function __construct(
        protected HomepageService $homepageService,
        protected MediaStorageService $mediaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('position')) {
            $query->where('position', (string) $request->query('position'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
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
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_path' => ['required', 'string', 'max:500'],
            'mobile_image_path' => ['nullable', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,published,inactive'],
            'storage_provider' => ['nullable', 'string', 'max:50'],
            'public_id' => ['nullable', 'string', 'max:255'],
            'mobile_public_id' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['position'] = $validated['position'] ?? 'home';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $status = $validated['status'] ?? 'published';
        $validated['status'] = $status;
        $validated['is_active'] = $validated['is_active'] ?? ($status !== 'inactive');

        $banner = Banner::create($validated);

        $this->homepageService->clearCache();
        $this->homepageService->logAudit(
            'banner.created',
            'Banner',
            $banner->id,
            "Tạo banner mới: {$banner->title}",
            ['data' => $banner->toArray()],
            $request->user()
        );

        return response()->json([
            'message' => 'Tạo banner thành công.',
            'data' => $banner,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $oldImagePath = $banner->image_path;
        $oldMobilePath = $banner->mobile_image_path;

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_path' => ['sometimes', 'required', 'string', 'max:500'],
            'mobile_image_path' => ['nullable', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,published,inactive'],
            'storage_provider' => ['nullable', 'string', 'max:50'],
            'public_id' => ['nullable', 'string', 'max:255'],
            'mobile_public_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['status']) && ! isset($validated['is_active'])) {
            $validated['is_active'] = ($validated['status'] !== 'inactive');
        } elseif (isset($validated['is_active']) && ! isset($validated['status'])) {
            $validated['status'] = $validated['is_active'] ? 'published' : 'inactive';
        }

        $banner->update($validated);

        // Delete old replaced local files if no longer used
        if (isset($validated['image_path']) && $validated['image_path'] !== $oldImagePath) {
            $this->safeDeleteLocalFile($oldImagePath, $banner->id);
        }
        if (isset($validated['mobile_image_path']) && $validated['mobile_image_path'] !== $oldMobilePath) {
            $this->safeDeleteLocalFile($oldMobilePath, $banner->id);
        }

        $this->homepageService->clearCache();
        $this->homepageService->logAudit(
            'banner.updated',
            'Banner',
            $banner->id,
            "Cập nhật banner: {$banner->title}",
            ['changes' => $validated],
            $request->user()
        );

        return response()->json([
            'message' => 'Cập nhật banner thành công.',
            'data' => $banner->fresh(),
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        $this->homepageService->clearCache();
        $this->homepageService->logAudit(
            'banner.deleted',
            'Banner',
            $banner->id,
            "Chuyển banner vào thùng rác: {$banner->title}",
            null,
            $request->user()
        );

        return response()->json([
            'message' => 'Xóa banner thành công.',
        ]);
    }

    /**
     * Upload an image file for banner.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ]);

        $file = $request->file('image');
        $now = now();
        $subFolder = "banners/{$now->format('Y')}/{$now->format('m')}";

        $result = $this->mediaService->upload($file, $subFolder);

        return response()->json([
            'status' => true,
            'message' => 'Tải ảnh banner lên thành công.',
            'data' => [
                'path' => $result['path'],
                'url' => $result['relative_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'provider' => $result['provider'],
                'name' => $result['name'],
                'size' => $result['size'],
            ],
        ]);
    }

    /**
     * Helper to safely remove local storage file if not referenced by another banner.
     */
    private function safeDeleteLocalFile(?string $path, int $bannerId): void
    {
        if (empty($path)) {
            return;
        }

        $normalized = $this->mediaService->normalizeLocalPath($path);

        $isReferenced = Banner::where('id', '!=', $bannerId)
            ->where(function ($q) use ($path, $normalized) {
                $q->where('image_path', $path)
                    ->orWhere('image_path', $normalized)
                    ->orWhere('image_path', '/storage/'.$normalized)
                    ->orWhere('mobile_image_path', $path)
                    ->orWhere('mobile_image_path', $normalized)
                    ->orWhere('mobile_image_path', '/storage/'.$normalized);
            })
            ->exists();

        if (! $isReferenced) {
            $this->mediaService->delete($path);
        }
    }
}
