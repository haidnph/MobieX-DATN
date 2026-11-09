// src/features/checkout/VNPayPage.jsx
// Isolated Payment Tab Flow: Opens VNPay payment gateway in a separate tab (_blank)
// while keeping the application context intact, with automatic status polling and manual verification.

import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'

export default function VNPayPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')
  const navigate = useNavigate()

  const [paymentUrl, setPaymentUrl] = useState(null)
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [verifying, setVerifying] = useState(false)
  const [error, setError] = useState(null)
  const [isPaid, setIsPaid] = useState(false)

  const pollingRef = useRef(null)
  const hasAutoOpenedRef = useRef(false)

  // ── Open payment window in a separate tab ───────────────────────────────────
  const openPaymentTab = useCallback((url) => {
    if (!url) return
    window.open(url, '_blank', 'noopener,noreferrer')
  }, [])

  // ── Initiate VNPay Payment Session ──────────────────────────────────────────
  useEffect(() => {
    if (!orderId) {
      setError('Không tìm thấy mã đơn hàng cần thanh toán.')
      setLoading(false)
      return
    }

    setLoading(true)
    axiosClient
      .post(EP.PAYMENT_VNPAY_CREATE, { order_id: Number(orderId) })
      .then(({ data }) => {
        const payload = data.data ?? data
        const url = payload.payment_url
        if (url) {
          setPaymentUrl(url)
          setOrder(payload.order ?? null)

          // Auto open once in a separate tab
          if (!hasAutoOpenedRef.current) {
            hasAutoOpenedRef.current = true
            openPaymentTab(url)
          }
        } else {
          setError('Không nhận được liên kết thanh toán từ cổng VNPay.')
        }
      })
      .catch((err) => {
        setError(err.message || 'Lỗi khi kết nối cổng thanh toán VNPay.')
      })
      .finally(() => {
        setLoading(false)
      })
  }, [orderId, openPaymentTab])

  // ── Check Order Status ──────────────────────────────────────────────────────
  const checkOrderStatus = useCallback(async (manual = false) => {
    if (!orderId) return
    if (manual) setVerifying(true)

    try {
      const { data } = await axiosClient.get(EP.ORDER_DETAIL(orderId))
      const orderData = data.data ?? data
      setOrder(orderData)

      const paid =
        orderData.payment_status === 'paid' ||
        ['confirmed', 'processing', 'shipping', 'delivered'].includes(orderData.status)

      if (paid) {
        setIsPaid(true)
        if (pollingRef.current) clearInterval(pollingRef.current)
        setTimeout(() => {
          navigate(`/orders/${orderId}?success=1`)
        }, 1500)
      } else if (manual) {
        // Not paid yet
      }
    } catch {
      // Ignore polling errors
    } finally {
      if (manual) setVerifying(false)
    }
  }, [orderId, navigate])

  // ── Polling every 3.5s while waiting for payment ────────────────────────────
  useEffect(() => {
    if (!orderId || isPaid || error || loading) return

    pollingRef.current = setInterval(() => {
      checkOrderStatus(false)
    }, 3500)

    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current)
    }
  }, [orderId, isPaid, error, loading, checkOrderStatus])

  // ── Render Error State ──────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="max-w-md mx-auto px-4 py-20 text-center space-y-4">
        <span className="material-symbols-outlined text-6xl text-error">error</span>
        <h2 className="text-xl font-bold text-on-surface">Không thể khởi tạo thanh toán</h2>
        <p className="text-sm text-on-surface-variant">{error}</p>
        <div className="flex justify-center gap-4 pt-2">
          {orderId && (
            <Link
              to={`/orders/${orderId}`}
              className="px-4 py-2 bg-surface-container text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container-high transition-colors"
            >
              Xem đơn hàng
            </Link>
          )}
          <Link
            to="/orders"
            className="px-4 py-2 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors"
          >
            Danh sách đơn
          </Link>
        </div>
      </div>
    )
  }

  // ── Render Initial Loading ──────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">Đang kết nối cổng thanh toán VNPay...</h2>
        <p className="text-xs text-on-surface-variant max-w-xs">
          Hệ thống đang chuẩn bị cửa sổ giao dịch riêng biệt để đảm bảo an toàn.
        </p>
      </div>
    )
  }

  // ── Render Paid Success State ───────────────────────────────────────────────
  if (isPaid) {
    return (
      <div className="max-w-md mx-auto px-4 py-16 text-center space-y-5">
        <div className="w-16 h-16 bg-secondary/15 rounded-full flex items-center justify-center mx-auto text-secondary">
          <span className="material-symbols-outlined text-4xl">check_circle</span>
        </div>
        <h1 className="text-2xl font-bold text-on-surface">Thanh toán thành công!</h1>
        <p className="text-sm text-on-surface-variant">
          Đơn hàng <strong className="text-on-surface font-mono">#{orderId}</strong> đã được ghi nhận thanh toán qua VNPay.
        </p>
        <p className="text-xs text-outline">Đang chuyển hướng tới chi tiết đơn hàng...</p>
      </div>
    )
  }

  // ── Render Isolated Waiting Gateway Screen ──────────────────────────────────
  return (
    <div className="max-w-xl mx-auto px-4 py-12 space-y-6">
      <div className="bg-surface-container rounded-3xl border border-outline-variant/30 p-6 sm:p-8 space-y-6 shadow-xl text-center">
        {/* Brand Banner */}
        <div className="flex items-center justify-center gap-2 text-primary font-bold text-xl">
          <span className="material-symbols-outlined text-3xl">credit_card</span>
          <span>Cổng Thanh Toán VNPay</span>
        </div>

        {/* Status Callout */}
        <div className="bg-primary/5 border border-primary/20 rounded-2xl p-5 space-y-2">
          <div className="flex items-center justify-center gap-2 text-primary font-semibold text-sm">
            <span className="material-symbols-outlined text-lg animate-pulse">open_in_new</span>
            Cửa sổ thanh toán đã được mở trong tab mới
          </div>
          <p className="text-xs text-on-surface-variant leading-relaxed">
            Vui lòng hoàn tất việc quét mã VNPAY-QR hoặc đăng nhập ứng dụng ngân hàng trên tab vừa mở. Tab hiện tại sẽ tự động cập nhật khi giao dịch thành công.
          </p>
        </div>

        {/* Order Brief */}
        <div className="bg-surface-container-low rounded-2xl p-4 text-xs space-y-2 text-left border border-outline-variant/20">
          <div className="flex justify-between items-center">
            <span className="text-outline">Mã đơn hàng:</span>
            <span className="font-mono font-bold text-on-surface">#{orderId}</span>
          </div>
          {order?.grand_total && (
            <div className="flex justify-between items-center">
              <span className="text-outline">Số tiền cần thanh toán:</span>
              <span className="font-bold text-base text-primary">
                {formatCurrencyVND(order.grand_total)}
              </span>
            </div>
          )}
          <div className="flex justify-between items-center">
            <span className="text-outline">Trạng thái thanh toán:</span>
            <span className="inline-flex items-center gap-1.5 text-warning font-semibold">
              <span className="w-2 h-2 rounded-full bg-warning animate-ping" />
              Đang chờ thanh toán
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="space-y-3 pt-2">
          <Button
            type="button"
            variant="primary"
            onClick={() => openPaymentTab(paymentUrl)}
            className="w-full flex items-center justify-center gap-2 py-3"
          >
            <span className="material-symbols-outlined text-lg">launch</span>
            Mở lại cổng thanh toán VNPay (Tab mới)
          </Button>

          <Button
            type="button"
            variant="ghost"
            loading={verifying}
            onClick={() => checkOrderStatus(true)}
            className="w-full flex items-center justify-center gap-2 py-2.5 border border-outline-variant/30 hover:border-primary"
          >
            <span className="material-symbols-outlined text-base">sync</span>
            Tôi đã thanh toán xong (Kiểm tra ngay)
          </Button>

          <div className="pt-2">
            <Link
              to={`/orders/${orderId}`}
              className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
            >
              Để thanh toán sau & xem chi tiết đơn hàng
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
