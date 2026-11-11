import { describe, it, expect } from 'vitest'

function addWishlistItem(currentItems, product, maxLimit = 50) {
  if (!product || !product.id) return { items: currentItems, added: false, reason: 'invalid' }
  if (currentItems.some((item) => Number(item.id) === Number(product.id))) {
    return { items: currentItems, added: false, reason: 'exists' }
  }
  if (currentItems.length >= maxLimit) {
    return { items: currentItems, added: false, reason: 'max_reached' }
  }
  return {
    items: [{ id: product.id, name: product.name }, ...currentItems],
    added: true,
  }
}

function removeWishlistItem(currentItems, productId) {
  return currentItems.filter((item) => Number(item.id) !== Number(productId))
}

function toggleWishlistItem(currentItems, product) {
  if (currentItems.some((item) => Number(item.id) === Number(product.id))) {
    return { items: removeWishlistItem(currentItems, product.id), action: 'removed' }
  }
  const res = addWishlistItem(currentItems, product)
  return { items: res.items, action: res.added ? 'added' : 'failed' }
}

describe('Wishlist Logic & Operations', () => {
  it('adds item to wishlist', () => {
    const res = addWishlistItem([], { id: 1, name: 'Galaxy S24 Ultra' })
    expect(res.added).toBe(true)
    expect(res.items).toHaveLength(1)
    expect(res.items[0].id).toBe(1)
  })

  it('prevents adding duplicate item to wishlist', () => {
    const initial = [{ id: 1, name: 'Galaxy S24 Ultra' }]
    const res = addWishlistItem(initial, { id: 1, name: 'Galaxy S24 Ultra' })
    expect(res.added).toBe(false)
    expect(res.reason).toBe('exists')
    expect(res.items).toHaveLength(1)
  })

  it('removes item from wishlist by ID', () => {
    const initial = [
      { id: 1, name: 'Galaxy S24 Ultra' },
      { id: 2, name: 'iPhone 16 Pro Max' },
    ]
    const updated = removeWishlistItem(initial, 1)
    expect(updated).toHaveLength(1)
    expect(updated[0].id).toBe(2)
  })

  it('toggles wishlist item between added and removed', () => {
    let list = []
    const product = { id: 99, name: 'Xiaomi 14' }

    // Toggle on
    const r1 = toggleWishlistItem(list, product)
    expect(r1.action).toBe('added')
    expect(r1.items).toHaveLength(1)

    // Toggle off
    const r2 = toggleWishlistItem(r1.items, product)
    expect(r2.action).toBe('removed')
    expect(r2.items).toHaveLength(0)
  })

  it('respects maximum wishlist limit', () => {
    const list = Array.from({ length: 50 }, (_, i) => ({ id: i + 1, name: `Phone ${i + 1}` }))
    const res = addWishlistItem(list, { id: 100, name: 'Phone 100' }, 50)
    expect(res.added).toBe(false)
    expect(res.reason).toBe('max_reached')
    expect(res.items).toHaveLength(50)
  })
})
