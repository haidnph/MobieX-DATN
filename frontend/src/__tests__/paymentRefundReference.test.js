import { describe, expect, it } from 'vitest'
import { getProviderTransactionReference } from '../features/admin/paymentRefundReference'

describe('provider refund references', () => {
  it('uses the MoMo provider transaction ID instead of the merchant order ID', () => {
    expect(getProviderTransactionReference({
      method: 'momo',
      gateway_transaction_id: 'MX_MM_12_attempt',
      response_data: { transId: 987654321 },
    })).toBe(987654321)
  })

  it('reads the captured provider ID from duplicate capture payloads', () => {
    expect(getProviderTransactionReference({
      method: 'zalopay',
      gateway_transaction_id: '241123_12_abc123',
      response_data: { duplicate_success_payload: { zp_trans_id: 123456789 } },
    })).toBe(123456789)
  })

  it('uses the verified VNPay transaction number', () => {
    expect(getProviderTransactionReference({
      method: 'vnpay',
      gateway_transaction_id: 'VNP-TRANSACTION',
      response_data: { vnp_TransactionNo: '12345' },
    })).toBe('12345')
  })
})
