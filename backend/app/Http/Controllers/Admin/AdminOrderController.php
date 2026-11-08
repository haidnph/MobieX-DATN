<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with([
                'user',
                'items.variant.product',
                'statusHistories',
                'shippingMethod',
                'payments',
            ])
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower((string) $request->query('status')));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', strtolower((string) $request->query('payment_status')));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }

    public function show(Request $request, int|string $id): JsonResponse
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
}
