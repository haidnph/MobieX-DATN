// src/features/checkout/PaymentReturnPage.jsx
// Handles VNPay return URL callback cleanly with server-side signature & status verification.

import EP from '../../api/endpoints'
import PaymentReturnView from './PaymentReturnView'

const VNPAY_RESPONSE_MESSAGES = {
  '00': 'Cổng VNPay đã ghi nhận giao dịch thành công.',
  '07': 'Trừ tiền thành công. Giao dịch đang được ngân hàng rà soát bổ sung.',
  '09': 'Thẻ/Tài khoản của quý khách chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
  '10': 'Quý khách xác thực thông tin thẻ/tài khoản không đúng quá số lần quy định.',
  '11': 'Đã hết hạn chờ thanh toán. Xin vui lòng thực hiện lại giao dịch.',
  '12': 'Thẻ/Tài khoản của quý khách đang bị khóa.',
  '13': 'Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Vui lòng thử lại.',
  '24': 'Giao dịch đã bị hủy theo yêu cầu của quý khách.',
  '51': 'Tài khoản của quý khách không đủ số dư để thực hiện giao dịch này.',
  '65': 'Tài khoản của quý khách đã vượt quá hạn mức giao dịch trong ngày.',
  '75': 'Ngân hàng thanh toán đang trong quá trình bảo trì.',
  '79': 'Quý khách nhập sai mật khẩu thanh toán quá số lần quy định.',
  '99': 'Giao dịch không thành công hoặc chữ ký xác thực bảo mật không hợp lệ.',
}

export function formatVNPayDate(dateStr) {
  if (!dateStr || dateStr.length < 14) return '—'
  return `${dateStr.slice(8, 10)}:${dateStr.slice(10, 12)}:${dateStr.slice(12, 14)} ${dateStr.slice(6, 8)}/${dateStr.slice(4, 6)}/${dateStr.slice(0, 4)}`
}

export function parseVNPayReturnState(searchParams, result, error) {
  const responseCode = searchParams.get('vnp_ResponseCode') || '99'
  const gatewaySucceeded = responseCode === '00' && !error
  const isConfirmed =
    !error && (result?.payment_status === 'paid' || result?.verification_status === 'success')
  const isCancelled = responseCode === '24'

  const orderId = result?.order_id || null
  const orderCode = result?.order_code || null
  const amount = Number(searchParams.get('vnp_Amount')) / 100 || Number(result?.amount || 0)
  const txnNo = searchParams.get('vnp_TransactionNo') || result?.transaction_no || '—'
  const txnRef = searchParams.get('vnp_TxnRef') || orderCode || '—'
  const bankCode = searchParams.get('vnp_BankCode') || '—'
  const cardType = searchParams.get('vnp_CardType') || ''
  const payDate = formatVNPayDate(searchParams.get('vnp_PayDate'))

  const details = [
    { label: 'Mã tham chiếu VNPay', value: txnRef },
    ...(txnNo !== '—' ? [{ label: 'Mã GD tại VNPay', value: txnNo }] : []),
    ...(bankCode !== '—' ? [{ label: 'Kênh thanh toán', value: cardType ? `${bankCode} (${cardType})` : bankCode }] : []),
    ...(payDate !== '—' ? [{ label: 'Thời gian giao dịch', value: payDate }] : []),
  ]

  return {
    orderId,
    orderCode,
    amount,
    transactionId: txnNo !== '—' ? txnNo : txnRef,
    responseCodeLabel: `Mã phản hồi: ${responseCode}`,
    gatewaySucceeded,
    isConfirmed,
    isCancelled,
    statusMessage: error || result?.message || VNPAY_RESPONSE_MESSAGES[responseCode] || 'Giao dịch không thành công.',
    details,
  }
}

export default function PaymentReturnPage() {
  return (
    <PaymentReturnView
      gateway="vnpay"
      returnEndpoint={EP.PAYMENT_VNPAY_RETURN}
      parseReturnState={parseVNPayReturnState}
    />
  )
}
