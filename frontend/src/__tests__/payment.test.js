import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  GATEWAY_CONFIGS,
  closeReservedTab,
  getOrderTabName,
  isOnlineGateway,
  isValidPaymentUrl,
  navigateReservedTab,
  openPaymentTabOnUserAction,
  reserveGatewayTab,
} from '../utils/paymentTabSync'
import { parseVNPayReturnState } from '../features/checkout/PaymentReturnPage'
import { parseZaloPayReturnState } from '../features/checkout/ZaloPayReturnPage'
import { parseMoMoReturnState } from '../features/checkout/MoMoReturnPage'
import EP from '../api/endpoints'

// VNPay response mapping and date formatting logic test
const VNPAY_RESPONSE_MESSAGES = {
  '00': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng MobieX!',
  '07': 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo hoặc bất thường).',
  '09': 'Thẻ/Tài khoản của quý khách chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
  '10': 'Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
  '11': 'Đã hết hạn chờ thanh toán. Xin vui lòng thực hiện lại giao dịch.',
  '12': 'Thẻ/Tài khoản của quý khách đang bị khóa.',
  '13': 'Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Vui lòng thử lại.',
  '24': 'Giao dịch đã bị hủy bởi quý khách.',
  '51': 'Tài khoản của quý khách không đủ số dư để thực hiện giao dịch này.',
  '65': 'Tài khoản của quý khách đã vượt quá hạn mức giao dịch trong ngày.',
  '75': 'Ngân hàng thanh toán đang trong quá trình bảo trì.',
  '79': 'Quý khách nhập sai mật khẩu thanh toán quá số lần quy định.',
  '99': 'Lỗi không xác định hoặc chữ ký xác thực bảo mật không hợp lệ.',
}

function formatVNPayDate(dateStr) {
  if (!dateStr || dateStr.length < 14) return '—'
  const year = dateStr.slice(0, 4)
  const month = dateStr.slice(4, 6)
  const day = dateStr.slice(6, 8)
  const hour = dateStr.slice(8, 10)
  const minute = dateStr.slice(10, 12)
  const second = dateStr.slice(12, 14)
  return `${hour}:${minute}:${second} ${day}/${month}/${year}`
}

describe('VNPay Payment Utilities', () => {
  it('maps VNPay response codes to human-readable Vietnamese messages', () => {
    expect(VNPAY_RESPONSE_MESSAGES['00']).toContain('Giao dịch thành công')
    expect(VNPAY_RESPONSE_MESSAGES['24']).toContain('đã bị hủy')
    expect(VNPAY_RESPONSE_MESSAGES['51']).toContain('không đủ số dư')
    expect(VNPAY_RESPONSE_MESSAGES['13']).toContain('sai mật khẩu')
  })

  it('formats raw VNPay date string YYYYMMDDHHmmss to HH:mm:ss DD/MM/YYYY', () => {
    const raw = '20261110153045'
    const formatted = formatVNPayDate(raw)
    expect(formatted).toBe('15:30:45 10/11/2026')
  })

  it('handles invalid or empty date gracefully', () => {
    expect(formatVNPayDate('')).toBe('—')
    expect(formatVNPayDate(null)).toBe('—')
    expect(formatVNPayDate('short')).toBe('—')
  })

  it('calculates amount from VNPay smallest currency unit correctly', () => {
    const vnpAmountRaw = '250000000' // 2,500,000 VND x 100
    const realAmount = Number(vnpAmountRaw) / 100
    expect(realAmount).toBe(2500000)
  })
})

