// src/features/checkout/PaymentReturnPage.jsx
// Handles return redirect from VNPay, verifies HMAC signature with backend, and displays result.

import { useEffect, useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'

export default function PaymentReturnPage() {
  const [searchParams] = useSearchParams()
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
        <h2 className="text-lg font-bold text-on-surface">Đang kiểm tra kết quả giao dịch VNPay...</h2>
      </div>
    )
  }

  const isSuccess = result?.status === 'success' || searchParams.get('vnp_ResponseCode') === '00'
  const orderId = result?.order_id || searchParams.get('vnp_TxnRef')
  const amount = Number(searchParams.get('vnp_Amount')) / 100 || result?.amount

  return (
    <div className="max-w-md mx-auto px-4 py-16 text-center space-y-6">
      {isSuccess ? (
        <div className="bg-surface-container rounded-2xl border border-secondary/30 p-8 space-y-5 shadow-2xl">
          <div className="w-16 h-16 bg-secondary-container/20 rounded-full flex items-center justify-center mx-auto text-secondary">
            <span className="material-symbols-outlined text-4xl">check_circle</span>
          </div>
          <div>
            <h1 className="text-2xl font-bold text-on-surface">Thanh toán thành công!</h1>
            <p className="text-xs text-on-surface-variant mt-1">
              Giao dịch qua cổng VNPay đã được xử lý và ghi nhận thành công.
            </p>
          </div>

          <div className="bg-surface-container-low rounded-xl p-4 text-sm space-y-2 text-left">
            <div className="flex justify-between">
              <span className="text-outline">Mã tham chiếu:</span>
              <span className="font-mono font-semibold text-on-surface">{searchParams.get('vnp_TxnRef') || '—'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-outline">Mã giao dịch VNPay:</span>
              <span className="font-mono text-on-surface">{searchParams.get('vnp_TransactionNo') || '—'}</span>
            </div>
            {amount > 0 && (
              <div className="flex justify-between">
                <span className="text-outline">Số tiền thanh toán:</span>
                <span className="font-bold text-secondary">{formatCurrencyVND(amount)}</span>
              </div>
            )}
            <div className="flex justify-between">
              <span className="text-outline">Ngân hàng:</span>
              <span className="text-on-surface">{searchParams.get('vnp_BankCode') || 'VNPay'}</span>
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            {orderId && (
              <Link
                to={`/orders/${orderId}`}
                className="flex-1 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors text-center"
              >
                Xem đơn hàng
              </Link>
            )}
            <Link
              to="/products"
              className="flex-1 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-bright transition-colors text-center"
            >
              Tiếp tục mua sắm
            </Link>
          </div>
        </div>
      ) : (
        <div className="bg-surface-container rounded-2xl border border-error/30 p-8 space-y-5 shadow-2xl">
          <div className="w-16 h-16 bg-error-container/20 rounded-full flex items-center justify-center mx-auto text-error">
            <span className="material-symbols-outlined text-4xl">cancel</span>
          </div>
          <div>
            <h1 className="text-2xl font-bold text-on-surface">Thanh toán không thành công</h1>
            <p className="text-xs text-on-surface-variant mt-1">
              {error || 'Giao dịch bị hủy hoặc xảy ra lỗi trong quá trình xử lý.'}
            </p>
          </div>

          <div className="flex gap-3 pt-2">
            {orderId ? (
              <Link
                to={`/orders/${orderId}`}
                className="flex-1 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors text-center"
              >
                Xem chi tiết đơn
              </Link>
            ) : (
              <Link
                to="/orders"
                className="flex-1 py-2.5 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors text-center"
              >
                Lịch sử đơn hàng
              </Link>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
