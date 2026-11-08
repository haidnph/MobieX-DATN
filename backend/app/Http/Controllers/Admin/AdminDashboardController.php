<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Get aggregate overview statistics for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();

        // Settled revenue: Paid orders that are not cancelled or refunded
        $totalRevenue = (float) Order::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $todayRevenue = (float) Order::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        $todayOrdersCount = Order::whereDate('created_at', $today)->count();
        $pendingOrdersCount = Order::where('status', 'pending')->count();
        $cancelledOrdersCount = Order::where('status', 'cancelled')->count();
        $totalOrdersCount = Order::count();

        // Low stock count & list
        $lowStockQuery = Inventory::query()
            ->whereRaw('(on_hand_quantity - reserved_quantity) <= min_stock')
            ->with(['variant.product:id,name']);

        $lowStockCount = $lowStockQuery->count();
        $lowStockItems = $lowStockQuery->take(10)->get()->map(function ($inv) {
            return [
                'inventory_id' => $inv->id,
                'variant_id' => $inv->product_variant_id,
                'product_name' => $inv->variant?->product?->name ?? 'N/A',
                'sku' => $inv->variant?->sku ?? 'N/A',
                'on_hand_quantity' => (int) $inv->on_hand_quantity,
                'reserved_quantity' => (int) $inv->reserved_quantity,
                'available_quantity' => (int) $inv->available_quantity,
                'min_stock' => (int) $inv->min_stock,
            ];
        });

        return response()->json([
            'data' => [
                'total_revenue' => $totalRevenue,
                'today_revenue' => $todayRevenue,
                'today_orders_count' => $todayOrdersCount,
                'pending_orders_count' => $pendingOrdersCount,
                'cancelled_orders_count' => $cancelledOrdersCount,
                'total_orders_count' => $totalOrdersCount,
                'low_stock_count' => $lowStockCount,
                'low_stock_items' => $lowStockItems,
            ],
        ]);
    }

    /**
     * Get revenue grouped by day for Chart.js.
     */
    public function revenueChart(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        if ($days < 1 || $days > 90) {
            $days = 7;
        }

        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        // Group by date
        $records = Order::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->selectRaw('DATE(created_at) as order_date, SUM(total_amount) as daily_revenue, COUNT(*) as daily_orders')
            ->groupBy('order_date')
            ->orderBy('order_date', 'asc')
            ->get()
            ->keyBy('order_date');

        $labels = [];
        $revenueData = [];
        $ordersData = [];
        $items = [];

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $record = $records->get($dateStr);
            $rev = $record ? (float) $record->daily_revenue : 0.0;
            $ord = $record ? (int) $record->daily_orders : 0;

            $labels[] = $current->format('d/m');
            $revenueData[] = $rev;
            $ordersData[] = $ord;

            $items[] = [
                'date' => $dateStr,
                'revenue' => $rev,
                'orders' => $ord,
            ];

            $current->addDay();
        }

        return response()->json([
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Doanh thu (VND)',
                        'data' => $revenueData,
                    ],
                    [
                        'label' => 'Số đơn hàng',
                        'data' => $ordersData,
                    ],
                ],
                'items' => $items,
            ],
        ]);
    }
}
