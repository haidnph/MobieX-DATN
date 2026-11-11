// src/features/admin/AdminOrderListPage.jsx
// Admin order listing with smart status tabs, multi-faceted filtering, keyword search, CSV export, and pagination.

import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending:    { label: 'Chờ xác nhận',  variant: 'pending', icon: 'pending_actions' },
  confirmed:  { label: 'Đã xác nhận',   variant: 'info',    icon: 'task_alt' },
  processing: { label: 'Đang chuẩn bị', variant: 'info',    icon: 'package_2' },
  shipping:   { label: 'Đang giao',     variant: 'warning', icon: 'local_shipping' },
  delivered:  { label: 'Giao thành công', variant: 'success', icon: 'check_circle' },
  cancelled:  { label: 'Đã hủy',        variant: 'error',   icon: 'cancel' },
}

const STATUS_TABS = [
  { key: '',           label: 'Tất cả' },
  { key: 'pending',    label: 'Chờ xác nhận' },
  { key: 'confirmed',  label: 'Đã xác nhận' },
  { key: 'processing', label: 'Đang chuẩn bị' },
  { key: 'shipping',   label: 'Đang giao' },
  { key: 'delivered',  label: 'Hoàn thành' },
  { key: 'cancelled',  label: 'Đã hủy' },
]

