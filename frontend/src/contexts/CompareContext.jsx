import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { useToast } from './ToastContext'

const CompareContext = createContext(null)

const STORAGE_KEY = 'mobiex_compare_items'
export const MAX_COMPARE_ITEMS = 4

/**
 * Modern inline SVG placeholder representing a sleek smartphone silhouette.
 * Embedded directly as Data URI so it renders instantly with 0 network latency and 0 risk of 404.
 */
export const FALLBACK_PHONE_SVG =
  'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" fill="none"><rect width="240" height="240" rx="20" fill="%23F1F5F9"/><g transform="translate(68,30)"><rect x="0" y="0" width="104" height="180" rx="18" fill="%23FFFFFF" stroke="%23CBD5E1" stroke-width="3"/><rect x="6" y="8" width="92" height="164" rx="12" fill="%23E2E8F0"/><rect x="36" y="14" width="32" height="7" rx="3.5" fill="%2394A3B8"/><circle cx="52" cy="85" r="20" fill="%23CBD5E1"/><path d="M44 80C44 76.7 46.7 74 50 74H54C57.3 74 60 76.7 60 80V90C60 93.3 57.3 96 54 96H50C46.7 96 44 93.3 44 90V80Z" fill="%2394A3B8"/><text x="52" y="126" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="600" fill="%2364748B">MobieX</text></g></svg>'

/**
 * Robust image URL extractor supporting all product representations
 * across backend responses, catalog cards, and search endpoints.
 */
export function getProductThumbnail(product) {
  if (!product) return FALLBACK_PHONE_SVG
  if (typeof product === 'string') {
    return product.trim() && product !== '/placeholder-phone.png' ? product : FALLBACK_PHONE_SVG
  }

  // 1. Direct thumbnail / primary image properties
  if (product.primary_image_url && product.primary_image_url !== '/placeholder-phone.png') {
    return product.primary_image_url
  }
  if (product.thumbnail && product.thumbnail !== '/placeholder-phone.png') {
    return product.thumbnail
  }
  if (product.image_url && product.image_url !== '/placeholder-phone.png') {
    return product.image_url
  }

  // 2. Images array
  if (Array.isArray(product.images) && product.images.length > 0) {
    const primary = product.images.find((img) => img.is_primary) || product.images[0]
    if (primary) {
      const url = primary.image_path || primary.image_url || primary.url
      if (url && url !== '/placeholder-phone.png') return url
    }
  }

  // 3. Variants images
  if (Array.isArray(product.variants) && product.variants.length > 0) {
    for (const v of product.variants) {
      if (v.image_url && v.image_url !== '/placeholder-phone.png') return v.image_url
      if (Array.isArray(v.images) && v.images.length > 0) {
        const vUrl = v.images[0].image_path || v.images[0].image_url
        if (vUrl && vUrl !== '/placeholder-phone.png') return vUrl
      }
    }
  }

  return FALLBACK_PHONE_SVG
}

export function CompareProvider({ children }) {
  const { showToast } = useToast()

  // Initialize and sanitize compare items from localStorage
  const [compareItems, setCompareItems] = useState(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      if (!saved) return []
      const parsed = JSON.parse(saved)
      if (!Array.isArray(parsed)) return []

      return parsed
        .filter((item) => item && item.id)
        .map((item) => ({
          ...item,
          id: Number(item.id),
          image_url:
            item.image_url && item.image_url !== '/placeholder-phone.png'
              ? item.image_url
              : FALLBACK_PHONE_SVG,
        }))
        .slice(0, MAX_COMPARE_ITEMS)
    } catch {
      return []
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(compareItems))
    } catch (err) {
      console.error('Failed to save compare items to localStorage', err)
    }
  }, [compareItems])

  const isInCompare = useCallback(
    (productId) => compareItems.some((item) => Number(item.id) === Number(productId)),
    [compareItems]
  )

  const addToCompare = useCallback(
    (product, silent = false) => {
      if (!product || !product.id) return false

      if (isInCompare(product.id)) {
        if (!silent) {
          showToast(`"${product.name || 'Sản phẩm'}" đã có trong danh sách so sánh`, 'info')
        }
        return false
      }

      if (compareItems.length >= MAX_COMPARE_ITEMS) {
        if (!silent) {
          showToast(`Chỉ có thể so sánh tối đa ${MAX_COMPARE_ITEMS} sản phẩm cùng lúc`, 'warning')
        }
        return false
      }

      const primaryImg = getProductThumbnail(product)
      const minPrice =
        product.base_price ||
        product.min_price ||
        product.price ||
        product.variants?.[0]?.price ||
        0

      const item = {
        id: Number(product.id),
        name: product.name || 'Sản phẩm',
        slug: product.slug || `${product.id}`,
        brand_name: product.brand?.name || product.brand_name || 'MobieX',
        price: Number(minPrice),
        image_url: primaryImg,
      }

      setCompareItems((prev) => [...prev, item])
      if (!silent) {
        showToast(`Đã thêm "${product.name || 'Sản phẩm'}" vào danh sách so sánh`, 'success')
      }
      return true
    },
    [compareItems, isInCompare, showToast]
  )

  const removeFromCompare = useCallback((productId) => {
    setCompareItems((prev) => prev.filter((item) => Number(item.id) !== Number(productId)))
  }, [])

  const clearCompare = useCallback(() => {
    setCompareItems([])
    showToast('Đã xóa tất cả sản phẩm khỏi danh sách so sánh', 'info')
  }, [showToast])

  const replaceCompareItems = useCallback((items) => {
    if (!Array.isArray(items)) return
    const sanitized = items
      .filter((i) => i && i.id)
      .slice(0, MAX_COMPARE_ITEMS)
      .map((i) => ({
        id: Number(i.id),
        name: i.name || 'Sản phẩm',
        slug: i.slug || `${i.id}`,
        brand_name: i.brand?.name || i.brand_name || 'MobieX',
        price: Number(i.price || i.base_price || i.min_price || 0),
        image_url: getProductThumbnail(i),
      }))
    setCompareItems(sanitized)
  }, [])

  const toggleCompare = useCallback(
    (product) => {
      if (!product || !product.id) return
      if (isInCompare(product.id)) {
        removeFromCompare(product.id)
        showToast(`Đã bỏ "${product.name || 'Sản phẩm'}" khỏi danh sách so sánh`, 'info')
      } else {
        addToCompare(product)
      }
    },
    [isInCompare, removeFromCompare, addToCompare, showToast]
  )

  const value = {
    compareItems,
    compareCount: compareItems.length,
    addToCompare,
    removeFromCompare,
    clearCompare,
    replaceCompareItems,
    isInCompare,
    toggleCompare,
    maxItems: MAX_COMPARE_ITEMS,
    getProductThumbnail,
  }

  return <CompareContext.Provider value={value}>{children}</CompareContext.Provider>
}

export function useCompare() {
  const context = useContext(CompareContext)
  if (!context) {
    throw new Error('useCompare must be used within a CompareProvider')
  }
  return context
}

export default CompareContext
