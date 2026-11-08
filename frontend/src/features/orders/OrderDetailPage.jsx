// src/features/orders/OrderDetailPage.jsx
// Order details view with live timeline, cancellation modal, and verified review triggers.

import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Badge from '../../components/common/Badge'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import Spinner from '../../components/common/Spinner'
import ReviewModal from './ReviewModal'

const STATUS_CONFIG = {
  pending: { label: 'Chờ xác nhận', variant: 'pending', icon: 'hourglass_empty' },
  confirmed: { label: 'Đã xác nhận', variant: 'info', icon: 'check_circle' },
  processing: { label: 'Đang chuẩn bị hàng', variant: 'info', icon: 'inventory' },
  shipping: { label: 'Đang vận chuyển', variant: 'warning', icon: 'local_shipping' },
  delivered: { label: 'Giao hàng thành công', variant: 'success', icon: 'verified' },
  cancelled: { label: 'Đã hủy', variant: 'error', icon: 'cancel' },
}

export default function OrderDetailPage() {
  const { id } = useParams()
  const { showToast } = useToast()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)

  // Cancel order modal
  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelling, setCancelling] = useState(false)

  // Review modal
  const [reviewItem, setReviewItem] = useState(null)

  const fetchOrderDetail = () => {
    setLoading(true)
    axiosClient
      .get(EP.ORDER_DETAIL(id))
      .then(({ data }) => setOrder(data.data ?? data))
      .catch((err) => showToast(err.message || 'Không tìm thấy đơn hàng', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchOrderDetail()
  }, [id])

  const handleCancelOrder = async () => {
    if (!cancelReason.trim()) {
      showToast('Vui lòng nhập lý do hủy đơn', 'warning')
      return
    }

    setCancelling(true)
    try {
      await axiosClient.patch(EP.ORDER_CANCEL(id), { cancel_reason: cancelReason.trim() })
      showToast('Hủy đơn hàng thành công!', 'success')
      setCancelOpen(false)
      fetchOrderDetail()
    } catch (err) {
      showToast(err.message || 'Không thể hủy đơn hàng', 'error')
    } finally {
      setCancelling(false)
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
        <Link to="/orders" className="text-primary hover:underline">
          Quay lại danh sách đơn hàng
        </Link>
      </div>
    )
  }

  const cfg = STATUS_CONFIG[order.status] || { label: order.status, variant: 'neutral', icon: 'info' }
  const items = order.items ?? []
  const histories = order.status_histories ?? []
  const isCancellable = order.status === 'pending'
  const isDelivered = order.status === 'delivered'

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-8">
      {/* Breadcrumb & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <nav className="flex items-center gap-2 text-sm text-on-surface-variant">
          <Link to="/orders" className="hover:text-on-surface transition-colors">Đơn hàng của tôi</Link>
          <span>/</span>
          <span className="font-mono text-on-surface">#{order.order_code || order.id}</span>
        </nav>

        {isCancellable && (
          <Button variant="danger" size="sm" onClick={() => setCancelOpen(true)}>
            Hủy đơn hàng
          </Button>
        )}
      </div>

      {/* Main Order Card */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/10">
          <div>
            <h1 className="text-xl font-bold text-on-surface flex items-center gap-2">
              Đơn hàng <span className="font-mono text-primary">#{order.order_code || order.id}</span>
            </h1>
            <p className="text-xs text-on-surface-variant mt-1">
              Thời gian đặt: {formatDateTime(order.created_at)}
            </p>
          </div>
          <Badge variant={cfg.variant} className="text-sm px-3 py-1">
            <span className="material-symbols-outlined text-sm mr-1">{cfg.icon}</span>
            {cfg.label}
          </Badge>
        </div>

        {/* Timeline */}
        {histories.length > 0 && (
          <div className="space-y-3">
            <h2 className="text-sm font-semibold text-on-surface-variant uppercase tracking-wider">
              Lịch sử trạng thái đơn hàng
            </h2>
            <div className="flex flex-col sm:flex-row gap-4 border-l-2 sm:border-l-0 sm:border-t-2 border-primary/30 pl-4 sm:pl-0 sm:pt-4">
              {histories.map((hist, idx) => (
                <div key={idx} className="relative sm:flex-1 space-y-1">
                  <div className="w-3 h-3 rounded-full bg-primary ring-4 ring-primary/20 absolute -left-[23px] sm:left-0 sm:-top-[23px] top-1" />
                  <p className="text-xs font-bold text-on-surface">
                    {STATUS_CONFIG[hist.status]?.label || hist.status}
                  </p>
                  <p className="text-[11px] text-outline">{formatDateTime(hist.created_at)}</p>
                  {hist.note && <p className="text-xs text-on-surface-variant">{hist.note}</p>}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Delivery & Payment Info */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-outline-variant/10">
          <div className="bg-surface-container-low rounded-xl p-4 space-y-2">
            <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
              Địa chỉ nhận hàng
            </h3>
            <p className="text-sm font-semibold text-on-surface">{order.recipient_name || order.user?.name}</p>
            <p className="text-xs text-on-surface-variant">{order.recipient_phone || order.phone}</p>
            <p className="text-xs text-on-surface-variant leading-relaxed">{order.shipping_address}</p>
            {order.note && (
              <p className="text-xs text-outline italic pt-1">Ghi chú: {order.note}</p>
            )}
          </div>

          <div className="bg-surface-container-low rounded-xl p-4 space-y-2">
            <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
              Thông tin thanh toán
            </h3>
            <div className="flex justify-between text-xs">
              <span className="text-outline">Phương thức:</span>
              <span className="font-semibold text-on-surface uppercase">{order.payment_method || 'COD'}</span>
            </div>
            <div className="flex justify-between text-xs">
              <span className="text-outline">Trạng thái thanh toán:</span>
              <span className="font-semibold text-secondary">
                {order.payment_status === 'settled' || order.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán'}
              </span>
            </div>
            {order.coupon_code && (
              <div className="flex justify-between text-xs">
                <span className="text-outline">Mã ưu đãi áp dụng:</span>
                <span className="font-mono text-secondary">{order.coupon_code}</span>
              </div>
            )}
          </div>
        </div>

        {/* Items List */}
        <div className="space-y-3 pt-4 border-t border-outline-variant/10">
          <h2 className="text-sm font-semibold text-on-surface-variant uppercase tracking-wider">
            Sản phẩm trong đơn ({items.length})
          </h2>
          <div className="divide-y divide-outline-variant/10">
            {items.map((item) => {
              const variant = item.variant || item.product_variant || {}
              const product = variant.product || item.product || {}
              const price = item.price ?? variant.price ?? 0

              return (
                <div key={item.id} className="py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <div className="w-16 h-16 bg-surface-container-high rounded-xl overflow-hidden shrink-0 flex items-center justify-center p-1">
                      {variant.image_url || product.thumbnail ? (
                        <img
                          src={variant.image_url || product.thumbnail}
                          alt={item.product_name}
                          className="w-full h-full object-contain"
                        />
                      ) : (
                        <span className="material-symbols-outlined text-3xl text-outline">devices</span>
                      )}
                    </div>
                    <div className="min-w-0">
                      <Link
                        to={`/products/${product.id}`}
                        className="text-sm font-semibold text-on-surface hover:text-primary transition-colors line-clamp-1"
                      >
                        {item.product_name || product.name || 'Sản phẩm'}
                      </Link>
                      <p className="text-xs text-on-surface-variant">
                        Phiên bản: {variant.name || variant.option_signature || 'Mặc định'}
                      </p>
                      <p className="text-xs text-outline">
                        {formatCurrencyVND(price)} x {item.quantity}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-4 self-end sm:self-center shrink-0">
                    <span className="text-sm font-bold text-on-surface">
                      {formatCurrencyVND(price * item.quantity)}
                    </span>
                    {isDelivered && (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setReviewItem({ product, orderItemId: item.id })}
                      >
                        <span className="material-symbols-outlined text-sm">rate_review</span>
                        Đánh giá
                      </Button>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        </div>

        {/* Pricing Summary */}
        <div className="pt-4 border-t border-outline-variant/10 flex justify-end">
          <div className="w-full sm:w-72 space-y-2 text-sm">
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
            <div className="flex justify-between text-on-surface-variant">
              <span>Phí vận chuyển:</span>
              <span className="text-secondary">Miễn phí</span>
            </div>
            <div className="flex justify-between text-base font-extrabold text-on-surface pt-2 border-t border-outline-variant/10">
              <span>Tổng thanh toán:</span>
              <span className="text-xl text-primary">{formatCurrencyVND(order.total_amount ?? order.total)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Cancel Order Modal */}
      <Modal open={cancelOpen} onClose={() => setCancelOpen(false)} title="Hủy đơn hàng" size="sm">
        <div className="space-y-4">
          <p className="text-sm text-on-surface-variant leading-relaxed">
            Bạn có chắc chắn muốn hủy đơn hàng này không? Số lượng sản phẩm sẽ được tự động giải phóng về kho hàng.
          </p>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">
              Lý do hủy đơn *
            </label>
            <textarea
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
              rows={3}
              placeholder="Ví dụ: Đổi ý, muốn thay đổi địa chỉ nhận hàng..."
              className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary resize-none"
              required
            />
          </div>
          <div className="flex justify-end gap-3 pt-2">
            <Button variant="ghost" onClick={() => setCancelOpen(false)} disabled={cancelling}>
              Đóng
            </Button>
            <Button variant="danger" onClick={handleCancelOrder} loading={cancelling}>
              Xác nhận hủy
            </Button>
          </div>
        </div>
      </Modal>

      {/* Review Modal */}
      {reviewItem && (
        <ReviewModal
          open={!!reviewItem}
          onClose={() => setReviewItem(null)}
          product={reviewItem.product}
          orderItemId={reviewItem.orderItemId}
          onSuccess={fetchOrderDetail}
        />
      )}
    </div>
  )
}
