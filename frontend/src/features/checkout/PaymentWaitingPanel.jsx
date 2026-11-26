// src/features/checkout/PaymentWaitingPanel.jsx
// Unified MobieX Online Payment Waiting & Synchronization Screen for VNPay, ZaloPay, and MoMo.
// Preserves the MobieX tab, displays live order & payment status, supports single-tab reuse,
// handles popup-blocked and tab-closed scenarios, syncs cross-tab results, and verifies with backend.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import QRCode from 'qrcode'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useToast } from '../../contexts/ToastContext'
import {
  clearActivePaymentSession,
  closeReservedTab,
  getGatewayConfig,
  getOrderTabName,
  isTabOpen,
  navigateReservedTab,
  openPaymentTabOnUserAction,
  reserveGatewayTab,
  subscribePaymentResult,
} from '../../utils/paymentTabSync'

function resolveQrSource(payment) {
  const value = payment?.qr_code_url || payment?.deeplink || ''
  if (!value || /^data:image\//i.test(value)) return ''
  return value
}

export default function PaymentWaitingPanel({
  gateway,
  orderId,
  initialOrder = null,
  initialPayment = null,
  initialPopupBlocked = false,
  initialTabOpened = false,
  initialCreateError = '',
  externalTabRef = null,
  checkoutSnapshot = null,
  onClearSession = null,
}) {
  const navigate = useNavigate()
  const { showToast } = useToast()
  const cfg = getGatewayConfig(gateway)

  const [order, setOrder] = useState(initialOrder)
  const [paymentData, setPaymentData] = useState(initialPayment)
  const [loading, setLoading] = useState(!initialPayment && !initialCreateError && !initialOrder?.payment_status)
  const [createError, setCreateError] = useState(initialCreateError)
  const [verifying, setVerifying] = useState(false)
  const [retrying, setRetrying] = useState(false)

  const [popupBlocked, setPopupBlocked] = useState(initialPopupBlocked)
  const [tabOpened, setTabOpened] = useState(initialTabOpened)
  const [gatewayTabClosed, setGatewayTabClosed] = useState(false)
  const [gatewayFeedback, setGatewayFeedback] = useState(null) // { status, message, transactionNo }
  const [verifyState, setVerifyState] = useState('')
  const [autoPollTimedOut, setAutoPollTimedOut] = useState(false)
  const [qrImage, setQrImage] = useState('')

  const internalTabRef = useRef(null)
  const tabRef = externalTabRef || internalTabRef
  const pollCountRef = useRef(0)
  const lastOpenAttemptAtRef = useRef(0)
  const lastManualCheckAtRef = useRef(0)
  const tabTargetName = getOrderTabName(orderId)

  // Keep synced with updated props from Checkout submission
  useEffect(() => {
    if (initialOrder) setOrder(initialOrder)
  }, [initialOrder])

  useEffect(() => {
    if (initialPayment) setPaymentData(initialPayment)
  }, [initialPayment])

  useEffect(() => {
    setPopupBlocked(initialPopupBlocked)
  }, [initialPopupBlocked])

  useEffect(() => {
    setTabOpened(initialTabOpened)
  }, [initialTabOpened])

  useEffect(() => {
    setCreateError(initialCreateError)
  }, [initialCreateError])

  const paymentUrl = paymentData?.payment_url || paymentData?.order_url || ''
  const isPaid = order?.payment_status === 'paid'
  const isOrderCancelled = order?.status === 'cancelled'
  const isFailed = !isPaid && !isOrderCancelled && (
    gatewayFeedback?.status === 'failed' ||
    gatewayFeedback?.status === 'cancelled' ||
    order?.payment_status === 'failed'
  )
  const isPendingConfirmation = !isPaid && !isFailed && gatewayFeedback?.status === 'pending_confirmation'

  const qrValue = useMemo(
    () => (gateway === 'momo' ? resolveQrSource(paymentData) : ''),
    [gateway, paymentData],
  )

  useEffect(() => {
    let active = true
    setQrImage('')
    if (!qrValue) return undefined
    QRCode.toDataURL(qrValue, {
      width: 240,
      margin: 2,
      errorCorrectionLevel: 'M',
      color: { dark: '#111827', light: '#ffffff' },
    })
      .then((img) => {
        if (active) setQrImage(img)
      })
      .catch(() => {
        if (active) setQrImage('')
      })
    return () => { active = false }
  }, [qrValue])

  // ── Standalone route initialization (NEVER auto-opens popup without user click!) ─
  useEffect(() => {
    if (!orderId) {
      setCreateError('Không tìm thấy mã đơn hàng cần thanh toán.')
      setLoading(false)
      return
    }
    if (initialPayment || initialCreateError) {
      setLoading(false)
      return
    }

    let active = true
    async function loadAndCreatePayment() {
      setLoading(true)
      try {
        const orderRes = await axiosClient.get(EP.ORDER_DETAIL(orderId))
        if (!active) return
        const orderObj = orderRes.data.data ?? orderRes.data
        setOrder(orderObj)

        if (orderObj.payment_status === 'paid' || orderObj.status === 'cancelled') {
          setLoading(false)
          return
        }

        const payRes = await axiosClient.post(
          cfg.createEndpoint,
          { order_id: Number(orderId) },
          { timeout: 30000 },
        )
        if (!active) return
        const payload = payRes.data.data ?? payRes.data
        const url = payload.payment_url || payload.order_url
        if (url) {
          setPaymentData(payload)
          if (payload.order) {
            setOrder((prev) => ({ ...(prev || {}), ...payload.order }))
          }
        } else {
          setCreateError(`Không nhận được liên kết thanh toán từ ${cfg.label}.`)
        }
      } catch (err) {
        if (!active) return
        setCreateError(
          err.response?.data?.message || err.message || `Lỗi kết nối ${cfg.fullName}.`,
        )
      } finally {
        if (active) setLoading(false)
      }
    }

    loadAndCreatePayment()
    return () => { active = false }
  }, [orderId, cfg.createEndpoint, cfg.label, cfg.fullName, initialPayment, initialCreateError])

  const MAX_WAIT_POLLS = 45

  // ── Verify / Poll Payment Status ──────────────────────────────────────────────
  const checkOrderStatus = useCallback(
    async ({ manual = false, verifyGateway = false } = {}) => {
      if (!orderId || isPaid || isOrderCancelled) return
      if (manual) {
        const now = Date.now()
        if (now - lastManualCheckAtRef.current < 2000) {
          showToast('Vui lòng đợi giây lát trước khi kiểm tra lại.', 'info')
          return
        }
        lastManualCheckAtRef.current = now
        pollCountRef.current = 0
        setAutoPollTimedOut(false)
        setVerifying(true)
      }

      try {
        if (verifyGateway) {
          const { data } = await axiosClient.post(EP.PAYMENT_VERIFY_STATUS(orderId), {}, { timeout: 15000 })
          const resData = data.data ?? data
          const vState = resData.verification_state || ''
          setVerifyState(vState)
          setOrder((prev) => ({
            ...(prev || {}),
            id: Number(orderId),
            order_code: resData.order_code || prev?.order_code,
            payment_status: resData.payment_status ?? prev?.payment_status,
            status: resData.order_status ?? prev?.status,
            total_amount: resData.total_amount ?? prev?.total_amount,
          }))

          if (resData.payment_status === 'paid') {
            clearActivePaymentSession()
            showToast(`Đơn hàng đã được ${cfg.label} xác nhận thanh toán!`, 'success')
            return
          }

          if (resData.payment_status === 'failed' || vState === 'verified_failed') {
            setGatewayFeedback({
              status: 'failed',
              message: data.message || 'Giao dịch không thành công. Quý khách có thể thử thanh toán lại.',
            })
            if (manual) {
              showToast(data.message || 'Giao dịch thanh toán không thành công.', 'error')
            }
            return
          }

          if (vState === 'flagged') {
            setGatewayFeedback({
              status: 'failed',
              message: data.message || 'Giao dịch đang bị cổng thanh toán rà soát bất thường.',
            })
            if (manual) {
              showToast(data.message || 'Giao dịch đang bị rà soát bảo mật.', 'warning')
            }
            return
          }

          if (manual) {
            showToast(
              data.message || 'Chưa nhận được xác nhận hoàn tất từ cổng thanh toán. Hệ thống vẫn giữ đơn cho bạn.',
              vState === 'gateway_unavailable' ? 'warning' : 'info',
            )
          }
          return
        }

        const { data } = await axiosClient.get(EP.ORDER_DETAIL(orderId))
        const orderData = data.data ?? data
        setOrder(orderData)

        if (orderData.payment_status === 'paid') {
          clearActivePaymentSession()
          showToast(`Thanh toán qua ${cfg.label} thành công!`, 'success')
        } else if (orderData.payment_status === 'failed') {
          setGatewayFeedback((prev) =>
            prev?.status === 'failed'
              ? prev
              : { status: 'failed', message: 'Giao dịch thanh toán chưa hoàn tất. Bạn có thể thử thanh toán lại.' },
          )
        }
      } catch (err) {
        if (manual) {
          showToast(
            err.response?.data?.message || 'Không thể kiểm tra trạng thái lúc này. Vui lòng thử lại sau vài giây.',
            'error',
          )
        }
      } finally {
        if (manual) setVerifying(false)
      }
    },
    [orderId, isPaid, isOrderCancelled, cfg.label, showToast],
  )

  // ── Subscribe to Return Tab Broadcasts (instant sync across tabs) ─────────────
  useEffect(() => {
    if (!orderId) return undefined
    return subscribePaymentResult(orderId, (event) => {
      setGatewayFeedback({
        status: event.status,
        message: event.message,
        transactionNo: event.transactionNo,
      })

      if (event.status === 'paid') {
        setOrder((prev) => ({ ...(prev || {}), payment_status: 'paid', status: 'confirmed' }))
        clearActivePaymentSession()
        showToast(`Thanh toán qua ${cfg.label} thành công!`, 'success')
      } else if (event.status === 'pending_confirmation') {
        // Trigger server-to-server check immediately once gateway return confirmed submission
        checkOrderStatus({ manual: false, verifyGateway: true })
      } else if (event.status === 'failed' || event.status === 'cancelled') {
        checkOrderStatus({ manual: false, verifyGateway: false })
      }
    })
  }, [orderId, cfg.label, checkOrderStatus, showToast])

  // ── Background Polling + Tab Visibility & Gateway-Tab-Closed Watchers ─────────
  useEffect(() => {
    if (!orderId || isPaid || isOrderCancelled || loading || createError || autoPollTimedOut) return undefined

    const pollInterval = window.setInterval(() => {
      pollCountRef.current += 1
      if (pollCountRef.current >= MAX_WAIT_POLLS) {
        setAutoPollTimedOut(true)
        return
      }
      const canQueryGateway = verifyState !== 'gateway_unavailable'
      const shouldVerifyGateway =
        canQueryGateway && (isPendingConfirmation || pollCountRef.current % 3 === 0) && Boolean(paymentUrl)
      checkOrderStatus({ manual: false, verifyGateway: shouldVerifyGateway })
    }, 4000)

    const tabCloseInterval = window.setInterval(() => {
      if (tabRef.current && !isTabOpen(tabRef.current)) {
        tabRef.current = null
        setGatewayTabClosed(true)
        checkOrderStatus({ manual: false, verifyGateway: true })
      }
    }, 1200)

    const handleVisibilityOrFocus = () => {
      if (document.visibilityState === 'visible') {
        checkOrderStatus({ manual: false, verifyGateway: verifyState !== 'gateway_unavailable' && Boolean(paymentUrl) })
      }
    }

    document.addEventListener('visibilitychange', handleVisibilityOrFocus)
    window.addEventListener('focus', handleVisibilityOrFocus)

    return () => {
      window.clearInterval(pollInterval)
      window.clearInterval(tabCloseInterval)
      document.removeEventListener('visibilitychange', handleVisibilityOrFocus)
      window.removeEventListener('focus', handleVisibilityOrFocus)
    }
  }, [
    orderId,
    isPaid,
    isOrderCancelled,
    loading,
    createError,
    autoPollTimedOut,
    verifyState,
    isPendingConfirmation,
    paymentUrl,
    checkOrderStatus,
    tabRef,
  ])

  // ── Clear session when paid or cancelled ──────────────────────────────────────
  useEffect(() => {
    if (isPaid || isOrderCancelled) {
      clearActivePaymentSession()
    }
  }, [isPaid, isOrderCancelled])

  // ── Open or Focus Payment Tab (Strict user-click action with double-click cooldown) ─
  const handleOpenOrFocusTab = () => {
    if (!paymentUrl) return
    const now = Date.now()
    if (now - lastOpenAttemptAtRef.current < 1200) {
      return
    }
    lastOpenAttemptAtRef.current = now
    setGatewayTabClosed(false)

    const { tab, blocked } = openPaymentTabOnUserAction(tabRef.current, paymentUrl, tabTargetName)
    if (blocked) {
      setPopupBlocked(true)
    } else {
      tabRef.current = tab
      setPopupBlocked(false)
      setTabOpened(true)
    }
  }

  // ── Retry Creating New Attempt (after failed payment or gateway creation error)
  const handleRetryPayment = async () => {
    if (!orderId || retrying) return
    setRetrying(true)
    setCreateError('')
    setGatewayFeedback(null)
    setGatewayTabClosed(false)

    // Reserve tab synchronously on click
    const { tab: reservedTab, blocked } = reserveGatewayTab(tabRef.current, cfg.label, tabTargetName)

    try {
      const { data } = await axiosClient.post(
        cfg.createEndpoint,
        { order_id: Number(orderId) },
        { timeout: 30000 },
      )
      const payload = data.data ?? data
      const url = payload.payment_url || payload.order_url

      if (!url) {
        closeReservedTab(reservedTab)
        setCreateError(`Không nhận được liên kết thanh toán từ ${cfg.label}.`)
        return
      }

      setPaymentData(payload)
      if (payload.order) {
        setOrder((prev) => ({ ...(prev || {}), ...payload.order }))
      }

      if (blocked) {
        setPopupBlocked(true)
      } else {
        const navigated = navigateReservedTab(reservedTab, url)
        if (navigated) {
          tabRef.current = reservedTab
          setPopupBlocked(false)
          setTabOpened(true)
        } else {
          closeReservedTab(reservedTab)
          setPopupBlocked(true)
        }
      }
    } catch (err) {
      closeReservedTab(reservedTab)
      const msg = err.response?.data?.message || err.message || `Không thể khởi tạo giao dịch ${cfg.label}.`
      setCreateError(msg)
      showToast(msg, 'error')
    } finally {
      setRetrying(false)
    }
  }

  const displayOrderCode = order?.order_code || paymentData?.order_code || checkoutSnapshot?.orderCode || `#${orderId}`
  const displayAmount = Number(
    order?.total_amount ?? order?.grand_total ?? paymentData?.amount ?? checkoutSnapshot?.totalAmount ?? 0,
  )

  // ── Loading State ─────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[55vh] gap-5 text-center px-4">
        <div
          className="w-16 h-16 rounded-2xl flex items-center justify-center shadow-lg animate-pulse text-white"
          style={{ background: cfg.badgeGradient }}
        >
          <span className="material-symbols-outlined text-3xl">{cfg.icon}</span>
        </div>
        <Spinner size="lg" />
        <div className="space-y-1">
          <h2 className="text-lg font-bold text-on-surface">Đang kết nối {cfg.fullName}...</h2>
          <p className="text-xs text-on-surface-variant max-w-xs">
            Hệ thống đang khởi tạo phiên giao dịch bảo mật cho đơn hàng của bạn.
          </p>
        </div>
      </div>
    )
  }

  // ── Paid Success State ────────────────────────────────────────────────────────
  if (isPaid) {
    return (
      <div className="max-w-xl mx-auto px-4 py-12 space-y-6">
        <div className="bg-surface-container rounded-3xl border border-emerald-500/30 p-6 sm:p-8 text-center space-y-5 shadow-xl relative overflow-hidden">
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-600" />
          <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
            <span className="material-symbols-outlined text-5xl">check_circle</span>
          </div>
          <div className="space-y-1.5">
            <span className="inline-block text-[11px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-500/10 px-3 py-1 rounded-full">
              Đã xác nhận thanh toán
            </span>
            <h1 className="text-2xl font-black text-on-surface">Thanh Toán Thành Công!</h1>
            <p className="text-sm text-on-surface-variant">
              Đơn hàng <strong className="font-mono text-on-surface">{displayOrderCode}</strong> đã được xác nhận thanh toán qua {cfg.label}.
            </p>
          </div>

          {displayAmount > 0 && (
            <div className="bg-surface-container-low rounded-2xl p-4 border border-outline-variant/20 inline-block min-w-[240px]">
              <p className="text-[11px] text-outline uppercase tracking-wider">Số tiền đã thanh toán</p>
              <p className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">
                {formatCurrencyVND(displayAmount)}
              </p>
            </div>
          )}

          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <Button
              type="button"
              variant="primary"
              onClick={() => navigate(`/orders/${orderId}?payment_success=1`)}
              className="flex-1"
            >
              <span className="material-symbols-outlined text-base">visibility</span>
              Xem chi tiết đơn hàng
            </Button>
            <Link
              to="/products"
              className="px-5 py-2.5 rounded-lg bg-surface-container-high text-on-surface text-sm font-semibold hover:bg-surface-bright transition-colors flex items-center justify-center"
            >
              Tiếp tục mua sắm
            </Link>
          </div>
        </div>
      </div>
    )
  }

  // ── Cancelled Order State ─────────────────────────────────────────────────────
  if (isOrderCancelled) {
    return (
      <div className="max-w-lg mx-auto px-4 py-14 text-center space-y-5">
        <div className="w-16 h-16 rounded-full bg-error/10 text-error flex items-center justify-center mx-auto">
          <span className="material-symbols-outlined text-4xl">cancel</span>
        </div>
        <h2 className="text-xl font-bold text-on-surface">Đơn hàng {displayOrderCode} đã bị hủy</h2>
        <p className="text-sm text-on-surface-variant">
          Đơn hàng này không còn hiệu lực thanh toán. Bạn có thể xem lại đơn hàng hoặc đặt đơn mới.
        </p>
        <div className="flex justify-center gap-3 pt-2 flex-wrap">
          <Link
            to={`/orders/${orderId}`}
            className="px-5 py-2.5 bg-surface-container text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container-high transition-colors"
          >
            Xem chi tiết đơn hàng
          </Link>
          <Link
            to="/products"
            className="px-5 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity"
          >
            Tiếp tục mua sắm
          </Link>
        </div>
      </div>
    )
  }

  // ── Initialization Failed Before Payment URL Was Ready ────────────────────────
  if (createError && !paymentUrl) {
    return (
      <div className="max-w-lg mx-auto px-4 py-14 space-y-5">
        <div className="bg-surface-container rounded-3xl border border-error/30 p-6 sm:p-8 text-center space-y-4 shadow-xl">
          <div className="w-16 h-16 rounded-full bg-error/10 text-error flex items-center justify-center mx-auto">
            <span className="material-symbols-outlined text-4xl">error</span>
          </div>
          <div className="space-y-1.5">
            <h2 className="text-xl font-bold text-on-surface">Không thể kết nối {cfg.fullName}</h2>
            <p className="text-sm text-on-surface-variant">{createError}</p>
            {orderId && (
              <p className="text-xs text-outline pt-1">
                Đơn hàng <span className="font-mono font-semibold text-on-surface">{displayOrderCode}</span> vẫn được lưu giữ — bạn không cần đặt lại đơn mới.
              </p>
            )}
          </div>
          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <Button
              type="button"
              variant="primary"
              loading={retrying}
              onClick={handleRetryPayment}
              className="flex-1"
            >
              <span className="material-symbols-outlined text-base">refresh</span>
              Thử kết nối lại {cfg.label}
            </Button>
            {orderId && (
              <Link
                to={`/orders/${orderId}`}
                onClick={() => onClearSession?.()}
                className="px-5 py-2.5 bg-surface-container-high text-on-surface rounded-lg text-sm font-semibold hover:bg-surface-bright transition-colors flex items-center justify-center"
              >
                Xem đơn hàng
              </Link>
            )}
          </div>
        </div>
      </div>
    )
  }

  // ── Active Payment Waiting Screen ─────────────────────────────────────────────
  return (
    <div className="max-w-3xl mx-auto px-4 py-8 sm:py-10 space-y-5">
      {/* Gateway Brand & Security Header */}
      <div className="flex flex-wrap items-center justify-between gap-3 bg-surface-container/70 border border-outline-variant/25 px-5 py-3.5 rounded-2xl">
        <div className="flex items-center gap-3">
          <div
            className="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-md shrink-0"
            style={{ background: cfg.badgeGradient }}
          >
            <span className="material-symbols-outlined text-xl">{cfg.icon}</span>
          </div>
          <div>
            <h1 className="text-sm sm:text-base font-bold text-on-surface">{cfg.fullName}</h1>
            <p className="text-[11px] text-on-surface-variant">{cfg.securityLabel}</p>
          </div>
        </div>
        <div className="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-full font-semibold">
          <span className="material-symbols-outlined text-sm">verified_user</span>
          <span>Giao dịch được bảo vệ</span>
        </div>
      </div>

      {/* Main Card */}
      <div className="bg-surface-container rounded-3xl border border-outline-variant/25 p-6 sm:p-8 space-y-6 shadow-xl">

        {/* Status Callout Banner */}
        {isFailed ? (
          <div className="bg-error/10 border border-error/30 rounded-2xl p-4 sm:p-5 flex items-start gap-3">
            <span className="material-symbols-outlined text-error text-2xl shrink-0 mt-0.5">cancel</span>
            <div className="space-y-1.5 flex-1">
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <p className="text-sm font-bold text-error">
                  {gatewayFeedback?.status === 'cancelled'
                    ? 'Bạn đã hủy giao dịch thanh toán'
                    : 'Giao dịch thanh toán chưa hoàn tất'}
                </p>
                <span className="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-error/15 text-error">
                  Thất bại / Đã hủy
                </span>
              </div>
              <p className="text-xs text-on-surface-variant leading-relaxed">
                {gatewayFeedback?.message || `Giao dịch qua ${cfg.label} chưa thành công. Đơn hàng của bạn vẫn được giữ — vui lòng thử thanh toán lại hoặc vào chi tiết đơn hàng nếu muốn hủy đơn.`}
              </p>
            </div>
          </div>
        ) : isPendingConfirmation ? (
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 sm:p-5 flex items-start gap-3">
            <span className="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping shrink-0 mt-1.5" />
            <div className="space-y-1 flex-1">
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <p className="text-sm font-bold text-amber-700 dark:text-amber-300">
                  Cổng {cfg.label} đã tiếp nhận — đang chờ xác nhận
                </p>
                <span className="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300">
                  Đang đối soát
                </span>
              </div>
              <p className="text-xs text-on-surface-variant leading-relaxed">
                Hệ thống đang đối soát kết quả chính thức từ {cfg.label}.{' '}
                <strong>Không cần đặt lại đơn hàng mới.</strong> Kết quả sẽ tự động cập nhật sau vài giây.
              </p>
            </div>
          </div>
        ) : popupBlocked ? (
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 sm:p-5 flex items-start gap-3">
            <span className="material-symbols-outlined text-amber-600 text-2xl shrink-0 mt-0.5">warning</span>
            <div className="space-y-2 flex-1">
              <p className="text-sm font-bold text-amber-700 dark:text-amber-300">
                Trình duyệt đã chặn tự động mở tab {cfg.label}
              </p>
              <p className="text-xs text-on-surface-variant leading-relaxed">
                Đơn hàng <strong>{displayOrderCode}</strong> đã sẵn sàng thanh toán. Vui lòng bấm trực tiếp nút bên dưới để mở trang thanh toán {cfg.label} trong tab mới.
              </p>
              {paymentUrl && (
                <a
                  href={paymentUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={() => {
                    setPopupBlocked(false)
                    setTabOpened(true)
                  }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 text-slate-950 text-xs font-bold hover:opacity-90 transition-opacity"
                >
                  <span className="material-symbols-outlined text-sm">open_in_new</span>
                  Mở trang thanh toán {cfg.label} ngay
                </a>
              )}
            </div>
          </div>
        ) : gatewayTabClosed ? (
          <div className="bg-surface-container-high/70 border border-outline-variant/30 rounded-2xl p-4 sm:p-5 flex items-start gap-3">
            <span className="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">tab</span>
            <div className="space-y-1 flex-1">
              <p className="text-sm font-bold text-on-surface">
                Cửa sổ thanh toán {cfg.label} đã được đóng
              </p>
              <p className="text-xs text-on-surface-variant leading-relaxed">
                Nếu bạn đã hoàn tất thanh toán trên {cfg.label}, hãy bấm <strong>&quot;Kiểm tra trạng thái ngay&quot;</strong>. Nếu chưa, bấm <strong>&quot;Mở trang thanh toán {cfg.label}&quot;</strong> để tiếp tục.
              </p>
            </div>
          </div>
        ) : (
          <div className={`${cfg.accentBg} border ${cfg.accentBorder} rounded-2xl p-4 sm:p-5 space-y-2`}>
            <div className="flex items-center justify-between gap-2 flex-wrap">
              <div className={`flex items-center gap-2 font-bold text-sm ${cfg.accentText}`}>
                <span className="material-symbols-outlined text-lg">
                  {tabOpened ? 'open_in_new' : 'lock_open'}
                </span>
                <span>
                  {tabOpened
                    ? `Đã chuyển sang trang thanh toán ${cfg.label} ở tab riêng`
                    : `Phiên thanh toán ${cfg.label} đã sẵn sàng`}
                </span>
              </div>
              <span className={`inline-flex items-center gap-1.5 text-[11px] font-semibold ${cfg.accentText}`}>
                <span className="w-2 h-2 rounded-full bg-current animate-ping" />
                Đang chờ thanh toán
              </span>
            </div>
            <p className="text-xs text-on-surface-variant leading-relaxed">
              {tabOpened
                ? `Vui lòng hoàn tất thanh toán trên tab ${cfg.label} vừa mở. `
                : `Bấm nút bên dưới để mở cổng thanh toán ${cfg.label} trong tab mới. `}
              <strong>Vui lòng không đóng trang MobieX này</strong> để tiếp tục theo dõi và tự động cập nhật trạng thái đơn hàng.
            </p>
          </div>
        )}

        {/* Order Details & MoMo QR Grid */}
        <div className={`grid grid-cols-1 ${qrImage ? 'md:grid-cols-12' : ''} gap-5`}>
          <div className={`${qrImage ? 'md:col-span-7' : ''} space-y-4`}>
            <div className="bg-surface-container-low rounded-2xl p-4 sm:p-5 text-sm space-y-3 border border-outline-variant/20">
              <div className="flex items-center justify-between text-xs">
                <span className="text-outline">Mã đơn hàng</span>
                <span className="font-mono font-bold text-on-surface">{displayOrderCode}</span>
              </div>
              <div className="flex items-center justify-between text-xs">
                <span className="text-outline">Phương thức thanh toán</span>
                <span className="font-semibold text-on-surface">{cfg.label}</span>
              </div>
              <div className="flex items-center justify-between text-xs">
                <span className="text-outline">Trạng thái thanh toán</span>
                <span className={`font-semibold px-2.5 py-0.5 rounded-full text-[11px] ${
                  isFailed
                    ? 'bg-error/15 text-error'
                    : isPendingConfirmation
                      ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                      : 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                }`}>
                  {isFailed
                    ? 'Chưa thanh toán (Thất bại)'
                    : isPendingConfirmation
                      ? 'Đang chờ đối soát'
                      : 'Đang chờ thanh toán'}
                </span>
              </div>

              {displayAmount > 0 && (
                <div className="flex items-center justify-between pt-2.5 border-t border-outline-variant/15">
                  <span className="text-sm font-semibold text-on-surface">Tổng tiền thanh toán</span>
                  <span className="font-extrabold text-xl text-primary">
                    {formatCurrencyVND(displayAmount)}
                  </span>
                </div>
              )}

              <div className="flex items-center justify-between pt-2 border-t border-outline-variant/15 text-[11px] text-on-surface-variant">
                <span>Cập nhật tự động</span>
                {autoPollTimedOut ? (
                  <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300 font-medium">
                    <span className="material-symbols-outlined text-sm">pause_circle</span>
                    Đã tạm dừng — bấm Kiểm tra trạng thái ngay
                  </span>
                ) : verifyState === 'gateway_unavailable' ? (
                  <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300 font-medium">
                    <span className="material-symbols-outlined text-sm">schedule</span>
                    Chờ IPN từ cổng (QueryDR tạm gián đoạn)
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5">
                    <Spinner size="xs" />
                    Đang lắng nghe kết quả từ hệ thống
                  </span>
                )}
              </div>
            </div>

            {/* Preserved Checkout Recipient Snapshot when available */}
            {checkoutSnapshot && (
              <div className="bg-surface-container-low/60 rounded-2xl p-4 text-xs space-y-1.5 border border-outline-variant/15">
                <p className="font-semibold text-on-surface">Thông tin nhận hàng đã lưu</p>
                <p className="text-on-surface-variant">
                  {checkoutSnapshot.recipientName} · <span className="font-mono">{checkoutSnapshot.phone}</span>
                </p>
                <p className="text-outline line-clamp-2">
                  {[checkoutSnapshot.shippingAddress, checkoutSnapshot.city].filter(Boolean).join(', ')}
                </p>
              </div>
            )}
          </div>

          {/* Optional MoMo QR returned directly by gateway */}
          {qrImage && (
            <div className="md:col-span-5 bg-surface-container-low rounded-2xl p-4 border border-outline-variant/20 flex flex-col items-center justify-center text-center space-y-2.5">
              <p className="text-xs font-bold text-pink-600 dark:text-pink-400">Hoặc quét mã QR Ví MoMo</p>
              <img
                src={qrImage}
                alt={`Mã QR MoMo cho đơn ${displayOrderCode}`}
                className="w-44 h-44 rounded-xl bg-white p-2 object-contain shadow-sm"
              />
              <p className="text-[11px] text-on-surface-variant leading-relaxed">
                Mở ứng dụng MoMo trên điện thoại và quét mã để thanh toán nhanh.
              </p>
            </div>
          )}
        </div>

        {/* Helpful Steps */}
        <div className="bg-surface-container-high/40 rounded-2xl p-4 text-xs space-y-2 text-on-surface-variant border border-outline-variant/15">
          <div className="font-semibold text-on-surface flex items-center gap-1.5">
            <span className="material-symbols-outlined text-sm text-primary">info</span>
            <span>Hướng dẫn nhanh:</span>
          </div>
          <ol className="list-decimal list-inside space-y-1 pl-1 text-[11px] leading-relaxed">
            {cfg.steps.map((step, index) => (
              <li key={index}>{step}</li>
            ))}
          </ol>
        </div>

        {/* Primary & Fallback Actions */}
        <div className="space-y-3 pt-1">
          {isFailed ? (
            <Button
              type="button"
              variant="primary"
              loading={retrying}
              onClick={handleRetryPayment}
              className="w-full py-3"
            >
              <span className="material-symbols-outlined text-lg">refresh</span>
              Thử thanh toán lại qua {cfg.label}
            </Button>
          ) : (
            <Button
              type="button"
              variant="primary"
              onClick={handleOpenOrFocusTab}
              disabled={!paymentUrl}
              className="w-full py-3"
            >
              <span className="material-symbols-outlined text-lg">open_in_new</span>
              {tabOpened ? `Mở lại cổng thanh toán ${cfg.label} (Tab riêng)` : `Mở trang thanh toán ${cfg.label} (Tab riêng)`}
            </Button>
          )}

          <Button
            type="button"
            variant="ghost"
            loading={verifying}
            onClick={() => checkOrderStatus({ manual: true, verifyGateway: true })}
            className="w-full py-2.5 border border-outline-variant/30 hover:border-primary text-xs font-semibold"
          >
            <span className="material-symbols-outlined text-base">sync</span>
            Tôi đã hoàn tất thanh toán — Kiểm tra trạng thái ngay
          </Button>

          {paymentUrl && !isFailed && (
            <div className="text-center">
              <a
                href={paymentUrl}
                className="text-xs text-primary hover:underline font-medium inline-flex items-center gap-1"
              >
                <span>Nếu popup vẫn bị chặn, bấm vào đây để mở trực tiếp trên tab này</span>
                <span className="material-symbols-outlined text-xs">arrow_forward</span>
              </a>
            </div>
          )}

          <div className="flex items-center justify-center gap-4 pt-2 text-xs text-outline flex-wrap">
            <Link
              to={`/orders/${orderId}`}
              onClick={() => onClearSession?.()}
              className="hover:text-on-surface hover:underline transition-colors"
            >
              Xem chi tiết đơn hàng {displayOrderCode}
            </Link>
            {onClearSession && (
              <>
                <span>•</span>
                <button
                  type="button"
                  onClick={() => {
                    clearActivePaymentSession()
                    onClearSession()
                    navigate(`/orders/${orderId}`)
                  }}
                  className="hover:text-on-surface hover:underline transition-colors cursor-pointer"
                >
                  Thanh toán sau
                </button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
