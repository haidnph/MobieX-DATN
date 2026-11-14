import { describe, it, expect } from 'vitest'
import {
  getProductThumbnail,
  FALLBACK_PHONE_SVG,
  MAX_COMPARE_ITEMS,
} from '../contexts/CompareContext'
import {
  COMPARE_UI,
  SPEC_GROUPS,
} from '../features/catalog/ProductComparePage'

// Pure functions mirror the compare logic
function addCompareItem(currentItems, product, maxLimit = MAX_COMPARE_ITEMS) {
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

  it('reliably extracts thumbnails and prevents broken placeholder loops', () => {
    // 1. primary_image_url
    expect(getProductThumbnail({ primary_image_url: 'https://example.com/p1.jpg' })).toBe(
      'https://example.com/p1.jpg'
    )

    // 2. thumbnail property
    expect(getProductThumbnail({ thumbnail: 'https://example.com/thumb.jpg' })).toBe(
      'https://example.com/thumb.jpg'
    )

    // 3. images array with is_primary
    expect(
      getProductThumbnail({
        images: [
          { image_path: 'https://example.com/sec.jpg', is_primary: false },
          { image_path: 'https://example.com/pri.jpg', is_primary: true },
        ],
      })
    ).toBe('https://example.com/pri.jpg')

    // 4. Missing / null / invalid fallback to inline SVG data URI
    expect(getProductThumbnail(null)).toBe(FALLBACK_PHONE_SVG)
    expect(getProductThumbnail({})).toBe(FALLBACK_PHONE_SVG)
    expect(getProductThumbnail({ primary_image_url: '/placeholder-phone.png' })).toBe(
      FALLBACK_PHONE_SVG
    )
    expect(FALLBACK_PHONE_SVG).toContain('data:image/svg+xml')
  })

  it('defines complete multilingual compare UI dictionaries for vi, en, zh-CN', () => {
    for (const lang of ['vi', 'en', 'zh-CN']) {
      const dict = COMPARE_UI[lang]
      expect(dict).toBeDefined()
      expect(dict.title).toBeDefined()
      expect(dict.onlyDiff).toBeDefined()
      expect(dict.addDevice).toBeDefined()
      expect(dict.bestPriceBadge).toBeDefined()
      expect(dict.sections.overview).toBeDefined()
      expect(dict.sections.display).toBeDefined()
      expect(dict.sections.performance).toBeDefined()
      expect(dict.sections.camera).toBeDefined()
      expect(dict.sections.battery).toBeDefined()
      expect(dict.sections.durability).toBeDefined()
    }

    expect(COMPARE_UI['zh-CN'].title).toBe('商品参数对比')
    expect(COMPARE_UI.en.title).toBe('Product Comparison')
  })

  it('defines comprehensive specification groups covering major device attributes', () => {
    expect(SPEC_GROUPS.length).toBeGreaterThanOrEqual(5)
    const allCodes = SPEC_GROUPS.flatMap((g) => g.codes)
    expect(allCodes).toContain('display_size')
    expect(allCodes).toContain('chipset')
    expect(allCodes).toContain('rear_camera')
    expect(allCodes).toContain('battery_capacity')
    expect(allCodes).toContain('operating_system')
  })
})
