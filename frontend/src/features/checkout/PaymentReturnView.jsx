// src/features/checkout/PaymentReturnView.jsx
// Shared professional payment return verification, cross-tab synchronization,
// and consistent 3-state receipt UI (Paid / Pending Confirmation / Failed or Cancelled).

import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'
import storage from '../../utils/storage'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import {
  broadcastPaymentResult,
  clearActivePaymentSession,
  getGatewayConfig,
} from '../../utils/paymentTabSync'

export default function PaymentReturnView({
  gateway,
  returnEndpoint,
  parseReturnState,
}) {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const cfg = getGatewayConfig(gateway)

  const [loading, setLoading] = useState(true)
  const [checking, setChecking] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [syncPollCount, setSyncPollCount] = useState(0)

  const verifiedOnceRef = useRef(false)

  const runVerifyWithBackend = useCallback(async (targetOrderId, manual = false) => {
    if (!targetOrderId || !storage.get('token')) return false
    if (manual) setChecking(true)
    try {
      const { data } = await axiosClient.post(
        EP.PAYMENT_VERIFY_STATUS(targetOrderId),
        {},
        { timeout: 15000 },
      )
      const resData = data.data ?? data
      if (resData?.payment_status === 'paid') {
        setResult((prev) => ({
          ...(prev || {}),
          payment_status: 'paid',
          order_status: resData.order_status ?? prev?.order_status,
          verification_status: 'success',
        }))
        clearActivePaymentSession()
        broadcastPaymentResult({
          orderId: targetOrderId,
          orderCode: resData.order_code,
          gateway,
          status: 'paid',
          message: 'Thanh toán đã được hệ thống xác nhận.',
        })
        return true
      }
    } catch {
      // Keep honest pending_confirmation state if gateway query cannot confirm yet
    } finally {
      if (manual) setChecking(false)
    }
    return false
  }, [gateway])

  const fetchReturnVerification = useCallback(async () => {
    const params = Object.fromEntries(searchParams.entries())
    setLoading(true)
    setError('')
    try {
      const { data } = await axiosClient.get(returnEndpoint, { params })
      const payload = data.data ?? data
      setResult(payload)
      return { payload, topStatus: data.status }
    } catch (err) {
      const errData = err.response?.data?.data ?? null
      if (errData) setResult(errData)
      const errMsg = err.response?.data?.message || err.message || 'Lỗi xác thực chữ ký dữ liệu từ cổng thanh toán.'
      setError(errMsg)
      return { payload: errData, topStatus: 'error', errMsg }
    } finally {
      setLoading(false)
    }
  }, [returnEndpoint, searchParams])

  // Initial verification on mount
  useEffect(() => {
    let active = true
    async function verify() {
      const { payload, topStatus, errMsg } = await fetchReturnVerification()
      if (!active) return

      const parsed = parseReturnState(searchParams, payload, errMsg)
      const orderId = parsed.orderId

      if (parsed.gatewaySucceeded && !errMsg) {
        if (parsed.isConfirmed) {
          clearActivePaymentSession()
          if (orderId) {
            broadcastPaymentResult({
              orderId,
              orderCode: payload?.order_code,
              gateway,
              status: 'paid',
              transactionNo: parsed.transactionId,
              message: 'Thanh toán đã được hệ thống xác nhận.',
            })
          }
        } else {
          if (orderId) {
            broadcastPaymentResult({
              orderId,
              orderCode: payload?.order_code,
              gateway,
              status: topStatus === 'pending_confirmation' ? 'pending_confirmation' : 'pending_confirmation',
              transactionNo: parsed.transactionId,
              message: `Cổng ${cfg.label} đã tiếp nhận giao dịch và đang chờ đối soát.`,
            })
          }
          if (orderId && !verifiedOnceRef.current) {
            verifiedOnceRef.current = true
            await runVerifyWithBackend(orderId, false)
          }
        }
      } else if (orderId) {
        broadcastPaymentResult({
          orderId,
          orderCode: payload?.order_code,
          gateway,
          status: parsed.isCancelled ? 'cancelled' : 'failed',
          transactionNo: parsed.transactionId,
          message: parsed.statusMessage,
        })
      }
    }

    verify()
    return () => { active = false }
  }, [fetchReturnVerification, parseReturnState, searchParams, gateway, cfg.label, runVerifyWithBackend])

  const parsedState = parseReturnState(searchParams, result, error)
  const {
    orderId,
    orderCode,
    amount,
    responseCodeLabel,
    isConfirmed,
    gatewaySucceeded,
    isCancelled,
    statusMessage,
    details,
  } = parsedState

  // Automatic delayed-callback follow-up polling when awaiting confirmation (max ~28s)
  useEffect(() => {
    if (loading || !gatewaySucceeded || isConfirmed || error || !orderId || syncPollCount >= 8) {
      return undefined
    }

    const timer = window.setTimeout(async () => {
      setSyncPollCount((c) => c + 1)
      if (storage.get('token')) {
        await runVerifyWithBackend(orderId, false)
      } else {
        await fetchReturnVerification()
      }
    }, 3500)

    return () => window.clearTimeout(timer)
  }, [loading, gatewaySucceeded, isConfirmed, error, orderId, syncPollCount, runVerifyWithBackend, fetchReturnVerification])

  const handleManualSync = async () => {
    if (!orderId) return
    if (storage.get('token')) {
      await runVerifyWithBackend(orderId, true)
    } else {
      setChecking(true)
      await fetchReturnVerification()
      setChecking(false)
    }
  }

  const handleCloseOrReturn = () => {
    window.close()
    // Fallback if browser blocks script close on non-script-opened tabs
    if (orderId) {
      navigate(`/orders/${orderId}`)
    } else {
      navigate('/orders')
    }
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">
          Đang xác thực kết quả giao dịch {cfg.label}...
        </h2>
        <p className="text-xs text-on-surface-variant max-w-sm leading-relaxed">
          Hệ thống đang đối soát chữ ký bảo mật với {cfg.label} để đảm bảo an toàn và chính xác cho đơn hàng của bạn.
        </p>
      </div>
    )
  }

  return (
    <div className="max-w-xl mx-auto px-4 py-10 sm:py-12 space-y-6">
      {/* Brand Header */}
      <div className="flex flex-wrap items-center justify-between gap-3 bg-surface-container/70 border border-outline-variant/25 px-5 py-3.5 rounded-2xl">
        <div className="flex items-center gap-3">
          <div
            className="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-sm shrink-0"
            style={{ background: cfg.badgeGradient }}
          >
            <span className="material-symbols-outlined text-lg">receipt_long</span>
          </div>
          <div>
            <h2 className="text-sm font-bold text-on-surface">Kết Quả Thanh Toán Trực Tuyến</h2>
            <p className="text-[11px] text-on-surface-variant">
              MobieX · {cfg.label}
            </p>
          </div>
        </div>
        {responseCodeLabel && (
          <span className="text-[11px] font-mono px-2.5 py-1 rounded-lg bg-surface-container-high text-on-surface-variant">
            {responseCodeLabel}
          </span>
        )}
      </div>

      {/* STATE 1: CONFIRMED PAID */}
      {isConfirmed && !error ? (
        <div className="bg-surface-container rounded-3xl border border-emerald-500/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          <div
            className="absolute top-0 left-0 right-0 h-1.5"
            style={{ background: cfg.badgeGradient }}
          />

          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
              <span className="material-symbols-outlined text-5xl">check_circle</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Thành Công!</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto leading-relaxed">
                Hệ thống đã xác nhận thanh toán hoàn tất. Đơn hàng của bạn đang được xử lý.
              </p>
            </div>
            {amount > 0 && (
              <div className="pt-2">
                <span className="text-xs text-outline block">Số tiền đã thanh toán</span>
                <span className="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                  {formatCurrencyVND(amount)}
                </span>
              </div>
            )}
          </div>

          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-3 border border-outline-variant/20">
            <div className="flex items-center justify-between pb-2 border-b border-outline-variant/15 text-outline">
              <span className="font-semibold text-on-surface uppercase tracking-wider text-[11px]">
                Biên lai giao dịch
              </span>
              <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">verified</span>
                Đã xác thực chữ ký
              </span>
            </div>

            {orderId && (
              <div className="flex justify-between items-center gap-3">
                <span className="text-outline">Mã đơn hàng:</span>
                <span className="font-mono font-bold text-on-surface">{orderCode || `#${orderId}`}</span>
              </div>
            )}

            {details.map((row) => (
              <div key={row.label} className="flex justify-between items-center gap-3">
                <span className="text-outline">{row.label}:</span>
                <span className="font-mono text-on-surface text-right break-all">{row.value}</span>
              </div>
            ))}

            <div className="flex justify-between items-center pt-2 border-t border-outline-variant/15">
              <span className="text-outline">Trạng thái thanh toán:</span>
              <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2.5 py-0.5 rounded-full">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                Đã thanh toán
              </span>
            </div>
          </div>

          <div className="space-y-2.5 pt-1 print:hidden">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <Link
                  to={`/orders/${orderId}?payment_success=1`}
                  className="flex-1 py-3 bg-primary text-on-primary rounded-xl text-sm font-bold hover:opacity-90 transition-opacity text-center flex items-center justify-center gap-1.5 shadow-md"
                >
                  <span className="material-symbols-outlined text-base">visibility</span>
                  Xem chi tiết đơn hàng
                </Link>
              )}
              <button
                type="button"
                onClick={() => window.print()}
                className="py-3 px-5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-bright transition-colors flex items-center justify-center gap-1.5 border border-outline-variant/30 cursor-pointer"
              >
                <span className="material-symbols-outlined text-base">print</span>
                In biên lai
              </button>
            </div>

            <div className="flex items-center justify-center gap-4 pt-1">
              <button
                type="button"
                onClick={handleCloseOrReturn}
                className="text-xs text-primary hover:underline font-medium cursor-pointer"
              >
                Đóng tab &amp; quay lại MobieX
              </button>
              <span className="text-outline">•</span>
              <Link
                to="/products"
                className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
              >
                Tiếp tục mua sắm →
              </Link>
            </div>
          </div>
        </div>
      ) : gatewaySucceeded && !error ? (
        /* STATE 2: PENDING CONFIRMATION (NEVER FAKE PAID!) */
        <div className="bg-surface-container rounded-3xl border border-amber-500/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 via-orange-400 to-blue-500" />

          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-amber-500/15 rounded-full flex items-center justify-center mx-auto text-amber-600 dark:text-amber-400 ring-8 ring-amber-500/10">
              <span className="material-symbols-outlined text-5xl">schedule</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Đang Chờ Xác Nhận Thanh Toán</h1>
              <p className="text-xs text-on-surface-variant mt-2 max-w-md mx-auto leading-relaxed">
                Cổng {cfg.label} đã tiếp nhận giao dịch của bạn. Hệ thống MobieX đang đối soát dữ liệu chính thức trước khi chuyển trạng thái sang đã thanh toán.
              </p>
            </div>
            {amount > 0 && (
              <div className="pt-1">
                <span className="text-xs text-outline block">Số tiền giao dịch</span>
                <span className="text-2xl font-extrabold text-primary">{formatCurrencyVND(amount)}</span>
              </div>
            )}
          </div>

          <div className="bg-amber-500/10 border border-amber-500/25 rounded-2xl p-4 text-xs text-amber-800 dark:text-amber-200 leading-relaxed flex items-start gap-2.5">
            <span className="material-symbols-outlined text-base shrink-0 mt-0.5">info</span>
            <p>
              <strong>Quý khách không cần đặt lại đơn hàng mới.</strong> Kết quả đối soát sẽ tự động cập nhật trong giây lát hoặc khi ngân hàng phản hồi webhook.
            </p>
          </div>

          <div className="bg-surface-container-low rounded-2xl p-4 sm:p-5 text-xs space-y-2.5 border border-outline-variant/20">
            {orderId && (
              <div className="flex justify-between items-center gap-3">
                <span className="text-outline">Mã đơn hàng:</span>
                <span className="font-mono font-bold text-on-surface">{orderCode || `#${orderId}`}</span>
              </div>
            )}
            {details.map((row) => (
              <div key={row.label} className="flex justify-between items-center gap-3">
                <span className="text-outline">{row.label}:</span>
                <span className="font-mono text-on-surface text-right break-all">{row.value}</span>
              </div>
            ))}
            <div className="flex justify-between items-center pt-2 border-t border-outline-variant/15">
              <span className="text-outline">Trạng thái hiện tại:</span>
              <span className="inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-300 font-bold bg-amber-500/15 px-2.5 py-0.5 rounded-full">
                <Spinner size="xs" />
                Đang đối soát thanh toán
              </span>
            </div>
          </div>

          <div className="space-y-2.5">
            <Button
              type="button"
              variant="primary"
              loading={checking}
              onClick={handleManualSync}
              className="w-full py-3"
            >
              <span className="material-symbols-outlined text-base">sync</span>
              Kiểm tra xác nhận ngay
            </Button>

            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <Link
                  to={`/orders/${orderId}`}
                  className="flex-1 py-2.5 px-4 bg-surface-container-high text-on-surface rounded-xl text-xs font-semibold hover:bg-surface-bright transition-colors text-center"
                >
                  Xem chi tiết đơn hàng
                </Link>
              )}
              <button
                type="button"
                onClick={handleCloseOrReturn}
                className="flex-1 py-2.5 px-4 bg-surface-container-high text-on-surface rounded-xl text-xs font-semibold hover:bg-surface-bright transition-colors cursor-pointer"
              >
                Quay lại tab MobieX
              </button>
            </div>
          </div>
        </div>
      ) : (
        /* STATE 3: FAILED OR CANCELLED */
        <div className="bg-surface-container rounded-3xl border border-error/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-red-500 via-rose-500 to-amber-500" />

          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto text-error ring-8 ring-error/5">
              <span className="material-symbols-outlined text-5xl">cancel</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">
                {error && !result
                  ? 'Không Thể Xác Thực Giao Dịch'
                  : isCancelled
                    ? 'Giao Dịch Đã Bị Hủy'
                    : 'Thanh Toán Chưa Hoàn Tất'}
              </h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto leading-relaxed">
                {statusMessage}
              </p>
            </div>
          </div>

          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-2.5 border border-outline-variant/20">
            {orderId && (
              <div className="flex justify-between items-center gap-3">
                <span className="text-outline">Mã đơn hàng:</span>
                <span className="font-mono font-bold text-on-surface">{orderCode || `#${orderId}`}</span>
              </div>
            )}
            {details.map((row) => (
              <div key={row.label} className="flex justify-between items-center gap-3">
                <span className="text-outline">{row.label}:</span>
                <span className="font-mono text-on-surface text-right break-all">{row.value}</span>
              </div>
            ))}
            {amount > 0 && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Số tiền:</span>
                <span className="font-bold text-on-surface">{formatCurrencyVND(amount)}</span>
              </div>
            )}
            <div className="pt-2 border-t border-outline-variant/15 text-[11px] text-on-surface-variant leading-relaxed">
              💡 <strong>Gợi ý:</strong> Đơn hàng của bạn vẫn đang được giữ tạm thời. Bạn có thể bấm thử thanh toán lại hoặc vào chi tiết đơn hàng nếu muốn hủy đơn.
            </div>
          </div>

          <div className="space-y-2.5 pt-1">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <button
                  type="button"
                  onClick={() => navigate(`/payment/${gateway}?order_id=${orderId}`)}
                  className="flex-1 py-3 bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary rounded-xl text-sm font-bold transition-all text-center flex items-center justify-center gap-1.5 shadow-md cursor-pointer"
                >
                  <span className="material-symbols-outlined text-base">refresh</span>
                  Thử thanh toán lại {cfg.label}
                </button>
              )}
              {orderId && (
                <Link
                  to={`/orders/${orderId}`}
                  className="py-3 px-5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-bright transition-colors text-center"
                >
                  Xem đơn hàng
                </Link>
              )}
            </div>

            <div className="flex items-center justify-center gap-4 pt-1">
              <button
                type="button"
                onClick={handleCloseOrReturn}
                className="text-xs text-primary hover:underline font-medium cursor-pointer"
              >
                Đóng tab &amp; quay lại MobieX
              </button>
              <span className="text-outline">•</span>
              <Link
                to="/orders"
                className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
              >
                Danh sách đơn hàng
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
