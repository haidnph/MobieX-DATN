// src/features/checkout/VNPayPage.jsx
// Isolated Payment Tab Flow: Opens VNPay payment gateway in a separate tab (_blank)
// with enterprise-grade UI/UX, 1-click Sandbox Test Card helper, and real-time polling verification.

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
  const [copiedField, setCopiedField] = useState(null)

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
          navigate(`/orders/${orderId}?payment_success=1`)
        }, 1500)
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

  // ── Copy Helper ─────────────────────────────────────────────────────────────
  const copyToClipboard = (text, fieldName) => {
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(text)
    }
    setCopiedField(fieldName)
    setTimeout(() => setCopiedField(null), 2000)
  }

  const copyAllTestInfo = () => {
    const info = `Ngân hàng: NCB\nSố thẻ: 9704198526191432198\nTên chủ thẻ: NGUYEN VAN A\nNgày phát hành: 07/15\nMã OTP: 123456`
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(info)
    }
    setCopiedField('all')
    setTimeout(() => setCopiedField(null), 2000)
  }

  // ── Render Error State ──────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="max-w-md mx-auto px-4 py-20 text-center space-y-4">
        <div className="w-16 h-16 rounded-full bg-error/10 text-error flex items-center justify-center mx-auto">
          <span className="material-symbols-outlined text-4xl">error</span>
        </div>
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
            className="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity"
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
          Hệ thống đang chuẩn bị cửa sổ giao dịch bảo mật riêng biệt. Vui lòng chờ trong giây lát.
        </p>
      </div>
    )
  }

  // ── Render Paid Success State ───────────────────────────────────────────────
  if (isPaid) {
    return (
      <div className="max-w-md mx-auto px-4 py-16 text-center space-y-5">
        <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
          <span className="material-symbols-outlined text-5xl">check_circle</span>
        </div>
        <h1 className="text-2xl font-bold text-on-surface">Thanh toán thành công!</h1>
        <p className="text-sm text-on-surface-variant">
          Đơn hàng <strong className="text-on-surface font-mono">#{orderId}</strong> đã được xác nhận thanh toán trực tuyến qua cổng VNPay.
        </p>
        <div className="inline-flex items-center gap-2 text-xs text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-full font-medium">
          <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
          Hệ thống đang chuyển hướng tới chi tiết đơn hàng...
        </div>
      </div>
    )
  }

  // ── Render Isolated Waiting Gateway Screen ──────────────────────────────────
  return (
    <div className="max-w-2xl mx-auto px-4 py-10 space-y-6">
      {/* Brand & Security Header */}
      <div className="flex flex-wrap items-center justify-between gap-3 bg-surface-container/60 border border-outline-variant/30 px-5 py-3 rounded-2xl">
        <div className="flex items-center gap-2.5">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white shadow-md">
            <span className="material-symbols-outlined text-xl">payments</span>
          </div>
          <div>
            <div className="text-sm font-bold text-on-surface flex items-center gap-1.5">
              <span>Cổng Thanh Toán Trực Tuyến VNPay</span>
              <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                Sandbox
              </span>
            </div>
            <p className="text-[11px] text-on-surface-variant">Chuẩn bảo mật quốc tế PCI-DSS & 256-bit SSL</p>
          </div>
        </div>
        <div className="flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-full font-medium">
          <span className="material-symbols-outlined text-sm">verified_user</span>
          <span>Bảo mật kép Dual-Confirmation</span>
        </div>
      </div>

      <div className="bg-surface-container rounded-3xl border border-outline-variant/30 p-6 sm:p-8 space-y-6 shadow-xl">
        {/* Status Callout */}
        <div className="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-4 sm:p-5 space-y-2">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-semibold text-sm">
              <span className="material-symbols-outlined text-lg animate-pulse">open_in_new</span>
              Cửa sổ thanh toán VNPay đã được mở trong tab mới
            </div>
            <span className="inline-flex items-center gap-1.5 text-[11px] text-blue-600 dark:text-blue-400 font-medium">
              <span className="w-2 h-2 rounded-full bg-blue-500 animate-ping" />
              Đang chờ giao dịch
            </span>
          </div>
          <p className="text-xs text-on-surface-variant leading-relaxed">
            Vui lòng hoàn tất thanh toán trên tab VNPay vừa mở. Tab hiện tại sẽ <strong>tự động nhận diện và cập nhật trạng thái đơn hàng</strong> ngay khi bạn hoàn tất.
          </p>
        </div>

        {/* Order Brief */}
        <div className="bg-surface-container-low rounded-2xl p-4 text-xs space-y-2.5 border border-outline-variant/20">
          <div className="flex justify-between items-center">
            <span className="text-outline">Mã đơn hàng:</span>
            <span className="font-mono font-bold text-sm text-on-surface">#{orderId}</span>
          </div>
          {order?.grand_total && (
            <div className="flex justify-between items-center pt-1 border-t border-outline-variant/15">
              <span className="text-outline">Số tiền cần thanh toán:</span>
              <span className="font-extrabold text-lg text-primary">
                {formatCurrencyVND(order.grand_total)}
              </span>
            </div>
          )}
          <div className="flex justify-between items-center pt-1 border-t border-outline-variant/15">
            <span className="text-outline">Tự động lắng nghe:</span>
            <span className="inline-flex items-center gap-1 text-[11px] text-on-surface-variant font-medium">
              <Spinner size="xs" />
              Kiểm tra trạng thái mỗi 3.5s
            </span>
          </div>
        </div>

        {/* ── VIP Virtual Bank Card UI (Sandbox Test Credentials) ──────────────── */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1.5 text-xs font-bold text-on-surface">
              <span className="material-symbols-outlined text-base text-primary">credit_card</span>
              <span>Thông tin thẻ test VNPay Sandbox (NCB)</span>
            </div>
            <button
              type="button"
              onClick={copyAllTestInfo}
              className="text-xs text-primary hover:underline font-medium flex items-center gap-1"
            >
              <span className="material-symbols-outlined text-sm">content_copy</span>
              {copiedField === 'all' ? 'Đã sao chép tất cả!' : 'Sao chép tất cả'}
            </button>
          </div>

          {/* Virtual ATM Card */}
          <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-900 p-5 text-white shadow-xl border border-indigo-500/20">
            {/* Holographic accent glow */}
            <div className="absolute -top-12 -right-12 w-40 h-40 bg-blue-500/20 rounded-full blur-2xl pointer-events-none" />
            <div className="absolute -bottom-8 -left-8 w-32 h-32 bg-indigo-500/20 rounded-full blur-2xl pointer-events-none" />

            <div className="relative z-10 space-y-4">
              {/* Card Header */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="font-black tracking-wider text-base text-amber-300">NCB</span>
                  <span className="text-[10px] uppercase tracking-widest text-slate-300 border border-slate-500/50 px-1.5 py-0.5 rounded">
                    Thẻ nội địa
                  </span>
                </div>
                <div className="flex items-center gap-1 text-[11px] text-slate-300">
                  <span className="material-symbols-outlined text-sm">contactless</span>
                  <span>Sandbox Test</span>
                </div>
              </div>

              {/* EMV Chip Representation */}
              <div className="w-10 h-7 rounded-md bg-gradient-to-tr from-amber-300 via-yellow-200 to-amber-400 border border-amber-500/40 shadow-inner flex items-center justify-center">
                <div className="w-7 h-5 border border-amber-700/30 rounded grid grid-cols-2" />
              </div>

              {/* Card Number */}
              <div className="space-y-1">
                <div className="text-[10px] uppercase text-slate-400 font-semibold tracking-wider">Số thẻ test</div>
                <div className="flex items-center justify-between gap-2 bg-black/30 rounded-xl px-3 py-1.5 border border-white/10">
                  <span className="font-mono text-base sm:text-lg font-bold tracking-widest text-white">
                    9704 1985 2619 1432 198
                  </span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard('9704198526191432198', 'card')}
                    className="p-1 rounded-lg hover:bg-white/20 text-slate-300 hover:text-white transition-colors"
                    title="Sao chép số thẻ"
                  >
                    <span className="material-symbols-outlined text-base">
                      {copiedField === 'card' ? 'done' : 'content_copy'}
                    </span>
                  </button>
                </div>
              </div>

              {/* Card Holder & Expiry & OTP Grid */}
              <div className="grid grid-cols-3 gap-2 pt-1 text-xs">
                {/* Holder */}
                <div className="space-y-0.5">
                  <div className="text-[10px] uppercase text-slate-400">Chủ thẻ</div>
                  <button
                    type="button"
                    onClick={() => copyToClipboard('NGUYEN VAN A', 'holder')}
                    className="w-full text-left font-mono font-semibold text-[11px] sm:text-xs text-white hover:text-amber-300 transition-colors flex items-center justify-between group"
                  >
                    <span>NGUYEN VAN A</span>
                    <span className="material-symbols-outlined text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                      {copiedField === 'holder' ? 'done' : 'content_copy'}
                    </span>
                  </button>
                </div>

                {/* Expiry */}
                <div className="space-y-0.5">
                  <div className="text-[10px] uppercase text-slate-400">Ngày phát hành</div>
                  <button
                    type="button"
                    onClick={() => copyToClipboard('07/15', 'date')}
                    className="w-full text-left font-mono font-semibold text-[11px] sm:text-xs text-white hover:text-amber-300 transition-colors flex items-center justify-between group"
                  >
                    <span>07/15</span>
                    <span className="material-symbols-outlined text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                      {copiedField === 'date' ? 'done' : 'content_copy'}
                    </span>
                  </button>
                </div>

                {/* OTP */}
                <div className="space-y-0.5">
                  <div className="text-[10px] uppercase text-slate-400">Mã OTP test</div>
                  <button
                    type="button"
                    onClick={() => copyToClipboard('123456', 'otp')}
                    className="w-full text-left font-mono font-bold text-[11px] sm:text-xs text-amber-300 hover:underline flex items-center justify-between group"
                  >
                    <span>123456</span>
                    <span className="material-symbols-outlined text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                      {copiedField === 'otp' ? 'done' : 'content_copy'}
                    </span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Step Guide */}
          <div className="bg-surface-container-high/40 rounded-xl p-3.5 text-xs space-y-1.5 text-on-surface-variant">
            <div className="font-semibold text-on-surface flex items-center gap-1">
              <span className="material-symbols-outlined text-sm text-primary">help_outline</span>
              <span>4 bước thanh toán nhanh tại cổng VNPay Sandbox:</span>
            </div>
            <ol className="list-decimal list-inside space-y-1 pl-1 text-[11px] leading-relaxed">
              <li>Trên tab VNPay, chọn phương thức <strong>&quot;Thẻ nội địa và tài khoản ngân hàng&quot;</strong>.</li>
              <li>Chọn biểu tượng ngân hàng <strong>NCB</strong>.</li>
              <li>Dán số thẻ <code className="font-mono bg-surface-container px-1 rounded text-primary">9704198526191432198</code>, tên <code className="font-mono bg-surface-container px-1 rounded">NGUYEN VAN A</code>, ngày <code className="font-mono bg-surface-container px-1 rounded">07/15</code>.</li>
              <li>Nhấn Tiếp tục, sau đó nhập mã OTP <code className="font-mono bg-surface-container px-1 rounded text-amber-600 font-bold">123456</code> để hoàn tất.</li>
            </ol>
          </div>
        </div>

        {/* Actions */}
        <div className="space-y-2.5 pt-2">
          <Button
            type="button"
            variant="primary"
            onClick={() => openPaymentTab(paymentUrl)}
            className="w-full flex items-center justify-center gap-2 py-3 text-sm font-bold shadow-lg shadow-primary/20"
          >
            <span className="material-symbols-outlined text-lg">launch</span>
            Mở lại cổng thanh toán VNPay (Tab mới)
          </Button>

          <Button
            type="button"
            variant="ghost"
            loading={verifying}
            onClick={() => checkOrderStatus(true)}
            className="w-full flex items-center justify-center gap-2 py-2.5 border border-outline-variant/30 hover:border-primary text-xs font-semibold"
          >
            <span className="material-symbols-outlined text-base">sync</span>
            Tôi đã hoàn tất thanh toán (Kiểm tra ngay)
          </Button>

          <div className="pt-2 text-center">
            <Link
              to={`/orders/${orderId}`}
              className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
            >
              Để thanh toán sau & xem chi tiết đơn hàng #{orderId}
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
