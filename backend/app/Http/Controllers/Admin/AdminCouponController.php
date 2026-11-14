<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->latest();

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
        $coupon = Coupon::withCount(['orders', 'redemptions'])->findOrFail($id);

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
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;

        $coupon = Coupon::create($validated);

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
            'discount_type' => ['sometimes', 'required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $coupon->update($validated);

        return response()->json([
            'message' => 'Cập nhật mã giảm giá thành công.',
            'data' => $coupon->fresh(),
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
