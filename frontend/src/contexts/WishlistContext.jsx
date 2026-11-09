import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { useToast } from './ToastContext'

const WishlistContext = createContext(null)

const STORAGE_KEY = 'mobiex_wishlist_items'
const MAX_WISHLIST_ITEMS = 50

export function WishlistProvider({ children }) {
  const { showToast } = useToast()

  const [wishlistItems, setWishlistItems] = useState(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      return saved ? JSON.parse(saved) : []
    } catch {
      return []
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(wishlistItems))
    } catch (err) {
      console.error('Failed to save wishlist items to localStorage', err)
    }
  }, [wishlistItems])

  const isInWishlist = useCallback(
    (productId) => wishlistItems.some((item) => Number(item.id) === Number(productId)),
    [wishlistItems]
  )

  const addToWishlist = useCallback(
    (product) => {
      if (!product || !product.id) return false

      if (isInWishlist(product.id)) {
        showToast(`"${product.name}" đã có trong danh sách yêu thích`, 'info')
        return false
      }

      if (wishlistItems.length >= MAX_WISHLIST_ITEMS) {
        showToast(`Danh sách yêu thích đã đạt tối đa ${MAX_WISHLIST_ITEMS} sản phẩm`, 'warning')
        return false
      }

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
        original_price: product.max_price || product.variants?.[0]?.compare_price || null,
        image_url: primaryImg,
        variants: product.variants || [],
      }

      setWishlistItems((prev) => [item, ...prev])
      showToast(`Đã thêm "${product.name}" vào danh sách yêu thích`, 'success')
      return true
    },
    [wishlistItems, isInWishlist, showToast]
  )

  const removeFromWishlist = useCallback((productId) => {
    setWishlistItems((prev) => prev.filter((item) => Number(item.id) !== Number(productId)))
  }, [])

  const clearWishlist = useCallback(() => {
    setWishlistItems([])
    showToast('Đã xóa tất cả sản phẩm khỏi danh sách yêu thích', 'info')
  }, [showToast])

  const toggleWishlist = useCallback(
    (product) => {
      if (!product || !product.id) return
      if (isInWishlist(product.id)) {
        removeFromWishlist(product.id)
        showToast(`Đã bỏ "${product.name}" khỏi danh sách yêu thích`, 'info')
      } else {
        addToWishlist(product)
      }
    },
    [isInWishlist, removeFromWishlist, addToWishlist, showToast]
  )

  const value = {
    wishlistItems,
    wishlistCount: wishlistItems.length,
    addToWishlist,
    removeFromWishlist,
    clearWishlist,
    isInWishlist,
    toggleWishlist,
  }

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
}

export function useWishlist() {
  const context = useContext(WishlistContext)
  if (!context) {
    throw new Error('useWishlist must be used within a WishlistProvider')
  }
  return context
}

export default WishlistContext
