export function getProviderTransactionReference(payment) {
  const payload = payment?.response_data ?? {}
  const duplicateCapture = payload.duplicate_success_payload ?? {}
  const method = String(payment?.method ?? '').toLowerCase()

  if (method === 'momo') {
    return payload.transId ?? duplicateCapture.transId ?? null
  }

  if (method === 'zalopay') {
    return payload.zp_trans_id ?? duplicateCapture.zp_trans_id ?? null
  }

  if (method === 'vnpay') {
    return payload.vnp_TransactionNo ?? duplicateCapture.vnp_TransactionNo ?? payment?.gateway_transaction_id ?? null
  }

  return payment?.gateway_transaction_id ?? null
}
