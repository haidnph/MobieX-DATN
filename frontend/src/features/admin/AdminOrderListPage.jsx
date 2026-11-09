// src/features/admin/AdminOrderListPage.jsx
// Admin order listing with filtering, keyword search, and pagination.

import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ xác nhận', variant: 'pending' },
  confirmed: { label: 'Đã xác nhận', variant: 'info' },
  processing: { label: 'Đang chuẩn bị', variant: 'info' },
  shipping: { label: 'Đang giao', variant: 'warning' },
  delivered: { label: 'Giao thành công', variant: 'success' },
  cancelled: { label: 'Đã hủy', variant: 'error' },
}

export default function AdminOrderListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const statusParam = searchParams.get('status') || ''
  const searchParam = searchParams.get('q') || ''
  const pageParam = Number(searchParams.get('page')) || 1

  const [orders, setOrders] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [keyword, setKeyword] = useState(searchParam)

  useEffect(() => {
    setLoading(true)
    const params = { page: pageParam, per_page: 10 }
    if (statusParam) params.status = statusParam
    if (searchParam) params.search = searchParam

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
  }, [statusParam, searchParam, pageParam])

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

  const handlePageChange = (newPage) => {
    const next = new URLSearchParams(searchParams)
    next.set('page', newPage)
    setSearchParams(next)
  }

  const handleExportCSV = () => {
    if (orders.length === 0) return

    // UTF-8 BOM so Excel displays Vietnamese correctly
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
      `"${(o.shipping_address || '').replace(/"/g, '""')}"`,
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
        <button
          type="button"
          onClick={handleExportCSV}
          disabled={orders.length === 0}
          className="px-4 py-2 bg-surface-container-high hover:bg-surface-bright text-on-surface rounded-xl text-sm font-semibold border border-outline-variant/30 transition-colors flex items-center gap-1.5 shadow-xs disabled:opacity-50 self-start sm:self-auto"
        >
          <span className="material-symbols-outlined text-base text-secondary">file_download</span>
          Xuất báo cáo CSV
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col md:flex-row gap-4 justify-between items-center">
        <form onSubmit={handleSearch} className="flex-1 w-full md:max-w-md flex gap-2">
          <input
            type="text"
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
            placeholder="Tìm theo mã đơn hoặc số điện thoại..."
            className="flex-1 bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
          />
          <button
            type="submit"
            className="px-4 py-2 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors flex items-center gap-1"
          >
            <span className="material-symbols-outlined text-base">search</span>
            Tìm
          </button>
        </form>

        <div className="flex items-center gap-3 w-full md:w-auto">
          <label className="text-xs font-semibold text-on-surface-variant uppercase shrink-0">
            Trạng thái:
          </label>
          <select
            value={statusParam}
            onChange={(e) => handleStatusFilter(e.target.value)}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ xác nhận</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="processing">Đang chuẩn bị</option>
            <option value="shipping">Đang giao hàng</option>
            <option value="delivered">Giao thành công</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>
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
            <p className="text-sm font-medium">Không tìm thấy đơn hàng nào phù hợp.</p>
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
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {orders.map((order) => {
                  const cfg = STATUS_CONFIG[order.status] || { label: order.status, variant: 'neutral' }
                  return (
                    <tr key={order.id} className="hover:bg-surface-container-high/40 transition-colors">
                      <td className="px-5 py-4 font-mono font-bold text-primary">
                        #{order.order_code || order.id}
                      </td>
                      <td className="px-5 py-4">
                        <p className="font-semibold text-on-surface">{order.recipient_name || order.user?.name || 'Khách'}</p>
                        <p className="text-xs text-outline">{order.recipient_phone || order.phone || '—'}</p>
                      </td>
                      <td className="px-5 py-4 text-xs text-on-surface-variant">
                        {formatDateTime(order.created_at)}
                      </td>
                      <td className="px-5 py-4 font-extrabold text-on-surface">
                        {formatCurrencyVND(order.total_amount ?? order.total)}
                      </td>
                      <td className="px-5 py-4 text-center">
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className="text-xs font-semibold uppercase text-outline">
                          {order.payment_method || 'COD'}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-right">
                        <Link
                          to={`/admin/orders/${order.id}`}
                          className="px-3 py-1.5 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors"
                        >
                          Chi tiết
                        </Link>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {meta.last_page > 1 && (
        <div className="flex justify-center pt-2">
          <Pagination
            currentPage={meta.current_page}
            totalPages={meta.last_page}
            onPageChange={handlePageChange}
          />
        </div>
      )}
    </div>
  )
}
