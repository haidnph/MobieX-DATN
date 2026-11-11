import { describe, it, expect } from 'vitest'

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
