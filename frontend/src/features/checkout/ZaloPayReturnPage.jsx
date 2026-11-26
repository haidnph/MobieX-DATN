// src/features/checkout/ZaloPayReturnPage.jsx
// Handles ZaloPay return URL callback cleanly with server-side checksum & status verification.

import EP from '../../api/endpoints'
import PaymentReturnView from './PaymentReturnView'

const ZALOPAY_STATUS_MESSAGES = {
  '1': 'Cổng ZaloPay đã tiếp nhận giao dịch thành công.',
  '2': 'Giao dịch thất bại. Vui lòng kiểm tra lại thông tin thanh toán hoặc số dư tài khoản.',
  '3': 'Giao dịch đang được ZaloPay xử lý. Hệ thống sẽ cập nhật kết quả sau ít phút.',
  '-1': 'Giao dịch bị từ chối hoặc đã bị hủy bởi người dùng.',
}

export function parseZaloPayReturnState(searchParams, result, error) {
  const statusCode = searchParams.get('status') ?? (result?.status !== undefined ? String(result.status) : '-1')
  const gatewaySucceeded = (statusCode === '1' || result?.verification_status === 'pending_confirmation' || result?.verification_status === 'success') && !error
  const isConfirmed =
    !error && (result?.payment_status === 'paid' || result?.verification_status === 'success')
  const isCancelled = statusCode === '-1'

  const orderId = result?.order_id || null
  const orderCode = result?.order_code || null
  const appTransId = searchParams.get('apptransid') || result?.app_trans_id || '—'
  const amount = Number(searchParams.get('amount') || result?.amount || 0)
  const bankCode = searchParams.get('bankcode') || result?.bank_code || 'ZALOPAY'

  const details = [
    { label: 'Mã giao dịch ZaloPay', value: appTransId },
    { label: 'Kênh thanh toán', value: bankCode },
  ]

  return {
    orderId,
    orderCode,
    amount,
    transactionId: appTransId,
    responseCodeLabel: `Trạng thái: ${statusCode}`,
    gatewaySucceeded,
    isConfirmed,
    isCancelled,
    statusMessage: error || ZALOPAY_STATUS_MESSAGES[statusCode] || result?.message || 'Giao dịch ZaloPay không thành công.',
    details,
  }
}

export default function ZaloPayReturnPage() {
  return (
    <PaymentReturnView
      gateway="zalopay"
      returnEndpoint={EP.PAYMENT_ZALOPAY_RETURN}
      parseReturnState={parseZaloPayReturnState}
    />
  )
}
