// src/features/admin/AdminDashboard.jsx
// Enterprise-grade Business Intelligence Dashboard:
// Executive KPI ribbon, interactive area/bar charts, donut status breakdown,
// best-selling leaderboard, payment method share, and low-stock telemetry.

import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import RevenueAreaChart from '../../components/charts/RevenueAreaChart'
import OrderStatusDonutChart from '../../components/charts/OrderStatusDonutChart'
import TopSellingLeaderboard from '../../components/dashboard/TopSellingLeaderboard'
import PaymentMethodCard from '../../components/dashboard/PaymentMethodCard'

export default function AdminDashboard() {
  const [stats, setStats] = useState(null)
  const [revenueChart, setRevenueChart] = useState([])
  const [chartSummary, setChartSummary] = useState(null)
  const [days, setDays] = useState(7)
  const [loading, setLoading] = useState(true)
  const [chartLoading, setChartLoading] = useState(false)
  const [refreshing, setRefreshing] = useState(false)

  // Fetch all dashboard data
  const loadDashboard = useCallback(async (selectedDays = 7, isRefresh = false) => {
    if (isRefresh) setRefreshing(true)
    else setChartLoading(true)

    try {
      const [statsRes, chartRes] = await Promise.allSettled([
        axiosClient.get(EP.ADMIN_DASHBOARD_STATS),
        axiosClient.get(EP.ADMIN_DASHBOARD_REVENUE_CHART, { params: { days: selectedDays } }),
      ])

      if (statsRes.status === 'fulfilled') {
        setStats(statsRes.value.data.data ?? statsRes.value.data)
      }

      if (chartRes.status === 'fulfilled') {
        const raw = chartRes.value.data.data ?? chartRes.value.data
        const items = raw.items ?? (Array.isArray(raw) ? raw : [])
        setRevenueChart(items)
        setChartSummary(raw.summary ?? null)
      }
    } catch {
      // Handled silently
    } finally {
      setLoading(false)
      setChartLoading(false)
      setRefreshing(false)
    }
  }, [])

  // Initial load
  useEffect(() => {
    loadDashboard(days)
  }, [loadDashboard, days])

  // Handle changing timeframe
  const handleDaysChange = (newDays) => {
    setDays(newDays)
    loadDashboard(newDays)
  }

  // Handle manual refresh
  const handleRefresh = () => {
    loadDashboard(days, true)
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
        <Spinner size="lg" />
        <span className="text-xs text-on-surface-variant font-medium">Đang tải dữ liệu kinh doanh thời gian thực...</span>
      </div>
    )
  }

  const lowStockItems = stats?.low_stock_items ?? []

  return (
    <div className="space-y-6 pb-8">
      {/* Page Header with Telemetry & Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface-container rounded-2xl p-5 border border-outline-variant/20 shadow-xs">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl md:text-2xl font-bold text-on-surface">
              Trung tâm Điều hành & Thống kê Kinh doanh
            </h1>
            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-secondary-container/30 border border-secondary/40 text-secondary text-[11px] font-semibold">
              <span className="w-2 h-2 rounded-full bg-secondary animate-pulse" />
              Live Telemetry
            </span>
          </div>
          <p className="text-xs text-on-surface-variant mt-1">
            Tổng hợp dữ liệu doanh số, sản lượng đơn và cảnh báo vận hành toàn diện
          </p>
        </div>

        <div className="flex items-center gap-2">
          {/* Refresh Button */}
          <button
            type="button"
            onClick={handleRefresh}
            disabled={refreshing}
            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-container-high hover:bg-surface-bright text-on-surface text-xs font-semibold border border-outline-variant/20 transition-all active:scale-95 disabled:opacity-60"
            title="Làm mới số liệu"
          >
            <span className={`material-symbols-outlined text-base ${refreshing ? 'animate-spin text-primary' : ''}`}>
              refresh
            </span>
            <span>{refreshing ? 'Đang cập nhật...' : 'Làm mới'}</span>
          </button>

          {/* Print/Export Button */}
          <button
            type="button"
            onClick={() => window.print()}
            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary text-xs font-semibold transition-all"
            title="In / Xuất báo cáo"
          >
            <span className="material-symbols-outlined text-base">print</span>
            <span>In báo cáo</span>
          </button>
        </div>
      </div>

      {/* Executive KPI Ribbon (5 Cards) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        {/* Total Settled Revenue */}
        <div className="bg-surface-container rounded-2xl p-4 md:p-5 border border-outline-variant/20 flex flex-col justify-between shadow-xs hover:border-primary/40 transition-colors">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-[11px] font-bold uppercase tracking-wider">Doanh thu thực thu</span>
            <span className="p-2 rounded-xl bg-primary/10 text-primary material-symbols-outlined text-xl">
              payments
            </span>
          </div>
          <div className="mt-3">
            <h2 className="text-xl md:text-2xl font-black text-on-surface font-mono tracking-tight">
              {formatCurrencyVND(stats?.total_revenue ?? 0)}
            </h2>
            <p className="text-[11px] text-secondary font-medium mt-1 flex items-center gap-1">
              <span className="material-symbols-outlined text-xs">verified</span>
              Giao dịch đã thanh toán
            </p>
          </div>
        </div>

        {/* Today's Revenue */}
        <div className="bg-surface-container rounded-2xl p-4 md:p-5 border border-outline-variant/20 flex flex-col justify-between shadow-xs hover:border-secondary/40 transition-colors">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-[11px] font-bold uppercase tracking-wider">Doanh thu hôm nay</span>
            <span className="p-2 rounded-xl bg-secondary/10 text-secondary material-symbols-outlined text-xl">
              trending_up
            </span>
          </div>
          <div className="mt-3">
            <h2 className="text-xl md:text-2xl font-black text-secondary font-mono tracking-tight">
              {formatCurrencyVND(stats?.today_revenue ?? 0)}
            </h2>
            <p className="text-[11px] text-on-surface-variant mt-1">Cập nhật trong ngày</p>
          </div>
        </div>

        {/* Orders Telemetry */}
        <div className="bg-surface-container rounded-2xl p-4 md:p-5 border border-outline-variant/20 flex flex-col justify-between shadow-xs hover:border-blue-400/40 transition-colors">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-[11px] font-bold uppercase tracking-wider">Đơn hàng (Hôm nay / Tổng)</span>
            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-500 material-symbols-outlined text-xl">
              shopping_bag
            </span>
          </div>
          <div className="mt-3">
            <h2 className="text-xl md:text-2xl font-black text-on-surface font-mono tracking-tight">
              {stats?.today_orders_count ?? 0} <span className="text-xs font-normal text-outline">/ {stats?.total_orders_count ?? 0}</span>
            </h2>
            <Link to="/admin/orders" className="text-[11px] text-primary hover:underline font-semibold mt-1 block">
              Quản lý đơn hàng →
            </Link>
          </div>
        </div>

        {/* Average Order Value (AOV) */}
        <div className="bg-surface-container rounded-2xl p-4 md:p-5 border border-outline-variant/20 flex flex-col justify-between shadow-xs hover:border-purple-400/40 transition-colors">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-[11px] font-bold uppercase tracking-wider">Giá trị đơn TB (AOV)</span>
            <span className="p-2 rounded-xl bg-purple-500/10 text-purple-500 material-symbols-outlined text-xl">
              insights
            </span>
          </div>
          <div className="mt-3">
            <h2 className="text-xl md:text-2xl font-black text-purple-400 font-mono tracking-tight">
              {formatCurrencyVND(stats?.average_order_value ?? 0)}
            </h2>
            <p className="text-[11px] text-on-surface-variant mt-1">Doanh thu trung bình / đơn</p>
          </div>
        </div>

        {/* Low Stock Alerts */}
        <div className="bg-surface-container rounded-2xl p-4 md:p-5 border border-outline-variant/20 flex flex-col justify-between shadow-xs hover:border-error/40 transition-colors">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-[11px] font-bold uppercase tracking-wider">Cảnh báo tồn kho</span>
            <span className="p-2 rounded-xl bg-error/10 text-error material-symbols-outlined text-xl">
              warning
            </span>
          </div>
          <div className="mt-3">
            <h2 className="text-xl md:text-2xl font-black text-error font-mono tracking-tight">
              {stats?.low_stock_count ?? lowStockItems.length}
            </h2>
            <Link to="/admin/inventories" className="text-[11px] text-error hover:underline font-semibold mt-1 block">
              Biến thể cạn kho (≤ 5) →
            </Link>
          </div>
        </div>
      </div>

      {/* Primary Analytics Section (Area/Bar Chart + Donut Status Breakdown) */}
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
        {/* Revenue & Volume Area Chart */}
        <div className="xl:col-span-8">
          <RevenueAreaChart
            data={revenueChart}
            days={days}
            onDaysChange={handleDaysChange}
            summary={chartSummary}
            loading={chartLoading}
          />
        </div>

        {/* Order Status Donut Chart */}
        <div className="xl:col-span-4">
          <OrderStatusDonutChart
            distribution={stats?.order_status_distribution}
            totalOrders={stats?.total_orders_count ?? 0}
            successRate={stats?.success_rate ?? 0}
          />
        </div>
      </div>

      {/* Secondary Analytics Section (Top Sellers Leaderboard + Payment Gateways) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top 5 Best Selling Products */}
        <TopSellingLeaderboard items={stats?.top_selling_products} />

        {/* Payment Gateways Breakdown */}
        <PaymentMethodCard distribution={stats?.payment_method_distribution} />
      </div>

      {/* Low Stock Alert Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-xs space-y-3">
        <div className="p-6 pb-2 flex items-center justify-between">
          <div>
            <h3 className="text-base font-bold text-on-surface">Cảnh báo Tồn kho Cạn kiệt</h3>
            <p className="text-xs text-on-surface-variant mt-0.5">
              Danh sách sản phẩm và biến thể có số lượng tồn khả dụng cần nhập thêm
            </p>
          </div>
          <Badge variant={lowStockItems.length > 0 ? 'error' : 'success'}>
            {lowStockItems.length} mục
          </Badge>
        </div>

        {lowStockItems.length === 0 ? (
          <div className="p-8 text-center text-sm text-secondary flex items-center justify-center gap-2">
            <span className="material-symbols-outlined text-lg">check_circle</span>
            Tất cả sản phẩm hiện đều có số lượng tồn kho an toàn.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-6 py-3 font-semibold">Mã SKU</th>
                  <th className="px-6 py-3 font-semibold">Sản phẩm / Biến thể</th>
                  <th className="px-6 py-3 text-center font-semibold">Khả dụng</th>
                  <th className="px-6 py-3 text-center font-semibold">Tồn thực</th>
                  <th className="px-6 py-3 text-center font-semibold">Đang giữ</th>
                  <th className="px-6 py-3 text-right font-semibold">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {lowStockItems.map((item, idx) => (
                  <tr key={idx} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="px-6 py-3 font-mono text-xs font-semibold text-primary">
                      {item.sku || '—'}
                    </td>
                    <td className="px-6 py-3 text-on-surface font-medium">
                      {item.product_name || item.variant_name || 'Biến thể'}
                    </td>
                    <td className="px-6 py-3 text-center">
                      <span
                        className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                          (item.available_quantity ?? 0) <= 2
                            ? 'bg-error-container/30 text-error'
                            : 'bg-amber-500/15 text-amber-500'
                        }`}
                      >
                        {item.available_quantity ?? 0}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-center text-on-surface-variant font-mono">
                      {item.on_hand_quantity ?? 0}
                    </td>
                    <td className="px-6 py-3 text-center text-outline font-mono">
                      {item.reserved_quantity ?? 0}
                    </td>
                    <td className="px-6 py-3 text-right">
                      <Link
                        to={`/admin/inventories?search=${encodeURIComponent(item.sku || item.product_name || '')}`}
                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary text-xs font-semibold transition-colors"
                      >
                        <span className="material-symbols-outlined text-sm">add_box</span>
                        Nhập kho
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
