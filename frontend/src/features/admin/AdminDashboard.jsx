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
import RevenueAreaChart from '../../components/charts/RevenueAreaChart'
import OrderStatusDonutChart from '../../components/charts/OrderStatusDonutChart'
import TopSellingLeaderboard from '../../components/dashboard/TopSellingLeaderboard'
import PaymentMethodCard from '../../components/dashboard/PaymentMethodCard'

export default function AdminDashboard() {
  const [stats, setStats] = useState(null)
  const [revenueChart, setRevenueChart] = useState([])
  const [chartSummary, setChartSummary] = useState(null)
  const [range, setRange] = useState('7')
  const [customRange, setCustomRange] = useState({ start: '', end: '' })
  const [appliedRange, setAppliedRange] = useState(null)
  const [loading, setLoading] = useState(true)
  const [chartLoading, setChartLoading] = useState(false)
  const [refreshing, setRefreshing] = useState(false)

  // Fetch all dashboard data
  const loadDashboard = useCallback(async (selectedRange = range, isRefresh = false) => {
    if (isRefresh) setRefreshing(true)
    else setChartLoading(true)

    try {
      const [statsRes, chartRes] = await Promise.allSettled([
        axiosClient.get(EP.ADMIN_DASHBOARD_STATS, { params: selectedRange === 'custom' ? appliedRange : { days: Number(selectedRange) } }),
        axiosClient.get(EP.ADMIN_DASHBOARD_REVENUE_CHART, { params: selectedRange === 'custom' ? appliedRange : { days: Number(selectedRange) } }),
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
  }, [range, appliedRange])

  // Initial load
  useEffect(() => {
    loadDashboard(range)
  }, [loadDashboard, range])

  // Handle changing timeframe
  const handleDaysChange = (newDays) => {
    setRange(String(newDays))
    setAppliedRange(null)
    loadDashboard(String(newDays))
  }

  // Handle manual refresh
  const handleRefresh = () => {
    loadDashboard(range, true)
  }

  if (loading) {
    return (
<div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
        <Spinner size="lg" />
        <span className="text-xs text-on-surface-variant font-medium">Đang tải dữ liệu kinh doanh thời gian thực...</span>
      </div>
    )
  }

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

      <div className="flex flex-wrap items-end gap-3 rounded-xl border border-outline-variant/20 bg-surface-container p-4">
<label className="text-xs text-on-surface-variant">Khoảng thời gian<select value={range} onChange={(event) => { setRange(event.target.value); setAppliedRange(null) }} className="mt-1 block rounded-lg border border-outline-variant/30 bg-surface-container-high px-3 py-2 text-sm text-on-surface"><option value="1">Hôm nay</option><option value="2">Hôm qua</option><option value="7">7 ngày gần nhất</option><option value="14">14 ngày gần nhất</option><option value="30">30 ngày gần nhất</option><option value="90">90 ngày gần nhất</option><option value="month">Tháng này</option><option value="prev_month">Tháng trước</option><option value="custom">Tùy chỉnh</option></select></label>
        {range === 'custom' && <><label className="text-xs text-on-surface-variant">Từ ngày<input type="date" value={customRange.start} max={customRange.end || undefined} onChange={(event) => setCustomRange((current) => ({ ...current, start: event.target.value }))} className="mt-1 block rounded-lg border border-outline-variant/30 bg-surface-container-high px-3 py-2 text-sm text-on-surface" /></label><label className="text-xs text-on-surface-variant">Đến ngày<input type="date" value={customRange.end} min={customRange.start || undefined} onChange={(event) => setCustomRange((current) => ({ ...current, end: event.target.value }))} className="mt-1 block rounded-lg border border-outline-variant/30 bg-surface-container-high px-3 py-2 text-sm text-on-surface" /></label><button type="button" disabled={!customRange.start || !customRange.end || customRange.start > customRange.end} onClick={() => { setAppliedRange({ start_date: customRange.start, end_date: customRange.end }); loadDashboard('custom') }} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary disabled:opacity-50">Áp dụng</button><button type="button" onClick={() => { setCustomRange({ start: '', end: '' }); setRange('7'); setAppliedRange(null) }} className="rounded-lg border border-outline-variant/30 px-4 py-2 text-sm">Đặt lại</button></>}
        <span className="text-xs text-on-surface-variant">{appliedRange ? `${appliedRange.start_date} – ${appliedRange.end_date}` : `Đang xem: ${range} ngày gần nhất`}</span>
      </div>
      {/* Executive KPI Ribbon */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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

      </div>

      {/* Primary Analytics Section (Area/Bar Chart + Donut Status Breakdown) */}
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 items-stretch">
        {/* Revenue & Volume Area Chart */}
        <div className="xl:col-span-8 flex flex-col">
          <RevenueAreaChart
            data={revenueChart}
            days={Number(range) || 7}
            onDaysChange={handleDaysChange}
            summary={chartSummary}
            loading={chartLoading}
          />
        </div>

        {/* Order Status Donut Chart */}
        <div className="xl:col-span-4 flex flex-col">
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

    </div>
  )
}
