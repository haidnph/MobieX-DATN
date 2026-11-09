import { describe, it, expect } from 'vitest'

// Unit-test the cartBadgeCount logic in isolation
function computeCartBadge(cartItems) {
  const count = cartItems.length
  return count > 99 ? '99+' : count
}

describe('Cart Badge Count (distinct line items)', () => {
  it('shows 0 when cart is empty', () => {
    expect(computeCartBadge([])).toBe(0)
  })

  it('shows 2 for 2 different products regardless of quantities', () => {
    const items = [
      { id: 1, product_variant_id: 1, quantity: 5 },
      { id: 2, product_variant_id: 2, quantity: 10 },
    ]
    expect(computeCartBadge(items)).toBe(2)
  })

  it('shows 1 for single product with large quantity', () => {
    const items = [{ id: 1, product_variant_id: 1, quantity: 99 }]
    expect(computeCartBadge(items)).toBe(1)
  })

  it('shows 99+ when cart has more than 99 distinct items', () => {
    const items = Array.from({ length: 100 }, (_, i) => ({
      id: i + 1,
      product_variant_id: i + 1,
      quantity: 1,
    }))
    expect(computeCartBadge(items)).toBe('99+')
  })

  it('shows 99 (not 99+) for exactly 99 items', () => {
    const items = Array.from({ length: 99 }, (_, i) => ({
      id: i + 1,
      product_variant_id: i + 1,
      quantity: 1,
    }))
    expect(computeCartBadge(items)).toBe(99)
  })
})
