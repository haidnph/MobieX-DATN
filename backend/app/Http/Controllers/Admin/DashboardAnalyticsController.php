
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardAnalyticsController extends Controller
{
    /**
     * Ghi nhận doanh thu từ đơn đã giao.
     * Database chưa có delivered_at nên tạm
     * thống kê theo ngày tạo đơn.
     */
    private const REVENUE_STATUS = 'DELIVERED';

    public function summary(): JsonResponse
    {
        $today = now()->toDateString();

        $startMonth = now()
            ->startOfMonth()
            ->toDateTimeString();

        $startYear = now()
            ->startOfYear()
            ->toDateTimeString();

        $stats = DB::table('orders')->selectRaw(
            'COUNT(*) AS total_orders, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END), 0) AS new_orders, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END), 0) AS shipping_orders, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END), 0) AS completed_orders, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END), 0) AS cancelled_orders, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? THEN total_amount ELSE 0 END), 0) AS total_revenue, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? AND DATE(created_at) = ? THEN total_amount ELSE 0 END), 0) AS today_revenue, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? AND created_at >= ? THEN total_amount ELSE 0 END), 0) AS month_revenue, ' .
            'COALESCE(SUM(CASE WHEN order_status = ? AND created_at >= ? THEN total_amount ELSE 0 END), 0) AS year_revenue',
            [
                'PENDING',
                'SHIPPING',
                'DELIVERED',
                'CANCELLED',
                self::REVENUE_STATUS,
                self::REVENUE_STATUS,
                $today,
                self::REVENUE_STATUS,
                $startMonth,
                self::REVENUE_STATUS,
                $startYear,
            ]
        )->first();

        return response()->json([
            'data' => [
                'orders' => [
                    'new' => (int) $stats->new_orders,
                    'shipping' => (int) $stats->shipping_orders,
                    'completed' => (int) $stats->completed_orders,
                    'cancelled' => (int) $stats->cancelled_orders,
                    'total' => (int) $stats->total_orders,
                ],
                'revenue' => [
                    'today' => (string) $stats->today_revenue,
                    'month' => (string) $stats->month_revenue,
                    'year' => (string) $stats->year_revenue,
                    'total' => (string) $stats->total_revenue,
                    'currency' => 'VND',
                ],
            ],
            'meta' => [
                'revenue_status' => self::REVENUE_STATUS,
                'date_basis' => 'orders.created_at',
                'timezone' => config('app.timezone'),
            ],
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => [
                'sometimes',
                'integer',
                'between:2000,2100',
            ],
        ]);

        $year = (int) ($data['year'] ?? now()->year);

        $records = DB::table('orders')
            ->selectRaw(
                'MONTH(created_at) AS month, ' .
                'SUM(total_amount) AS revenue'
            )
            ->where('order_status', self::REVENUE_STATUS)
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'month' => $month,
                'revenue' => (string) (
                    $records->get($month)->revenue ?? '0'
                ),
            ];
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'months' => $months,
            ],
            'meta' => [
                'currency' => 'VND',
                'revenue_status' => self::REVENUE_STATUS,
                'date_basis' => 'orders.created_at',
            ],
        ]);
    }

    public function orderStatus(): JsonResponse
    {
        $results = DB::table('orders')
            ->selectRaw(
                'order_status, COUNT(*) AS quantity'
            )
            ->groupBy('order_status')
            ->pluck('quantity', 'order_status');

        return response()->json([
            'data' => [
                'new' => (int) (
                    $results['PENDING'] ?? 0
                ),
                'shipping' => (int) (
                    $results['SHIPPING'] ?? 0
                ),
                'completed' => (int) (
                    $results['DELIVERED'] ?? 0
                ),
                'cancelled' => (int) (
                    $results['CANCELLED'] ?? 0
                ),
                'total' => (int) $results->sum(),
            ],
        ]);
    }
}