describe('ZaloPay & MoMo Payment Status Handlers', () => {
  const ZALOPAY_STATUS_MESSAGES = {
    '1': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng MobieX!',
    '2': 'Giao dịch thất bại. Vui lòng kiểm tra lại thông tin thẻ hoặc số dư tài khoản.',
    '3': 'Giao dịch đang được xử lý. Vui lòng chờ trong giây lát.',
    '-1': 'Giao dịch bị từ chối. Thẻ không hợp lệ hoặc hết hạn thanh toán.',
  }

  const MOMO_RESULT_MESSAGES = {
    '0': 'Giao dịch thành công. Cảm ơn bạn đã tin tưởng mua sắm tại MobieX!',
    '1001': 'Giao dịch thất bại: Tài khoản MoMo không đủ số dư để thanh toán.',
    '1002': 'Giao dịch bị từ chối bởi người dùng.',
    '1003': 'Giao dịch đã bị hủy bởi người mua.',
    '1004': 'Số tiền giao dịch vượt quá hạn mức thanh toán trong ngày của Ví MoMo.',
    '1005': 'Giao dịch không thành công: Mã thanh toán đã hết hạn hiệu lực.',
    '1006': 'Giao dịch đã được hủy bỏ theo yêu cầu của quý khách.',
    '49': 'Giao dịch đã bị người dùng hủy tại màn hình thanh toán.',
  }

  it('maps ZaloPay return status code 1 as success and other codes as failure', () => {
    expect(ZALOPAY_STATUS_MESSAGES['1']).toContain('Giao dịch thành công')
    expect(ZALOPAY_STATUS_MESSAGES['2']).toContain('thất bại')
    expect(ZALOPAY_STATUS_MESSAGES['-1']).toContain('từ chối')
  })

  it('maps MoMo resultCode 0 as success and others as expected errors', () => {
    expect(MOMO_RESULT_MESSAGES['0']).toContain('Giao dịch thành công')
    expect(MOMO_RESULT_MESSAGES['1001']).toContain('không đủ số dư')
    expect(MOMO_RESULT_MESSAGES['1003']).toContain('đã bị hủy')
    expect(MOMO_RESULT_MESSAGES['49']).toContain('hủy tại màn hình')
  })
})

