import { describe, it, expect } from 'vitest'

// Pure functions mirror the compare logic
function addCompareItem(currentItems, product, maxLimit = 4) {
  if (!product || !product.id) return { items: currentItems, added: false, reason: 'invalid' }
  if (currentItems.some((item) => Number(item.id) === Number(product.id))) {
    return { items: currentItems, added: false, reason: 'exists' }
  }
  if (currentItems.length >= maxLimit) {
    return { items: currentItems, added: false, reason: 'max_reached' }
  }
  return {
    items: [...currentItems, { id: product.id, name: product.name }],
    added: true,
  }
}

function detectSpecDifference(products, specCode) {
  if (products.length < 2) return false
  const values = products.map((p) => {
    const spec = p.specifications?.find((s) => s.definition?.code === specCode)
    return spec?.value_text ?? spec?.numeric_value ?? '—'
  })
  return values.some((val) => val !== values[0])
}

describe('Product Compare Logic & Limit Safeguards', () => {
  it('adds a product to empty compare list', () => {
    const res = addCompareItem([], { id: 10, name: 'iPhone 16 Pro' })
    expect(res.added).toBe(true)
    expect(res.items).toHaveLength(1)
    expect(res.items[0].id).toBe(10)
  })

  it('rejects duplicate products in compare list', () => {
    const initial = [{ id: 10, name: 'iPhone 16 Pro' }]
    const res = addCompareItem(initial, { id: 10, name: 'iPhone 16 Pro' })
    expect(res.added).toBe(false)
    expect(res.reason).toBe('exists')
    expect(res.items).toHaveLength(1)
  })

  it('enforces maximum 4 products in comparison matrix', () => {
    let current = []
    for (let i = 1; i <= 4; i++) {
      current = addCompareItem(current, { id: i, name: `Phone ${i}` }).items
    }
    expect(current).toHaveLength(4)

    // Attempting 5th product
    const res = addCompareItem(current, { id: 5, name: 'Phone 5' })
    expect(res.added).toBe(false)
    expect(res.reason).toBe('max_reached')
    expect(res.items).toHaveLength(4)
  })

  it('correctly detects differences between products specifications', () => {
    const products = [
      {
        id: 1,
        specifications: [
          { definition: { code: 'chipset' }, value_text: 'Apple A18' },
        ],
      },
      {
        id: 2,
        specifications: [
          { definition: { code: 'chipset' }, value_text: 'Snapdragon 8 Gen 3' },
        ],
      },
    ]

    expect(detectSpecDifference(products, 'chipset')).toBe(true)
  })

  it('returns false when all compared devices share identical specification', () => {
    const products = [
      {
        id: 1,
        specifications: [
          { definition: { code: 'water_resistance' }, value_text: 'IP68' },
        ],
      },
      {
        id: 2,
        specifications: [
          { definition: { code: 'water_resistance' }, value_text: 'IP68' },
        ],
      },
    ]

    expect(detectSpecDifference(products, 'water_resistance')).toBe(false)
  })
})
