// src/features/checkout/VNPayPage.jsx
// Route wrapper for VNPay online payment waiting & status synchronization.

import { useSearchParams } from 'react-router-dom'
import PaymentWaitingPanel from './PaymentWaitingPanel'

export default function VNPayPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')

  return <PaymentWaitingPanel gateway="vnpay" orderId={orderId} />
}
