// src/features/orders/OrderHistoryPage.jsx
// Customer order list with status filter tabs, search, and pagination.

import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDate } from '../../utils/formatDate'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'
import Spinner from '../../components/common/Spinner'

const STATUS_TABS = [
  { key: '', label: 'Tất cả' },
  { key: 'pending', label: 'Chờ xử lý' },
  { key: 'confirmed', label: 'Đã xác nhận' },
  { key: 'processing', label: 'Đang đóng gói' },
  { key: 'shipping', label: 'Đang giao' },
  { key: 'delivered', label: 'Đã giao' },
  { key: 'cancelled', label: 'Đã hủy' },
]

const STATUS_CONFIG = {
  pending: { label: 'Chờ xử lý', variant: 'pending' },
  confirmed: { label: 'Đã xác nhận', variant: 'info' },
  processing: { label: 'Đang xử lý', variant: 'info' },
  shipping: { label: 'Đang giao hàng', variant: 'warning' },
  delivered: { label: 'Hoàn thành', variant: 'success' },
  cancelled: { label: 'Đã hủy', variant: 'error' },
}

export default function OrderHistoryPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const statusParam = searchParams.get('status') || ''
  const pageParam = Number(searchParams.get('page')) || 1

  const { addToCart } = useCart()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [orders, setOrders] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)
  const [reorderingOrderId, setReorderingOrderId] = useState(null)

  const handleReorder = async (order) => {
    const items = order?.items || []
    if (items.length === 0) return

    setReorderingOrderId(order.id)
    let addedCount = 0

    for (const item of items) {
      const variantId = item.product_variant_id || item.variant?.id
      if (variantId) {
        try {
          await addToCart(variantId, item.quantity || 1)
          addedCount++
        } catch {
          // Skip out-of-stock items
        }
      }
    }

    setReorderingOrderId(null)

    if (addedCount > 0) {
      showToast(`Đã thêm ${addedCount} sản phẩm vào giỏ hàng!`, 'success')
      navigate('/cart')
    } else {
      showToast('Sản phẩm trong đơn hiện không còn khả dụng.', 'warning')
    }
  }

  useEffect(() => {
    setLoading(true)
    const params = { page: pageParam, per_page: 8 }
    if (statusParam) params.status = statusParam

    axiosClient
      .get(EP.ORDERS, { params })
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
  }, [statusParam, pageParam])

  const handleTabChange = (key) => {
    const next = new URLSearchParams(searchParams)
    if (key) next.set('status', key)
    else next.delete('status')
    next.delete('page')
    setSearchParams(next)
  }

  const handlePageChange = (newPage) => {
    const next = new URLSearchParams(searchParams)
    next.set('page', newPage)
    setSearchParams(next)
  }

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-6">
      {/* Title */}
      <div className="flex items-center gap-3">
        <span className="w-1 h-6 bg-primary rounded-full" />
        <h1 className="text-2xl font-bold text-on-surface">Đơn hàng của tôi</h1>
      </div>

      {/* Filter Tabs */}
      <div className="flex gap-2 overflow-x-auto pb-2 border-b border-outline-variant/20 scrollbar-none">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.key}
            onClick={() => handleTabChange(tab.key)}
            className={[
              'px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all',
              statusParam === tab.key
                ? 'bg-primary-container text-on-primary-container font-semibold shadow'
                : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Orders List */}
      {loading ? (
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      ) : orders.length === 0 ? (
        <div className="bg-surface-container rounded-2xl p-12 text-center text-on-surface-variant space-y-3">
          <span className="material-symbols-outlined text-6xl text-outline">receipt_long</span>
          <h2 className="text-lg font-bold text-on-surface">Không tìm thấy đơn hàng nào</h2>
          <p className="text-sm">Bạn chưa có đơn hàng nào ở trạng thái này.</p>
          <Link
            to="/products"
            className="inline-block mt-2 px-5 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors"
          >
            Mua sắm ngay
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => {
            const cfg = STATUS_CONFIG[order.status] || { label: order.status, variant: 'neutral' }
            const items = order.items ?? []
            const firstItem = items[0]
            const remainingCount = items.length - 1

            return (
              <div
                key={order.id}
                className="bg-surface-container rounded-2xl border border-outline-variant/20 p-5 md:p-6 space-y-4 hover:border-outline-variant/40 transition-colors"
              >
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-outline-variant/10 text-sm">
                  <div className="flex items-center gap-3">
                    <span className="font-mono font-bold text-on-surface">#{order.order_code || order.id}</span>
                    <span className="text-outline">•</span>
                    <span className="text-on-surface-variant text-xs">{formatDate(order.created_at)}</span>
                  </div>
                  <Badge variant={cfg.variant}>{cfg.label}</Badge>
                </div>

                {/* Items Preview */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="w-14 h-14 bg-surface-container-high rounded-xl overflow-hidden shrink-0 flex items-center justify-center p-1">
                      {firstItem?.variant?.image_url || firstItem?.product?.thumbnail ? (
                        <img
                          src={firstItem?.variant?.image_url || firstItem?.product?.thumbnail}
                          alt={firstItem?.product_name || 'Item'}
                          className="w-full h-full object-contain"
                        />
                      ) : (
                        <span className="material-symbols-outlined text-2xl text-outline">smartphone</span>
                      )}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-on-surface line-clamp-1">
                        {firstItem?.product_name || firstItem?.variant?.product?.name || 'Sản phẩm MobieX'}
                      </p>
                      <p className="text-xs text-on-surface-variant">
                        Số lượng: x{firstItem?.quantity || 1}
                        {remainingCount > 0 && ` (và ${remainingCount} sản phẩm khác)`}
                      </p>
                    </div>
                  </div>

                  <div className="flex flex-wrap items-center gap-3 self-end sm:self-center">
                    <div className="text-right">
                      <p className="text-xs text-on-surface-variant">Tổng tiền</p>
                      <p className="text-base font-extrabold text-primary">
                        {formatCurrencyVND(order.total_amount ?? order.total)}
                      </p>
                    </div>

                    {(order.status === 'delivered' || order.status === 'cancelled') && (
                      <button
                        type="button"
                        onClick={() => handleReorder(order)}
                        disabled={reorderingOrderId === order.id}
                        className="flex items-center gap-1 px-3 py-2 bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary rounded-xl text-xs font-bold transition-all disabled:opacity-50"
                      >
                        <span className="material-symbols-outlined text-base">
                          {reorderingOrderId === order.id ? 'progress_activity' : 'replay'}
                        </span>
                        <span>{reorderingOrderId === order.id ? 'Đang thêm...' : 'Mua lại'}</span>
                      </button>
                    )}

                    <Link
                      to={`/orders/${order.id}`}
                      className="px-4 py-2 bg-surface-container-high text-on-surface hover:bg-surface-bright rounded-xl text-sm font-semibold transition-colors"
                    >
                      Chi tiết
                    </Link>
                  </div>
                </div>
              </div>
            )
          })}

          {meta.last_page > 1 && (
            <div className="pt-4 flex justify-center">
              <Pagination
                currentPage={meta.current_page}
                totalPages={meta.last_page}
                onPageChange={handlePageChange}
              />
            </div>
          )}
        </div>
      )}
    </div>
  )
}
