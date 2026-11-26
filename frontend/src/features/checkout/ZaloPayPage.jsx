// src/features/checkout/ZaloPayPage.jsx
// Route wrapper for ZaloPay online payment waiting & status synchronization.

import { useSearchParams } from 'react-router-dom'
import PaymentWaitingPanel from './PaymentWaitingPanel'

export default function ZaloPayPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')

  return <PaymentWaitingPanel gateway="zalopay" orderId={orderId} />
}
