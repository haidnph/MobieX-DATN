import { describe, it, expect, beforeEach } from 'vitest'
import { formatCurrencyVND } from '../utils/formatCurrencyVND'
import { formatDate, formatDateTime, formatRelativeTime } from '../utils/formatDate'
import storage from '../utils/storage'

describe('formatCurrencyVND', () => {
  it('formats number to Vietnamese Dong correctly', () => {
    const formatted = formatCurrencyVND(34990000)
    expect(formatted).toContain('34.990.000')
    expect(formatted).toContain('₫')
  })

  it('handles zero amount', () => {
    const formatted = formatCurrencyVND(0)
    expect(formatted).toContain('0')
    expect(formatted).toContain('₫')
  })

  it('returns fallback dash for null/undefined/NaN', () => {
    expect(formatCurrencyVND(null)).toBe('—')
    expect(formatCurrencyVND(undefined)).toBe('—')
    expect(formatCurrencyVND(NaN)).toBe('—')
  })
})

describe('formatDate', () => {
  it('formats ISO date to Vietnamese locale', () => {
    const result = formatDate('2026-11-08T12:00:00Z')
    expect(result).not.toBe('—')
    expect(result).toContain('2026')
  })

  it('returns fallback dash for invalid date', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('invalid-date')).toBe('—')
  })
})

describe('formatDateTime', () => {
  it('formats ISO date with time', () => {
    const result = formatDateTime('2026-11-08T15:30:00Z')
    expect(result).not.toBe('—')
    expect(result).toContain('2026')
  })
})

describe('formatRelativeTime', () => {
  it('formats relative time just now', () => {
    const justNow = new Date().toISOString()
    const result = formatRelativeTime(justNow)
    expect(result).toBe('vừa xong')
  })
})

describe('storage utility', () => {
  beforeEach(() => {
    storage.clear()
  })

  it('stores and retrieves string tokens', () => {
    storage.set('token', 'sample-token-123')
    expect(storage.get('token')).toBe('sample-token-123')
  })

  it('removes specific keys', () => {
    storage.set('test_key', 'val')
    storage.remove('test_key')
    expect(storage.get('test_key')).toBeNull()
  })

  it('clears all items with app prefix', () => {
    storage.set('token', 'abc')
    storage.set('user', { id: 1 })
    storage.clear()
    expect(storage.get('token')).toBeNull()
    expect(storage.get('user')).toBeNull()
  })
})
