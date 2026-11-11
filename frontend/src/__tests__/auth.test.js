import { describe, it, expect } from 'vitest'

function normalizeUser(rawUser) {
  if (!rawUser) return null
  const rawRoles = rawUser.roles ?? []
  const roleSlugs = rawRoles.map((r) => (typeof r === 'string' ? r : r?.slug)).filter(Boolean)
  const permissions = rawUser.permissions ?? []
  const permissionSlugs = permissions.map((p) => (typeof p === 'string' ? p : p?.slug)).filter(Boolean)
  return {
    ...rawUser,
    roles: roleSlugs,
    rawRoles,
    permissions: permissionSlugs,
  }
}

describe('Auth User Normalization', () => {
  it('normalizes object roles from Laravel backend into string slugs', () => {
    const rawUser = {
      id: 1,
      name: 'Super Admin',
      email: 'superadmin@gmail.com',
      roles: [
        { id: 1, slug: 'admin', name: 'Quản trị viên' },
        { id: 2, slug: 'customer', name: 'Khách hàng' },
      ],
    }

    const normalized = normalizeUser(rawUser)
    expect(normalized.roles).toEqual(['admin', 'customer'])
    expect(normalized.roles.includes('admin')).toBe(true)
    expect(normalized.rawRoles).toHaveLength(2)
  })

  it('handles string roles cleanly', () => {
    const rawUser = {
      id: 2,
      name: 'Khách hàng',
      roles: ['customer'],
    }

    const normalized = normalizeUser(rawUser)
    expect(normalized.roles).toEqual(['customer'])
    expect(normalized.roles.includes('customer')).toBe(true)
    expect(normalized.roles.includes('admin')).toBe(false)
  })

  it('handles user with null/empty roles without crashing', () => {
    const rawUser = { id: 3, name: 'Anonymous' }
    const normalized = normalizeUser(rawUser)
    expect(normalized.roles).toEqual([])
  })

  it('returns null for falsy user', () => {
    expect(normalizeUser(null)).toBeNull()
    expect(normalizeUser(undefined)).toBeNull()
  })
})