export default function AdminOrderListPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const statusParam        = searchParams.get('status') || ''
  const searchParam        = searchParams.get('q') || ''
  const paymentStatusParam = searchParams.get('payment_status') || ''
  const fromDateParam      = searchParams.get('from_date') || ''
  const toDateParam        = searchParams.get('to_date') || ''
  const pageParam          = Number(searchParams.get('page')) || 1

  const [orders, setOrders] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [keyword, setKeyword] = useState(searchParam)
  const [showAdvanced, setShowAdvanced] = useState(Boolean(paymentStatusParam || fromDateParam || toDateParam))

  // Fetch orders
  useEffect(() => {
    setLoading(true)
    const params = { page: pageParam, per_page: 10 }
    if (statusParam) params.status = statusParam
    if (searchParam) params.search = searchParam
    if (paymentStatusParam) params.payment_status = paymentStatusParam
    if (fromDateParam) params.from_date = fromDateParam
    if (toDateParam) params.to_date = toDateParam

    axiosClient
      .get(EP.ADMIN_ORDERS, { params })
      .then(({ data }) => {
        const payload = data.data ?? data
        const items = Array.isArray(payload) ? payload : (payload.data ?? [])
        setOrders(items)
        if (payload.current_page) {
          setMeta({
            current_page: payload.current_page,
            last_page: payload.last_page,
            total: payload.total,
          })
        }
      })
      .catch(() => setOrders([]))
      .finally(() => setLoading(false))
  }, [statusParam, searchParam, paymentStatusParam, fromDateParam, toDateParam, pageParam])

  // Handlers
  const handleSearch = (e) => {
    e.preventDefault()
    const next = new URLSearchParams(searchParams)
    if (keyword.trim()) next.set('q', keyword.trim())
    else next.delete('q')
    next.delete('page')
    setSearchParams(next)
  }

  const handleStatusFilter = (val) => {
    const next = new URLSearchParams(searchParams)
    if (val) next.set('status', val)
    else next.delete('status')
    next.delete('page')
    setSearchParams(next)
  }

  const handlePaymentFilter = (val) => {
    const next = new URLSearchParams(searchParams)
    if (val) next.set('payment_status', val)
    else next.delete('payment_status')
    next.delete('page')
    setSearchParams(next)
  }

  const handleDateFilter = (type, val) => {
    const next = new URLSearchParams(searchParams)
    if (val) next.set(type, val)
    else next.delete(type)
    next.delete('page')
    setSearchParams(next)
  }

  const handleResetFilters = () => {
    setKeyword('')
    setSearchParams(new URLSearchParams())
  }

  const handlePageChange = (newPage) => {
    const next = new URLSearchParams(searchParams)
    next.set('page', newPage)
    setSearchParams(next)
  }

  const handleExportCSV = () => {
    if (orders.length === 0) return

    const BOM = '\uFEFF'
    const headers = [
      'Mã đơn hàng',
      'Khách hàng',
      'SĐT',
      'Địa chỉ',
      'Ngày tạo',
      'Tổng tiền (VNĐ)',
      'Trạng thái đơn',
      'Thanh toán',
    ]

    const rows = orders.map((o) => [
      `"${o.order_code || o.id}"`,
      `"${o.shipping_name || o.user?.name || 'Khách lẻ'}"`,
      `"${o.shipping_phone || ''}"`,
      `"${(o.shipping_address_line || o.shipping_address || '').replace(/"/g, '""')}"`,
      `"${formatDateTime(o.created_at)}"`,
      `"${o.total_amount || 0}"`,
      `"${STATUS_CONFIG[o.status]?.label || o.status}"`,
      `"${o.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán'}"`,
    ])

    const csvContent = BOM + [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `MobieX_DonHang_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  // Active filters list
  const activeFilters = useMemo(() => {
    const list = []
    if (statusParam) {
      list.push({ key: 'status', label: `Trạng thái: ${STATUS_CONFIG[statusParam]?.label || statusParam}` })
    }
    if (searchParam) {
      list.push({ key: 'q', label: `Tìm: "${searchParam}"` })
    }
    if (paymentStatusParam) {
      list.push({ key: 'payment_status', label: `Thanh toán: ${paymentStatusParam === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán'}` })
    }
    if (fromDateParam) {
      list.push({ key: 'from_date', label: `Từ: ${fromDateParam}` })
    }
    if (toDateParam) {
      list.push({ key: 'to_date', label: `Đến: ${toDateParam}` })
    }
    return list
  }, [statusParam, searchParam, paymentStatusParam, fromDateParam, toDateParam])

  const removeFilter = (key) => {
    const next = new URLSearchParams(searchParams)
    next.delete(key)
    if (key === 'q') setKeyword('')
    next.delete('page')
    setSearchParams(next)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý đơn hàng</h1>
          <p className="text-sm text-on-surface-variant">
            Tổng số: <strong className="text-on-surface">{meta.total}</strong> đơn hàng trên hệ thống
          </p>
        </div>
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <button
            type="button"
            onClick={handleExportCSV}
            disabled={orders.length === 0}
            className="px-4 py-2 bg-surface-container-high hover:bg-surface-bright text-on-surface rounded-xl text-sm font-semibold border border-outline-variant/30 transition-colors flex items-center gap-1.5 shadow-xs disabled:opacity-50"
          >
            <span className="material-symbols-outlined text-base text-secondary">file_download</span>
            Xuất CSV
          </button>
        </div>
      </div>

      {/* Smart Status Tabs */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none border-b border-outline-variant/20">
        {STATUS_TABS.map((tab) => {
          const isActive = statusParam === tab.key
          return (
            <button
              key={tab.key}
              onClick={() => handleStatusFilter(tab.key)}
              className={[
                'px-4 py-2.5 rounded-xl text-sm font-medium transition-all shrink-0 flex items-center gap-2',
                isActive
                  ? 'bg-primary text-on-primary shadow-xs font-semibold'
                  : 'bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface',
              ].join(' ')}
            >
              {tab.key && STATUS_CONFIG[tab.key]?.icon && (
                <span className="material-symbols-outlined text-base">{STATUS_CONFIG[tab.key].icon}</span>
              )}
              <span>{tab.label}</span>
            </button>
          )
        })}
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 space-y-4">
        <div className="flex flex-col md:flex-row gap-3 justify-between items-center">
          {/* Keyword Search */}
          <form onSubmit={handleSearch} className="flex-1 w-full md:max-w-md flex gap-2">
            <div className="relative flex-1">
              <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none">
                search
              </span>
              <input
                type="text"
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                placeholder="Tìm mã đơn, tên hoặc số điện thoại khách hàng..."
                className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl pl-9 pr-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
            <button
              type="submit"
              className="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-semibold hover:bg-primary/90 transition-colors shrink-0"
            >
              Tìm
            </button>
          </form>

          {/* Filter Toggle Button */}
          <div className="flex items-center gap-2 w-full md:w-auto justify-end">
            <button
              type="button"
              onClick={() => setShowAdvanced((v) => !v)}
              className={[
                'flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all',
                showAdvanced || activeFilters.length > 0
                  ? 'bg-primary-container/20 border-primary/40 text-primary'
                  : 'bg-surface-container-low border-outline-variant/40 text-on-surface-variant hover:text-on-surface',
              ].join(' ')}
            >
              <span className="material-symbols-outlined text-base">tune</span>
              <span>Bộ lọc nâng cao</span>
              {activeFilters.length > 0 && (
                <span className="w-5 h-5 rounded-full bg-primary text-on-primary text-[10px] flex items-center justify-center font-bold">
                  {activeFilters.length}
                </span>
              )}
            </button>
          </div>
        </div>

        {/* Collapsible Advanced Filters */}
        {showAdvanced && (
          <div className="pt-3 border-t border-outline-variant/15 grid grid-cols-1 sm:grid-cols-3 gap-3 animate-in fade-in duration-150">
            {/* Payment Status */}
            <div>
              <label className="block text-xs font-semibold text-on-surface-variant uppercase mb-1">
                Thanh toán
              </label>
              <select
                value={paymentStatusParam}
                onChange={(e) => handlePaymentFilter(e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="">Tất cả trạng thái</option>
                <option value="paid">Đã thanh toán (Paid)</option>
                <option value="unpaid">Chưa thanh toán (Unpaid)</option>
              </select>
            </div>

            {/* From Date */}
            <div>
              <label className="block text-xs font-semibold text-on-surface-variant uppercase mb-1">
                Từ ngày
              </label>
              <input
                type="date"
                value={fromDateParam}
                onChange={(e) => handleDateFilter('from_date', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>

            {/* To Date */}
            <div>
              <label className="block text-xs font-semibold text-on-surface-variant uppercase mb-1">
                Đến ngày
              </label>
              <input
                type="date"
                value={toDateParam}
                onChange={(e) => handleDateFilter('to_date', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
          </div>
        )}

        {/* Active Filters Pills */}
        {activeFilters.length > 0 && (
          <div className="pt-2 border-t border-outline-variant/15 flex flex-wrap items-center gap-2">
            <span className="text-xs text-on-surface-variant">Đang lọc:</span>
            {activeFilters.map((f) => (
              <span
                key={f.key}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-surface-container-high text-on-surface border border-outline-variant/30"
              >
                <span>{f.label}</span>
                <button
                  type="button"
                  onClick={() => removeFilter(f.key)}
                  className="hover:text-error transition-colors"
                >
                  <span className="material-symbols-outlined text-xs">close</span>
                </button>
              </span>
            ))}
            <button
              type="button"
              onClick={handleResetFilters}
              className="text-xs text-primary hover:underline font-medium ml-1"
            >
              Xóa tất cả bộ lọc
            </button>
          </div>
        )}
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : orders.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">inbox</span>
            <p className="text-sm font-medium">Không tìm thấy đơn hàng nào phù hợp với bộ lọc.</p>
            {activeFilters.length > 0 && (
              <button
                type="button"
                onClick={handleResetFilters}
                className="text-xs text-primary font-semibold hover:underline"
              >
                Đặt lại toàn bộ bộ lọc
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Mã đơn</th>
                  <th className="px-5 py-3.5">Khách hàng</th>
                  <th className="px-5 py-3.5">Ngày đặt</th>
                  <th className="px-5 py-3.5">Tổng tiền</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-center">Thanh toán</th>
                  <th className="px-5 py-3.5 text-right">Chi tiết</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-on-surface">
                {orders.map((order) => {
                  const cfg = STATUS_CONFIG[order.status] || {
                    label: order.status,
                    variant: 'neutral',
                  }
                  const isPaid = order.payment_status === 'paid'

                  return (
                    <tr
                      key={order.id}
                      className="hover:bg-surface-container-high/40 transition-colors"
                    >
                      <td className="px-5 py-4 font-mono font-semibold text-primary">
                        <Link
                          to={`/admin/orders/${order.id}`}
                          className="hover:underline flex items-center gap-1.5"
                        >
                          <span className="material-symbols-outlined text-base">receipt</span>
                          <span>{order.order_code || `#${order.id}`}</span>
                        </Link>
                      </td>
                      <td className="px-5 py-4">
                        <div className="font-semibold text-on-surface">
                          {order.shipping_name || order.user?.name || 'Khách lẻ'}
                        </div>
                        <div className="text-xs text-on-surface-variant">
                          {order.shipping_phone || order.user?.email || '—'}
                        </div>
                      </td>
                      <td className="px-5 py-4 text-xs text-on-surface-variant">
                        {formatDateTime(order.created_at)}
                      </td>
                      <td className="px-5 py-4 font-semibold text-primary whitespace-nowrap">
                        {formatCurrencyVND(order.total_amount)}
                      </td>
                      <td className="px-5 py-4 text-center">
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span
                          className={[
                            'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold',
                            isPaid
                              ? 'bg-success-container text-on-success-container'
                              : 'bg-surface-container-high text-on-surface-variant',
                          ].join(' ')}
                        >
                          <span className="material-symbols-outlined text-xs">
                            {isPaid ? 'check_circle' : 'schedule'}
                          </span>
                          {isPaid ? 'Đã thanh toán' : 'Chưa thanh toán'}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-right">
                        <Link
                          to={`/admin/orders/${order.id}`}
                          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-surface-container-high hover:bg-primary hover:text-on-primary transition-all text-xs font-semibold text-on-surface border border-outline-variant/30"
                        >
                          <span>Xem</span>
                          <span className="material-symbols-outlined text-sm">arrow_forward</span>
                        </Link>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {meta.last_page > 1 && (
          <div className="p-4 border-t border-outline-variant/20 flex justify-center">
            <Pagination
              currentPage={meta.current_page}
              totalPages={meta.last_page}
              onPageChange={handlePageChange}
            />
          </div>
        )}
      </div>
    </div>
  )
}
