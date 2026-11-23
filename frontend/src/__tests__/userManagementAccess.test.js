import { describe, expect, it } from 'vitest'
import { canAssignUserRole, canManageUserAccount } from '../features/admin/userManagementAccess'

describe('admin user management access helpers', () => {
  it('lets only a super-admin manage a super-admin account', () => {
    const superAdmin = { roles: [{ slug: 'super-admin' }] }
    expect(canManageUserAccount({ roles: ['admin'] }, superAdmin)).toBe(false)
    expect(canManageUserAccount({ roles: ['super-admin'] }, superAdmin)).toBe(true)
    expect(canManageUserAccount({ roles: ['admin'] }, { roles: ['customer'] })).toBe(true)
  })

  it('does not offer the super-admin role to other managers', () => {
    expect(canAssignUserRole({ roles: ['admin'], permissions: ['users.manage'] }, {
      slug: 'super-admin',
      permissions: ['users.manage'],
    })).toBe(false)
  })

  it('allows a role only when all of its permissions are already held', () => {
    const actor = { permissions: [{ slug: 'users.manage' }, { slug: 'orders.view' }] }
    expect(canAssignUserRole(actor, {
      slug: 'support',
      permissions: [{ slug: 'orders.view' }],
    })).toBe(true)
    expect(canAssignUserRole(actor, {
      slug: 'role-manager',
      permissions: ['roles.manage'],
    })).toBe(false)
  })

  it('fails closed when actor permissions or role are missing', () => {
    expect(canAssignUserRole({ roles: ['admin'] }, { slug: 'limited', permissions: ['users.manage'] })).toBe(false)
    expect(canAssignUserRole({ roles: ['admin'], permissions: ['users.manage'] }, null)).toBe(false)
  })
})
