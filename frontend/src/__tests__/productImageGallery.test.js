import { describe, it, expect } from 'vitest'

// Helper function that mirrors gallery reordering and primary promotion logic
function reorderGallery(items, fromIndex, toIndex) {
  if (fromIndex < 0 || fromIndex >= items.length || toIndex < 0 || toIndex >= items.length || fromIndex === toIndex) {
    return items
  }
  const copy = [...items]
  const [moved] = copy.splice(fromIndex, 1)
  copy.splice(toIndex, 0, moved)
  return copy.map((item, idx) => ({ ...item, sort_order: idx }))
}

function setPrimaryImage(items, targetId) {
  return items.map((item) => ({
    ...item,
    is_primary: item.id === targetId,
  }))
}

function deleteGalleryImage(items, targetId) {
  const target = items.find((item) => item.id === targetId)
  const remaining = items.filter((item) => item.id !== targetId)
  if (target?.is_primary && remaining.length > 0) {
    remaining[0] = { ...remaining[0], is_primary: true }
  }
  return remaining.map((item, idx) => ({ ...item, sort_order: idx }))
}

function resolveProductThumbnail(product) {
  if (!product) return null
  const primaryImg = product.images?.find((img) => img.is_primary)
  if (primaryImg?.image_url) return primaryImg.image_url
  if (primaryImg?.image_path) return primaryImg.image_path

  if (product.primary_image_url) return product.primary_image_url
  if (product.thumbnail) return product.thumbnail

  const firstImg = product.images?.[0]
  if (firstImg?.image_url) return firstImg.image_url
  if (firstImg?.image_path) return firstImg.image_path

  return null
}

function validateUploadFiles(files, maxFiles = 10, maxMb = 5) {
  const allowed = ['image/jpeg', 'image/png', 'image/webp']
  const valid = []
  const errors = []

  Array.from(files).slice(0, maxFiles).forEach((f) => {
    if (!allowed.includes(f.type)) {
      errors.push(`"${f.name}": Sai định dạng`)
      return
    }
    if (f.size > maxMb * 1024 * 1024) {
      errors.push(`"${f.name}": Vượt quá ${maxMb}MB`)
      return
    }
    valid.push(f)
  })

  return { valid, errors }
}

describe('Product Image Gallery & Upload Logic', () => {
  it('correctly reorders images and recalculates sort_order', () => {
    const list = [
      { id: 10, sort_order: 0, is_primary: true },
      { id: 11, sort_order: 1, is_primary: false },
      { id: 12, sort_order: 2, is_primary: false },
    ]

    const reordered = reorderGallery(list, 2, 0)
    expect(reordered.map((img) => img.id)).toEqual([12, 10, 11])
    expect(reordered[0].sort_order).toBe(0)
    expect(reordered[1].sort_order).toBe(1)
    expect(reordered[2].sort_order).toBe(2)
  })

  it('sets target image as primary and clears previous primary', () => {
    const list = [
      { id: 1, is_primary: true },
      { id: 2, is_primary: false },
      { id: 3, is_primary: false },
    ]

    const updated = setPrimaryImage(list, 2)
    expect(updated[0].is_primary).toBe(false)
    expect(updated[1].is_primary).toBe(true)
    expect(updated[2].is_primary).toBe(false)
  })

  it('automatically promotes the next available image when primary image is deleted', () => {
    const list = [
      { id: 100, is_primary: true },
      { id: 101, is_primary: false },
      { id: 102, is_primary: false },
    ]

    const afterDelete = deleteGalleryImage(list, 100)
    expect(afterDelete).toHaveLength(2)
    expect(afterDelete[0].id).toBe(101)
    expect(afterDelete[0].is_primary).toBe(true)
    expect(afterDelete[1].is_primary).toBe(false)
  })

  it('resolves product primary thumbnail uniformly across different object shapes', () => {
    const productWithPrimary = {
      images: [
        { id: 1, image_url: 'https://cdn.example.com/sec.jpg', is_primary: false },
        { id: 2, image_url: 'https://cdn.example.com/main.jpg', is_primary: true },
      ],
      thumbnail: 'https://cdn.example.com/fallback.jpg',
    }
    expect(resolveProductThumbnail(productWithPrimary)).toBe('https://cdn.example.com/main.jpg')

    const productWithThumbnailOnly = {
      thumbnail: 'https://cdn.example.com/single.jpg',
    }
    expect(resolveProductThumbnail(productWithThumbnailOnly)).toBe('https://cdn.example.com/single.jpg')

    const productEmpty = {}
    expect(resolveProductThumbnail(productEmpty)).toBeNull()
  })

  it('validates file uploads rejecting bad formats and oversized files while preserving valid files', () => {
    const fakeFiles = [
      { name: 'photo.jpg', type: 'image/jpeg', size: 1024 * 1024 },
      { name: 'document.pdf', type: 'application/pdf', size: 500 * 1024 },
      { name: 'big_photo.png', type: 'image/png', size: 6 * 1024 * 1024 },
      { name: 'clean.webp', type: 'image/webp', size: 2 * 1024 * 1024 },
    ]

    const result = validateUploadFiles(fakeFiles)
    expect(result.valid).toHaveLength(2)
    expect(result.valid[0].name).toBe('photo.jpg')
    expect(result.valid[1].name).toBe('clean.webp')
    expect(result.errors).toHaveLength(2)
  })

  it('correctly calculates lightbox next and prev indices with wrap-around', () => {
    const total = 5
    const getNext = (current) => (current + 1) % total
    const getPrev = (current) => (current - 1 + total) % total

    expect(getNext(0)).toBe(1)
    expect(getNext(4)).toBe(0) // wrap around
    expect(getPrev(0)).toBe(4) // wrap around
    expect(getPrev(3)).toBe(2)
  })

  it('normalizes client gallery images sorting by sort_order and deduplicating', () => {
    const rawImages = [
      { id: 2, image_url: 'https://cdn.example.com/2.jpg', sort_order: 1 },
      { id: 1, image_url: 'https://cdn.example.com/1.jpg', sort_order: 0 },
      { id: 3, image_url: 'https://cdn.example.com/1.jpg', sort_order: 2 }, // duplicate URL
    ]

    const sorted = [...rawImages].sort((a, b) => a.sort_order - b.sort_order)
    const unique = [...new Set(sorted.map((img) => img.image_url))]

    expect(unique).toEqual([
      'https://cdn.example.com/1.jpg',
      'https://cdn.example.com/2.jpg',
    ])
  })
})
