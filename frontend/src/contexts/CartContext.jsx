// src/contexts/CartContext.jsx
// Global shopping cart state — synced with backend via REST API.
// Falls back to empty cart when user is not authenticated.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import { useAuth } from './AuthContext'
import storage from '../utils/storage'

const CartContext = createContext(null)
const COUPON_STORAGE_KEY = 'applied_coupon'

export function CartProvider({ children }) {
  const { user } = useAuth()

  const [cartItems, setCartItems] = useState([])
  const [cartSummary, setCartSummary] = useState({
    subtotal: 0,
    discount: 0,
    total: 0,
    couponCode: storage.get(COUPON_STORAGE_KEY) || null,
    couponInvalidReason: null,
  })
  const [loading, setLoading] = useState(false)

  // ── Fetch cart from backend ────────────────────────────────────────────
  const fetchCart = useCallback(async (forcedCouponCode) => {
    if (!user) {
      setCartItems([])
      setCartSummary({ subtotal: 0, discount: 0, total: 0, couponCode: null, couponInvalidReason: null })
      return
    }
    setLoading(true)
    try {
      const activeCoupon =
        forcedCouponCode !== undefined
          ? (forcedCouponCode || null)
          : (storage.get(COUPON_STORAGE_KEY) || null)

      const params = activeCoupon ? { coupon_code: activeCoupon } : {}
      const { data } = await axiosClient.get(EP.CART, { params })
      const payload = data.data ?? data
      setCartItems(payload.items ?? [])

      if (payload.coupon_code) {
        storage.set(COUPON_STORAGE_KEY, payload.coupon_code)
        setCartSummary({
          subtotal: payload.subtotal ?? 0,
          discount: payload.discount ?? 0,
          total: payload.total ?? (payload.subtotal ?? 0),
          couponCode: payload.coupon_code,
          couponInvalidReason: null,
        })
      } else {
        if (payload.coupon_invalid_reason) {
          storage.remove(COUPON_STORAGE_KEY)
        }
        setCartSummary({
          subtotal: payload.subtotal ?? 0,
          discount: 0,
          total: payload.total ?? (payload.subtotal ?? 0),
          couponCode: null,
          couponInvalidReason: payload.coupon_invalid_reason ?? null,
        })
      }
    } catch {
      // Non-critical — keep existing state
    } finally {
      setLoading(false)
    }
  }, [user])

  // Refresh cart whenever the logged-in user changes
  useEffect(() => {
    fetchCart()
  }, [fetchCart])

  // ── Add item ───────────────────────────────────────────────────────────
  const addToCart = useCallback(async (variantId, quantity = 1) => {
    await axiosClient.post(EP.CART_ITEMS, {
      variant_id: variantId,
      product_variant_id: variantId,
      quantity,
    })
    await fetchCart()
  }, [fetchCart])

  // ── Update quantity ────────────────────────────────────────────────────
  const updateQuantity = useCallback(async (cartItemId, quantity) => {
    await axiosClient.put(EP.CART_ITEM(cartItemId), { quantity })
    await fetchCart()
  }, [fetchCart])

  // ── Remove item ────────────────────────────────────────────────────────
  const removeFromCart = useCallback(async (cartItemId) => {
    await axiosClient.delete(EP.CART_ITEM(cartItemId))
    await fetchCart()
  }, [fetchCart])

  // ── Apply coupon ───────────────────────────────────────────────────────
  const applyCoupon = useCallback(async (couponCode) => {
    const code = (couponCode || '').trim().toUpperCase()
    if (!code) {
      throw new Error('Vui lòng nhập mã giảm giá')
    }
    const { data } = await axiosClient.post(EP.CART_APPLY_COUPON, { coupon_code: code })
    const resPayload = data.data ?? data
    storage.set(COUPON_STORAGE_KEY, code)
    await fetchCart(code)
    return {
      status: true,
      message: data.message || 'Áp dụng mã giảm giá thành công!',
      ...resPayload,
    }
  }, [fetchCart])

  // ── Clear coupon ───────────────────────────────────────────────────────
  const clearCoupon = useCallback(async () => {
    storage.remove(COUPON_STORAGE_KEY)
    setCartSummary((prev) => ({
      ...prev,
      discount: 0,
      total: prev.subtotal,
      couponCode: null,
      couponInvalidReason: null,
    }))
    await fetchCart('')
  }, [fetchCart])

  // ── Clear entire cart ───────────────────────────────────────────────────
  const clearCart = useCallback(async () => {
    storage.remove(COUPON_STORAGE_KEY)
    await axiosClient.delete(EP.CART_CLEAR)
    await fetchCart('')
  }, [fetchCart])

  // ── Computed ───────────────────────────────────────────────────────────
  // Distinct line items count (not sum of quantities)
  // Badge shows number of different products in cart, capped at 99
  const itemCount = cartItems.length
  const cartBadgeCount = itemCount > 99 ? '99+' : itemCount

  return (
    <CartContext.Provider
      value={{
        cartItems,
        cartSummary,
        itemCount,
        cartBadgeCount,
        loading,
        fetchCart,
        addToCart,
        updateQuantity,
        removeFromCart,
        clearCart,
        applyCoupon,
        clearCoupon,
      }}
    >
      {children}
    </CartContext.Provider>
  )
}

export function useCart() {
  const ctx = useContext(CartContext)
  if (!ctx) throw new Error('useCart must be used inside <CartProvider>')
  return ctx
}
