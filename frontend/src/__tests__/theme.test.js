import { describe, it, expect, beforeEach } from 'vitest'

function getInitialTheme(stored) {
  if (stored === 'light' || stored === 'dark') return stored
  return 'dark'
}

function nextTheme(current) {
  return current === 'dark' ? 'light' : 'dark'
}

function validateTheme(theme) {
  return theme === 'dark' || theme === 'light'
}

describe('Theme Mode Logic', () => {
  it('defaults to dark mode when no saved preference exists', () => {
    expect(getInitialTheme(null)).toBe('dark')
    expect(getInitialTheme(undefined)).toBe('dark')
    expect(getInitialTheme('invalid_theme')).toBe('dark')
  })

  it('restores stored theme preference from localStorage correctly', () => {
    expect(getInitialTheme('light')).toBe('light')
    expect(getInitialTheme('dark')).toBe('dark')
  })

  it('toggles correctly between dark and light themes', () => {
    let current = 'dark'
    current = nextTheme(current)
    expect(current).toBe('light')
    current = nextTheme(current)
    expect(current).toBe('dark')
  })

  it('validates theme names correctly', () => {
    expect(validateTheme('dark')).toBe(true)
    expect(validateTheme('light')).toBe(true)
    expect(validateTheme('solarized')).toBe(false)
    expect(validateTheme('')).toBe(false)
  })
})
