// src/features/orders/OrderDetailPage.jsx
// Order details view with live timeline, cancellation modal, and verified review triggers.

import { useCallback, useEffect, useState } from 'react'
import { useParams, Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { getGatewayConfig, isOnlineGateway, subscribePaymentResult } from '../../utils/paymentTabSync'
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
  received: { label: 'Đã nhận hàng', variant: 'info', icon: 'inventory' },
  delivered: { label: 'Giao hàng thành công', variant: 'success', icon: 'verified' },
  cancelled: { label: 'Đã hủy', variant: 'error', icon: 'cancel' },
}

export default function OrderDetailPage() {
  const { id } = useParams()
  const [searchParams] = useSearchParams()
  const { showToast } = useToast()
  const { addToCart } = useCart()
  const navigate = useNavigate()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [verifyingPayment, setVerifyingPayment] = useState(false)

  // Reorder state
  const [reordering, setReordering] = useState(false)

  // Cancel order modal
  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelling, setCancelling] = useState(false)
  const [confirmingReceived, setConfirmingReceived] = useState(false)

  // Review modal
  const [reviewItem, setReviewItem] = useState(null)

  const handleReorder = async () => {
    if (!order?.items || order.items.length === 0) return

    setReordering(true)
    let addedCount = 0

    for (const item of order.items) {
      const variantId = item.product_variant_id || item.variant?.id
      if (variantId) {
        try {
          await addToCart(variantId, item.quantity || 1)
          addedCount++
        } catch {
          // Skip unavailable items
        }
      }
    }

    setReordering(false)

    if (addedCount > 0) {
      showToast(`Đã thêm ${addedCount} sản phẩm vào giỏ hàng!`, 'success')
      navigate('/cart')
    } else {
      showToast('Sản phẩm trong đơn hiện không còn khả dụng.', 'warning')
    }
  }

  const fetchOrderDetail = useCallback((silent = false) => {
    if (!silent) setLoading(true)
    axiosClient
      .get(EP.ORDER_DETAIL(id))
      .then(({ data }) => setOrder(data.data ?? data))
      .catch((err) => {
        if (!silent) showToast(err.message || 'Không tìm thấy đơn hàng', 'error')
      })
      .finally(() => {
        if (!silent) setLoading(false)
      })
  }, [id, showToast])

  useEffect(() => {
    fetchOrderDetail()
  }, [fetchOrderDetail])

  // Instant cross-tab sync if user completes payment in separate tab while viewing OrderDetail
  useEffect(() => {
    if (!id) return undefined
    return subscribePaymentResult(id, (event) => {
      if (event?.status === 'paid') {
        showToast('Thanh toán đơn hàng đã được xác nhận!', 'success')
      }
      fetchOrderDetail(true)
    })
  }, [id, fetchOrderDetail, showToast])

  const handleVerifyPaymentStatus = async () => {
    if (!id || verifyingPayment) return
    setVerifyingPayment(true)
    try {
      const { data } = await axiosClient.post(EP.PAYMENT_VERIFY_STATUS(id), {}, { timeout: 15000 })
      const resData = data.data ?? data
      const vState = resData?.verification_state || ''
      if (resData?.payment_status === 'paid') {
        showToast('Thanh toán đã được xác nhận từ cổng thanh toán!', 'success')
      } else if (resData?.payment_status === 'failed' || vState === 'verified_failed') {
        showToast(data.message || 'Giao dịch thanh toán không thành công.', 'error')
      } else if (vState === 'flagged') {
        showToast(data.message || 'Giao dịch đang bị cổng thanh toán rà soát bảo mật.', 'warning')
      } else if (vState === 'gateway_unavailable') {
        showToast(data.message || 'Tạm thời chưa thể đối soát với cổng thanh toán. Hệ thống đang chờ IPN.', 'warning')
      } else {
        showToast(data.message || 'Đơn hàng vẫn đang chờ xác nhận thanh toán.', 'info')
      }
      fetchOrderDetail(true)
    } catch (err) {
      showToast(err.message || 'Không thể kiểm tra trạng thái thanh toán.', 'error')
    } finally {
      setVerifyingPayment(false)
    }
  }

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

  const handleConfirmReceived = async () => {
    setConfirmingReceived(true)
    try {
      await axiosClient.patch(EP.ORDER_CONFIRM_RECEIVED(id))
      showToast('Đã xác nhận nhận hàng. Cửa hàng sẽ chốt đơn sau đó.', 'success')
      fetchOrderDetail()
    } catch (err) {
      showToast(err.response?.data?.message || 'Không thể xác nhận đã nhận hàng', 'error')
    } finally {
      setConfirmingReceived(false)
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
  const isCancellable = order.status === 'pending' && order.payment_status !== 'paid'
  const isDelivered = order.status === 'delivered'
  const isPaid = order.payment_status === 'settled' || order.payment_status === 'paid'
  const isPaymentFailed = order.payment_status === 'failed'
  const isPaymentRefunded = order.payment_status === 'refunded'
  const methodLower = (order.payment_method || 'cod').toLowerCase()
  const canPayOnlineNow =
    order.status === 'pending' &&
    !isPaid &&
    (isOnlineGateway(methodLower) || methodLower === 'bank_transfer')

  const paymentMethodLabel = {
    vnpay: 'VNPay',
    zalopay: 'ZaloPay',
    momo: 'Ví MoMo',
    bank_transfer: 'Chuyển khoản VietQR',
    cod: 'Thanh toán khi nhận hàng (COD)',
  }[methodLower] || order.payment_method

  const payRouteMap = {
    vnpay: `/payment/vnpay?order_id=${order.id}`,
    zalopay: `/payment/zalopay?order_id=${order.id}`,
    momo: `/payment/momo?order_id=${order.id}`,
    bank_transfer: `/payment/bank-transfer?order_id=${order.id}`,
  }

  const showPaymentSuccessBanner = searchParams.get('payment_success') === '1' && isPaid
  const showCodSuccessBanner = searchParams.get('success') === '1' && methodLower === 'cod'

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-8">
      {showPaymentSuccessBanner && (
        <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 sm:p-5 flex items-center gap-3 text-emerald-700 dark:text-emerald-300">
          <span className="material-symbols-outlined text-2xl shrink-0">check_circle</span>
          <div className="text-sm">
            <p className="font-bold">Thanh toán trực tuyến thành công!</p>
            <p className="text-xs text-on-surface-variant mt-0.5">
              Đơn hàng #{order.order_code || order.id} đã được xác nhận thanh toán qua {paymentMethodLabel}.
            </p>
          </div>
        </div>
      )}

      {showCodSuccessBanner && (
        <div className="bg-primary/10 border border-primary/25 rounded-2xl p-4 sm:p-5 flex items-center gap-3">
          <span className="material-symbols-outlined text-2xl text-primary shrink-0">local_shipping</span>
          <div className="text-sm">
            <p className="font-bold text-on-surface">Đặt hàng thành công!</p>
            <p className="text-xs text-on-surface-variant mt-0.5">
              Đơn hàng #{order.order_code || order.id} đã được tiếp nhận. Bạn sẽ thanh toán khi nhận hàng (COD).
            </p>
          </div>
        </div>
      )}
      {/* Breadcrumb & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <nav className="flex items-center gap-2 text-sm text-on-surface-variant">
          <Link to="/orders" className="hover:text-on-surface transition-colors">Đơn hàng của tôi</Link>
          <span>/</span>
          <span className="font-mono text-on-surface">#{order.order_code || order.id}</span>
        </nav>

        <div className="flex items-center gap-2">
          {['delivered', 'received'].includes(order.status) && (
            <Link to={`/orders/${id}/return`} className="rounded-xl border border-primary px-3 py-2 text-sm font-semibold text-primary hover:bg-primary/10">Yêu cầu trả hàng</Link>
          )}
          {(order.status === 'delivered' || order.status === 'cancelled') && (
            <Button
              variant="primary"
              size="sm"
              onClick={handleReorder}
              loading={reordering}
              className="flex items-center gap-1.5"
            >
              <span className="material-symbols-outlined text-base">replay</span>
              Mua lại
            </Button>
          )}

          {isCancellable && (
            <Button variant="danger" size="sm" onClick={() => setCancelOpen(true)}>
              Hủy đơn hàng
            </Button>
          )}

          {order.status === 'shipping' && (
            <Button variant="primary" size="sm" onClick={handleConfirmReceived} loading={confirmingReceived}>
              <span className="material-symbols-outlined text-base">inventory</span>
              Tôi đã nhận hàng
            </Button>
          )}

          {/* Invoice print link — available once order is confirmed */}
          {order.status !== 'pending' && order.status !== 'cancelled' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(`/orders/${id}/invoice`)}
              className="border border-outline-variant/40 text-on-surface-variant hover:border-primary hover:text-primary"
            >
              <span className="material-symbols-outlined text-base">receipt_long</span>
              In hóa đơn
            </Button>
          )}
        </div>
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
            <p className="text-sm font-semibold text-on-surface">
              {order.shipping_name || order.recipient_name || order.user?.name}
            </p>
            <p className="text-xs text-on-surface-variant font-mono">
              {order.shipping_phone || order.recipient_phone || order.phone}
            </p>
            <p className="text-xs text-on-surface-variant leading-relaxed">
              {[
                order.shipping_address_line || order.shipping_address,
                order.shipping_ward_name,
                order.shipping_province_name,
              ]
                .filter(Boolean)
                .join(', ') || '—'}
            </p>
            {(order.customer_note || order.note) && (
              <p className="text-xs text-outline italic pt-1">
                Ghi chú: {order.customer_note || order.note}
              </p>
            )}
          </div>

          <div className="bg-surface-container-low rounded-xl p-4 space-y-3">
            <h3 className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">
              Thông tin thanh toán
            </h3>
            <div className="flex justify-between text-xs items-center">
              <span className="text-outline">Phương thức:</span>
              <span className="font-semibold text-on-surface">{paymentMethodLabel}</span>
            </div>
            <div className="flex justify-between text-xs items-center">
              <span className="text-outline">Trạng thái thanh toán:</span>
              <span className={`font-semibold px-2 py-0.5 rounded-full text-[11px] ${
                isPaid
                  ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                  : isPaymentFailed
                    ? 'bg-error/15 text-error'
                    : isPaymentRefunded
                      ? 'bg-primary/15 text-primary'
                      : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
              }`}>
                {isPaid
                  ? 'Đã thanh toán'
                  : isPaymentFailed
                    ? 'Thanh toán thất bại'
                    : isPaymentRefunded
                      ? 'Đã hoàn tiền'
                      : methodLower === 'cod'
                        ? 'Thanh toán khi nhận hàng'
                        : 'Chờ thanh toán'}
              </span>
            </div>
            {order.coupon_code && (
              <div className="flex justify-between text-xs">
                <span className="text-outline">Mã ưu đãi áp dụng:</span>
                <span className="font-mono text-secondary">{order.coupon_code}</span>
              </div>
            )}

            {canPayOnlineNow && (
              <div className="pt-2 border-t border-outline-variant/15 flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  onClick={() => navigate(payRouteMap[methodLower])}
                  className="flex-1 text-xs"
                >
                  <span className="material-symbols-outlined text-sm">payments</span>
                  {isPaymentFailed
                    ? `Thử thanh toán lại ${getGatewayConfig(methodLower)?.label || ''}`
                    : `Thanh toán ngay`}
                </Button>
                {isOnlineGateway(methodLower) && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    loading={verifyingPayment}
                    onClick={handleVerifyPaymentStatus}
                    className="border border-outline-variant/30 text-xs"
                  >
                    <span className="material-symbols-outlined text-sm">sync</span>
                    Kiểm tra trạng thái
                  </Button>
                )}
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
              <span>Phí vận chuyển{order.shipping_method_name ? ` (${order.shipping_method_name})` : ''}:</span>
              <span className={Number(order.shipping_fee) > 0 ? 'text-on-surface font-medium' : 'text-secondary'}>
                {Number(order.shipping_fee) > 0 ? formatCurrencyVND(order.shipping_fee) : 'Miễn phí'}
              </span>
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
