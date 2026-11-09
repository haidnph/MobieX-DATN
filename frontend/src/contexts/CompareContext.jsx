import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { useToast } from './ToastContext'

const CompareContext = createContext(null)

const STORAGE_KEY = 'mobiex_compare_items'
const MAX_COMPARE_ITEMS = 4

export function CompareProvider({ children }) {
  const { showToast } = useToast()

  const [compareItems, setCompareItems] = useState(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      return saved ? JSON.parse(saved) : []
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
    (product) => {
      if (!product || !product.id) return false

      if (isInCompare(product.id)) {
        showToast(`"${product.name}" đã có trong danh sách so sánh`, 'info')
        return false
      }

      if (compareItems.length >= MAX_COMPARE_ITEMS) {
        showToast(`Chỉ có thể so sánh tối đa ${MAX_COMPARE_ITEMS} sản phẩm cùng lúc`, 'warning')
        return false
      }

      // Chuẩn hóa item gọn gàng để lưu trong localStorage
      const primaryImg =
        product.primary_image_url ||
        product.images?.find((img) => img.is_primary)?.image_url ||
        product.images?.[0]?.image_url ||
        '/placeholder-phone.png'

      const item = {
        id: product.id,
        name: product.name,
        slug: product.slug,
        brand_name: product.brand?.name || '',
        price: product.base_price || product.variants?.[0]?.price || 0,
        image_url: primaryImg,
      }

      setCompareItems((prev) => [...prev, item])
      showToast(`Đã thêm "${product.name}" vào danh sách so sánh`, 'success')
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

  const toggleCompare = useCallback(
    (product) => {
      if (!product || !product.id) return
      if (isInCompare(product.id)) {
        removeFromCompare(product.id)
        showToast(`Đã bỏ "${product.name}" khỏi danh sách so sánh`, 'info')
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
    isInCompare,
    toggleCompare,
    maxItems: MAX_COMPARE_ITEMS,
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
