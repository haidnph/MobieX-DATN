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
}sửa lại code đẩy lên tránh xung đột k merger vào đc
…

Worked for 7s

Mình đã chỉnh sửa file AdminCouponController.php cho dự án Laravel MobileX theo hướng giữ nguyên chức năng, hạn chế ảnh hưởng đến các thành viên khác khi Merge.

PHP syntax: Không có lỗi
1. Tải file đã chỉnh sửa
Tải AdminCouponController.php

2. Những điểm đã chỉnh sửa
Nội dung

Thay đổi

Tìm kiếm Coupon

Kiểm tra dữ liệu đầu vào

Lọc trạng thái

Xử lý đúng cả giá trị false

Phân trang

Giới hạn 1–100

Mã giảm giá

Chuẩn hóa chữ hoa trước khi kiểm tra trùng

Cập nhật Coupon

Kiểm tra dữ liệu trước khi lưu

Xóa Coupon

Không cho xóa khi đã có đơn hàng hoặc lượt sử dụng

Mình giữ nguyên:

Tên Controller và các hàm API.

Tên Model Coupon.

Các quan hệ orders, redemptions.

Cấu trúc JSON trả về.

Các nghiệp vụ tạo, sửa, xóa mã giảm giá.

3. Cách thay file trong VS Code
Thay file tại:

app/Http/Controllers/Admin/AdminCouponController.php
4. Lệnh đẩy lên GitHub
Mở Terminal trong VS Code:

Kiểm tra nhánh hiện tại

git branch

Kiểm tra file thay đổi

git status

Chỉ thêm file Coupon

git add app/Http/Controllers/Admin/AdminCouponController.php

Commit

git commit -m "Improve admin coupon management"

Push

git push origin ten-nhanh-cua-ban

5. Đưa code vào main
git fetch origin
git merge origin/main

Nếu không có xung đột:

git push origin ten-nhanh-cua-ban

Sau đó tạo Pull Request để Merge vào main.

Lưu ý: Không có cách chỉnh code nào đảm bảo tuyệt đối không xảy ra Merge Conflict. Việc chỉ thêm đúng file Coupon vào commit giúp tránh đưa nhầm những thay đổi khác lên Git, nhưng nếu thành viên khác cũng sửa file này thì vẫn có thể xung đột.

Bạn nên gửi mình ảnh kết quả git status trước khi commit để mình kiểm tra giúp bạn có đang chuẩn bị đẩy nhầm file của thành viên khác không.


AdminCouponController.php
Code




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
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'discount_type' => ['sometimes', 'nullable', Rule::in(['fixed', 'percent'])],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Coupon::query()->latest();

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where('code', 'like', "%{$search}%");
        }

        if (! empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return response()->json($query->paginate($perPage));
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
        // Normalize the code before validation so uniqueness is checked consistently.
        if ($request->has('code') && is_string($request->input('code'))) {
            $request->merge([
                'code' => strtoupper(trim($request->input('code'))),
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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

        // Keep coupon codes uppercase when they are changed.
        if ($request->has('code') && is_string($request->input('code'))) {
            $request->merge([
                'code' => strtoupper(trim($request->input('code'))),
            ]);
        }

        $validated = $request->validate([
            'code' => [
