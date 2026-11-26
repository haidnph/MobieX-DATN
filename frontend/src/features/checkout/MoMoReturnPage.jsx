// src/features/checkout/MoMoReturnPage.jsx
// Handles Ví MoMo return URL callback cleanly with server-side HMAC signature & status verification.

import EP from '../../api/endpoints'
import PaymentReturnView from './PaymentReturnView'

const MOMO_RESULT_MESSAGES = {
  '0': 'Cổng Ví MoMo đã tiếp nhận giao dịch thành công.',
  '1001': 'Giao dịch thất bại: Tài khoản MoMo không đủ số dư để thanh toán.',
  '1002': 'Giao dịch bị từ chối bởi nhà phát hành.',
  '1003': 'Giao dịch đã bị hủy bởi người mua.',
  '1004': 'Số tiền giao dịch vượt quá hạn mức thanh toán trong ngày của Ví MoMo.',
  '1005': 'Giao dịch không thành công: Liên kết thanh toán đã hết hạn.',
  '1006': 'Giao dịch đã bị hủy theo yêu cầu của quý khách.',
  '1007': 'Giao dịch bị từ chối vì lý do bảo mật tài khoản MoMo.',
  '49': 'Giao dịch đã bị hủy tại màn hình thanh toán MoMo.',
  '9000': 'Giao dịch đã được ủy quyền trên Ví MoMo.',
}

export function parseMoMoReturnState(searchParams, result, error) {
  const resultCode =
    searchParams.get('resultCode') ??
    (result?.result_code !== undefined ? String(result.result_code) : '-1')
  const gatewaySucceeded = resultCode === '0' && !error
  const isConfirmed =
    !error && (result?.payment_status === 'paid' || result?.verification_status === 'success')
  const isCancelled = ['49', '1003', '1006'].includes(resultCode)

  const orderId = result?.order_id || null
  const orderCode = result?.order_code || null
  const transId = searchParams.get('transId') || result?.trans_id || '—'
  const momoOrderId = searchParams.get('orderId') || result?.order_id_param || '—'
  const amount = Number(searchParams.get('amount') || result?.amount || 0)
  const payType = searchParams.get('payType') || result?.pay_type || 'Ví MoMo'

  const details = [
    { label: 'Mã tham chiếu MoMo', value: momoOrderId },
    ...(transId !== '—' ? [{ label: 'Mã giao dịch MoMo', value: transId }] : []),
    { label: 'Phương thức', value: payType },
  ]

  return {
    orderId,
    orderCode,
    amount,
    transactionId: transId !== '—' ? transId : momoOrderId,
    responseCodeLabel: `ResultCode: ${resultCode}`,
    gatewaySucceeded,
    isConfirmed,
    isCancelled,
    statusMessage:
      error ||
      MOMO_RESULT_MESSAGES[resultCode] ||
      searchParams.get('message') ||
      result?.message ||
      'Giao dịch MoMo không thành công.',
    details,
  }
}

export default function MoMoReturnPage() {
  return (
    <PaymentReturnView
      gateway="momo"
      returnEndpoint={EP.PAYMENT_MOMO_RETURN}
      parseReturnState={parseMoMoReturnState}
    />
  )
}