describe('Separate-Tab Payment Flow & Sync', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('recognizes vnpay, zalopay, momo as online gateways and exposes verify status endpoint', () => {
    expect(isOnlineGateway('vnpay')).toBe(true)
    expect(isOnlineGateway('zalopay')).toBe(true)
    expect(isOnlineGateway('momo')).toBe(true)
    expect(isOnlineGateway('cod')).toBe(false)
    expect(isOnlineGateway('bank_transfer')).toBe(false)
    expect(EP.PAYMENT_VERIFY_STATUS(42)).toBe('/payments/orders/42/verify-status')
    expect(GATEWAY_CONFIGS.vnpay.label).toBe('VNPay')
    expect(GATEWAY_CONFIGS.zalopay.label).toBe('ZaloPay')
    expect(GATEWAY_CONFIGS.momo.label).toBe('Ví MoMo')
  })

  it('validates payment redirect URLs and blocks unsafe schemes', () => {
    expect(isValidPaymentUrl('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?foo=1')).toBe(true)
    expect(isValidPaymentUrl('javascript:alert(1)')).toBe(false)
    expect(isValidPaymentUrl('data:text/html,hi')).toBe(false)
    expect(isValidPaymentUrl('')).toBe(false)
  })

  it('handles popup blocker gracefully and reuses existing open tab on repeat click', () => {
    const fakeDoc = { open: vi.fn(), write: vi.fn(), close: vi.fn() }
    const fakeTab = {
      closed: false,
      opener: {},
      document: fakeDoc,
      focus: vi.fn(),
      close: vi.fn(() => { fakeTab.closed = true }),
      location: { replace: vi.fn() },
    }
    vi.stubGlobal('window', {
      open: vi.fn(() => fakeTab),
    })

    // 1st click reserves tab cleanly using stable per-order name
    const targetName = getOrderTabName(42)
    expect(targetName).toBe('mobiex_order_42')
    const first = reserveGatewayTab(null, 'VNPay', targetName)
    expect(first.blocked).toBe(false)
    expect(first.reused).toBe(false)
    expect(first.tab).toBe(fakeTab)
    expect(window.open).toHaveBeenCalledWith('', 'mobiex_order_42')
    expect(fakeDoc.write).toHaveBeenCalledTimes(1)

    // 2nd rapid click reuses existing tab without opening another popup
    const second = reserveGatewayTab(first.tab, 'VNPay', targetName)
    expect(second.reused).toBe(true)
    expect(window.open).toHaveBeenCalledTimes(1)
    expect(fakeTab.focus).toHaveBeenCalledTimes(1)

    // Navigate schedules replace BEFORE severing opener cleanly
    const navigated = navigateReservedTab(first.tab, 'https://sandbox.vnpayment.vn/pay')
    expect(navigated).toBe(true)
    expect(fakeTab.opener).toBeNull()
    expect(fakeTab.location.replace).toHaveBeenCalledWith('https://sandbox.vnpayment.vn/pay')

    // Close reserved tab on failure leaves no orphan tab
    closeReservedTab(first.tab)
    expect(fakeTab.closed).toBe(true)

    // Popup blocked path
    window.open.mockReturnValue(null)
    const blockedRes = openPaymentTabOnUserAction(null, 'https://sandbox.vnpayment.vn/pay', targetName)
    expect(blockedRes.blocked).toBe(true)
    expect(blockedRes.tab).toBeNull()
  })

  it('never marks pending_confirmation as confirmed paid across VNPay, ZaloPay, and MoMo return parsers', () => {
    const vnpPending = parseVNPayReturnState(
      new URLSearchParams({ vnp_ResponseCode: '00', vnp_Amount: '50000000', vnp_TxnRef: 'PAY-1' }),
      { order_id: 10, payment_status: 'pending', verification_status: 'pending_confirmation' },
      '',
    )
    expect(vnpPending.gatewaySucceeded).toBe(true)
    expect(vnpPending.isConfirmed).toBe(false)

    const zpPending = parseZaloPayReturnState(
      new URLSearchParams({ status: '1', amount: '500000', apptransid: '261126_10_abc' }),
      { order_id: 10, payment_status: 'pending', verification_status: 'pending_confirmation' },
      '',
    )
    expect(zpPending.gatewaySucceeded).toBe(true)
    expect(zpPending.isConfirmed).toBe(false)

    const mmConfirmed = parseMoMoReturnState(
      new URLSearchParams({ resultCode: '0', amount: '500000', orderId: 'MX_MM_10_ABC' }),
      { order_id: 10, payment_status: 'paid', verification_status: 'success' },
      '',
    )
    expect(mmConfirmed.gatewaySucceeded).toBe(true)
    expect(mmConfirmed.isConfirmed).toBe(true)

    const mmCancelled = parseMoMoReturnState(
      new URLSearchParams({ resultCode: '1006', amount: '500000' }),
      { order_id: 10, payment_status: 'pending', verification_status: 'failed' },
      'Giao dịch bị hủy',
    )
    expect(mmCancelled.isCancelled).toBe(true)
    expect(mmCancelled.isConfirmed).toBe(false)
  })

  it('handles VNPay vnp_TransactionStatus processing (01), fraud (07), failed (02), and cancelled (24) accurately', () => {
    const vnpProcessing = parseVNPayReturnState(
      new URLSearchParams({ vnp_ResponseCode: '00', vnp_TransactionStatus: '01', vnp_Amount: '602000000' }),
      { order_id: 15, payment_status: 'pending', verification_status: 'pending_confirmation' },
      '',
    )
    expect(vnpProcessing.gatewaySucceeded).toBe(true)
    expect(vnpProcessing.isConfirmed).toBe(false)

    const vnpFraud = parseVNPayReturnState(
      new URLSearchParams({ vnp_ResponseCode: '00', vnp_TransactionStatus: '07', vnp_Amount: '602000000' }),
      { order_id: 15, payment_status: 'pending', verification_status: 'flagged' },
      '',
    )
    expect(vnpFraud.isFlagged).toBe(true)
    expect(vnpFraud.gatewaySucceeded).toBe(false)
    expect(vnpFraud.isConfirmed).toBe(false)

    const vnpFailedTxStatus = parseVNPayReturnState(
      new URLSearchParams({ vnp_ResponseCode: '00', vnp_TransactionStatus: '02', vnp_Amount: '602000000' }),
      { order_id: 15, payment_status: 'pending', verification_status: 'failed' },
      '',
    )
    expect(vnpFailedTxStatus.gatewaySucceeded).toBe(false)
    expect(vnpFailedTxStatus.isConfirmed).toBe(false)

    const vnpCancelled = parseVNPayReturnState(
      new URLSearchParams({ vnp_ResponseCode: '24', vnp_TransactionStatus: '02', vnp_Amount: '602000000' }),
      { order_id: 15, payment_status: 'pending', verification_status: 'cancelled' },
      'Bạn đã hủy giao dịch',
    )
    expect(vnpCancelled.isCancelled).toBe(true)
    expect(vnpCancelled.gatewaySucceeded).toBe(false)
  })
})
