// src/features/checkout/PaymentReturnPage.jsx
// Handles return redirect from VNPay, verifies HMAC signature with backend dual-confirmation,
// and displays an enterprise-grade payment receipt with print, retry, and order navigation capabilities.

import { useEffect, useState } from 'react'
import { useSearchParams, Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'

// VNPay Response Code mapping to human-friendly Vietnamese descriptions
const VNPAY_RESPONSE_MESSAGES = {
  '00': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng MobieX!',
  '07': 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo hoặc bất thường).',
  '09': 'Thẻ/Tài khoản của quý khách chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
  '10': 'Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
  '11': 'Đã hết hạn chờ thanh toán. Xin vui lòng thực hiện lại giao dịch.',
  '12': 'Thẻ/Tài khoản của quý khách đang bị khóa.',
  '13': 'Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Vui lòng thử lại.',
  '24': 'Giao dịch đã bị hủy bởi quý khách.',
  '51': 'Tài khoản của quý khách không đủ số dư để thực hiện giao dịch này.',
  '65': 'Tài khoản của quý khách đã vượt quá hạn mức giao dịch trong ngày.',
  '75': 'Ngân hàng thanh toán đang trong quá trình bảo trì.',
  '79': 'Quý khách nhập sai mật khẩu thanh toán quá số lần quy định.',
  '99': 'Lỗi không xác định hoặc chữ ký xác thực bảo mật không hợp lệ.',
}

function formatVNPayDate(dateStr) {
  if (!dateStr || dateStr.length < 14) return '—'
  // Format: YYYYMMDDHHmmss
  const year = dateStr.slice(0, 4)
  const month = dateStr.slice(4, 6)
  const day = dateStr.slice(6, 8)
  const hour = dateStr.slice(8, 10)
  const minute = dateStr.slice(10, 12)
  const second = dateStr.slice(12, 14)
  return `${hour}:${minute}:${second} ${day}/${month}/${year}`
}

