// src/features/checkout/BankTransferPage.jsx
// Trang thanh toán chuyển khoản ngân hàng qua mã VietQR Napas 24/7
// Hỗ trợ tự động điền ngân hàng, STK, tên chủ tài khoản, số tiền và nội dung chuyển khoản.
// Tích hợp nút sao chép, trạng thái chờ xác nhận, và bộ mô phỏng kiểm thử giao dịch cho ĐATN.

import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'
import { useToast } from '../../contexts/ToastContext'

export default function BankTransferPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [order, setOrder] = useState(null)
  const [bankInfo, setBankInfo] = useState({ bankId: '', bankName: '', accountNumber: '', accountName: '', qrUrl: '' })
  const [error, setError] = useState(null)
  const [copiedField, setCopiedField] = useState(null)
  const [isWaitingConfirmation, setIsWaitingConfirmation] = useState(false)
  const [isPaid, setIsPaid] = useState(false)
  const [submittingConfirm, setSubmittingConfirm] = useState(false)

  const pollingRef = useRef(null)

  // ── 1. Khởi tạo phiên thanh toán VietQR & lấy cấu hình ─────────────────────
  useEffect(() => {
    if (!orderId) {
      setError('Không tìm thấy mã đơn hàng cần thanh toán.')
      setLoading(false)
      return
    }

    setLoading(true)

    // Lấy thông tin thanh toán VietQR từ backend
    axiosClient
      .post(EP.PAYMENT_BANK_CREATE, { order_id: Number(orderId) })
      .then(({ data }) => {
        const payload = data.data ?? data
        if (payload.order) {
          setOrder(payload.order)
        }
        if (payload.is_paid || payload.payment_status === 'paid') {
          setIsPaid(true)
        }
        if (payload.is_waiting_confirmation) {
          setIsWaitingConfirmation(true)
        }
        if (payload.bank_id) {
          setBankInfo({
            bankId: payload.bank_id,
            bankName: payload.bank_name || '',
            accountNumber: payload.account_number || '',
            accountName: payload.account_name || '',
            qrUrl: payload.qr_url || '',
          })
        }
      })
      .catch((err) => {
        setError(err.response?.data?.message || err.message || 'Could not create bank transfer payment.')
      })
      .finally(() => {
        setLoading(false)
      })
  }, [orderId])

  // ── 2. Polling kiểm tra trạng thái thanh toán tự động ────────────────────────
  const checkOrderStatus = useCallback(
    async (silent = true) => {
      if (!orderId || isPaid) return
      try {
        const { data } = await axiosClient.get(EP.ORDER_DETAIL(orderId))
        const orderData = data.data ?? data
        setOrder(orderData)

        const paid =
          orderData.payment_status === 'paid'

        if (paid) {
          setIsPaid(true)
          if (pollingRef.current) clearInterval(pollingRef.current)
          showToast('Thanh toán đơn hàng thành công!', 'success')
          setTimeout(() => {
            navigate(`/orders/${orderId}?payment_success=1`)
          }, 1800)
        }
      } catch {
        if (!silent) showToast('Không thể kiểm tra trạng thái đơn hàng.', 'error')
      }
    },
    [orderId, isPaid, navigate, showToast],
  )

  useEffect(() => {
    if (!orderId || isPaid || error || loading) return

    pollingRef.current = setInterval(() => {
      checkOrderStatus(true)
    }, 3500)

    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current)
    }
  }, [orderId, isPaid, error, loading, checkOrderStatus])

  // ── 3. Xử lý sao chép ──────────────────────────────────────────────────────
  const handleCopy = (text, field) => {
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(String(text).trim())
    }
    setCopiedField(field)
    showToast(`Đã sao chép: ${text}`, 'success')
    setTimeout(() => setCopiedField(null), 2000)
  }

  // ── 4. Xử lý khi khách bấm "Tôi đã thanh toán" ─────────────────────────────
  // Không tự ý đổi trạng thái sang paid, mà chuyển sang chờ đối soát
  const handleCustomerReportedPaid = async () => {
    if (!orderId) return
    setSubmittingConfirm(true)
    try {
      await axiosClient.post(EP.PAYMENT_BANK_CONFIRM_PAID, { order_id: Number(orderId) })
      setIsWaitingConfirmation(true)
      showToast('Đã ghi nhận thông báo chuyển khoản! Hệ thống đang chờ đối soát biến động số dư.', 'info')
    } catch (err) {
      showToast(err.response?.data?.message || err.message || 'Lỗi khi gửi thông báo thanh toán', 'error')
    } finally {
      setSubmittingConfirm(false)
    }
  }

  // ── 5. Bộ mô phỏng kiểm thử giao dịch (Dành cho ĐATN / Demo) ───────────────
  const amount = Number(order?.grand_total || order?.total_amount || order?.total || 0)
  const orderCode = order?.order_code || `DH${orderId}`
  const transferContent = orderCode

  const qrImageUrl = bankInfo.qrUrl

  // ── Render Error ────────────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="max-w-md mx-auto px-4 py-20 text-center space-y-4">
        <div className="w-16 h-16 rounded-full bg-error/10 text-error flex items-center justify-center mx-auto">
          <span className="material-symbols-outlined text-4xl">error</span>
        </div>
        <h2 className="text-xl font-bold text-on-surface">Không thể tải thông tin thanh toán</h2>
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

  // ── Render Loading ──────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">Đang tạo mã VietQR thanh toán...</h2>
        <p className="text-xs text-on-surface-variant max-w-xs">
          Hệ thống đang chuẩn bị mã QR thanh toán chuẩn Napas 24/7 cho đơn hàng của bạn.
        </p>
      </div>
    )
  }

  // ── Render Paid Success State ───────────────────────────────────────────────
  if (isPaid) {
    return (
      <div className="max-w-md mx-auto px-4 py-16 text-center space-y-5 animate-fade-in">
        <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
          <span className="material-symbols-outlined text-5xl">check_circle</span>
        </div>
        <h1 className="text-2xl font-bold text-on-surface">Thanh toán thành công!</h1>
        <p className="text-sm text-on-surface-variant">
          Đơn hàng <strong className="text-on-surface font-mono">#{orderCode}</strong> đã được xác nhận thanh toán chuyển khoản ngân hàng thành công.
        </p>
        <div className="inline-flex items-center gap-2 text-xs text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-full font-medium">
          <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
          Hệ thống đang chuyển hướng tới chi tiết đơn hàng...
        </div>
      </div>
    )
  }

  // ── Render VietQR Transfer Screen ──────────────────────────────────────────
  return (
    <div className="max-w-4xl mx-auto px-4 py-8 sm:py-12 space-y-6 animate-fade-in">
      {/* Header Banner */}
      <div className="flex flex-wrap items-center justify-between gap-3 bg-surface-container/70 border border-outline-variant/30 px-5 py-3.5 rounded-2xl">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shadow-xs">
            <span className="material-symbols-outlined text-2xl">account_balance</span>
          </div>
          <div>
            <div className="text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
              <span>Chuyển khoản Ngân hàng (VietQR)</span>
              <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-primary/15 text-primary">
                Napas 24/7
              </span>
            </div>
            <p className="text-xs text-on-surface-variant">
              Tự động nhận diện ngân hàng, số tài khoản, số tiền và nội dung chuyển khoản
            </p>
          </div>
        </div>
        <div className="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-full font-semibold">
          <span className="material-symbols-outlined text-sm">verified</span>
          <span>Bảo vệ giao dịch an toàn</span>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* CỘT TRÁI: KHUNG MÃ VIETQR (5 cols) */}
        <div className="lg:col-span-5 bg-surface-container rounded-3xl border border-outline-variant/30 p-6 flex flex-col items-center justify-center text-center shadow-lg relative overflow-hidden">
          {/* Subtle gradient background glow */}
          <div className="absolute -top-20 -left-20 w-48 h-48 bg-primary/10 rounded-full blur-3xl pointer-events-none" />

          <div className="relative z-10 w-full flex flex-col items-center space-y-4">
            <div className="inline-flex items-center gap-1.5 text-xs font-bold text-primary uppercase tracking-wider bg-primary/10 px-3 py-1 rounded-full">
              <span className="material-symbols-outlined text-sm">qr_code_scanner</span>
              <span>Quét mã bằng mọi App Ngân hàng</span>
            </div>

            {/* QR Image Box */}
            <div className="relative p-2.5 bg-white rounded-2xl shadow-md border border-slate-200/80 max-w-[280px] w-full">
              <img
                src={qrImageUrl}
                alt={`Mã VietQR chuyển khoản ${orderCode}`}
                className="w-full h-auto rounded-xl object-contain block mx-auto"
                loading="eager"
              />
            </div>

            <div className="text-center space-y-1">
              <p className="text-xs font-semibold text-on-surface">
                Mở ứng dụng ngân hàng &gt; Chọn Quét QR
              </p>
              <p className="text-[11px] text-on-surface-variant">
                Hệ thống tự động điền sẵn toàn bộ thông tin thanh toán
              </p>
            </div>
          </div>
        </div>

        {/* CỘT PHẢI: CHI TIẾT TÀI KHOẢN VÀ THAO TÁC (7 cols) */}
        <div className="lg:col-span-7 bg-surface-container rounded-3xl border border-outline-variant/30 p-6 sm:p-7 space-y-5 shadow-lg flex flex-col justify-between">
          <div className="space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-outline-variant/20">
              <h3 className="text-base font-bold text-on-surface flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-xl">payments</span>
                <span>Thông tin chuyển khoản chính xác</span>
              </h3>
              <div className="text-right">
                <span className="text-[11px] text-outline block">Mã đơn hàng</span>
                <span className="font-mono font-bold text-xs text-primary">#{orderCode}</span>
              </div>
            </div>

            {/* Thông tin 1: Ngân hàng */}
            <div className="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/20 space-y-1">
              <span className="text-[11px] uppercase font-bold text-outline tracking-wider">Ngân hàng nhận</span>
              <div className="text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">account_balance</span>
                <span>{bankInfo.bankName}</span>
              </div>
            </div>

            {/* Thông tin 2: Chủ tài khoản */}
            <div className="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/20 space-y-1">
              <span className="text-[11px] uppercase font-bold text-outline tracking-wider">Tên chủ tài khoản</span>
              <div className="text-sm sm:text-base font-extrabold text-on-surface tracking-wide uppercase">
                {bankInfo.accountName}
              </div>
            </div>

            {/* Thông tin 3: Số tài khoản (Có nút sao chép) */}
            <div className="p-3.5 rounded-2xl bg-surface-container-low border border-outline-variant/20 flex items-center justify-between gap-3">
              <div className="space-y-0.5">
                <span className="text-[11px] uppercase font-bold text-outline tracking-wider">Số tài khoản nhận</span>
                <div className="text-base sm:text-lg font-mono font-black text-primary tracking-wider">
                  {bankInfo.accountNumber}
                </div>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handleCopy(bankInfo.accountNumber, 'account_number')}
                className="shrink-0 flex items-center gap-1.5 text-xs font-semibold rounded-xl"
              >
                <span className="material-symbols-outlined text-sm">
                  {copiedField === 'account_number' ? 'done' : 'content_copy'}
                </span>
                <span>{copiedField === 'account_number' ? 'Đã sao chép' : 'Sao chép số TK'}</span>
              </Button>
            </div>

            {/* Thông tin 4: Số tiền thanh toán (Có nút sao chép) */}
            <div className="p-3.5 rounded-2xl bg-primary/5 border border-primary/20 flex items-center justify-between gap-3">
              <div className="space-y-0.5">
                <span className="text-[11px] uppercase font-bold text-primary tracking-wider">Số tiền cần thanh toán</span>
                <div className="text-lg sm:text-xl font-black text-primary">
                  {formatCurrencyVND(amount)}
                </div>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handleCopy(amount, 'amount')}
                className="shrink-0 flex items-center gap-1.5 text-xs font-semibold rounded-xl"
              >
                <span className="material-symbols-outlined text-sm">
                  {copiedField === 'amount' ? 'done' : 'content_copy'}
                </span>
                <span>{copiedField === 'amount' ? 'Đã sao chép' : 'Sao chép tiền'}</span>
              </Button>
            </div>

            {/* Thông tin 5: Nội dung chuyển khoản (Có nút sao chép) */}
            <div className="p-3.5 rounded-2xl bg-amber-500/5 border border-amber-500/25 flex items-center justify-between gap-3">
              <div className="space-y-0.5">
                <div className="flex items-center gap-1">
                  <span className="text-[11px] uppercase font-bold text-amber-700 dark:text-amber-400 tracking-wider">
                    Nội dung chuyển khoản (Bắt buộc)
                  </span>
                  <span className="material-symbols-outlined text-xs text-amber-600">info</span>
                </div>
                <div className="text-base sm:text-lg font-mono font-black text-amber-700 dark:text-amber-300 tracking-wider">
                  {transferContent}
                </div>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => handleCopy(transferContent, 'content')}
                className="shrink-0 flex items-center gap-1.5 text-xs font-semibold rounded-xl border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/10"
              >
                <span className="material-symbols-outlined text-sm">
                  {copiedField === 'content' ? 'done' : 'content_copy'}
                </span>
                <span>{copiedField === 'content' ? 'Đã sao chép' : 'Sao chép nội dung'}</span>
              </Button>
            </div>

            {/* Trạng thái xác nhận (Callout) */}
            {isWaitingConfirmation ? (
              <div className="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 flex items-start gap-3">
                <span className="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping shrink-0 mt-1.5" />
                <div className="text-xs space-y-1">
                  <p className="font-bold text-amber-700 dark:text-amber-300 text-sm">
                    Đang chờ hệ thống đối soát giao dịch...
                  </p>
                  <p className="text-on-surface-variant leading-relaxed">
                    Đã ghi nhận thông báo chuyển khoản của bạn. Hệ thống đang tiến hành đối soát biến động số dư tài khoản ngân hàng. Vui lòng giữ lại biên lai chuyển khoản thành công.
                  </p>
                </div>
              </div>
            ) : (
              <div className="text-xs text-outline leading-relaxed px-1">
                💡 <span className="font-semibold text-on-surface-variant">Lưu ý:</span> Khi quét mã VietQR bằng app ngân hàng, nội dung và số tiền sẽ được tự động điền chính xác. Vui lòng không thay đổi nội dung chuyển khoản để hệ thống khớp đơn tự động.
              </div>
            )}
          </div>

          {/* Action Buttons */}
          <div className="space-y-3 pt-2">
            {!isWaitingConfirmation ? (
              <Button
                type="button"
                variant="primary"
                loading={submittingConfirm}
                onClick={handleCustomerReportedPaid}
                className="w-full py-3 text-sm font-bold rounded-2xl shadow-md flex items-center justify-center gap-2 cursor-pointer"
              >
                <span className="material-symbols-outlined text-lg">check_circle</span>
                <span>Tôi đã chuyển khoản thành công</span>
              </Button>
            ) : (
              <Button
                type="button"
                variant="ghost"
                onClick={() => checkOrderStatus(false)}
                className="w-full py-2.5 text-xs font-semibold rounded-2xl border border-outline-variant/30 flex items-center justify-center gap-2"
              >
                <span className="material-symbols-outlined text-base">sync</span>
                <span>Kiểm tra lại trạng thái ngay</span>
              </Button>
            )}


            <div className="text-center pt-2">
              <Link
                to={`/orders/${orderId}`}
                className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
              >
                Để thanh toán sau &amp; Xem đơn hàng #{orderCode}
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
