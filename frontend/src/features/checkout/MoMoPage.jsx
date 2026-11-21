import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

function qrSource(qrCodeUrl, deeplink) {
  const value = qrCodeUrl || deeplink
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value
  return `https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=12&data=${encodeURIComponent(value)}`
}

export default function MoMoPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')
  const navigate = useNavigate()
  const { showToast } = useToast()
  const [loading, setLoading] = useState(true)
  const [checking, setChecking] = useState(false)
  const [order, setOrder] = useState(null)
  const [payment, setPayment] = useState(null)
  const [momo, setMomo] = useState({ phone: '', name: '' })
  const [error, setError] = useState('')
  const [paymentError, setPaymentError] = useState('')
  const [copied, setCopied] = useState('')

  useEffect(() => {
    if (!orderId) {
      setError('Không tìm thấy mã đơn hàng cần thanh toán.')
      setLoading(false)
      return
    }

    let cancelled = false
    async function initializePayment() {
      try {
        const [orderResponse, configResponse] = await Promise.all([
          axiosClient.get(EP.ORDER_DETAIL(orderId)),
          axiosClient.get(EP.PAYMENT_CONFIG),
        ])
        if (cancelled) return
        const orderData = orderResponse.data.data ?? orderResponse.data
        const config = configResponse.data.data ?? configResponse.data
        setOrder(orderData)
        setMomo(config.momo || { phone: '', name: '' })
        if (orderData.payment_status === 'paid') return
        try {
          const paymentResponse = await axiosClient.post(EP.PAYMENT_MOMO_CREATE, { order_id: Number(orderId) })
          if (!cancelled) setPayment(paymentResponse.data.data ?? paymentResponse.data)
        } catch (paymentError) {
          if (!cancelled) setPaymentError(paymentError.response?.data?.message || paymentError.message || 'Could not create a MoMo Sandbox transaction.')
        }
      } catch (err) {
        if (!cancelled) setError(err.response?.data?.message || err.message || 'Unable to load order details.')
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    initializePayment()

    return () => { cancelled = true }
  }, [orderId])

  const checkStatus = useCallback(async (notify = false) => {
    if (!orderId) return
    setChecking(true)
    try {
      const { data } = await axiosClient.get(EP.ORDER_DETAIL(orderId))
      const nextOrder = data.data ?? data
      setOrder(nextOrder)
      if (nextOrder.payment_status === 'paid') {
        showToast('MoMo đã xác nhận thanh toán thành công.', 'success')
        navigate(`/orders/${orderId}?payment_success=1`, { replace: true })
      } else if (notify) {
        showToast('Đang chờ MoMo xác nhận giao dịch. Đơn hàng chưa được đánh dấu đã thanh toán.', 'info')
      }
    } catch (err) {
      if (notify) showToast(err.message || 'Không thể kiểm tra trạng thái đơn hàng.', 'error')
    } finally {
      setChecking(false)
    }
  }, [orderId, navigate, showToast])

  useEffect(() => {
    if (!payment || order?.payment_status === 'paid') return undefined
    const timer = window.setInterval(() => checkStatus(false), 5000)
    return () => window.clearInterval(timer)
  }, [payment, order?.payment_status, checkStatus])

  const qrUrl = useMemo(
    () => qrSource(payment?.qr_code_url, payment?.deeplink),
    [payment],
  )

  const copy = async (value, name) => {
    try {
      await navigator.clipboard.writeText(String(value || ''))
      setCopied(name)
      showToast('Đã sao chép.', 'success')
      window.setTimeout(() => setCopied(''), 1800)
    } catch {
      showToast('Không thể sao chép trên trình duyệt này.', 'error')
    }
  }

  if (loading) return <div className="min-h-[60vh] flex flex-col items-center justify-center gap-4"><Spinner size="lg" /><p className="text-sm text-on-surface-variant">Đang tạo giao dịch MoMo Sandbox…</p></div>
  if (error) return <div className="max-w-xl mx-auto px-4 py-20 text-center space-y-4"><span className="material-symbols-outlined text-5xl text-error">error</span><h1 className="text-xl font-bold text-on-surface">Unable to load payment details</h1><p className="text-sm text-on-surface-variant">{error}</p><Link className="text-primary hover:underline" to={`/orders/${orderId}`}>View order</Link></div>

  const amount = Number(payment?.amount ?? order?.total_amount ?? order?.grand_total ?? 0)
  const orderCode = payment?.order_code || order?.order_code || `DH${orderId}`

  return (
    <main className="w-full px-4 sm:px-6 lg:px-10 xl:px-12 py-8 sm:py-12">
      <div className="max-w-5xl mx-auto space-y-6">
        <header className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-outline-variant/25 bg-surface-container p-5">
          <div><p className="text-xs uppercase tracking-wider font-bold text-pink-500">MoMo Sandbox</p><h1 className="text-xl sm:text-2xl font-extrabold text-on-surface mt-1">Quét mã QR để thanh toán</h1></div>
          <span className="rounded-full bg-amber-500/10 text-amber-600 px-3 py-1.5 text-xs font-semibold">{order?.payment_status === 'paid' ? 'Đã xác nhận' : 'Đang chờ xác nhận'}</span>
        </header>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <section className="rounded-3xl border border-outline-variant/25 bg-surface-container p-6 sm:p-8 flex flex-col items-center gap-5 text-center">
            {qrUrl ? <img src={qrUrl} alt={`Mã QR MoMo cho đơn ${orderCode}`} className="w-72 h-72 max-w-full rounded-2xl bg-white p-3 object-contain" /> : <div className="w-72 h-72 rounded-2xl bg-surface-container-high flex items-center justify-center text-sm text-on-surface-variant">{paymentError || 'Sandbox did not return a QR code or deeplink.'}</div>}
            {payment?.payment_url && <a href={payment.payment_url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-pink-600 hover:underline">Mở giao dịch MoMo Sandbox</a>}
            <p className="text-xs text-on-surface-variant">Mã QR được lấy từ giao dịch MoMo Sandbox hoặc tạo từ deeplink do Sandbox trả về.</p>
          </section>

          <section className="rounded-3xl border border-outline-variant/25 bg-surface-container p-6 sm:p-8 space-y-4">
            <h2 className="text-base font-bold text-on-surface">Thông tin thanh toán</h2>
            <div className="rounded-xl bg-surface-container-low p-4"><p className="text-xs text-outline">Mã đơn hàng / nội dung</p><div className="flex items-center justify-between gap-2 mt-1"><strong className="font-mono text-on-surface break-all">{payment?.transfer_content || orderCode}</strong><button type="button" className="text-xs text-primary font-semibold" onClick={() => copy(payment?.transfer_content || orderCode, 'content')}>{copied === 'content' ? 'Đã chép' : 'Sao chép'}</button></div></div>
            <div className="rounded-xl bg-surface-container-low p-4"><p className="text-xs text-outline">Người nhận theo cấu hình</p><p className="font-semibold text-on-surface mt-1">{momo.name || 'Chưa cấu hình tên ví'}</p></div>
            <div className="rounded-xl bg-surface-container-low p-4"><p className="text-xs text-outline">Số điện thoại MoMo</p><div className="flex items-center justify-between gap-2 mt-1"><strong className="font-mono text-on-surface">{momo.phone || 'Chưa cấu hình số điện thoại'}</strong><button type="button" className="text-xs text-primary font-semibold" onClick={() => copy(momo.phone, 'phone')}>{copied === 'phone' ? 'Đã chép' : 'Sao chép'}</button></div></div>
            <div className="rounded-xl bg-pink-500/5 border border-pink-500/20 p-4"><p className="text-xs text-pink-600">Số tiền cần thanh toán</p><p className="text-2xl font-extrabold text-pink-600 mt-1">{formatCurrencyVND(amount)}</p></div>
            <Button type="button" variant="primary" className="w-full" loading={checking} onClick={() => checkStatus(true)}>Tôi đã thanh toán / Kiểm tra trạng thái</Button>
            <p className="text-xs text-on-surface-variant">Nút này chỉ kiểm tra trạng thái. Đơn hàng chỉ được chuyển sang đã thanh toán sau callback/IPN hợp lệ từ MoMo hoặc thao tác mô phỏng của quản trị viên.</p>
            <Link to={`/orders/${orderId}`} className="block text-center text-xs text-outline hover:text-primary">Để thanh toán sau · Xem đơn #{orderCode}</Link>
          </section>
        </div>
      </div>
    </main>
  )
}
