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

        if ($request->filled('variant_id')) {
            $query->where('variant_id', $request->variant_id);
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    }

    // Kiểm tra biến thể thuộc sản phẩm
    private function checkVariant($productId, $variantId)
    {
        if (!$variantId) {
            return null;
        }

        $variant = ProductVariant::find($variantId);

        if (!$variant || $variant->product_id != $productId) {
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
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
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
        ]);

        $variantError = $this->checkVariant(
            $validated['product_id'],
            $validated['variant_id'] ?? null
        );

        if ($variantError) {
            return $variantError;
        }

        $createdImages = [];

        try {
            DB::transaction(function () use ($request, $validated, &$createdImages) {
                $productId = $validated['product_id'];
                $variantId = $validated['variant_id'] ?? null;

                $files = $request->file('images');

                foreach ($files as $index => $file) {
                    // Lưu file vào storage/app/public/products
                    $path = $file->store('products', 'public');

                    // Đường dẫn công khai
                    $imageUrl = asset('storage/' . $path);

                    $isPrimary = !empty($validated['is_primary'])
                        && $index === 0;

                    // Chỉ có một ảnh chính trên mỗi biến thể
                    if ($isPrimary) {
                        $this->clearPrimaryImage(
                            $productId,
                            $variantId
                        );
                    }

                    $image = ProductImage::create([
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'image_url' => $imageUrl,
                        'alt_text' => $validated['alt_text'] ?? null,
                        'sort_order' => ($validated['sort_order'] ?? 0) + $index,
                        'is_primary' => $isPrimary,
                    ]);

                    $createdImages[] = $image;
                }
            });
        } catch (\Throwable $e) {
            // Dọn các file nếu quá trình lưu thất bại
            foreach ($createdImages as $image) {
                $this->deleteImageFile($image->image_url);
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

        $variantId = array_key_exists('variant_id', $validated)
            ? $validated['variant_id']
            : $image->variant_id;

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
                $variantId
            ) {
                if (
                    !empty($validated['is_primary'])
                    || $variantId != $image->variant_id
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

        // Chỉ xóa ảnh cũ sau khi cập nhật database thành công
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

        $path = parse_url($imageUrl, PHP_URL_PATH);

        if (!$path) {
            return;
        }

        // Tìm vị trí /storage/ trong URL
        $storagePosition = strpos($path, '/storage/');

        if ($storagePosition === false) {
            return;
        }

        $storagePath = substr(
            $path,
            $storagePosition + strlen('/storage/')
        );

        if (!empty($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }
    // Xóa hình ảnh
    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);

        $imageUrl = $image->image_url;

        DB::transaction(function () use ($image) {
            $image->delete();
        });

        $this->deleteImageFile($imageUrl);

        return response()->json([
            'status' => true,
            'message' => 'Xóa hình ảnh thành công.',
        ]);
    }
}
