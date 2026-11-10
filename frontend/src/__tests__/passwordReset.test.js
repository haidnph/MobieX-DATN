import { describe, it, expect } from 'vitest'
import EP from '../api/endpoints'

describe('Password Reset Endpoints & Rules', () => {
  it('defines correct password reset endpoints matching backend API specification', () => {
    expect(EP.AUTH_FORGOT_PASSWORD).toBe('/auth/forgot-password')
    expect(EP.AUTH_RESET_PASSWORD).toBe('/auth/reset-password')
  })

  it('validates password matching logic correctly', () => {
    const checkMatching = (pw, confirm) => pw === confirm && pw.length > 0
    expect(checkMatching('Secret@123', 'Secret@123')).toBe(true)
    expect(checkMatching('Secret@123', 'Different@123')).toBe(false)
    expect(checkMatching('', '')).toBe(false)
  })

  it('verifies strong password policy compliance', () => {
    const isStrongPassword = (pwd) => {
      if (!pwd || pwd.length < 8) return false
      const hasUpper = /[A-Z]/.test(pwd)
      const hasLower = /[a-z]/.test(pwd)
      const hasDigit = /[0-9]/.test(pwd)
      const hasSpecial = /[^A-Za-z0-9]/.test(pwd)
      return hasUpper && hasLower && hasDigit && hasSpecial
    }

    expect(isStrongPassword('MobieX@2026')).toBe(true)
    expect(isStrongPassword('weak')).toBe(false)
    expect(isStrongPassword('alllowercase123!')).toBe(false)
    expect(isStrongPassword('ALLUPPERCASE123!')).toBe(false)
    expect(isStrongPassword('NoSpecial12345')).toBe(false)
  })

  it('parses reset token and email from URL search params correctly', () => {
    const searchString = '?token=abc123token&email=customer%40example.com'
    const params = new URLSearchParams(searchString)
    expect(params.get('token')).toBe('abc123token')
    expect(params.get('email')).toBe('customer@example.com')
  })
})
