// src/features/checkout/VNPayPage.jsx
// Initiates VNPay gateway redirect by requesting the signed payment URL from backend.

import { useEffect, useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Spinner from '../../components/common/Spinner'

export default function VNPayPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')
  const navigate = useNavigate()

  const [error, setError] = useState(null)

  useEffect(() => {
    if (!orderId) {
      setError('Không tìm thấy mã đơn hàng cần thanh toán.')
      return
    }

    axiosClient
      .post(EP.PAYMENT_VNPAY_CREATE, { order_id: Number(orderId) })
      .then(({ data }) => {
        const payload = data.data ?? data
        if (payload.payment_url) {
          // Redirect browser to VNPay gateway
          window.location.href = payload.payment_url
        } else {
          setError('Không nhận được liên kết thanh toán từ cổng VNPay.')
        }
      })
      .catch((err) => {
        setError(err.message || 'Lỗi khi kết nối cổng thanh toán VNPay.')
      })
  }, [orderId])

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

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
      <Spinner size="lg" />
      <h2 className="text-lg font-bold text-on-surface">Đang chuyển hướng tới cổng VNPay...</h2>
      <p className="text-xs text-on-surface-variant max-w-xs">
        Vui lòng không đóng trình duyệt hoặc làm mới trang trong quá trình điều hướng an toàn.
      </p>
    </div>
  )
}
