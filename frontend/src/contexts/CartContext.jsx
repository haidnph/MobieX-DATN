// src/contexts/CartContext.jsx
// Global shopping cart state — synced with backend via REST API.
// Falls back to empty cart when user is not authenticated.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import { useAuth } from './AuthContext'

const CartContext = createContext(null)

export function CartProvider({ children }) {
  const { user } = useAuth()

  const [cartItems, setCartItems] = useState([])
  const [cartSummary, setCartSummary] = useState({
    subtotal: 0,
    discount: 0,
    total: 0,
    couponCode: null,
  })
  const [loading, setLoading] = useState(false)

  // ── Fetch cart from backend ────────────────────────────────────────────
  const fetchCart = useCallback(async () => {
    if (!user) {
      setCartItems([])
      setCartSummary({ subtotal: 0, discount: 0, total: 0, couponCode: null })
      return
    }
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.CART)
      const payload = data.data ?? data
      setCartItems(payload.items ?? [])
      setCartSummary({
        subtotal: payload.subtotal ?? 0,
        discount: payload.discount ?? 0,
        total: payload.total ?? 0,
        couponCode: payload.coupon_code ?? null,
      })
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
    const { data } = await axiosClient.post(EP.CART_APPLY_COUPON, { coupon_code: couponCode })
    await fetchCart()
    return data?.data ?? data
  }, [fetchCart])

  // ── Clear coupon ───────────────────────────────────────────────────────
  const clearCoupon = useCallback(() => {
    setCartSummary((prev) => ({ ...prev, discount: 0, couponCode: null }))
  }, [])

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
