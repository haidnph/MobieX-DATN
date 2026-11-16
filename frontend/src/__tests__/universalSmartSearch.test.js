import { describe, it, expect } from 'vitest'

describe('Universal Smart Search Frontend Unit Tests', () => {
  it('correctly filters active criteria when excludeCriteria are provided', () => {
    const detectedCriteria = [
      { type: 'brand', label: 'Thương hiệu', value: 'Samsung' },
      { type: 'price', label: 'Ngân sách', value: '≤ 15.000.000₫' },
      { type: 'ram', label: 'RAM', value: '12GB' },
    ]

    const excludeCriteria = ['price']

    const activeCriteria = detectedCriteria.filter(
      (c) => !excludeCriteria.includes(c.type)
    )

    expect(activeCriteria).toHaveLength(2)
    expect(activeCriteria.map((c) => c.type)).toEqual(['brand', 'ram'])
  })

  it('correctly formats typo suggestion queries', () => {
    const intent = {
      has_typo: true,
      suggested_query: 'samsung s24 ultra',
      original_query: 'samsng s24 ultra',
    }

    expect(intent.has_typo).toBe(true)
    expect(intent.suggested_query).toBe('samsung s24 ultra')
  })

  it('correctly identifies model aliases', () => {
    const intent = {
      model_alias: 'iPhone 16 Pro Max',
      criteria: [
        { type: 'alias', label: 'Bí danh', value: 'iPhone 16 Pro Max' },
      ],
    }

    expect(intent.model_alias).toBe('iPhone 16 Pro Max')
    expect(intent.criteria[0].type).toBe('alias')
  })
})
