// src/features/admin/AdminOrderDetailPage.jsx
// Admin order detail management with strict state machine workflow transition controls.

import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import { getProviderTransactionReference } from './paymentRefundReference'
import { useAuth } from '../../contexts/AuthContext'
import Badge from '../../components/common/Badge'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ xác nhận', variant: 'pending' },
  confirmed: { label: 'Đã xác nhận', variant: 'info' },
  processing: { label: 'Đang chuẩn bị hàng', variant: 'info' },
  shipping: { label: 'Đang giao hàng (Xuất kho)', variant: 'warning' },
  received: { label: 'Khách đã nhận hàng', variant: 'info' },
  delivered: { label: 'Giao hàng thành công', variant: 'success' },
  cancelled: { label: 'Đã hủy (Giải phóng kho)', variant: 'error' },
}

// Strict one-way state machine
const VALID_TRANSITIONS = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'cancelled'],
  processing: ['shipping', 'cancelled'],
  shipping: [],
  received: ['delivered'],
  delivered: [],
  cancelled: [],
}

export default function AdminOrderDetailPage() {
  const { id } = useParams()
  const { showToast } = useToast()
  const { hasPermission, isAdmin } = useAuth()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)

  // Status transition state
  const [nextStatus, setNextStatus] = useState('')
  const [transitionNote, setTransitionNote] = useState('')
  const [updating, setUpdating] = useState(false)
  const [simulatingPayment, setSimulatingPayment] = useState(false)
  const [refundDrafts, setRefundDrafts] = useState({})
  const [savingRefundPaymentId, setSavingRefundPaymentId] = useState(null)
  const [gatewayRefundPaymentId, setGatewayRefundPaymentId] = useState(null)
  const [refreshingGatewayRefundPaymentId, setRefreshingGatewayRefundPaymentId] = useState(null)

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

  const handleSimulatePayment = async (action) => {
    const endpoint = order.payment_method === 'momo'
      ? EP.ADMIN_SIMULATE_MOMO_PAYMENT
      : EP.ADMIN_SIMULATE_BANK_PAYMENT
    setSimulatingPayment(true)
    try {
      await axiosClient.post(endpoint, { order_id: order.id, action })
      showToast(action === 'success' ? 'Đã mô phỏng thanh toán thành công.' : 'Đã mô phỏng giao dịch thất bại.', action === 'success' ? 'success' : 'info')
      fetchOrder()
    } catch (err) {
      showToast(err.message || 'Không thể mô phỏng thanh toán.', 'error')
    } finally {
      setSimulatingPayment(false)
    }
  }

  const handleConfirmRefund = async (event, payment) => {
    event.preventDefault()
    const draft = refundDrafts[payment.id] || {}
    setSavingRefundPaymentId(payment.id)
    try {
      await axiosClient.post(EP.ADMIN_CONFIRM_PAYMENT_REFUND(payment.id), {
        refund_transaction_id: draft.transactionId,
        refund_amount: payment.amount,
        refund_note: draft.note,
      })
      showToast('Đã ghi nhận giao dịch hoàn tiền đã thực hiện trên cổng thanh toán.', 'success')
      fetchOrder()
    } catch (err) {
      showToast(err.message || 'Không thể ghi nhận đối soát hoàn tiền.', 'error')
    } finally {
      setSavingRefundPaymentId(null)
    }
  }

  const handleGatewayRefund = async (payment) => {
    const note = refundDrafts[payment.id]?.note?.trim()
    if (!note || note.length < 5) {
      showToast('Nhập lý do hoàn tiền (ít nhất 5 ký tự) trước khi gửi cổng thanh toán.', 'error')
      return
    }
    setGatewayRefundPaymentId(payment.id)
    try {
      const { data } = await axiosClient.post(EP.ADMIN_REQUEST_PAYMENT_REFUND(payment.id), { note })
      showToast(data.message || 'Đã gửi yêu cầu hoàn tiền.', data.data?.status === 'succeeded' ? 'success' : 'info')
      fetchOrder()
    } catch (err) {
      showToast(err.message || 'Không thể gửi yêu cầu hoàn tiền.', 'error')
      fetchOrder()
    } finally {
      setGatewayRefundPaymentId(null)
    }
  }

  const handleRefreshGatewayRefund = async (payment) => {
    setRefreshingGatewayRefundPaymentId(payment.id)
    try {
      const { data } = await axiosClient.post(EP.ADMIN_REFRESH_PAYMENT_REFUND(payment.id))
      showToast(data.message || 'Đã cập nhật trạng thái hoàn tiền.', data.data?.status === 'succeeded' ? 'success' : 'info')
      fetchOrder()
    } catch (err) {
      showToast(err.message || 'Không thể truy vấn trạng thái hoàn tiền.', 'error')
    } finally {
      setRefreshingGatewayRefundPaymentId(null)
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
              <p className="text-sm font-semibold text-on-surface">{order.shipping_name || order.user?.name}</p>
              <p className="text-xs text-on-surface-variant">SĐT: {order.shipping_phone || order.phone}</p>
              <p className="text-xs text-on-surface-variant">Email: {order.user?.email || '—'}</p>
              <p>
                 Địa chỉ: {[
                     order.shipping_address_line,
                      order.shipping_ward_name,
                      order.shipping_province_name,
                       ].filter(Boolean).join(', ') || '—'}
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
                  {order.payment_status === 'refunded'
                    ? 'Đã hoàn tiền'
                    : order.payment_status === 'settled' || order.payment_status === 'paid'
                      ? 'Đã thu tiền'
                      : 'Chưa thu tiền'}
                </span>
              </div>
              {(order.payments || []).filter((payment) => (
                payment.refund_required
                || payment.status === 'refunded'
                || (payment.status === 'success' && order.payment_status === 'paid')
              )).map((payment) => (
                (() => {
                  const refund = payment.refunds?.[0]
                  const refundInProgress = refund && ['pending', 'processing', 'unknown'].includes(refund.status)
                  return (
                <div key={payment.id} role="alert" className="rounded-xl border border-error/30 bg-error-container/40 p-3 text-xs text-on-error-container">
                  <p className="font-bold">
                    {payment.refund_required
                      ? 'Giao dịch capture trùng — cần hoàn tiền/đối soát'
                      : payment.status === 'refunded'
                        ? 'Đã đối soát hoàn tiền'
                        : 'Giao dịch đã thu tiền — cần hoàn tiền nếu hủy đơn'}
                  </p>
                  <p className="mt-1">{payment.method?.toUpperCase()} · {formatCurrencyVND(payment.amount)}</p>
                  <p className="mt-1 break-all font-mono">
                    Mã giao dịch gốc: {getProviderTransactionReference(payment) || 'Chưa có mã giao dịch từ cổng'}
                  </p>
                  {payment.status === 'refunded' ? (
                    <div className="mt-2 space-y-1">
                      <p>Mã hoàn tiền: <span className="font-mono">{payment.refund_transaction_id}</span></p>
                      <p>Ghi nhận lúc: {formatDateTime(payment.refunded_at)}</p>
                      <p>{payment.refund_note}</p>
                    </div>
                  ) : hasPermission('orders.manage') ? (
                    <form className="mt-3 space-y-2 border-t border-error/20 pt-3" onSubmit={(event) => handleConfirmRefund(event, payment)}>
                      {refund && (
                        <div className="rounded-lg border border-outline-variant bg-surface/70 p-2">
                          <p>Yêu cầu hoàn tự động: <strong>{refund.status}</strong></p>
                          {refundInProgress && (
                            <button
                              type="button"
                              onClick={() => handleRefreshGatewayRefund(payment)}
                              disabled={refreshingGatewayRefundPaymentId === payment.id}
                              className="mt-2 rounded-lg border border-outline-variant px-3 py-2 font-semibold disabled:opacity-60"
                            >
                              {refreshingGatewayRefundPaymentId === payment.id ? 'Đang truy vấn…' : 'Truy vấn trạng thái cổng'}
                            </button>
                          )}
                          {refund.status === 'failed' && <p className="mt-1">Cổng từ chối yêu cầu. Có thể tiếp tục hoàn ngoài cổng rồi ghi nhận bên dưới.</p>}
                        </div>
                      )}
                      {!refund && ['vnpay', 'momo', 'zalopay'].includes(payment.method) && (
                        <button
                          type="button"
                          onClick={() => handleGatewayRefund(payment)}
                          disabled={gatewayRefundPaymentId === payment.id}
                          className="w-full rounded-lg bg-error px-3 py-2 font-semibold text-white disabled:opacity-60"
                        >
                          {gatewayRefundPaymentId === payment.id ? 'Đang gửi yêu cầu…' : `Gửi yêu cầu hoàn ${payment.method.toUpperCase()}`}
                        </button>
                      )}
                      {!refundInProgress && (
                      <>
                      <p className="font-semibold">Chỉ ghi nhận sau khi đã hoàn tiền thật trên cổng thanh toán.</p>
                      <input
                        required
                        maxLength={100}
                        value={refundDrafts[payment.id]?.transactionId || ''}
                        onChange={(event) => setRefundDrafts((current) => ({
                          ...current,
                          [payment.id]: { ...current[payment.id], transactionId: event.target.value },
                        }))}
                        placeholder="Mã giao dịch hoàn tiền từ cổng"
                        className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"
                      />
                      <textarea
                        required
                        minLength={5}
                        maxLength={1000}
                        value={refundDrafts[payment.id]?.note || ''}
                        onChange={(event) => setRefundDrafts((current) => ({
                          ...current,
                          [payment.id]: { ...current[payment.id], note: event.target.value },
                        }))}
                        placeholder="Ghi chú đối soát"
                        rows={2}
                        className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-on-surface"
                      />
                      <button
                        type="submit"
                        disabled={savingRefundPaymentId === payment.id}
                        className="rounded-lg bg-error px-3 py-2 font-semibold text-white disabled:opacity-60"
                      >
                        {savingRefundPaymentId === payment.id ? 'Đang lưu…' : 'Ghi nhận đã hoàn tiền'}
                      </button>
                      </>
                      )}
                    </form>
                  ) : null}
                </div>
                  )
                })()
              ))}
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
          {['bank_transfer', 'momo'].includes(order.payment_method) && order.payment_status !== 'paid' && (isAdmin() || hasPermission('orders.manage')) && (
            <div className="bg-amber-500/5 rounded-2xl border border-amber-500/25 p-5 space-y-3">
              <div>
                <h3 className="text-sm font-bold text-on-surface">Mô phỏng thanh toán (ĐATN)</h3>
                <p className="text-xs text-on-surface-variant mt-1">Chỉ quản trị viên có quyền xác nhận kết quả kiểm thử.</p>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <Button type="button" variant="primary" size="sm" loading={simulatingPayment} disabled={simulatingPayment} onClick={() => handleSimulatePayment('success')}>
                  Thành công
                </Button>
                <Button type="button" variant="outline" size="sm" loading={simulatingPayment} disabled={simulatingPayment} onClick={() => handleSimulatePayment('failed')}>
                  Thất bại
                </Button>
              </div>
            </div>
          )}

          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-5">
            <div>
              <h3 className="text-base font-bold text-on-surface">Cập nhật trạng thái đơn</h3>
              <p className="text-xs text-on-surface-variant mt-1">
                Quy trình State Machine một chiều theo kiến trúc MobieX.
              </p>
            </div>

            {order.status === 'shipping' ? (
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center text-sm text-amber-900">
                Đang chờ khách hàng xác nhận đã nhận hàng. Sau đó quản trị viên mới có thể chốt giao thành công.
              </div>
            ) : allowedNext.length === 0 ? (
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
