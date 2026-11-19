// src/__tests__/adminHomepageCms.test.js
// Unit and integration tests for MobieX Admin Homepage CMS and Storefront Synchronization.

import { describe, it, expect } from 'vitest'
import EP from '../api/endpoints'
import { resolveImageUrl } from '../utils/resolveImageUrl'

describe('Homepage CMS Endpoints & URL Resolution', () => {
  it('defines all required Homepage CMS and Banner upload endpoints', () => {
    expect(EP.HOMEPAGE).toBe('/homepage')
    expect(EP.ADMIN_HOMEPAGE).toBe('/admin/homepage')
    expect(EP.ADMIN_HOMEPAGE_SECTION(4)).toBe('/admin/homepage/sections/4')
    expect(EP.ADMIN_HOMEPAGE_REORDER).toBe('/admin/homepage/reorder')
    expect(EP.ADMIN_HOMEPAGE_PUBLISH).toBe('/admin/homepage/publish')
    expect(EP.ADMIN_HOMEPAGE_REVERT).toBe('/admin/homepage/revert')
    expect(EP.ADMIN_HOMEPAGE_RESET_DEFAULTS).toBe('/admin/homepage/reset-defaults')
    expect(EP.ADMIN_HOMEPAGE_PREVIEW).toBe('/admin/homepage/preview')
    expect(EP.ADMIN_HOMEPAGE_AUDIT_LOGS).toBe('/admin/homepage/audit-logs')
    expect(EP.ADMIN_BANNER_UPLOAD).toBe('/admin/banners/upload-image')
  })

  it('resolves banner local paths and relative storage paths properly', () => {
    // Relative banner upload path
    expect(resolveImageUrl('banners/2026/11/banner1.webp')).toBe('/storage/banners/2026/11/banner1.webp')
    // Full local URL normalization
    expect(resolveImageUrl('http://127.0.0.1:8000/storage/banners/banner2.png')).toBe('/storage/banners/banner2.png')
    // Already relative
    expect(resolveImageUrl('/storage/banners/hero.jpg')).toBe('/storage/banners/hero.jpg')
    // External CDN
    expect(resolveImageUrl('https://images.unsplash.com/photo-test')).toBe('https://images.unsplash.com/photo-test')
    // Null or undefined
    expect(resolveImageUrl(null)).toBeNull()
  })

  it('filters inactive sections and preserves configured sort order', () => {
    const rawSections = [
      { id: 1, section_key: 'hero_banner', sort_order: 1, is_active: true },
      { id: 2, section_key: 'categories', sort_order: 3, is_active: true },
      { id: 3, section_key: 'flash_sale', sort_order: 2, is_active: false }, // Disabled
      { id: 4, section_key: 'featured_products', sort_order: 4, is_active: true },
    ]

    const activeSections = rawSections
      .filter((s) => s.is_active)
      .sort((a, b) => a.sort_order - b.sort_order)

    expect(activeSections).toHaveLength(3)
    expect(activeSections[0].section_key).toBe('hero_banner')
    expect(activeSections[1].section_key).toBe('categories')
    expect(activeSections[2].section_key).toBe('featured_products')
    expect(activeSections.map((s) => s.section_key)).not.toContain('flash_sale')
  })

  it('validates banner active window logic', () => {
    const now = new Date('2026-11-19T12:00:00Z').getTime()

    const banners = [
      { id: 1, title: 'Active Now', is_active: true, start_at: null, end_at: null },
      { id: 2, title: 'Expired', is_active: true, start_at: null, end_at: '2026-11-18T00:00:00Z' },
      { id: 3, title: 'Scheduled Future', is_active: true, start_at: '2026-11-25T00:00:00Z', end_at: null },
      { id: 4, title: 'Paused Inactive', is_active: false, start_at: null, end_at: null },
    ]

    const liveBanners = banners.filter((b) => {
      if (!b.is_active) return false
      if (b.start_at && new Date(b.start_at).getTime() > now) return false
      if (b.end_at && new Date(b.end_at).getTime() < now) return false
      return true
    })

    expect(liveBanners).toHaveLength(1)
    expect(liveBanners[0].title).toBe('Active Now')
  })
})
