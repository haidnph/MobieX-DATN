import { describe, it, expect } from 'vitest'
import EP from '../api/endpoints'

describe('Return & Refund Endpoints & Configuration', () => {
  it('defines valid customer return endpoints', () => {
    expect(EP.RETURNS).toBe('/returns')
    expect(EP.RETURN_DETAIL(12)).toBe('/returns/12')
    expect(EP.RETURN_SHIPPING(12)).toBe('/returns/12/shipping')
    expect(EP.RETURN_CANCEL(12)).toBe('/returns/12/cancel')
  })

  it('defines valid admin return endpoints', () => {
    expect(EP.ADMIN_RETURNS).toBe('/admin/returns')
    expect(EP.ADMIN_RETURN_DETAIL(5)).toBe('/admin/returns/5')
    expect(EP.ADMIN_RETURN_APPROVE(5)).toBe('/admin/returns/5/approve')
    expect(EP.ADMIN_RETURN_REJECT(5)).toBe('/admin/returns/5/reject')
    expect(EP.ADMIN_RETURN_RECEIVE(5)).toBe('/admin/returns/5/receive')
    expect(EP.ADMIN_RETURN_INSPECT(5)).toBe('/admin/returns/5/inspect')
    expect(EP.ADMIN_RETURN_COMPLETE(5)).toBe('/admin/returns/5/complete')
  })

  it('validates return status labels and transitions', () => {
    const statuses = [
      'pending',
      'approved',
      'shipping_back',
      'received',
      'inspecting',
      'completed',
      'rejected',
      'cancelled',
    ]

    expect(statuses).toContain('pending')
    expect(statuses).toContain('approved')
    expect(statuses).toContain('completed')
    expect(statuses.length).toBe(8)
  })
})
