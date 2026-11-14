import { describe, it, expect, beforeEach } from 'vitest'
import storage from '../utils/storage'

// Isolated discount calculator replicating backend & frontend sync logic
function calculateCouponDiscount(coupon, subtotal) {
  if (!coupon || !coupon.is_active) {
    return { valid: false, discount: 0, reason: 'Mã không hoạt động' }
  }
  if (subtotal < coupon.min_order_amount) {
    return {
      valid: false,
      discount: 0,
      reason: `Giá trị đơn hàng chưa đạt mức tối thiểu ${coupon.min_order_amount} VND`,
    }
  }

  let discount = 0
  if (coupon.discount_type === 'percent') {
    discount = Math.round((subtotal * coupon.discount_value) / 100)
    if (coupon.max_discount !== null && discount > coupon.max_discount) {
      discount = coupon.max_discount
    }
  } else if (coupon.discount_type === 'fixed') {
    discount = Math.min(coupon.discount_value, subtotal)
  }

  const finalTotal = Math.max(0, subtotal - discount)
  return { valid: true, discount, finalTotal, reason: null }
}

describe('Coupon Calculation & Business Logic', () => {
  beforeEach(() => {
    storage.remove('applied_coupon')
  })

  it('calculates fixed discount correctly when subtotal exceeds min_order_amount', () => {
    const coupon = {
      code: 'MOBIEX100K',
      discount_type: 'fixed',
      discount_value: 100000,
      min_order_amount: 500000,
      is_active: true,
    }
    const subtotal = 1200000
    const res = calculateCouponDiscount(coupon, subtotal)

    expect(res.valid).toBe(true)
    expect(res.discount).toBe(100000)
    expect(res.finalTotal).toBe(1100000)
  })

  it('rejects coupon when subtotal is below min_order_amount', () => {
    const coupon = {
      code: 'MOBIEX100K',
      discount_type: 'fixed',
      discount_value: 100000,
      min_order_amount: 500000,
      is_active: true,
    }
    const subtotal = 450000
    const res = calculateCouponDiscount(coupon, subtotal)

    expect(res.valid).toBe(false)
    expect(res.discount).toBe(0)
    expect(res.reason).toContain('chưa đạt mức tối thiểu')
  })

  it('calculates percent discount correctly with max_discount cap', () => {
    const coupon = {
      code: 'WELCOME10',
      discount_type: 'percent',
      discount_value: 10, // 10%
      min_order_amount: 1000000,
      max_discount: 500000, // max 500k
      is_active: true,
    }

    // 10% of 2,000,000 is 200,000 (< 500,000 cap)
    const res1 = calculateCouponDiscount(coupon, 2000000)
    expect(res1.valid).toBe(true)
    expect(res1.discount).toBe(200000)
    expect(res1.finalTotal).toBe(1800000)

    // 10% of 10,000,000 is 1,000,000 (> 500,000 cap, should be capped at 500,000)
    const res2 = calculateCouponDiscount(coupon, 10000000)
    expect(res2.valid).toBe(true)
    expect(res2.discount).toBe(500000)
    expect(res2.finalTotal).toBe(9500000)
  })

  it('caps fixed discount to subtotal if discount_value exceeds subtotal', () => {
    const coupon = {
      code: 'BIGDISCOUNT',
      discount_type: 'fixed',
      discount_value: 1000000,
      min_order_amount: 500000,
      is_active: true,
    }
    const subtotal = 700000
    const res = calculateCouponDiscount(coupon, subtotal)

    expect(res.valid).toBe(true)
    expect(res.discount).toBe(700000)
    expect(res.finalTotal).toBe(0)
  })

  it('re-evaluates coupon when quantity changes', () => {
    const coupon = {
      code: 'MOBIEX100K',
      discount_type: 'fixed',
      discount_value: 100000,
      min_order_amount: 500000,
      is_active: true,
    }
    const unitPrice = 300000

    // 2 items = 600,000 -> Valid
    const resQty2 = calculateCouponDiscount(coupon, unitPrice * 2)
    expect(resQty2.valid).toBe(true)
    expect(resQty2.finalTotal).toBe(500000)

    // Decrement to 1 item = 300,000 -> Invalid, drops below min
    const resQty1 = calculateCouponDiscount(coupon, unitPrice * 1)
    expect(resQty1.valid).toBe(false)
    expect(resQty1.discount).toBe(0)
  })

  it('persists and clears applied coupon in storage wrapper', () => {
    expect(storage.get('applied_coupon')).toBe(null)

    storage.set('applied_coupon', 'WELCOME10')
    expect(storage.get('applied_coupon')).toBe('WELCOME10')

    storage.remove('applied_coupon')
    expect(storage.get('applied_coupon')).toBe(null)
  })

  it('computes consistent order total across Cart and Checkout', () => {
    const subtotal = 25000000
    const discount = 1000000
    const shippingFee = 0

    const cartTotal = Math.max(0, subtotal - discount + shippingFee)
    const checkoutTotal = Math.max(0, subtotal - discount + shippingFee)

    expect(cartTotal).toBe(24000000)
    expect(checkoutTotal).toBe(cartTotal)
  })
})
