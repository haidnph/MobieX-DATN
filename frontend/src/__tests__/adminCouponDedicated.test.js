import { describe, it, expect } from 'vitest'

// Helper mirroring toDatetimeLocal in CouponForm
function toDatetimeLocal(isoString) {
  if (!isoString) return ''
  try {
    const d = new Date(isoString)
    if (isNaN(d.getTime())) return ''
    const pad = (n) => String(n).padStart(2, '0')
    const yyyy = d.getFullYear()
    const mm = pad(d.getMonth() + 1)
    const dd = pad(d.getDate())
    const hh = pad(d.getHours())
    const min = pad(d.getMinutes())
    return `${yyyy}-${mm}-${dd}T${hh}:${min}`
  } catch {
    return ''
  }
}

// Helper formatting coupon payload mirroring CouponForm
function formatCouponPayload(form) {
  const code = (form.code || '').trim().toUpperCase()
  const discountType = form.discount_type || 'fixed'
  const discountValue = Number(form.discount_value) || 0
  const minOrder = form.min_order_amount ? Number(form.min_order_amount) : 0
  const maxDiscount =
    discountType === 'percent' && form.max_discount
      ? Number(form.max_discount)
      : null
  const usageLimit = form.usage_limit ? Number(form.usage_limit) : null
  const startAt = form.start_at ? form.start_at.replace('T', ' ') + ':00' : null
  const endAt = form.end_at ? form.end_at.replace('T', ' ') + ':00' : null
  const isActive = Boolean(form.is_active)

  return {
    code,
    discount_type: discountType,
    discount_value: discountValue,
    min_order_amount: minOrder,
    max_discount: maxDiscount,
    usage_limit: usageLimit,
    start_at: startAt,
    end_at: endAt,
    is_active: isActive,
  }
}

// Client validation helper
function validateCouponForm(form) {
  const errs = {}
  if (!form.code || !form.code.trim()) {
    errs.code = 'Mã giảm giá không được để trống.'
  } else if (form.code.trim().length < 3) {
    errs.code = 'Mã giảm giá phải có tối thiểu 3 ký tự.'
  }

  const val = Number(form.discount_value)
  if (!form.discount_value || isNaN(val) || val <= 0) {
    errs.discount_value = 'Giá trị giảm phải là số lớn hơn 0.'
  } else if (form.discount_type === 'percent' && val > 100) {
    errs.discount_value = 'Mức giảm theo phần trăm không thể vượt quá 100%.'
  }

  if (form.start_at && form.end_at) {
    const start = new Date(form.start_at).getTime()
    const end = new Date(form.end_at).getTime()
    if (end < start) {
      errs.end_at = 'Thời gian kết thúc phải sau hoặc bằng thời gian bắt đầu.'
    }
  }

  return errs
}

describe('Admin Coupon Dedicated Form & UX Logic', () => {
  it('correctly parses ISO 8601 timestamps into datetime-local format for form inputs', () => {
    const iso1 = '2026-11-20T10:30:00.000000Z'
    const formatted = toDatetimeLocal(iso1)
    expect(formatted).toMatch(/^2026-11-\d{2}T\d{2}:\d{2}$/)

    expect(toDatetimeLocal(null)).toBe('')
    expect(toDatetimeLocal('')).toBe('')
    expect(toDatetimeLocal('invalid-date')).toBe('')
  })

  it('formats percent coupon payload correctly with max_discount and uppercase code', () => {
    const form = {
      code: '  blackfriday20  ',
      discount_type: 'percent',
      discount_value: '20',
      min_order_amount: '2000000',
      max_discount: '500000',
      usage_limit: '50',
      start_at: '2026-11-25T00:00',
      end_at: '2026-11-30T23:59',
      is_active: true,
    }

    const payload = formatCouponPayload(form)
    expect(payload).toEqual({
      code: 'BLACKFRIDAY20',
      discount_type: 'percent',
      discount_value: 20,
      min_order_amount: 2000000,
      max_discount: 500000,
      usage_limit: 50,
      start_at: '2026-11-25 00:00:00',
      end_at: '2026-11-30 23:59:00',
      is_active: true,
    })
  })

  it('omits max_discount when discount_type is fixed', () => {
    const form = {
      code: 'MOBIEX100K',
      discount_type: 'fixed',
      discount_value: '100000',
      min_order_amount: '500000',
      max_discount: '300000', // Even if entered, fixed coupons should nullify max_discount
      usage_limit: '',
      start_at: '',
      end_at: '',
      is_active: true,
    }

    const payload = formatCouponPayload(form)
    expect(payload.discount_type).toBe('fixed')
    expect(payload.discount_value).toBe(100000)
    expect(payload.max_discount).toBeNull()
    expect(payload.usage_limit).toBeNull()
    expect(payload.start_at).toBeNull()
    expect(payload.end_at).toBeNull()
  })

  it('validates required fields, percent limits and chronological dates', () => {
    // 1. Missing code
    const err1 = validateCouponForm({ code: '', discount_type: 'fixed', discount_value: '50000' })
    expect(err1.code).toContain('không được để trống')

    // 2. Short code
    const err2 = validateCouponForm({ code: 'AB', discount_type: 'fixed', discount_value: '50000' })
    expect(err2.code).toContain('tối thiểu 3 ký tự')

    // 3. Percent over 100%
    const err3 = validateCouponForm({ code: 'OVER100', discount_type: 'percent', discount_value: '120' })
    expect(err3.discount_value).toContain('không thể vượt quá 100%')

    // 4. Invalid date range (end before start)
    const err4 = validateCouponForm({
      code: 'VALIDCODE',
      discount_type: 'fixed',
      discount_value: '50000',
      start_at: '2026-12-10T10:00',
      end_at: '2026-12-05T10:00',
    })
    expect(err4.end_at).toContain('phải sau hoặc bằng')

    // 5. Valid form
    const err5 = validateCouponForm({
      code: 'PERFECT',
      discount_type: 'percent',
      discount_value: '15',
      start_at: '2026-12-01T00:00',
      end_at: '2026-12-31T23:59',
    })
    expect(Object.keys(err5)).toHaveLength(0)
  })

  it('correctly maps existing coupon data into edit form state', () => {
    const couponFromApi = {
      id: 42,
      code: 'WELCOME10',
      discount_type: 'percent',
      discount_value: '10.00',
      min_order_amount: '1000000.00',
      max_discount: '500000.00',
      usage_limit: 1000,
      used_count: 15,
      reserved_count: 2,
      start_at: '2026-01-01T00:00:00.000000Z',
      end_at: '2026-12-31T23:59:59.000000Z',
      is_active: 1,
    }

    const editFormState = {
      code: couponFromApi.code,
      discount_type: couponFromApi.discount_type,
      discount_value: String(Number(couponFromApi.discount_value)),
      min_order_amount: String(Number(couponFromApi.min_order_amount)),
      max_discount: String(Number(couponFromApi.max_discount)),
      usage_limit: String(couponFromApi.usage_limit),
      start_at: toDatetimeLocal(couponFromApi.start_at),
      end_at: toDatetimeLocal(couponFromApi.end_at),
      is_active: Boolean(couponFromApi.is_active),
    }

    expect(editFormState.code).toBe('WELCOME10')
    expect(editFormState.discount_type).toBe('percent')
    expect(editFormState.discount_value).toBe('10')
    expect(editFormState.min_order_amount).toBe('1000000')
    expect(editFormState.max_discount).toBe('500000')
    expect(editFormState.usage_limit).toBe('1000')
    expect(editFormState.is_active).toBe(true)
  })
})
