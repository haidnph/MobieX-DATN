import { describe, it, expect } from 'vitest'
import {
  getProductThumbnail,
  FALLBACK_PHONE_SVG,
  MAX_COMPARE_ITEMS,
} from '../contexts/CompareContext'
import {
  COMPARE_UI,
  SPEC_GROUPS,
  SPEC_ALIASES,
  CANONICAL_NAMES,
  normalizeSpecKey,
  getProductConfigurations,
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

/**
 * Strict difference detector mirroring the upgraded ProductComparePage logic:
 * - Empty / null / missing values are NOT treated as differences
 * - At least 2 real valid values required to be contrasted
 */
function detectRealSpecDifference(products, specCode) {
  if (!products || products.length < 2) return false

  const rawValues = products.map((p) => {
    const spec = p.specifications?.find((s) => {
      const code = s.definition?.code || s.code
      const aliases = SPEC_ALIASES[specCode] || [specCode]
      return code && aliases.includes(code)
    })
    if (!spec) return null
    if (spec.numeric_value !== null && spec.numeric_value !== undefined) {
      return Number(spec.numeric_value)
    }
    const txt = (spec.value_text || spec.value || '').trim()
    return txt && txt !== '—' ? txt : null
  })

  // Filter only valid real values
  const validValues = rawValues.filter((v) => v !== null && v !== '')
  if (validValues.length < 2) return false

  const firstVal = String(validValues[0]).trim().toLowerCase()
  return validValues.some((v) => String(v).trim().toLowerCase() !== firstVal)
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

  it('correctly detects real differences between products specifications', () => {
    const products = [
      {
        id: 1,
        specifications: [
          { definition: { code: 'chipset' }, value_text: 'Apple A18 Pro' },
        ],
      },
      {
        id: 2,
        specifications: [
          { definition: { code: 'chipset' }, value_text: 'Snapdragon 8 Gen 3' },
        ],
      },
    ]

    expect(detectRealSpecDifference(products, 'chipset')).toBe(true)
  })

  it('returns false when all compared devices share identical specification', () => {
    const products = [
      {
        id: 1,
        specifications: [
          { definition: { code: 'front_camera' }, value_text: '12MP TrueDepth' },
        ],
      },
      {
        id: 4,
        specifications: [
          { definition: { code: 'front_camera' }, value_text: '12MP TrueDepth' },
        ],
      },
    ]

    expect(detectRealSpecDifference(products, 'front_camera')).toBe(false)
  })

  it('does NOT count missing/null/empty specs as a difference', () => {
    // Phone 1 has spec, Phone 2 is missing spec
    const productsOneMissing = [
      {
        id: 1,
        specifications: [
          { definition: { code: 'chipset' }, value_text: 'Apple A18 Pro' },
        ],
      },
      {
        id: 11,
        specifications: [], // Missing spec in DB
      },
    ]
    expect(detectRealSpecDifference(productsOneMissing, 'chipset')).toBe(false)

    // Both phones missing spec
    const productsBothMissing = [
      { id: 11, specifications: [] },
      { id: 74, specifications: [] },
    ]
    expect(detectRealSpecDifference(productsBothMissing, 'display_size')).toBe(false)
  })

  it('correctly handles stress test products 488, 489, 791 with 0 specs and identical capacity', () => {
    const stressProducts = [
      {
        id: 488,
        name: 'MobieX Stress Scale Product #208 5G Performance',
        brand: { name: 'JBL' },
        variants: [{ option_signature: 'capacity:128gb', price: 5700000 }],
        specifications: [],
      },
      {
        id: 489,
        name: 'MobieX Stress Scale Product #209 5G Performance',
        brand: { name: 'Apple' },
        variants: [{ option_signature: 'capacity:128gb', price: 5725000 }],
        specifications: [],
      },
      {
        id: 791,
        name: 'MobieX Stress Scale Product #511 5G Performance',
        brand: { name: 'OPPO' },
        variants: [{ option_signature: 'capacity:128gb', price: 13275000 }],
        specifications: [],
      },
    ]

    // Hardware specs are missing for all 3 -> MUST NOT be counted as difference
    expect(detectRealSpecDifference(stressProducts, 'chipset')).toBe(false)
    expect(detectRealSpecDifference(stressProducts, 'display_size')).toBe(false)
    expect(detectRealSpecDifference(stressProducts, 'battery_capacity')).toBe(false)

    // Configurations extracted accurately without disjoint cross-products
    const configs488 = getProductConfigurations(stressProducts[0])
    expect(configs488).toEqual(['128GB ROM'])

    const configs489 = getProductConfigurations(stressProducts[1])
    expect(configs489).toEqual(['128GB ROM'])

    const configs791 = getProductConfigurations(stressProducts[2])
    expect(configs791).toEqual(['128GB ROM'])
  })

  it('extracts real variant configurations without creating fake cross-product combinations', () => {
    const productWithSpecificPairs = {
      variants: [
        { option_signature: 'color:den|ram:8gb|storage:128gb', name: '8GB/128GB' },
        { option_signature: 'color:trang|ram:12gb|storage:256gb', name: '12GB/256GB' },
      ],
    }

    const configs = getProductConfigurations(productWithSpecificPairs)
    expect(configs).toEqual(['8GB RAM / 128GB ROM', '12GB RAM / 256GB ROM'])
    // Should NOT contain non-existent combinations like '8GB RAM / 256GB ROM' or '12GB RAM / 128GB ROM'
    expect(configs).not.toContain('8GB RAM / 256GB ROM')
    expect(configs).not.toContain('12GB RAM / 128GB ROM')
  })

  it('normalizes Vietnamese spec keys accurately across diacritics', () => {
    expect(normalizeSpecKey('Kích thước màn hình')).toBe('kichthuocmanhinh')
    expect(normalizeSpecKey('Vi xử lý')).toBe('vixuly')
    expect(normalizeSpecKey('Độ phân giải màn hình')).toBe('dophangiaimanhinh')
    expect(normalizeSpecKey('Dung lượng pin')).toBe('dungluongpin')
    expect(normalizeSpecKey('Camera sau')).toBe('camerasau')
    expect(normalizeSpecKey('Kháng nước/bụi')).toBe('khangnuocbui')
    expect(normalizeSpecKey('Trọng lượng')).toBe('trongluong')
  })

  it('resolves specification aliases into canonical keys', () => {
    // Screen size aliases
    expect(SPEC_ALIASES.display_size).toContain('screen_size')
    expect(SPEC_ALIASES.display_size).toContain('kich-thuoc-man-hinh')
    expect(SPEC_ALIASES.display_size).toContain('display_size')

    // Processor aliases
    expect(SPEC_ALIASES.chipset).toContain('cpu')
    expect(SPEC_ALIASES.chipset).toContain('vi-xu-ly')
    expect(SPEC_ALIASES.chipset).toContain('chipset')

    // Battery aliases
    expect(SPEC_ALIASES.battery_capacity).toContain('dung-luong-pin')
    expect(SPEC_ALIASES.battery_capacity).toContain('battery')

    // Canonical names mapping
    expect(CANONICAL_NAMES.display_size).toContain('kichthuocmanhinh')
    expect(CANONICAL_NAMES.chipset).toContain('vixuly')
  })

  it('reliably extracts thumbnails and prevents broken placeholder loops', () => {
    expect(getProductThumbnail({ primary_image_url: 'https://example.com/p1.jpg' })).toBe(
      'https://example.com/p1.jpg'
    )
    expect(getProductThumbnail({ thumbnail: 'https://example.com/thumb.jpg' })).toBe(
      'https://example.com/thumb.jpg'
    )
    expect(
      getProductThumbnail({
        images: [
          { image_path: 'https://example.com/sec.jpg', is_primary: false },
          { image_path: 'https://example.com/pri.jpg', is_primary: true },
        ],
      })
    ).toBe('https://example.com/pri.jpg')

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
      expect(dict.notSpecified).toBeDefined()
      expect(dict.sections.overview).toBeDefined()
      expect(dict.sections.display).toBeDefined()
      expect(dict.sections.performance).toBeDefined()
      expect(dict.sections.camera).toBeDefined()
      expect(dict.sections.battery).toBeDefined()
      expect(dict.sections.durability).toBeDefined()
    }

    expect(COMPARE_UI.vi.notSpecified).toBe('Chưa cập nhật')
    expect(COMPARE_UI.en.notSpecified).toBe('Not specified')
    expect(COMPARE_UI['zh-CN'].notSpecified).toBe('未提供')
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
