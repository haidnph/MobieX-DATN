// src/features/cart/CartPage.jsx
// Full shopping cart page with item quantity management, coupon code input, and order summary.

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useCart } from '../../contexts/CartContext'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'
import Spinner from '../../components/common/Spinner'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'

export default function CartPage() {
  const {
    cartItems,
    cartSummary,
    updateQuantity,
    removeFromCart,
    clearCart,
    applyCoupon,
    clearCoupon,
    loading,
  } = useCart()
  const { user } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [couponInput, setCouponInput] = useState('')
  const [applyingCoupon, setApplyingCoupon] = useState(false)
  const [updatingId, setUpdatingId] = useState(null)
  const [clearDialogOpen, setClearDialogOpen] = useState(false)
  const [clearingCart, setClearingCart] = useState(false)

  const handleClearCart = async () => {
    setClearingCart(true)
    try {
      await clearCart()
      showToast('Đã xóa toàn bộ sản phẩm khỏi giỏ hàng', 'info')
      setClearDialogOpen(false)
    } catch (err) {
      showToast(err.message || 'Không thể xóa giỏ hàng', 'error')
    } finally {
      setClearingCart(false)
    }
  }

  const handleQuantity = async (itemId, currentQty, delta, maxStock) => {
    const newQty = currentQty + delta
    if (newQty < 1) return
    if (maxStock && newQty > maxStock) {
      showToast(`Số lượng tối đa có thể mua là ${maxStock}`, 'warning')
      return
    }

    setUpdatingId(itemId)
    try {
      await updateQuantity(itemId, newQty)
    } catch (err) {
      showToast(err.message || 'Không thể cập nhật số lượng', 'error')
    } finally {
      setUpdatingId(null)
    }
  }

  const handleRemove = async (itemId) => {
    setUpdatingId(itemId)
    try {
      await removeFromCart(itemId)
      showToast('Đã xóa sản phẩm khỏi giỏ hàng', 'info')
    } catch (err) {
      showToast(err.message || 'Không thể xóa sản phẩm', 'error')
    } finally {
      setUpdatingId(null)
    }
  }

  const handleApplyCoupon = async (e) => {
    e.preventDefault()
    if (!couponInput.trim()) return
    setApplyingCoupon(true)
    try {
      const res = await applyCoupon(couponInput.trim())
      showToast(res.message || 'Áp dụng mã giảm giá thành công!', 'success')
      setCouponInput('')
    } catch (err) {
      showToast(err.message || 'Mã giảm giá không hợp lệ', 'error')
    } finally {
      setApplyingCoupon(false)
    }
  }

  const handleProceedToCheckout = () => {
    if (!user) {
      navigate('/login', { state: { from: { pathname: '/checkout' } } })
      return
    }
    navigate('/checkout')
  }

  if (loading && cartItems.length === 0) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" />
      </div>
    )
  }

  if (cartItems.length === 0) {
    return (
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-16 flex flex-col items-center justify-center text-center space-y-4">
        <span className="material-symbols-outlined text-8xl text-outline">remove_shopping_cart</span>
        <h1 className="text-2xl font-bold text-on-surface">Giỏ hàng của bạn đang trống</h1>
        <p className="text-on-surface-variant text-sm max-w-sm">
          Khám phá ngay hàng trăm thiết bị công nghệ và phụ kiện đỉnh cao tại MobieX!
        </p>
        <Link
          to="/products"
          className="inline-flex items-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-semibold hover:bg-primary hover:text-on-primary transition-all mt-2"
        >
          Tiếp tục mua sắm
          <span className="material-symbols-outlined text-lg">arrow_forward</span>
        </Link>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-8">
      {/* Title */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="w-1 h-6 bg-primary rounded-full" />
          <h1 className="text-2xl font-bold text-on-surface">Giỏ hàng ({cartItems.length} sản phẩm)</h1>
        </div>
        {cartItems.length > 0 && (
          <button
            type="button"
            onClick={() => setClearDialogOpen(true)}
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-error hover:bg-error-container/20 px-3 py-1.5 rounded-xl border border-error/30 transition-colors"
          >
            <span className="material-symbols-outlined text-base">delete_sweep</span>
            Xóa toàn bộ giỏ hàng
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Cart items list (8 cols) */}
        <div className="lg:col-span-8 space-y-4">
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden divide-y divide-outline-variant/10">
            {cartItems.map((item) => {
              const variant = item.variant || item.product_variant || {}
              const product = variant.product || item.product || {}
              const maxStock = variant.inventory?.available_quantity ?? variant.available_quantity ?? 99
              const price = item.price ?? variant.price ?? 0
              const itemTotal = price * item.quantity

              return (
                <div key={item.id} className="p-4 md:p-6 flex flex-col sm:flex-row items-center gap-4">
                  {/* Thumbnail */}
                  <div className="w-20 h-20 bg-surface-container-high rounded-xl overflow-hidden shrink-0 flex items-center justify-center p-2">
                    {variant.image_url || product.thumbnail ? (
                      <img
                        src={variant.image_url || product.thumbnail}
                        alt={product.name}
                        className="w-full h-full object-contain"
                      />
                    ) : (
                      <span className="material-symbols-outlined text-3xl text-outline">smartphone</span>
                    )}
                  </div>

                  {/* Info */}
                  <div className="flex-1 min-w-0 text-center sm:text-left space-y-1">
                    <Link
                      to={`/products/${product.id}`}
                      className="text-base font-semibold text-on-surface hover:text-primary transition-colors line-clamp-1"
                    >
                      {product.name || 'Sản phẩm'}
                    </Link>
                    <p className="text-xs text-on-surface-variant">
                      Phiên bản: {variant.name || variant.option_signature || 'Mặc định'}
                    </p>
                    <p className="text-sm font-bold text-primary">
                      {formatCurrencyVND(price)}
                    </p>
                  </div>

                  {/* Quantity control */}
                  <div className="flex items-center border border-outline-variant/40 rounded-xl overflow-hidden bg-surface-container-low shrink-0">
                    <button
                      type="button"
                      onClick={() => handleQuantity(item.id, item.quantity, -1, maxStock)}
                      disabled={item.quantity <= 1 || updatingId === item.id}
                      className="px-2.5 py-1.5 text-on-surface hover:bg-surface-container-high transition-colors disabled:opacity-30"
                    >
                      <span className="material-symbols-outlined text-sm">remove</span>
                    </button>
                    <span className="px-3 py-1.5 text-sm font-semibold text-on-surface min-w-[2rem] text-center">
                      {updatingId === item.id ? '...' : item.quantity}
                    </span>
                    <button
                      type="button"
                      onClick={() => handleQuantity(item.id, item.quantity, 1, maxStock)}
                      disabled={item.quantity >= maxStock || updatingId === item.id}
                      className="px-2.5 py-1.5 text-on-surface hover:bg-surface-container-high transition-colors disabled:opacity-30"
                    >
                      <span className="material-symbols-outlined text-sm">add</span>
                    </button>
                  </div>

                  {/* Subtotal */}
                  <div className="text-right shrink-0 min-w-[100px]">
                    <p className="text-sm font-extrabold text-on-surface">
                      {formatCurrencyVND(itemTotal)}
                    </p>
                  </div>

                  {/* Remove */}
                  <button
                    onClick={() => handleRemove(item.id)}
                    disabled={updatingId === item.id}
                    className="p-2 text-outline hover:text-error transition-colors rounded-lg hover:bg-surface-container-high shrink-0"
                    title="Xóa khỏi giỏ"
                  >
                    <span className="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>
              )
            })}
          </div>

          <div className="flex justify-between items-center pt-2">
            <Link
              to="/products"
              className="inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium"
            >
              <span className="material-symbols-outlined text-base">arrow_back</span>
              Chọn thêm sản phẩm khác
            </Link>
          </div>
        </div>

        {/* Right Summary (4 cols) */}
        <div className="lg:col-span-4 space-y-4">
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6">
            <h2 className="text-lg font-bold text-on-surface">Tóm tắt đơn hàng</h2>

            {/* Coupon input */}
            <form onSubmit={handleApplyCoupon} className="space-y-2">
              <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                Mã giảm giá
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={couponInput}
                  onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
                  placeholder="Nhập mã voucher..."
                  className="flex-1 bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary uppercase font-mono"
                />
                <Button type="submit" variant="secondary" size="sm" loading={applyingCoupon}>
                  Áp dụng
                </Button>
              </div>

              {cartSummary.couponCode && (
                <div className="flex items-center justify-between bg-secondary-container/10 border border-secondary/20 rounded-xl px-3 py-2 mt-2">
                  <div className="flex items-center gap-1.5 text-xs text-secondary font-semibold">
                    <span className="material-symbols-outlined text-sm">check_circle</span>
                    Mã: {cartSummary.couponCode}
                  </div>
                  <button
                    type="button"
                    onClick={clearCoupon}
                    className="text-xs text-outline hover:text-error transition-colors"
                  >
                    Bỏ mã
                  </button>
                </div>
              )}
            </form>

            {/* Price lines */}
            <div className="space-y-3 pt-4 border-t border-outline-variant/10 text-sm">
              <div className="flex justify-between text-on-surface-variant">
                <span>Tạm tính</span>
                <span className="text-on-surface font-medium">
                  {formatCurrencyVND(cartSummary.subtotal)}
                </span>
              </div>

              {cartSummary.discount > 0 && (
                <div className="flex justify-between text-secondary">
                  <span>Giảm giá</span>
                  <span>-{formatCurrencyVND(cartSummary.discount)}</span>
                </div>
              )}

              <div className="flex justify-between text-on-surface-variant">
                <span>Phí vận chuyển</span>
                <span className="text-secondary font-medium">Miễn phí</span>
              </div>

              <div className="pt-3 border-t border-outline-variant/10 flex justify-between items-baseline">
                <span className="text-base font-bold text-on-surface">Tổng cộng</span>
                <span className="text-2xl font-extrabold text-primary">
                  {formatCurrencyVND(cartSummary.total || cartSummary.subtotal - cartSummary.discount)}
                </span>
              </div>
            </div>

            <Button
              variant="primary"
              size="lg"
              className="w-full mt-4"
              onClick={handleProceedToCheckout}
            >
              Tiến hành thanh toán
              <span className="material-symbols-outlined text-lg">arrow_forward</span>
            </Button>
          </div>
        </div>
      </div>

      {/* Clear Cart Confirmation Dialog */}
      <ConfirmDialog
        open={clearDialogOpen}
        onCancel={() => setClearDialogOpen(false)}
        onConfirm={handleClearCart}
        title="Xóa toàn bộ giỏ hàng?"
        message="Bạn có chắc chắn muốn xóa tất cả sản phẩm khỏi giỏ hàng không? Thao tác này không thể hoàn tác."
        confirmLabel="Xóa toàn bộ"
        loading={clearingCart}
      />
    </div>
  )
}