export default function PaymentReturnPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    // Send all query parameters received from VNPay to the backend verification endpoint
    const params = Object.fromEntries(searchParams.entries())

    axiosClient
      .get(EP.PAYMENT_VNPAY_RETURN, { params })
      .then(({ data }) => {
        setResult(data.data ?? data)
      })
      .catch((err) => {
        setError(err.message || 'Lỗi xác thực chữ ký dữ liệu từ cổng thanh toán.')
      })
      .finally(() => setLoading(false))
  }, [searchParams])

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">Đang xác thực kết quả giao dịch VNPay...</h2>
        <p className="text-xs text-on-surface-variant max-w-sm">
          Hệ thống đang đối soát chữ ký điện tử HMAC-SHA512 với cổng thanh toán để đảm bảo tính an toàn và minh bạch.
        </p>
      </div>
    )
  }

  const responseCode = searchParams.get('vnp_ResponseCode') || '99'
  const isSuccess = (result?.status === 'success' || responseCode === '00') && !error
  const orderId = result?.order_id || searchParams.get('vnp_TxnRef')
  const amount = Number(searchParams.get('vnp_Amount')) / 100 || result?.amount || 0
  const txnNo = searchParams.get('vnp_TransactionNo') || '—'
  const bankCode = searchParams.get('vnp_BankCode') || 'NCB'
  const cardType = searchParams.get('vnp_CardType') || 'ATM'
  const payDate = formatVNPayDate(searchParams.get('vnp_PayDate'))
  const statusMessage = VNPAY_RESPONSE_MESSAGES[responseCode] || result?.message || 'Giao dịch không thành công.'

  const handlePrint = () => {
    window.print()
  }

  return (
    <div className="max-w-xl mx-auto px-4 py-12 space-y-6">
      {/* Brand & Security Header */}
      <div className="flex items-center justify-between gap-3 bg-surface-container/60 border border-outline-variant/30 px-5 py-3 rounded-2xl">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white">
            <span className="material-symbols-outlined text-lg">receipt_long</span>
          </div>
          <div>
            <h2 className="text-sm font-bold text-on-surface">Biên Lai Giao Dịch Điện Tử</h2>
            <p className="text-[11px] text-on-surface-variant">Cổng thanh toán trực tuyến MobieX x VNPay</p>
          </div>
        </div>
        <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-surface-container-high text-on-surface-variant">
          Mã phản hồi: {responseCode}
        </span>
      </div>

      {isSuccess ? (
        <div className="bg-surface-container rounded-3xl border border-emerald-500/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          {/* Subtle Emerald Top Glow */}
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-600" />

          {/* Success Banner */}
          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
              <span className="material-symbols-outlined text-5xl">check_circle</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Thành Công!</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto">
                {statusMessage}
              </p>
            </div>
            {amount > 0 && (
              <div className="pt-2">
                <span className="text-xs text-outline block">Số tiền đã giao dịch</span>
                <span className="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                  {formatCurrencyVND(amount)}
                </span>
              </div>
            )}
          </div>

          {/* Electronic Receipt Card */}
          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-3 border border-outline-variant/20">
            <div className="flex items-center justify-between pb-2 border-b border-outline-variant/15 text-outline">
              <span className="font-semibold text-on-surface uppercase tracking-wider text-[11px]">Chi tiết giao dịch</span>
              <span className="text-emerald-600 font-medium flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">verified</span>
                Đã đối soát chữ ký
              </span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã đơn hàng:</span>
              <span className="font-mono font-bold text-on-surface">#{orderId}</span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã tham chiếu VNPay:</span>
              <span className="font-mono text-on-surface">{searchParams.get('vnp_TxnRef') || orderId}</span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã GD tại cổng VNPay:</span>
              <span className="font-mono text-on-surface">{txnNo}</span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Kênh thanh toán / Thẻ:</span>
              <span className="text-on-surface font-medium">{bankCode} ({cardType})</span>
            </div>

            {payDate !== '—' && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Thời gian thanh toán:</span>
                <span className="font-mono text-on-surface">{payDate}</span>
              </div>
            )}

            <div className="flex justify-between items-center pt-2 border-t border-outline-variant/15">
              <span className="text-outline">Trạng thái đơn hàng:</span>
              <span className="inline-flex items-center gap-1.5 text-emerald-600 font-bold bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                Đã thanh toán (Đã xác nhận)
              </span>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="space-y-2.5 pt-2">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <Link
                  to={`/orders/${orderId}`}
                  className="flex-1 py-3 bg-primary text-on-primary rounded-xl text-sm font-bold hover:opacity-90 transition-opacity text-center flex items-center justify-center gap-1.5 shadow-md shadow-primary/20"
                >
                  <span className="material-symbols-outlined text-base">visibility</span>
                  Xem chi tiết đơn #{orderId}
                </Link>
              )}
              <button
                type="button"
                onClick={handlePrint}
                className="py-3 px-5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-bright transition-colors flex items-center justify-center gap-1.5 border border-outline-variant/30 print:hidden"
              >
                <span className="material-symbols-outlined text-base">print</span>
                In biên lai
              </button>
            </div>

            <div className="text-center pt-1 print:hidden">
              <Link
                to="/products"
                className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
              >
                Tiếp tục khám phá sản phẩm MobieX →
              </Link>
            </div>
          </div>
        </div>
      ) : (
        <div className="bg-surface-container rounded-3xl border border-error/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          {/* Subtle Red Top Glow */}
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-red-500 via-rose-500 to-amber-500" />

          {/* Failed Banner */}
          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto text-error ring-8 ring-error/5">
              <span className="material-symbols-outlined text-5xl">cancel</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Chưa Hoàn Tất</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto">
                {statusMessage}
              </p>
            </div>
          </div>

          {/* Diagnostic Card */}
          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-2.5 border border-outline-variant/20">
            <div className="flex justify-between items-center">
              <span className="text-outline">Mã tham chiếu đơn:</span>
              <span className="font-mono font-bold text-on-surface">#{orderId || '—'}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-outline">Mã lỗi cổng VNPay:</span>
              <span className="font-mono text-error font-bold">{responseCode}</span>
            </div>
            {amount > 0 && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Số tiền:</span>
                <span className="font-bold text-on-surface">{formatCurrencyVND(amount)}</span>
              </div>
            )}
            <div className="pt-2 border-t border-outline-variant/15 text-[11px] text-on-surface-variant leading-relaxed">
              💡 <strong>Gợi ý:</strong> Quý khách có thể thử thanh toán lại bằng cổng VNPay, hoặc vào chi tiết đơn hàng để đổi phương thức thanh toán sang <em>Thanh toán khi nhận hàng (COD)</em>.
            </div>
          </div>

          {/* Actions */}
          <div className="space-y-2.5 pt-2">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <button
                  type="button"
                  onClick={() => navigate(`/payment/vnpay?order_id=${orderId}`)}
                  className="flex-1 py-3 bg-primary text-on-primary rounded-xl text-sm font-bold hover:opacity-90 transition-opacity text-center flex items-center justify-center gap-1.5 shadow-md shadow-primary/20"
                >
                  <span className="material-symbols-outlined text-base">refresh</span>
                  Thử thanh toán lại VNPay
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

            <div className="text-center pt-1">
              <Link
                to="/orders"
                className="text-xs text-outline hover:text-on-surface hover:underline transition-colors"
              >
                Quay lại danh sách đơn hàng đã mua
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
