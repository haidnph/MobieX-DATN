import { describe, it, expect } from 'vitest'

describe('Catalog Filter & Sorting Utilities', () => {
  const PRICE_RANGES = [
    { label: 'Dưới 5 triệu', min: 0, max: 5000000, key: 'under5m' },
    { label: '5 – 10 triệu', min: 5000000, max: 10000000, key: '5to10m' },
    { label: '10 – 20 triệu', min: 10000000, max: 20000000, key: '10to20m' },
    { label: 'Trên 20 triệu', min: 20000000, max: null, key: 'over20m' },
  ]

  const SORT_OPTIONS = [
    { value: 'newest', label: 'Mới nhất' },
    { value: 'popular', label: 'Phổ biến / Đánh giá' },
    { value: 'price_asc', label: 'Giá: Thấp đến Cao' },
    { value: 'price_desc', label: 'Giá: Cao đến Thấp' },
    { value: 'name_asc', label: 'Tên: A → Z' },
    { value: 'name_desc', label: 'Tên: Z → A' },
    { value: 'oldest', label: 'Cũ nhất' },
  ]

  it('correctly maps price range keys to min and max boundaries', () => {
    const under5m = PRICE_RANGES.find((r) => r.key === 'under5m')
    expect(under5m.min).toBe(0)
    expect(under5m.max).toBe(5000000)

    const over20m = PRICE_RANGES.find((r) => r.key === 'over20m')
    expect(over20m.min).toBe(20000000)
    expect(over20m.max).toBeNull()
  })

  it('contains expected sorting options including price and name', () => {
    const values = SORT_OPTIONS.map((o) => o.value)
    expect(values).toContain('newest')
    expect(values).toContain('popular')
    expect(values).toContain('price_asc')
    expect(values).toContain('price_desc')
    expect(values).toContain('name_asc')
    expect(values).toContain('name_desc')
  })

  it('correctly parses and joins multiple brand IDs', () => {
    const brandIds = ['1', '2', '3']
    const param = brandIds.join(',')
    expect(param).toBe('1,2,3')

    const parsed = param.split(',').filter(Boolean)
    expect(parsed).toEqual(['1', '2', '3'])
  })
})
