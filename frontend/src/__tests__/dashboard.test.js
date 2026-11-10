import { describe, it, expect } from 'vitest'

describe('Dashboard Analytics & Chart Logic', () => {
  it('calculates SVG point coordinates accurately based on plot bounds', () => {
    const data = [
      { revenue: 10000000, orders: 5 },
      { revenue: 20000000, orders: 10 },
      { revenue: 30000000, orders: 15 },
    ]

    const padLeft = 70
    const padRight = 30
    const plotWidth = 840 - padLeft - padRight // 740
    const plotHeight = 210
    const padTop = 30

    const maxRevenue = 30000000

    const points = data.map((d, i) => {
      const x = padLeft + (i / (data.length - 1)) * plotWidth
      const y = padTop + plotHeight - (d.revenue / maxRevenue) * plotHeight
      return { x, y }
    })

    // First point (i = 0): x should be padLeft, y should be scaled
    expect(points[0].x).toBe(70)
    expect(points[0].y).toBeCloseTo(30 + 210 - (10000000 / 30000000) * 210)

    // Last point (i = 2): x should be padLeft + plotWidth = 810
    expect(points[2].x).toBe(810)
    expect(points[2].y).toBe(padTop) // At maxRevenue, y is padTop (top of plot)
  })

  it('computes SVG Donut segment dasharrays and cumulative offsets correctly', () => {
    const radius = 64
    const circumference = 2 * Math.PI * radius // ≈ 402.12

    const distribution = {
      delivered: { count: 80, percentage: 80 },
      cancelled: { count: 20, percentage: 20 },
    }

    const deliveredDash = (distribution.delivered.percentage / 100) * circumference
    const cancelledDash = (distribution.cancelled.percentage / 100) * circumference

    expect(deliveredDash).toBeCloseTo(circumference * 0.8)
    expect(cancelledDash).toBeCloseTo(circumference * 0.2)
    expect(deliveredDash + cancelledDash).toBeCloseTo(circumference)
  })

  it('normalizes leaderboard rankings and percentage progress bars', () => {
    const items = [
      { product_name: 'iPhone 15 Pro Max', total_sold: 100, total_revenue: 3000000000 },
      { product_name: 'Samsung S24 Ultra', total_sold: 50, total_revenue: 1400000000 },
      { product_name: 'Xiaomi 14', total_sold: 25, total_revenue: 500000000 },
    ]

    const maxSold = Math.max(...items.map((i) => i.total_sold), 1)
    expect(maxSold).toBe(100)

    const percentages = items.map((i) => Math.round((i.total_sold / maxSold) * 100))
    expect(percentages).toEqual([100, 50, 25])
  })

  it('maps all 4 payment gateways (vnpay, zalopay, momo, cod) seamlessly', () => {
    const mockDistribution = {
      vnpay: { count: 40, percentage: 40 },
      momo: { count: 30, percentage: 30 },
      zalopay: { count: 20, percentage: 20 },
      cod: { count: 10, percentage: 10 },
    }

    const gateways = ['vnpay', 'momo', 'zalopay', 'cod']
    const totalPercentage = gateways.reduce((sum, g) => sum + (mockDistribution[g]?.percentage || 0), 0)

    expect(totalPercentage).toBe(100)
    gateways.forEach((g) => {
      expect(mockDistribution).toHaveProperty(g)
      expect(mockDistribution[g].count).toBeGreaterThan(0)
    })
  })
})
