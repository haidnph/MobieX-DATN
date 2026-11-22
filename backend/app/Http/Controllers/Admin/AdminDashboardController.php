<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function stats(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->dateRange($request);
        $orders = Order::query()->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);

        // Settled revenue: Paid orders that are not cancelled or refunded
        $totalRevenue = (float) (clone $orders)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $todayRevenue = (float) (clone $orders)->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $todayOrdersCount = (clone $orders)->count();
        $pendingOrdersCount = (clone $orders)->where('status', 'pending')->count();
        $cancelledOrdersCount = (clone $orders)->where('status', 'cancelled')->count();
        $totalOrdersCount = (clone $orders)->count();

        // Average order value (AOV) for settled orders
        $paidOrdersCount = (clone $orders)->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $averageOrderValue = $paidOrdersCount > 0
            ? round($totalRevenue / $paidOrdersCount, 0)
            : 0.0;

        // Order Status Distribution
        $statusCounts = (clone $orders)
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
        $paymentCounts = (clone $orders)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $activeOrdersCount = (clone $orders)->whereNotIn('status', ['cancelled'])->count();
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
            ->whereDate('orders.created_at', '>=', $startDate)
            ->whereDate('orders.created_at', '<=', $endDate)
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
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Get revenue grouped by day for interactive multi-mode charts.
     */
    public function revenueChart(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->dateRange($request);
        $days = $startDate->diffInDays($endDate) + 1;

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

    /** @return array{Carbon, Carbon} */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            return [Carbon::parse($validated['start_date'])->startOfDay(), Carbon::parse($validated['end_date'])->endOfDay()];
        }

        $today = Carbon::today();
        $days = (int) ($validated['days'] ?? 7);

        return match ($request->query('days')) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'prev_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->startOfMonth()->subSecond()],
            '2' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            default => [$today->copy()->subDays($days - 1)->startOfDay(), $today->copy()->endOfDay()],
        };
    }
}
