<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\HomepageSection;
use App\Services\CouponRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherWalletController extends Controller
{
    private function reason(Coupon $coupon, ?int $userId = null): ?string
    {
        if (! $coupon->is_active) {
            return 'Voucher ngừng hoạt động';
        }
        if ($coupon->start_at && now()->lt($coupon->start_at)) {
            return 'Chưa đến thời gian sử dụng';
        }
        if ($coupon->end_at && now()->gt($coupon->end_at)) {
            return 'Voucher hết hạn';
        }
        if ($userId && app(CouponRules::class)->customerLimitReached($coupon, $userId)) {
            return 'Đã hết lượt cá nhân hoặc đang giữ cho đơn hàng';
        }
        if ($coupon->usage_limit !== null && $coupon->used_count + $coupon->reserved_count >= $coupon->usage_limit) {
            return 'Voucher hết lượt';
        }
        if ($coupon->issued_quantity !== null
            && DB::table('user_coupons')->where('coupon_id', $coupon->id)->count() >= $coupon->issued_quantity) {
            return 'Voucher đã được lưu hết';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function row(Coupon $coupon, ?int $userId = null): array
    {
        return array_merge($coupon->only([
            'id', 'code', 'name', 'description', 'discount_type', 'discount_value', 'issued_quantity',
            'min_order_amount', 'max_discount', 'start_at', 'end_at', 'usage_limit',
            'max_uses_per_customer',
        ]), [
            'products' => $coupon->products()->select('products.id', 'products.name')->get()->map(fn ($item) => $item->only(['id', 'name'])),
            'categories' => $coupon->categories()->select('categories.id', 'categories.name')->get()->map(fn ($item) => $item->only(['id', 'name'])),
            'brands' => $coupon->brands()->select('brands.id', 'brands.name')->get()->map(fn ($item) => $item->only(['id', 'name'])),
            'remaining' => $coupon->issued_quantity === null ? null : max(0, $coupon->issued_quantity - DB::table('user_coupons')->where('coupon_id', $coupon->id)->count()),
            'usage_remaining' => $coupon->usage_limit === null ? null : max(0, $coupon->usage_limit - $coupon->used_count - $coupon->reserved_count),
            'saved_count' => DB::table('user_coupons')->where('coupon_id', $coupon->id)->count(),
            'reason' => $this->reason($coupon, $userId),
            'saved' => $userId ? DB::table('user_coupons')->where('user_id', $userId)->where('coupon_id', $coupon->id)->exists() : false,
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $userId = $request->user('sanctum')?->id;
        $validated = $request->validate([
            'section' => ['sometimes', 'boolean'],
            'coupon_ids' => ['sometimes', 'array'],
            'coupon_ids.*' => ['integer', 'distinct'],
        ]);
        $couponIds = collect($validated['coupon_ids'] ?? [])->map(fn ($id): int => (int) $id);

        $query = Coupon::query()->where('is_active', true);
        if ($request->boolean('section')) {
            if ($couponIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $couponIds);
            }
        } elseif ($couponIds->isNotEmpty()) {
            $query->whereIn('id', $couponIds);
        } else {
            $query->where('show_on_home', true);
        }

        $coupons = $query->get()->filter(fn (Coupon $coupon): bool => $this->reason($coupon) === null);
        if ($couponIds->isNotEmpty()) {
            $positions = array_flip($couponIds->all());
            $coupons = $coupons->sortBy(fn (Coupon $coupon): int => $positions[$coupon->id] ?? PHP_INT_MAX);
        }

        return response()->json(['data' => $coupons->map(fn (Coupon $coupon): array => $this->row($coupon, $userId))->values()]);
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $userId = (int) $request->user()->id;
        $isPublishedOnHome = HomepageSection::query()
            ->where('is_active', true)
            ->whereJsonContains('published_settings->settings->coupon_ids', $coupon->id)
            ->exists();
        abort_unless($coupon->show_on_home || $isPublishedOnHome, 404);

        $result = DB::transaction(function () use ($id, $userId): ?string {
            $coupon = Coupon::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if (DB::table('user_coupons')->where('user_id', $userId)->where('coupon_id', $id)->exists()) {
                return null;
            }
            if ($reason = $this->reason($coupon, $userId)) {
                return $reason;
            }
            DB::table('user_coupons')->insert([
                'user_id' => $userId,
                'coupon_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return null;
        });
        if ($result !== null) {
            return response()->json(['message' => $result], 422);
        }

        return response()->json(['message' => 'Đã lưu voucher', 'saved' => true]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $couponIds = DB::table('user_coupons')->where('user_id', $userId)->pluck('coupon_id');
        $coupons = Coupon::withTrashed()->whereIn('id', $couponIds)->get()->map(function (Coupon $coupon) use ($userId): array {
            $row = $this->row($coupon, $userId);
            if ($coupon->trashed()) {
                $row['reason'] = 'Voucher ngừng hoạt động';
            }
            $hasBeenUsed = DB::table('coupon_redemptions')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->where('status', 'consumed')
                ->exists();
            $row['wallet_status'] = $hasBeenUsed
                ? 'used'
                : ($coupon->end_at && now()->gt($coupon->end_at) ? 'expired' : 'available');

            return $row;
        });

        return response()->json(['data' => $coupons]);
    }
}
