<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Average order value (AOV) for settled orders
        $paidOrdersCount = Order::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $averageOrderValue = $paidOrdersCount > 0
            ? round($totalRevenue / $paidOrdersCount, 0)
            : 0.0;

        // Order Status Distribution
        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $allStatuses = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
        $orderStatusDistribution = [];
        foreach ($allStatuses as $st) {
            $count = (int) ($statusCounts[$st] ?? 0);
            $percentage = $totalOrdersCount > 0 ? round(($count / $totalOrdersCount) * 100, 1) : 0.0;
            $orderStatusDistribution[$st] = [
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Delivered success rate
        $deliveredCount = (int) ($statusCounts['delivered'] ?? 0);
        $successRate = $totalOrdersCount > 0
            ? round(($deliveredCount / $totalOrdersCount) * 100, 1)
            : 0.0;

        // Payment Method Distribution
        $paymentCounts = Order::query()
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $activeOrdersCount = Order::whereNotIn('status', ['cancelled'])->count();
        $methods = ['vnpay', 'zalopay', 'momo', 'cod'];
        $paymentMethodDistribution = [];
        foreach ($methods as $m) {
            $rec = $paymentCounts->get($m);
            $count = $rec ? (int) $rec->count : 0;
            $amt = $rec ? (float) $rec->total_amount : 0.0;
            $pct = $activeOrdersCount > 0 ? round(($count / $activeOrdersCount) * 100, 1) : 0.0;
            $paymentMethodDistribution[$m] = [
                'count' => $count,
                'total_amount' => $amt,
                'percentage' => $pct,
            ];
        }

        // Top 5 Best Selling Products
        $topSellingProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNotIn('orders.status', ['cancelled'])
            ->selectRaw('order_items.product_id, order_items.product_name, MAX(order_items.image_path) as image_path, SUM(order_items.quantity) as total_sold, SUM(order_items.price * order_items.quantity) as total_revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => (int) $item->product_id,
                    'product_name' => $item->product_name,
                    'image_path' => $item->image_path,
                    'total_sold' => (int) $item->total_sold,
                    'total_revenue' => (float) $item->total_revenue,
                ];
            });

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
                'average_order_value' => $averageOrderValue,
                'today_orders_count' => $todayOrdersCount,
                'pending_orders_count' => $pendingOrdersCount,
                'cancelled_orders_count' => $cancelledOrdersCount,
                'total_orders_count' => $totalOrdersCount,
                'success_rate' => $successRate,
                'order_status_distribution' => $orderStatusDistribution,
                'payment_method_distribution' => $paymentMethodDistribution,
                'top_selling_products' => $topSellingProducts,
                'low_stock_count' => $lowStockCount,
                'low_stock_items' => $lowStockItems,
            ],
        ]);
    }

    /**
     * Get revenue grouped by day for interactive multi-mode charts.
     */
    public function revenueChart(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        if (! in_array($days, [7, 14, 30, 90], true)) {
            if ($days < 1 || $days > 90) {
                $days = 7;
            }
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
        $totalPeriodRevenue = 0.0;
        $totalPeriodOrders = 0;
        $peakRevenue = 0.0;
        $peakDate = null;

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $record = $records->get($dateStr);
            $rev = $record ? (float) $record->daily_revenue : 0.0;
            $ord = $record ? (int) $record->daily_orders : 0;

            $labels[] = $current->format('d/m');
            $revenueData[] = $rev;
            $ordersData[] = $ord;

            $totalPeriodRevenue += $rev;
            $totalPeriodOrders += $ord;

            if ($rev >= $peakRevenue) {
                $peakRevenue = $rev;
                $peakDate = $dateStr;
            }

            $items[] = [
                'date' => $dateStr,
                'label' => $current->format('d/m'),
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
                'summary' => [
                    'total_revenue' => $totalPeriodRevenue,
                    'total_orders' => $totalPeriodOrders,
                    'peak_revenue' => $peakRevenue,
                    'peak_date' => $peakDate,
                    'days' => $days,
                ],
            ],
        ]);
    }
}
