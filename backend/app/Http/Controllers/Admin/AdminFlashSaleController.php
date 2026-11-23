<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\ProductVariant;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminFlashSaleController extends Controller
{
    public function index(): JsonResponse
    {
        $sales = FlashSale::query()
            ->with(['items.variant.product', 'creator:id,name'])
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return response()->json($sales);
    }

    public function show(int|string $id): JsonResponse
    {
        $sale = FlashSale::query()
            ->with(['items.variant.product', 'creator:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $sale]);
    }

    public function variantOptions(Request $request): JsonResponse
    {
        $query = ProductVariant::query()
            ->select(['id', 'product_id', 'sku', 'option_signature', 'price'])
            ->where('is_active', true)
            ->whereHas('product', fn ($productQuery) => $productQuery->where('status', 'active'))
            ->with('product:id,name')
            ->orderBy('product_id')
            ->orderBy('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($variantQuery) use ($search): void {
                $variantQuery->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->paginate(min(max((int) $request->query('per_page', 50), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateSale($request);
        $items = $validated['items'];
        unset($validated['items']);

        $sale = DB::transaction(function () use ($request, $validated, $items): FlashSale {
            $this->assertSaleConfiguration($items, $validated['start_at'], $validated['end_at']);
            $sale = FlashSale::query()->create([
                ...$validated,
                'created_by_user_id' => $request->user()->id,
            ]);
            $sale->items()->createMany($items);

            return $sale->load(['items.variant.product', 'creator:id,name']);
        });

        app(HomepageService::class)->clearCache();

        return response()->json([
            'message' => 'Tạo chiến dịch Flash Sale thành công.',
            'data' => $sale,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $sale = FlashSale::query()->findOrFail($id);
        $validated = $this->validateSale($request, $sale->id, $sale);
        $items = $validated['items'] ?? null;
        unset($validated['items']);

        $sale = DB::transaction(function () use ($sale, $validated, $items): FlashSale {
            $lockedSale = FlashSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            $configurationChanged = $items !== null
                || array_key_exists('start_at', $validated)
                || array_key_exists('end_at', $validated)
                || in_array($validated['status'] ?? null, ['scheduled', 'active'], true);

            if ($configurationChanged) {
                $configurationItems = $items ?? $lockedSale->items()
                    ->get(['product_variant_id', 'sale_price', 'quantity_limit'])
                    ->map(static fn ($item): array => [
                        'product_variant_id' => $item->product_variant_id,
                        'sale_price' => $item->sale_price,
                        'quantity_limit' => $item->quantity_limit,
                    ])->all();
                $this->assertSaleConfiguration(
                    $configurationItems,
                    $validated['start_at'] ?? $lockedSale->start_at,
                    $validated['end_at'] ?? $lockedSale->end_at,
                    (int) $lockedSale->id,
                );
            }

            if ($items !== null) {
                $hasReservedOrSoldItems = $lockedSale->items()
                    ->where(function ($query): void {
                        $query->where('quantity_reserved', '>', 0)
                            ->orWhere('quantity_sold', '>', 0);
                    })->exists();
                $hasOrderHistory = DB::table('order_items')->where('flash_sale_id', $lockedSale->id)->exists();

                if ($hasReservedOrSoldItems || $hasOrderHistory) {
                    throw ValidationException::withMessages([
                        'items' => 'Không thể thay danh sách sản phẩm sau khi chiến dịch đã có lượt giữ chỗ hoặc đơn hàng.',
                    ]);
                }

                $lockedSale->items()->delete();
                $lockedSale->items()->createMany($items);
            }

            $lockedSale->update($validated);

            return $lockedSale->fresh(['items.variant.product', 'creator:id,name']);
        });

        app(HomepageService::class)->clearCache();

        return response()->json([
            'message' => 'Cập nhật chiến dịch Flash Sale thành công.',
            'data' => $sale,
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $sale = FlashSale::query()->findOrFail($id);
        $hasOrderHistory = DB::table('order_items')->where('flash_sale_id', $sale->id)->exists();
        $hasReservedOrSoldItems = $sale->items()
            ->where(function ($query): void {
                $query->where('quantity_reserved', '>', 0)
                    ->orWhere('quantity_sold', '>', 0);
            })->exists();

        if ($hasOrderHistory || $hasReservedOrSoldItems) {
            $sale->update(['status' => 'cancelled']);
            $message = 'Chiến dịch đã có đơn hàng nên được chuyển sang trạng thái đã hủy để giữ lịch sử.';
        } else {
            $sale->delete();
            $message = 'Đã xóa chiến dịch Flash Sale.';
        }

        app(HomepageService::class)->clearCache();

        return response()->json(['message' => $message]);
    }

    private function validateSale(Request $request, ?int $ignoreId = null, ?FlashSale $existing = null): array
    {
        if ($request->filled('slug')) {
            $slug = Str::slug((string) $request->input('slug'));
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'slug' => 'Slug phải chứa ký tự chữ hoặc số.',
                ]);
            }

            $request->merge(['slug' => $slug]);
        }

        $validated = $request->validate([
            'name' => [$ignoreId === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [
                $ignoreId === null ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('flash_sales', 'slug')->ignore($ignoreId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_at' => [$ignoreId === null ? 'required' : 'sometimes', 'date'],
            'end_at' => [$ignoreId === null ? 'required' : 'sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'active', 'ended', 'cancelled'])],
            'items' => [$ignoreId === null ? 'required' : 'sometimes', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required_with:items', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.sale_price' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.quantity_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $startAt = $validated['start_at'] ?? $existing?->start_at;
        $endAt = $validated['end_at'] ?? $existing?->end_at;
        if ($startAt && $endAt && ! Carbon::parse($endAt)->gt(Carbon::parse($startAt))) {
            throw ValidationException::withMessages([
                'end_at' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
            ]);
        }

        return $validated;
    }

    private function assertSaleConfiguration(array $items, mixed $startAt, mixed $endAt, ?int $ignoreSaleId = null): void
    {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->with('product')
            ->get()
            ->keyBy('id');
        $start = Carbon::parse($startAt);
        $end = Carbon::parse($endAt);

        foreach ($items as $index => $item) {
            $variant = $variants->get((int) $item['product_variant_id']);

            if (! $variant || ! $variant->is_active || $variant->product?->status !== 'active') {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Chỉ có thể thêm phiên bản của sản phẩm đang kinh doanh.',
                ]);
            }

            if ((float) $item['sale_price'] >= (float) $variant->price) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_price" => 'Giá Flash Sale phải thấp hơn giá bán hiện tại.',
                ]);
            }

            $overlappingCampaign = DB::table('flash_sale_items')
                ->join('flash_sales', 'flash_sales.id', '=', 'flash_sale_items.flash_sale_id')
                ->where('flash_sale_items.product_variant_id', $variant->id)
                ->whereIn('flash_sales.status', ['draft', 'scheduled', 'active'])
                ->where('flash_sales.start_at', '<', $end)
                ->where('flash_sales.end_at', '>', $start)
                ->when($ignoreSaleId !== null, fn ($query) => $query->where('flash_sales.id', '!=', $ignoreSaleId))
                ->exists();

            if ($overlappingCampaign) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Phiên bản này đã thuộc một chiến dịch Flash Sale có thời gian giao nhau.',
                ]);
            }
        }
    }
}
