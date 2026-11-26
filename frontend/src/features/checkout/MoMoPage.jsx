// src/features/checkout/MoMoPage.jsx
// Route wrapper for Ví MoMo online payment waiting & status synchronization.

import { useSearchParams } from 'react-router-dom'
import PaymentWaitingPanel from './PaymentWaitingPanel'

export default function MoMoPage() {
  const [searchParams] = useSearchParams()
  const orderId = searchParams.get('order_id')

  return <PaymentWaitingPanel gateway="momo" orderId={orderId} />
}
