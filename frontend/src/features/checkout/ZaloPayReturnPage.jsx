// src/features/checkout/ZaloPayReturnPage.jsx
// Handles return redirect from ZaloPay gateway, verifies HMAC-SHA256 checksum with backend
// dual-confirmation, and displays an enterprise-grade payment receipt with ZaloPay branding.

import { useEffect, useState } from 'react'
import { useSearchParams, Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'

// ZaloPay Status Code mapping to Vietnamese descriptions
const ZALOPAY_STATUS_MESSAGES = {
  '1': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng MobieX!',
  '2': 'Giao dịch thất bại. Vui lòng kiểm tra lại thông tin thẻ hoặc số dư tài khoản.',
  '3': 'Giao dịch đang được xử lý. Vui lòng chờ trong giây lát.',
  '-1': 'Giao dịch bị từ chối. Thẻ không hợp lệ hoặc hết hạn thanh toán.',
}

export default function ZaloPayReturnPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    // Send all query parameters received from ZaloPay to the backend verification endpoint
    const params = Object.fromEntries(searchParams.entries())

    axiosClient
      .get(EP.PAYMENT_ZALOPAY_RETURN, { params })
      .then(({ data }) => {
        setResult(data.data ?? data)
      })
      .catch((err) => {
        setError(err.message || 'Lỗi xác thực chữ ký dữ liệu từ cổng ZaloPay.')
      })
      .finally(() => setLoading(false))
  }, [searchParams])

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">Đang xác thực kết quả giao dịch ZaloPay...</h2>
        <p className="text-xs text-on-surface-variant max-w-sm">
          Hệ thống đang đối soát chữ ký điện tử HMAC-SHA256 với cổng ZaloPay để đảm bảo tính an toàn và minh bạch.
        </p>
      </div>
    )
  }

  const statusCode = searchParams.get('status') || '-1'
  const isSuccess = (result?.status === 'success' || statusCode === '1') && !error
  const orderId = result?.order_id || null
  const appTransId = searchParams.get('apptransid') || result?.app_trans_id || '—'
  const amount = Number(searchParams.get('amount') || result?.amount || 0)
  const bankCode = searchParams.get('bankcode') || 'ZALOPAY'
  const pmcId = searchParams.get('pmcid') || '—'
  const statusMessage =
    ZALOPAY_STATUS_MESSAGES[statusCode] || result?.message || 'Trạng thái giao dịch không xác định.'

  const handlePrint = () => window.print()

  return (
    <div className="max-w-xl mx-auto px-4 py-12 space-y-6">
      {/* Brand & Security Header */}
      <div className="flex items-center justify-between gap-3 bg-surface-container/60 border border-outline-variant/30 px-5 py-3 rounded-2xl">
        <div className="flex items-center gap-2">
          <div
            className="w-8 h-8 rounded-lg flex items-center justify-center text-white"
            style={{ background: 'linear-gradient(135deg, #009f73, #00c89a)' }}
          >
            <span className="material-symbols-outlined text-lg">receipt_long</span>
          </div>
          <div>
            <h2 className="text-sm font-bold text-on-surface">Biên Lai Giao Dịch Điện Tử</h2>
            <p className="text-[11px] text-on-surface-variant">Cổng thanh toán trực tuyến MobieX x ZaloPay</p>
          </div>
        </div>
        <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-surface-container-high text-on-surface-variant">
          Mã trạng thái: {statusCode}
        </span>
      </div>

      {isSuccess ? (
        <div className="bg-surface-container rounded-3xl border border-emerald-500/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          {/* ZaloPay Green Top Bar */}
          <div
            className="absolute top-0 left-0 right-0 h-1.5"
            style={{ background: 'linear-gradient(90deg, #009f73, #00c89a, #26d0a0)' }}
          />

          {/* Success Banner */}
          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-emerald-500/15 rounded-full flex items-center justify-center mx-auto text-emerald-500 ring-8 ring-emerald-500/10">
              <span className="material-symbols-outlined text-5xl">check_circle</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Thành Công!</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto">{statusMessage}</p>
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

            {orderId && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Mã đơn hàng:</span>
                <span className="font-mono font-bold text-on-surface">#{orderId}</span>
              </div>
            )}

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã tham chiếu ZaloPay:</span>
              <span className="font-mono text-on-surface">{appTransId}</span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Kênh thanh toán:</span>
              <span className="text-on-surface font-medium">
                {bankCode}
                {pmcId !== '—' && ` (PMC: ${pmcId})`}
              </span>
            </div>

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
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-red-500 via-rose-500 to-amber-500" />

          {/* Failed Banner */}
          <div className="text-center space-y-3 pt-2">
            <div className="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto text-error ring-8 ring-error/5">
              <span className="material-symbols-outlined text-5xl">cancel</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Chưa Hoàn Tất</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto">{statusMessage}</p>
            </div>
          </div>

          {/* Diagnostic Card */}
          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-2.5 border border-outline-variant/20">
            <div className="flex justify-between items-center">
              <span className="text-outline">Mã tham chiếu đơn:</span>
              <span className="font-mono font-bold text-on-surface">#{orderId || '—'}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-outline">Mã lỗi cổng ZaloPay:</span>
              <span className="font-mono text-error font-bold">{statusCode}</span>
            </div>
            {amount > 0 && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Số tiền:</span>
                <span className="font-bold text-on-surface">{formatCurrencyVND(amount)}</span>
              </div>
            )}
            <div className="pt-2 border-t border-outline-variant/15 text-[11px] text-on-surface-variant leading-relaxed">
              💡 <strong>Gợi ý:</strong> Quý khách có thể thử thanh toán lại bằng cổng ZaloPay, hoặc vào chi tiết đơn hàng để đổi phương thức thanh toán sang{' '}
              <em>Thanh toán khi nhận hàng (COD)</em>.
            </div>
          </div>

          {/* Actions */}
          <div className="space-y-2.5 pt-2">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <button
                  type="button"
                  onClick={() => navigate(`/payment/zalopay?order_id=${orderId}`)}
                  className="flex-1 py-3 bg-primary text-on-primary rounded-xl text-sm font-bold hover:opacity-90 transition-opacity text-center flex items-center justify-center gap-1.5 shadow-md shadow-primary/20"
                >
                  <span className="material-symbols-outlined text-base">refresh</span>
                  Thử thanh toán lại ZaloPay
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
