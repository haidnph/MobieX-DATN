// src/features/admin/AdminDashboard.jsx
// Admin dashboard overview: revenue analytics, pending order telemetry, and low-stock inventory alerts.

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AdminDashboard() {
  const [stats, setStats] = useState(null)
  const [revenueChart, setRevenueChart] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    Promise.allSettled([
      axiosClient.get(EP.ADMIN_DASHBOARD_STATS),
      axiosClient.get(EP.ADMIN_DASHBOARD_REVENUE_CHART, { params: { days: 7 } }),
    ])
      .then(([statsRes, chartRes]) => {
        if (statsRes.status === 'fulfilled') {
          setStats(statsRes.value.data.data ?? statsRes.value.data)
        }
        if (chartRes.status === 'fulfilled') {
          const chartData = chartRes.value.data.data ?? chartRes.value.data
          setRevenueChart(Array.isArray(chartData) ? chartData : [])
        }
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" />
      </div>
    )
  }

  const maxRevenue = Math.max(...revenueChart.map((d) => Number(d.revenue) || 0), 1)
  const lowStockItems = stats?.low_stock_items ?? []

  return (
    <div className="space-y-8">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-on-surface">Trung tâm điều khiển (Dashboard)</h1>
        <p className="text-sm text-on-surface-variant mt-1">
          Tổng quan số liệu kinh doanh, tiến độ đơn hàng và cảnh báo tồn kho thời gian thực.
        </p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Settled Revenue */}
        <div className="bg-surface-container rounded-2xl p-5 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-xs font-semibold uppercase tracking-wider">Doanh thu thực thu</span>
            <span className="material-symbols-outlined text-primary text-xl">payments</span>
          </div>
          <div className="mt-3">
            <h2 className="text-2xl font-extrabold text-on-surface">
              {formatCurrencyVND(stats?.total_settled_revenue ?? stats?.total_revenue ?? 0)}
            </h2>
            <p className="text-[11px] text-secondary mt-1">Giao dịch đã thanh toán thành công</p>
          </div>
        </div>

        {/* Today's Revenue */}
        <div className="bg-surface-container rounded-2xl p-5 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-xs font-semibold uppercase tracking-wider">Doanh thu hôm nay</span>
            <span className="material-symbols-outlined text-secondary text-xl">trending_up</span>
          </div>
          <div className="mt-3">
            <h2 className="text-2xl font-extrabold text-secondary">
              {formatCurrencyVND(stats?.today_revenue ?? 0)}
            </h2>
            <p className="text-[11px] text-on-surface-variant mt-1">Cập nhật theo ngày hiện tại</p>
          </div>
        </div>

        {/* Pending Orders */}
        <div className="bg-surface-container rounded-2xl p-5 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-xs font-semibold uppercase tracking-wider">Đơn chờ xử lý</span>
            <span className="material-symbols-outlined text-tertiary-container text-xl">pending_actions</span>
          </div>
          <div className="mt-3">
            <h2 className="text-2xl font-extrabold text-tertiary-container">
              {stats?.pending_orders_count ?? 0}
            </h2>
            <Link to="/admin/orders?status=pending" className="text-[11px] text-primary hover:underline mt-1 block">
              Xem và xử lý ngay →
            </Link>
          </div>
        </div>

        {/* Low Stock Items */}
        <div className="bg-surface-container rounded-2xl p-5 border border-outline-variant/20 flex flex-col justify-between">
          <div className="flex items-center justify-between text-on-surface-variant">
            <span className="text-xs font-semibold uppercase tracking-wider">Cảnh báo tồn kho</span>
            <span className="material-symbols-outlined text-error text-xl">warning</span>
          </div>
          <div className="mt-3">
            <h2 className="text-2xl font-extrabold text-error">
              {stats?.low_stock_items_count ?? lowStockItems.length}
            </h2>
            <p className="text-[11px] text-on-surface-variant mt-1">Sản phẩm sắp hết hàng (≤ 5)</p>
          </div>
        </div>
      </div>

      {/* Revenue Chart Section (7 Days) */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-base font-bold text-on-surface">Biểu đồ doanh thu 7 ngày gần nhất</h3>
            <p className="text-xs text-on-surface-variant">Theo dõi tốc độ tăng trưởng doanh số tuần qua</p>
          </div>
          <span className="material-symbols-outlined text-outline">bar_chart</span>
        </div>

        {revenueChart.length === 0 ? (
          <div className="py-12 text-center text-sm text-on-surface-variant">
            Chưa có dữ liệu phát sinh giao dịch trong 7 ngày gần nhất.
          </div>
        ) : (
          <div className="h-56 flex items-end gap-3 pt-6 pb-2 px-2">
            {revenueChart.map((d, idx) => {
              const val = Number(d.revenue) || 0
              const heightPercent = Math.max(8, Math.round((val / maxRevenue) * 100))
              return (
                <div key={idx} className="flex-1 flex flex-col items-center gap-2 h-full justify-end group">
                  <span className="text-[10px] text-outline opacity-0 group-hover:opacity-100 transition-opacity font-mono">
                    {formatCurrencyVND(val)}
                  </span>
                  <div
                    style={{ height: `${heightPercent}%` }}
                    className="w-full bg-primary-container group-hover:bg-primary rounded-t-lg transition-all duration-300 shadow-[0_0_10px_rgba(77,142,255,0.2)]"
                  />
                  <span className="text-[11px] text-on-surface-variant truncate w-full text-center">
                    {d.date || `N-${7 - idx}`}
                  </span>
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* Low Stock Alert Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden space-y-3">
        <div className="p-6 pb-2 flex items-center justify-between">
          <div>
            <h3 className="text-base font-bold text-on-surface">Cảnh báo tồn kho cạn kiệt</h3>
            <p className="text-xs text-on-surface-variant">Các biến thể có lượng tồn khả dụng cần nhập thêm</p>
          </div>
          <Badge variant="error">{lowStockItems.length} mục</Badge>
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
                  <th className="px-6 py-3">SKU</th>
                  <th className="px-6 py-3">Sản phẩm / Biến thể</th>
                  <th className="px-6 py-3 text-center">Khả dụng</th>
                  <th className="px-6 py-3 text-center">Tồn thực</th>
                  <th className="px-6 py-3 text-center">Đang giữ</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {lowStockItems.map((item, idx) => (
                  <tr key={idx} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="px-6 py-3 font-mono text-xs font-semibold text-primary">
                      {item.sku || '—'}
                    </td>
                    <td className="px-6 py-3 text-on-surface">
                      {item.product_name || item.variant_name || 'Biến thể'}
                    </td>
                    <td className="px-6 py-3 text-center">
                      <span className="px-2.5 py-1 bg-error-container/20 text-error rounded-full text-xs font-bold">
                        {item.available_quantity ?? 0}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-center text-on-surface-variant font-mono">
                      {item.on_hand_quantity ?? 0}
                    </td>
                    <td className="px-6 py-3 text-center text-outline font-mono">
                      {item.reserved_quantity ?? 0}
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
