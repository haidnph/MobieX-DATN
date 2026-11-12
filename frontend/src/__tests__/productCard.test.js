import { describe, it, expect } from 'vitest'

// Pure logic extract mirroring ProductCard calculation rules
function computeProductCardState(product) {
  if (!product) return null

  const price =
    product.min_price && Number(product.min_price) > 0
      ? Number(product.min_price)
      : product.variants?.length
        ? Math.min(...product.variants.map((v) => Number(v.price) || 0))
        : Number(product.base_price) || 0

  const originalPrice =
    product.max_price && Number(product.max_price) > 0
      ? Number(product.max_price)
      : product.variants?.length
        ? Math.max(...product.variants.map((v) => Number(v.compare_price) || 0))
        : null

  const discountPercent =
    originalPrice && originalPrice > price
      ? Math.round(((originalPrice - price) / originalPrice) * 100)
      : (product.discount_percent || 0)

  const totalStock = product.variants?.length
    ? product.variants.reduce(
        (sum, v) => sum + (v.inventory?.available_quantity ?? v.available_quantity ?? v.stock_quantity ?? 0),
        0
      )
    : (product.stock_quantity ?? product.total_stock ?? 0)

  const isDiscontinued = product.status !== 'active'
  const isOutOfStock = !isDiscontinued && product.variants?.length > 0 && totalStock <= 0
  const isLowStock = !isDiscontinued && !isOutOfStock && totalStock > 0 && totalStock <= 3

  const thumbnail =
    product.images?.find((img) => img.is_primary)?.image_url ||
    product.images?.find((img) => img.is_primary)?.image_path ||
    product.primary_image_url ||
    product.thumbnail ||
    product.images?.[0]?.image_url ||
    null

  return {
    price,
    originalPrice,
    discountPercent,
    totalStock,
    isDiscontinued,
    isOutOfStock,
    isLowStock,
    thumbnail,
  }
}

describe('ProductCard State & Stock Logic', () => {
  it('correctly marks product as discontinued when status is inactive', () => {
    const product = {
      id: 1,
      name: 'iPhone 12 Pro',
      status: 'inactive',
      variants: [{ id: 10, price: 15000000, available_quantity: 10 }],
    }
    const state = computeProductCardState(product)
    expect(state.isDiscontinued).toBe(true)
    expect(state.isOutOfStock).toBe(false)
    expect(state.isLowStock).toBe(false)
  })

  it('correctly flags out of stock when total stock is 0', () => {
    const product = {
      id: 2,
      name: 'Galaxy S24 Ultra',
      status: 'active',
      variants: [
        { id: 21, price: 25000000, inventory: { available_quantity: 0 } },
        { id: 22, price: 28000000, inventory: { available_quantity: 0 } },
      ],
    }
    const state = computeProductCardState(product)
    expect(state.isDiscontinued).toBe(false)
    expect(state.isOutOfStock).toBe(true)
    expect(state.isLowStock).toBe(false)
  })

  it('correctly flags low stock when total available quantity is between 1 and 3', () => {
    const product = {
      id: 3,
      name: 'Xiaomi 14 Pro',
      status: 'active',
      variants: [
        { id: 31, price: 18000000, inventory: { available_quantity: 2 } },
      ],
    }
    const state = computeProductCardState(product)
    expect(state.isLowStock).toBe(true)
    expect(state.totalStock).toBe(2)
  })

  it('calculates discount percentage correctly when compare_price is higher than price', () => {
    const product = {
      id: 4,
      name: 'iPad Pro M4',
      status: 'active',
      min_price: 20000000,
      max_price: 25000000,
      variants: [{ id: 41, price: 20000000, compare_price: 25000000, stock_quantity: 10 }],
    }
    const state = computeProductCardState(product)
    expect(state.price).toBe(20000000)
    expect(state.originalPrice).toBe(25000000)
    expect(state.discountPercent).toBe(20) // (25M - 20M) / 25M = 20%
  })

  it('resolves primary image correctly or falls back to first image', () => {
    const productWithPrimary = {
      id: 5,
      status: 'active',
      images: [
        { id: 1, image_url: 'img1.jpg', is_primary: false },
        { id: 2, image_url: 'primary.jpg', is_primary: true },
      ],
    }
    expect(computeProductCardState(productWithPrimary).thumbnail).toBe('primary.jpg')

    const productWithoutPrimary = {
      id: 6,
      status: 'active',
      images: [
        { id: 1, image_url: 'img1.jpg', is_primary: false },
      ],
    }
    expect(computeProductCardState(productWithoutPrimary).thumbnail).toBe('img1.jpg')
  })
})
