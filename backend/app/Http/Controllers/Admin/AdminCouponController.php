<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->withCount(['orders', 'redemptions', 'collectors'])->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->filled('discount_type')) {
            $query->where('discount_type', strtolower((string) $request->query('discount_type')));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $coupons = $query->paginate($perPage);

        return response()->json($coupons);
    }

    public function show(int|string $id): JsonResponse
    {
        $coupon = Coupon::with(['products:id,name', 'categories:id,name', 'brands:id,name'])->withCount(['orders', 'redemptions', 'collectors'])->findOrFail($id);

        return response()->json([
            'data' => $coupon,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('type') && ! $request->has('discount_type')) {
            $request->merge(['discount_type' => $request->input('type')]);
        }
        if ($request->has('value') && ! $request->has('discount_value')) {
            $request->merge(['discount_value' => $request->input('value')]);
        }
        if ($request->has('expires_at') && ! $request->has('end_at')) {
            $request->merge(['end_at' => $request->input('expires_at')]);
        }
        if ($request->has('max_discount_amount') && ! $request->has('max_discount')) {
            $request->merge(['max_discount' => $request->input('max_discount_amount')]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'issued_quantity' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'distinct', 'exists:brands,id'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
            'applies_to_sale_items' => ['sometimes', 'boolean'],
            'is_stackable' => ['sometimes', 'boolean'],
        ]);

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            return response()->json(['message' => 'Phần trăm giảm giá không thể vượt quá 100%.'], 422);
        }

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;

        $productIds = $validated['product_ids'] ?? [];
        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];
        unset($validated['product_ids'], $validated['category_ids'], $validated['brand_ids']);
        $coupon = DB::transaction(function () use ($validated, $productIds, $categoryIds, $brandIds): Coupon {
            $coupon = Coupon::create($validated);
            $coupon->products()->sync($productIds);
            $coupon->categories()->sync($categoryIds);
            $coupon->brands()->sync($brandIds);

            return $coupon->load(['products:id,name', 'categories:id,name', 'brands:id,name']);
        });

        return response()->json([
            'message' => 'Tạo mã giảm giá thành công.',
            'data' => $coupon,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        if ($request->has('type') && ! $request->has('discount_type')) {
            $request->merge(['discount_type' => $request->input('type')]);
        }
        if ($request->has('value') && ! $request->has('discount_value')) {
            $request->merge(['discount_value' => $request->input('value')]);
        }
        if ($request->has('expires_at') && ! $request->has('end_at')) {
            $request->merge(['end_at' => $request->input('expires_at')]);
        }
        if ($request->has('max_discount_amount') && ! $request->has('max_discount')) {
            $request->merge(['max_discount' => $request->input('max_discount_amount')]);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['sometimes', 'required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'issued_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'brand_ids' => ['sometimes', 'nullable', 'array'],
            'brand_ids.*' => ['integer', 'distinct', 'exists:brands,id'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
            'applies_to_sale_items' => ['sometimes', 'boolean'],
            'is_stackable' => ['sometimes', 'boolean'],
        ]);

        $discountType = $validated['discount_type'] ?? $coupon->discount_type;
        $discountValue = $validated['discount_value'] ?? $coupon->discount_value;
        if ($discountType === 'percent' && (float) $discountValue > 100) {
            return response()->json(['message' => 'Phần trăm giảm giá không thể vượt quá 100%.'], 422);
        }
        if (isset($validated['issued_quantity']) && $validated['issued_quantity'] !== null
            && $validated['issued_quantity'] < $coupon->collectors()->count()) {
            return response()->json(['message' => 'Số lượng phát hành không thể thấp hơn số voucher khách hàng đã lưu.'], 422);
        }
        if (isset($validated['usage_limit']) && $validated['usage_limit'] !== null
            && $validated['usage_limit'] < $coupon->used_count + $coupon->reserved_count) {
            return response()->json(['message' => 'Giới hạn sử dụng không thể thấp hơn số lượt đã dùng và đang giữ.'], 422);
        }

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $syncProducts = array_key_exists('product_ids', $validated);
        $syncCategories = array_key_exists('category_ids', $validated);
        $syncBrands = array_key_exists('brand_ids', $validated);
        $productIds = $validated['product_ids'] ?? [];
        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];
        unset($validated['product_ids'], $validated['category_ids'], $validated['brand_ids']);
        $couponId = $coupon->id;
        $coupon = DB::transaction(function () use ($couponId, $validated, $syncProducts, $productIds, $syncCategories, $categoryIds, $syncBrands, $brandIds): Coupon {
            $coupon = Coupon::query()->whereKey($couponId)->lockForUpdate()->firstOrFail();
            if (isset($validated['issued_quantity']) && $validated['issued_quantity'] !== null
                && $validated['issued_quantity'] < $coupon->collectors()->count()) {
                throw ValidationException::withMessages(['issued_quantity' => 'Số lượng phát hành không thể thấp hơn số voucher khách hàng đã lưu.']);
            }
            if (isset($validated['usage_limit']) && $validated['usage_limit'] !== null
                && $validated['usage_limit'] < $coupon->used_count + $coupon->reserved_count) {
                throw ValidationException::withMessages(['usage_limit' => 'Giới hạn sử dụng không thể thấp hơn số lượt đã dùng và đang giữ.']);
            }
            $coupon->update($validated);
            if ($syncProducts) {
                $coupon->products()->sync($productIds);
            }
            if ($syncCategories) {
                $coupon->categories()->sync($categoryIds);
            }
            if ($syncBrands) {
                $coupon->brands()->sync($brandIds);
            }

            return $coupon->fresh()->load(['products:id,name', 'categories:id,name', 'brands:id,name']);
        });

        return response()->json([
            'message' => 'Cập nhật mã giảm giá thành công.',
            'data' => $coupon->fresh()->load(['products:id,name', 'categories:id,name', 'brands:id,name']),
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        if ($coupon->orders()->exists() || $coupon->used_count > 0) {
            return response()->json([
                'message' => 'Không thể xóa mã giảm giá đã phát sinh đơn hàng. Vui lòng chuyển trạng thái sang không hoạt động.',
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Xóa mã giảm giá thành công.',
        ]);
    }
}
