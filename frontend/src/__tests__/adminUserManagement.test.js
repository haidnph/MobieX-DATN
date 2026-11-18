import { describe, it, expect } from 'vitest'
import { ROLE_CONFIG } from '../features/admin/AdminUserPage'
import EP from '../api/endpoints'

describe('Admin User Management & RBAC Config', () => {
  it('defines valid canonical roles with Vietnamese labels', () => {
    expect(ROLE_CONFIG.admin.label).toBe('Quản trị viên')
    expect(ROLE_CONFIG.accountant.label).toBe('Kế toán')
    expect(ROLE_CONFIG.warehouse.label).toBe('Thủ kho')
    expect(ROLE_CONFIG.sales.label).toBe('Bán hàng')
    expect(ROLE_CONFIG.customer.label).toBe('Khách hàng')
  })

  it('provides icons and variants for each role', () => {
    Object.values(ROLE_CONFIG).forEach((cfg) => {
      expect(cfg.label).toBeDefined()
      expect(cfg.variant).toBeDefined()
      expect(cfg.icon).toBeDefined()
    })
  })

  it('matches required endpoint URL templates', () => {
    expect(EP.ADMIN_USERS).toBe('/admin/users')
    expect(EP.ADMIN_USER_CREATE).toBe('/admin/users')
    expect(EP.ADMIN_USER_DETAIL(99)).toBe('/admin/users/99')
    expect(EP.ADMIN_USER_UPDATE(99)).toBe('/admin/users/99')
    expect(EP.ADMIN_USER_PASSWORD_RESET(99)).toBe('/admin/users/99/password')
    expect(EP.ADMIN_USER_STATUS(99)).toBe('/admin/users/99/status')
    expect(EP.ADMIN_USER_ROLES(99)).toBe('/admin/users/99/roles')
    expect(EP.ADMIN_USER_ROLE_DELETE(99, 'sales')).toBe('/admin/users/99/roles/sales')
    expect(EP.ADMIN_ROLES).toBe('/admin/roles')
    expect(EP.ADMIN_ROLE_PERMISSIONS_SYNC(1)).toBe('/admin/roles/1/permissions')
    expect(EP.ADMIN_PERMISSIONS).toBe('/admin/permissions')
  })
})
