<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Validate query parameters before building the order query.
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([
                'pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled',
            ])],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()
            ->with([
                'user',
                'items.variant.product',
                'statusHistories',
                'shippingMethod',
                'payments',
            ])
            ->latest();

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', strtolower($filters['payment_status']));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(int|string $id): JsonResponse
    {
        $order = Order::with([
            'user',
            'items.variant.product',
            'statusHistories.user',
            'shippingMethod',
            'payments',
            'coupon',
        ])->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['confirmed', 'processing', 'shipping', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:255'],