import { describe, it, expect } from 'vitest'
import { getSafeIntendedUrl } from '../utils/urlUtils'
import { normalizeUser, ROLE_PERMISSION_MAP } from '../contexts/AuthContext'

describe('getSafeIntendedUrl', () => {
  it('returns target if valid internal relative path', () => {
    expect(getSafeIntendedUrl('/checkout')).toBe('/checkout')
    expect(getSafeIntendedUrl('/products/iphone-15?color=black#specs')).toBe(
      '/products/iphone-15?color=black#specs',
    )
  })

  it('handles React Router location objects', () => {
    const loc = { pathname: '/account/profile', search: '?tab=security', hash: '#pwd' }
    expect(getSafeIntendedUrl(loc)).toBe('/account/profile?tab=security#pwd')
  })

  it('rejects external protocol-relative URLs (open redirect)', () => {
    expect(getSafeIntendedUrl('//evil.com')).toBe('/')
    expect(getSafeIntendedUrl('//google.com/phishing')).toBe('/')
  })

  it('rejects backslash / Windows style path injection', () => {
    expect(getSafeIntendedUrl('/\\evil.com')).toBe('/')
    expect(getSafeIntendedUrl('\\evil.com')).toBe('/')
    expect(getSafeIntendedUrl('/path\\to\\danger')).toBe('/')
  })

  it('rejects full URLs with protocols', () => {
    expect(getSafeIntendedUrl('https://evil.com')).toBe('/')
    expect(getSafeIntendedUrl('http://evil.com')).toBe('/')
    expect(getSafeIntendedUrl('javascript:alert(1)')).toBe('/')
    expect(getSafeIntendedUrl('/https://evil.com')).toBe('/')
  })

  it('rejects auth loop paths', () => {
    expect(getSafeIntendedUrl('/login')).toBe('/')
    expect(getSafeIntendedUrl('/admin/login')).toBe('/')
    expect(getSafeIntendedUrl('/register')).toBe('/')
    expect(getSafeIntendedUrl('/forgot-password')).toBe('/')
    expect(getSafeIntendedUrl('/reset-password')).toBe('/')
  })

  describe('Admin Portal Context (isAdminPortal: true)', () => {
    it('redirects to /admin if path is outside /admin', () => {
      expect(getSafeIntendedUrl('/', '/admin', { isAdminPortal: true })).toBe('/admin')
      expect(getSafeIntendedUrl('/products', '/admin', { isAdminPortal: true })).toBe('/admin')
      expect(getSafeIntendedUrl('/checkout', '/admin', { isAdminPortal: true })).toBe('/admin')
    })

    it('preserves valid /admin subpaths', () => {
      expect(
        getSafeIntendedUrl('/admin/orders?status=pending', '/admin', { isAdminPortal: true }),
      ).toBe('/admin/orders?status=pending')
      expect(
        getSafeIntendedUrl('/admin/inventories', '/admin', { isAdminPortal: true }),
      ).toBe('/admin/inventories')
    })
  })

  describe('Client Storefront Context (isAdminPortal: false)', () => {
    it('prevents non-admin user (customer) from redirecting into /admin', () => {
      expect(
        getSafeIntendedUrl('/admin/orders', '/', { isAdminPortal: false, canAccessAdmin: false }),
      ).toBe('/')
      expect(
        getSafeIntendedUrl('/admin', '/', { isAdminPortal: false, canAccessAdmin: false }),
      ).toBe('/')
    })

    it('allows admin/staff user to proceed to /admin if that was their explicit intended destination', () => {
      expect(
        getSafeIntendedUrl('/admin/orders', '/', { isAdminPortal: false, canAccessAdmin: true }),
      ).toBe('/admin/orders')
    })

    it('keeps admin/staff on client page if intended destination was client page', () => {
      expect(
        getSafeIntendedUrl('/checkout', '/', { isAdminPortal: false, canAccessAdmin: true }),
      ).toBe('/checkout')
      expect(
        getSafeIntendedUrl('/', '/', { isAdminPortal: false, canAccessAdmin: true }),
      ).toBe('/')
    })
  })
})

describe('AuthContext - normalizeUser and ROLE_PERMISSION_MAP', () => {
  it('maps canonical permissions for sales role', () => {
    const rawUser = { id: 10, name: 'Sales Staff', roles: ['sales'] }
    const normalized = normalizeUser(rawUser)
    expect(normalized.roles).toEqual(['sales'])
    expect(normalized.permissions).toContain('orders.view')
    expect(normalized.permissions).toContain('products.view')
    expect(normalized.permissions).toContain('dashboard.view')
    expect(normalized.permissions).not.toContain('inventory.adjust')
  })

  it('maps canonical permissions for warehouse role', () => {
    const rawUser = { id: 11, name: 'Kho', roles: ['warehouse'] }
    const normalized = normalizeUser(rawUser)
    expect(normalized.roles).toEqual(['warehouse'])
    expect(normalized.permissions).toContain('inventory.view')
    expect(normalized.permissions).toContain('inventory.import')
    expect(normalized.permissions).toContain('inventory.adjust')
    expect(normalized.permissions).not.toContain('coupons.manage')
  })

  it('maps canonical permissions for admin role (* wildcard)', () => {
    const rawUser = { id: 1, name: 'Admin', roles: ['admin'] }
    const normalized = normalizeUser(rawUser)
    expect(normalized.permissions).toContain('*')
  })

  it('maps customer permissions without admin access', () => {
    const rawUser = { id: 20, name: 'Customer', roles: ['customer'] }
    const normalized = normalizeUser(rawUser)
    expect(normalized.permissions).toContain('profile.update-own')
    expect(normalized.permissions).not.toContain('dashboard.view')
    expect(normalized.permissions).not.toContain('orders.manage')
  })
})
