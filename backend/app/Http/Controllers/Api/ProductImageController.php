<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    // Lấy danh sách hình ảnh
    public function index(Request $request)
    {
        $query = ProductImage::with(['product', 'variant'])
            ->orderBy('sort_order');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('product_variant_id')) {
            $variantId = $request->product_variant_id;

            if ($variantId === 'null' || $variantId === 'none') {
                $query->whereNull('product_variant_id');
            } else {
                $query->where('product_variant_id', $variantId);
            }
        } elseif ($request->filled('variant_id')) {
            $variantId = $request->variant_id;

            if ($variantId === 'null' || $variantId === 'none') {
                $query->whereNull('product_variant_id');
            } else {
                $query->where('product_variant_id', $variantId);
            }
        } elseif ($request->boolean('general_only')) {
            $query->whereNull('product_variant_id');
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    }

    // Kiểm tra biến thể thuộc sản phẩm
    private function checkVariant($productId, $variantId)
    {
        if (! $variantId) {
            return null;
        }

        $variant = ProductVariant::find($variantId);

        if (! $variant || $variant->product_id != $productId) {
            return response()->json([
                'status' => false,
                'message' => 'Biến thể không thuộc sản phẩm này.',
            ], 422);
        }

        return null;
    }

    // Bỏ ảnh chính cũ của cùng một biến thể
    private function clearPrimaryImage($productId, $variantId, $exceptId = null)
    {
        $query = ProductImage::where('product_id', $productId);

        if ($variantId === null) {
            $query->whereNull('product_variant_id');
        } else {
            $query->where('product_variant_id', $variantId);
        }

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_primary' => false]);
    }

    // Lưu nhiều hình ảnh
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
            'primary_index' => 'nullable|integer|min:0|max:10',
        ]);

        $variantError = $this->checkVariant(
            $validated['product_id'],
            $validated['variant_id'] ?? null
        );

        if ($variantError) {
            return $variantError;
        }

        $createdImages = [];
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $validated,
                &$createdImages,
                &$storedPaths
            ) {
                $productId = $validated['product_id'];
                $variantId = $validated['variant_id'] ?? null;
                $files = $request->file('images');

                $hasExistingPrimaryQuery = ProductImage::where('product_id', $productId);
                if ($variantId === null) {
                    $hasExistingPrimaryQuery->whereNull('product_variant_id');
                } else {
                    $hasExistingPrimaryQuery->where('product_variant_id', $variantId);
                }
                $hasExistingPrimary = $hasExistingPrimaryQuery->where('is_primary', true)->exists();

                $primaryIndex = $request->has('primary_index')
                    ? (int) $request->input('primary_index')
                    : null;

                $maxOrderQuery = ProductImage::where('product_id', $productId);
                if ($variantId === null) {
                    $maxOrderQuery->whereNull('product_variant_id');
                } else {
                    $maxOrderQuery->where('product_variant_id', $variantId);
                }
                $currentMaxOrder = $maxOrderQuery->exists() ? (int) $maxOrderQuery->max('sort_order') : -1;
                $baseOrder = isset($validated['sort_order'])
                    ? (int) $validated['sort_order']
                    : ($currentMaxOrder + 1);

                foreach ($files as $index => $file) {
                    $path = $file->store('products', 'public');
                    $storedPaths[] = $path;

                    $imageUrl = asset('storage/' . $path);

                    if ($primaryIndex !== null) {
                        $isPrimary = ($index === $primaryIndex);
                    } elseif (! empty($validated['is_primary'])) {
                        $isPrimary = ($index === 0);
                    } elseif (! $hasExistingPrimary && $index === 0) {
                        $isPrimary = true;
                    } else {
                        $isPrimary = false;
                    }

                    if ($isPrimary) {
                        $this->clearPrimaryImage(
                            $productId,
                            $variantId
                        );
                        $hasExistingPrimary = true;
                    }

                    $image = ProductImage::create([
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'image_url' => $imageUrl,
                        'alt_text' => $validated['alt_text'] ?? null,
                        'sort_order' => $baseOrder + $index,
                        'is_primary' => $isPrimary,
                    ]);

                    $createdImages[] = $image;
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        return response()->json([
            'status' => true,
            'message' => 'Upload nhiều hình ảnh thành công.',
            'data' => $createdImages,
        ], 201);
    }

    // Cập nhật thông tin hoặc thay thế hình ảnh
    public function update(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);

        $validated = $request->validate([
            'variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'alt_text' => 'sometimes|nullable|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'is_primary' => 'sometimes|boolean',
            'image' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $oldVariantId = $image->variant_id;

        $variantId = array_key_exists('variant_id', $validated)
            ? $validated['variant_id']
            : $oldVariantId;

        $variantError = $this->checkVariant(
            $image->product_id,
            $variantId
        );

        if ($variantError) {
            return $variantError;
        }

        $oldImageUrl = $image->image_url;
        $newPath = null;

        try {
            if ($request->hasFile('image')) {
                $newPath = $request->file('image')->store(
                    'products',
                    'public'
                );

                $validated['image_url'] = asset('storage/' . $newPath);
            }

            unset($validated['image']);

            DB::transaction(function () use (
                $image,
                $validated,
                $variantId,
                $oldVariantId
            ) {
                if (
                    ! empty($validated['is_primary'])
                    || $variantId != $oldVariantId
                ) {
                    $this->clearPrimaryImage(
                        $image->product_id,
                        $variantId,
                        $image->id
                    );
                }

                $image->update($validated);
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            throw $e;
        }

        if ($newPath) {
            $this->deleteImageFile($oldImageUrl);
        }

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật hình ảnh thành công.',
            'data' => $image->fresh(),
        ]);
    }

    // Xóa file ảnh trong Storage
    private function deleteImageFile($imageUrl)
    {
        if (empty($imageUrl)) {
            return;
        }

        $parsed = parse_url($imageUrl, PHP_URL_PATH);
        $path = $parsed !== false && $parsed !== null ? $parsed : $imageUrl;
        $path = ltrim($path, '/');

        $storagePosition = strpos($path, 'storage/');

        if ($storagePosition !== false) {
            $storagePath = substr(
                $path,
                $storagePosition + strlen('storage/')
            );

            if (! empty($storagePath) && Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
                return;
            }
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // Sắp xếp hình ảnh
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*.id' => 'required|integer|exists:product_images,id',
            'images.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['images'] as $item) {
                ProductImage::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Sắp xếp hình ảnh thành công.',
        ]);
    }

    // Đặt ảnh làm ảnh đại diện
    public function setPrimary(Request $request, $id)
    {
        $image = ProductImage::findOrFail($id);

        DB::transaction(function () use ($image) {
            $this->clearPrimaryImage(
                $image->product_id,
                $image->variant_id,
                $image->id
            );

            $image->update(['is_primary' => true]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Đã đặt làm ảnh đại diện.',
            'data' => $image->fresh(),
        ]);
    }

    // Xóa hình ảnh
    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);
        $imageUrl = $image->image_url;
        $productId = $image->product_id;
        $variantId = $image->product_variant_id;
        $wasPrimary = (bool) $image->is_primary;

        DB::transaction(function () use ($image, $productId, $variantId, $wasPrimary) {
            $image->delete();

            // Nếu ảnh bị xóa là ảnh chính, tự động chỉ định ảnh tiếp theo làm ảnh chính mới
            if ($wasPrimary) {
                $nextPrimaryQuery = ProductImage::where('product_id', $productId);
                if ($variantId === null) {
                    $nextPrimaryQuery->whereNull('product_variant_id');
                } else {
                    $nextPrimaryQuery->where('product_variant_id', $variantId);
                }

                $nextPrimary = $nextPrimaryQuery->orderBy('sort_order')->orderBy('id')->first();
                if ($nextPrimary) {
                    $nextPrimary->update(['is_primary' => true]);
                }
            }
        });

        $this->deleteImageFile($imageUrl);

        return response()->json([
            'status' => true,
            'message' => 'Xóa hình ảnh thành công.',
        ]);
    }
}
