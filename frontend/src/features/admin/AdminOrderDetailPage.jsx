// src/features/admin/AdminOrderDetailPage.jsx
// Admin order detail management with strict state machine workflow transition controls.

import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Badge from '../../components/common/Badge'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ xác nhận', variant: 'pending' },
  confirmed: { label: 'Đã xác nhận', variant: 'info' },
  processing: { label: 'Đang chuẩn bị hàng', variant: 'info' },
  shipping: { label: 'Đang giao hàng (Xuất kho)', variant: 'warning' },
  delivered: { label: 'Giao hàng thành công', variant: 'success' },
  cancelled: { label: 'Đã hủy (Giải phóng kho)', variant: 'error' },
}

// Strict one-way state machine
const VALID_TRANSITIONS = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'cancelled'],
  processing: ['shipping', 'cancelled'],
  shipping: ['delivered'],
  delivered: [],
  cancelled: [],
}

export default function AdminOrderDetailPage() {
  const { id } = useParams()
  const { showToast } = useToast()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)

  // Status transition state
  const [nextStatus, setNextStatus] = useState('')
  const [transitionNote, setTransitionNote] = useState('')
  const [updating, setUpdating] = useState(false)

  const fetchOrder = () => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_ORDER_DETAIL(id))
      .then(({ data }) => {
        const ord = data.data ?? data
        setOrder(ord)
        // Reset next status selection
        const available = VALID_TRANSITIONS[ord.status] || []
        setNextStatus(available[0] || '')
      })
      .catch((err) => showToast(err.message || 'Không thể tải thông tin đơn hàng', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchOrder()
  }, [id])

  const handleUpdateStatus = async (e) => {
    e.preventDefault()
    if (!nextStatus) return

    setUpdating(true)
    try {
      await axiosClient.patch(EP.ADMIN_ORDER_STATUS(id), {
        status: nextStatus,
        note: transitionNote.trim() || undefined,
      })
      showToast(`Đã chuyển trạng thái đơn hàng sang: ${STATUS_CONFIG[nextStatus]?.label || nextStatus}`, 'success')
      setTransitionNote('')
      fetchOrder()
    } catch (err) {
      showToast(err.message || 'Không thể cập nhật trạng thái đơn hàng', 'error')
    } finally {
      setUpdating(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" />
      </div>
    )
  }

  if (!order) {
    return (
      <div className="max-w-md mx-auto px-4 py-20 text-center space-y-4">
        <span className="material-symbols-outlined text-6xl text-outline">error</span>
        <h2 className="text-xl font-bold text-on-surface">Không tìm thấy đơn hàng</h2>
        <Link to="/admin/orders" className="text-primary hover:underline">
          Quay lại danh sách quản lý
        </Link>
      </div>
    )
  }

  const cfg = STATUS_CONFIG[order.status] || { label: order.status, variant: 'neutral' }
  const allowedNext = VALID_TRANSITIONS[order.status] || []
  const items = order.items ?? []
  const histories = order.status_histories ?? []

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <nav className="flex items-center gap-2 text-xs text-on-surface-variant mb-1">
            <Link to="/admin/orders" className="hover:text-on-surface">Quản lý đơn hàng</Link>
            <span>/</span>
            <span className="font-mono text-on-surface">#{order.order_code || order.id}</span>
          </nav>
          <h1 className="text-2xl font-bold text-on-surface flex items-center gap-3">
            Đơn hàng <span className="font-mono text-primary">#{order.order_code || order.id}</span>
            <Badge variant={cfg.variant}>{cfg.label}</Badge>
          </h1>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Left: Info, Items, Timeline (8 cols) */}
        <div className="lg:col-span-8 space-y-6">
          {/* Customer & Shipping Summary */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                Khách hàng & Nhận hàng
              </h3>
              <p className="text-sm font-semibold text-on-surface">{order.recipient_name || order.user?.name}</p>
              <p className="text-xs text-on-surface-variant">SĐT: {order.recipient_phone || order.phone}</p>
              <p className="text-xs text-on-surface-variant">Email: {order.user?.email || '—'}</p>
              <p className="text-xs text-on-surface-variant leading-relaxed pt-1">
                Địa chỉ: {order.shipping_address}
              </p>
              {order.note && (
                <p className="text-xs text-outline italic">Ghi chú khách: {order.note}</p>
              )}
            </div>

            <div className="space-y-2">
              <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                Thanh toán & Khuyến mãi
              </h3>
              <div className="flex justify-between text-xs">
                <span className="text-outline">Phương thức:</span>
                <span className="font-mono font-semibold uppercase text-on-surface">{order.payment_method || 'COD'}</span>
              </div>
              <div className="flex justify-between text-xs">
                <span className="text-outline">Trạng thái:</span>
                <span className="font-semibold text-secondary">
                  {order.payment_status === 'settled' || order.payment_status === 'paid' ? 'Đã thu tiền' : 'Chưa thu tiền'}
                </span>
              </div>
              {order.coupon_code && (
                <div className="flex justify-between text-xs">
                  <span className="text-outline">Voucher áp dụng:</span>
                  <span className="font-mono font-bold text-secondary">{order.coupon_code}</span>
                </div>
              )}
              <div className="flex justify-between text-xs">
                <span className="text-outline">Thời gian tạo:</span>
                <span className="text-on-surface">{formatDateTime(order.created_at)}</span>
              </div>
            </div>
          </div>

          {/* Items */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4">
            <h3 className="text-sm font-bold text-on-surface uppercase tracking-wider">
              Danh sách sản phẩm ({items.length})
            </h3>
            <div className="divide-y divide-outline-variant/10">
              {items.map((item) => {
                const variant = item.variant || item.product_variant || {}
                const product = variant.product || item.product || {}
                const price = item.price ?? variant.price ?? 0

                return (
                  <div key={item.id} className="py-3 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3 min-w-0">
                      <div className="w-12 h-12 bg-surface-container-high rounded-xl overflow-hidden shrink-0 flex items-center justify-center p-1">
                        {variant.image_url || product.thumbnail ? (
                          <img
                            src={variant.image_url || product.thumbnail}
                            alt={item.product_name}
                            className="w-full h-full object-contain"
                          />
                        ) : (
                          <span className="material-symbols-outlined text-xl text-outline">devices</span>
                        )}
                      </div>
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-on-surface truncate">{item.product_name || product.name}</p>
                        <p className="text-xs text-outline font-mono">
                          SKU: {variant.sku || '—'} • SL: {item.quantity}
                        </p>
                      </div>
                    </div>

                    <div className="text-right shrink-0">
                      <p className="text-sm font-bold text-on-surface">{formatCurrencyVND(price * item.quantity)}</p>
                      <p className="text-xs text-outline">{formatCurrencyVND(price)} / cái</p>
                    </div>
                  </div>
                )
              })}
            </div>

            {/* Total breakdown */}
            <div className="pt-4 border-t border-outline-variant/10 space-y-2 text-sm max-w-xs ml-auto">
              <div className="flex justify-between text-on-surface-variant">
                <span>Tạm tính:</span>
                <span className="text-on-surface">{formatCurrencyVND(order.subtotal_amount ?? order.subtotal)}</span>
              </div>
              {Number(order.discount_amount) > 0 && (
                <div className="flex justify-between text-secondary">
                  <span>Giảm giá:</span>
                  <span>-{formatCurrencyVND(order.discount_amount)}</span>
                </div>
              )}
              <div className="flex justify-between text-base font-extrabold text-on-surface pt-2 border-t border-outline-variant/10">
                <span>Tổng cộng:</span>
                <span className="text-xl text-primary">{formatCurrencyVND(order.total_amount ?? order.total)}</span>
              </div>
            </div>
          </div>

          {/* Timeline */}
          {histories.length > 0 && (
            <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4">
              <h3 className="text-sm font-bold text-on-surface uppercase tracking-wider">
                Lịch sử luồng trạng thái
              </h3>
              <div className="space-y-4">
                {histories.map((hist, idx) => (
                  <div key={idx} className="flex items-start gap-3 text-xs border-l-2 border-primary/40 pl-3">
                    <div className="flex-1">
                      <span className="font-bold text-on-surface">
                        {STATUS_CONFIG[hist.status]?.label || hist.status}
                      </span>
                      {hist.from_status && (
                        <span className="text-outline"> (từ: {STATUS_CONFIG[hist.from_status]?.label || hist.from_status})</span>
                      )}
                      {hist.note && <p className="text-on-surface-variant mt-0.5">{hist.note}</p>}
                    </div>
                    <span className="text-outline">{formatDateTime(hist.created_at)}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Right: State Machine Workflow Controls (4 cols) */}
        <div className="lg:col-span-4 space-y-6">
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-5">
            <div>
              <h3 className="text-base font-bold text-on-surface">Cập nhật trạng thái đơn</h3>
              <p className="text-xs text-on-surface-variant mt-1">
                Quy trình State Machine một chiều theo kiến trúc MobieX.
              </p>
            </div>

            {allowedNext.length === 0 ? (
              <div className="bg-surface-container-low rounded-xl p-4 text-center text-xs text-outline space-y-1">
                <span className="material-symbols-outlined text-2xl text-secondary block">lock</span>
                Đơn hàng đã ở trạng thái kết thúc (<strong>{cfg.label}</strong>). Không thể chuyển tiếp.
              </div>
            ) : (
              <form onSubmit={handleUpdateStatus} className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                    Trạng thái kế tiếp:
                  </label>
                  <select
                    value={nextStatus}
                    onChange={(e) => setNextStatus(e.target.value)}
                    className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
                    required
                  >
                    {allowedNext.map((st) => (
                      <option key={st} value={st}>
                        {STATUS_CONFIG[st]?.label || st}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                    Ghi chú điều chuyển (tùy chọn):
                  </label>
                  <textarea
                    value={transitionNote}
                    onChange={(e) => setTransitionNote(e.target.value)}
                    rows={3}
                    placeholder="Nhập ghi chú cho lượt cập nhật..."
                    className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                  />
                </div>

                <div className="p-3 bg-primary/10 border border-primary/20 rounded-xl text-xs text-on-surface-variant leading-relaxed">
                  {nextStatus === 'shipping' && (
                    <span>
                      ⚠️ <strong>Thời điểm Dispatch:</strong> Hệ thống sẽ xuất kho và trừ tồn thực tế (<code>on_hand_quantity</code>).
                    </span>
                  )}
                  {nextStatus === 'cancelled' && (
                    <span>
                      ⚠️ <strong>Hủy đơn:</strong> Hệ thống sẽ tự động hoàn trả số lượng giữ chỗ (<code>reserved_quantity</code>) về kho.
                    </span>
                  )}
                  {nextStatus !== 'shipping' && nextStatus !== 'cancelled' && (
                    <span>Hệ thống ghi nhận lịch sử trạng thái có định danh Admin thực hiện.</span>
                  )}
                </div>

                <Button type="submit" variant="primary" size="md" className="w-full" loading={updating}>
                  Xác nhận chuyển trạng thái
                </Button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
