// src/features/checkout/MoMoReturnPage.jsx
// Handles return redirect from MoMo gateway, verifies HMAC-SHA256 signature with backend
// dual-confirmation, and displays an enterprise-grade payment receipt with MoMo branding.

import { useEffect, useState } from 'react'
import { useSearchParams, Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'

// MoMo Result Code mapping to Vietnamese descriptions
const MOMO_RESULT_MESSAGES = {
  '0': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng mua sắm tại MobieX!',
  '1001': 'Giao dịch thất bại: Tài khoản MoMo không đủ số dư để thanh toán.',
  '1002': 'Giao dịch bị từ chối bởi người dùng.',
  '1003': 'Giao dịch đã bị hủy bởi người mua.',
  '1004': 'Số tiền giao dịch vượt quá hạn mức thanh toán trong ngày của Ví MoMo.',
  '1005': 'Giao dịch không thành công: Mã thanh toán đã hết hạn hiệu lực.',
  '1006': 'Giao dịch đã được hủy bỏ theo yêu cầu của quý khách.',
  '1007': 'Giao dịch bị từ chối vì lý do bảo mật tài khoản MoMo.',
  '49': 'Giao dịch đã bị người dùng hủy tại màn hình thanh toán.',
  '9000': 'Giao dịch đã được xác nhận thành công trên hệ thống MoMo.',
}

export default function MoMoReturnPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    // Collect all query parameters from MoMo redirect URL
    const params = Object.fromEntries(searchParams.entries())

    axiosClient
      .get(EP.PAYMENT_MOMO_RETURN, { params })
      .then(({ data }) => {
        setResult(data.data ?? data)
      })
      .catch((err) => {
        if (err.response?.data?.data) {
          setResult(err.response.data.data)
        }
        setError(err.response?.data?.message || err.message || 'Lỗi xác thực chữ ký dữ liệu từ cổng MoMo.')
      })
      .finally(() => setLoading(false))
  }, [searchParams])

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
        <Spinner size="lg" />
        <h2 className="text-lg font-bold text-on-surface">Đang đối soát kết quả giao dịch MoMo...</h2>
        <p className="text-xs text-on-surface-variant max-w-sm">
          MobieX đang kiểm tra tính toàn vẹn chữ ký điện tử HMAC-SHA256 với cổng thanh toán MoMo.
        </p>
      </div>
    )
  }

  const resultCode = searchParams.get('resultCode') ?? (result?.result_code !== undefined ? String(result.result_code) : '-1')
  const isSuccess = (resultCode === '0' || result?.status === 'success') && !error
  const orderId = result?.order_id || null
  const transId = searchParams.get('transId') || result?.trans_id || '—'
  const momoOrderId = searchParams.get('orderId') || result?.order_id_param || '—'
  const amount = Number(searchParams.get('amount') || result?.amount || 0)
  const payType = searchParams.get('payType') || result?.pay_type || 'Ví MoMo / QR Code'
  const statusMessage =
    MOMO_RESULT_MESSAGES[resultCode] ||
    searchParams.get('message') ||
    result?.message ||
    'Trạng thái giao dịch không xác định.'

  const handlePrint = () => window.print()

  return (
    <div className="max-w-xl mx-auto px-4 py-12 space-y-6">
      {/* Brand & Security Header */}
      <div className="flex items-center justify-between gap-3 bg-surface-container/60 border border-outline-variant/30 px-5 py-3 rounded-2xl">
        <div className="flex items-center gap-2">
          <div
            className="w-8 h-8 rounded-lg flex items-center justify-center text-white"
            style={{ background: 'linear-gradient(135deg, #a50064, #d82d8b)' }}
          >
            <span className="material-symbols-outlined text-lg">receipt_long</span>
          </div>
          <div>
            <h2 className="text-sm font-bold text-on-surface">Biên Lai Giao Dịch Điện Tử</h2>
            <p className="text-[11px] text-on-surface-variant">Cổng thanh toán MoMo Sandbox x MobieX</p>
          </div>
        </div>
        <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-surface-container-high text-on-surface-variant">
          ResultCode: {resultCode}
        </span>
      </div>

      {isSuccess ? (
        <div className="bg-surface-container rounded-3xl border border-pink-500/30 p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
          {/* MoMo Magenta Top Bar */}
          <div
            className="absolute top-0 left-0 right-0 h-1.5"
            style={{ background: 'linear-gradient(90deg, #a50064, #d82d8b, #ff4b91)' }}
          />

          {/* Success Banner */}
          <div className="text-center space-y-3 pt-2">
            <div
              className="w-20 h-20 rounded-full flex items-center justify-center mx-auto text-white ring-8 ring-pink-500/10 shadow-lg"
              style={{ background: 'linear-gradient(135deg, #a50064, #d82d8b)' }}
            >
              <span className="material-symbols-outlined text-5xl">check_circle</span>
            </div>
            <div>
              <h1 className="text-2xl font-black text-on-surface">Thanh Toán Thành Công!</h1>
              <p className="text-xs text-on-surface-variant mt-1.5 max-w-md mx-auto">{statusMessage}</p>
            </div>
            {amount > 0 && (
              <div className="pt-2">
                <span className="text-xs text-outline block">Số tiền đã giao dịch qua MoMo</span>
                <span className="text-3xl font-extrabold text-pink-600 dark:text-pink-400">
                  {formatCurrencyVND(amount)}
                </span>
              </div>
            )}
          </div>

          {/* Electronic Receipt Card */}
          <div className="bg-surface-container-low rounded-2xl p-5 text-xs space-y-3 border border-outline-variant/20">
            <div className="flex items-center justify-between pb-2 border-b border-outline-variant/15 text-outline">
              <span className="font-semibold text-on-surface uppercase tracking-wider text-[11px]">Chi tiết giao dịch MoMo</span>
              <span className="text-pink-600 dark:text-pink-400 font-medium flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">verified</span>
                HMAC-SHA256 Đã xác thực
              </span>
            </div>

            {orderId && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Mã đơn hàng MobieX:</span>
                <span className="font-mono font-bold text-on-surface">#{orderId}</span>
              </div>
            )}

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã tham chiếu MoMo:</span>
              <span className="font-mono text-on-surface text-[11px] truncate max-w-[240px] text-right" title={momoOrderId}>
                {momoOrderId}
              </span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Mã GD cổng MoMo (transId):</span>
              <span className="font-mono font-semibold text-on-surface">{transId}</span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-outline">Phương thức:</span>
              <span className="text-on-surface font-medium capitalize">
                {payType}
              </span>
            </div>

            <div className="flex justify-between items-center pt-2 border-t border-outline-variant/15">
              <span className="text-outline">Trạng thái đơn hàng:</span>
              <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
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
                  className="flex-1 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-xl text-sm font-bold transition-all text-center flex items-center justify-center gap-1.5 shadow-md shadow-pink-600/20"
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
                Tiếp tục mua sắm tại MobieX →
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
              <span className="text-outline">Mã lỗi cổng MoMo:</span>
              <span className="font-mono text-error font-bold">{resultCode}</span>
            </div>
            {amount > 0 && (
              <div className="flex justify-between items-center">
                <span className="text-outline">Số tiền:</span>
                <span className="font-bold text-on-surface">{formatCurrencyVND(amount)}</span>
              </div>
            )}
            <div className="pt-2 border-t border-outline-variant/15 text-[11px] text-on-surface-variant leading-relaxed">
              💡 <strong>Gợi ý:</strong> Quý khách có thể bấm &quot;Thử thanh toán lại MoMo&quot; để quét lại mã QR hoặc đổi phương thức thanh toán trong chi tiết đơn hàng.
            </div>
          </div>

          {/* Actions */}
          <div className="space-y-2.5 pt-2">
            <div className="flex flex-col sm:flex-row gap-3">
              {orderId && (
                <button
                  type="button"
                  onClick={() => navigate(`/payment/momo?order_id=${orderId}`)}
                  className="flex-1 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-xl text-sm font-bold transition-all text-center flex items-center justify-center gap-1.5 shadow-md shadow-pink-600/20"
                >
                  <span className="material-symbols-outlined text-base">refresh</span>
                  Thử thanh toán lại MoMo
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
