// src/features/checkout/CheckoutPage.jsx
// Order checkout page: shipping information, payment method selection, and atomic order creation.

import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useCart } from '../../contexts/CartContext'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'

export default function CheckoutPage() {
  const { cartItems, cartSummary, fetchCart } = useCart()
  const { user } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [form, setForm] = useState({
    recipient_name: user?.name || '',
    phone: user?.phone || '',
    shipping_address: '',
    city: 'Hà Nội',
    note: '',
    payment_method: 'cod', // 'cod' or 'vnpay'
  })
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setErrors((prev) => ({ ...prev, [e.target.name]: '' }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (cartItems.length === 0) {
      showToast('Giỏ hàng của bạn đang trống', 'error')
      navigate('/cart')
      return
    }

    setSubmitting(true)
    setErrors({})

    try {
      // Build order payload matching backend OrderController store endpoint
      const payload = {
        recipient_name: form.recipient_name,
        recipient_phone: form.phone,
        shipping_address: `${form.shipping_address}, ${form.city}`,
        note: form.note,
        payment_method: form.payment_method,
        coupon_code: cartSummary.couponCode || undefined,
        items: cartItems.map((item) => ({
          product_variant_id: item.product_variant_id || item.variant?.id,
          quantity: item.quantity,
        })),
      }

      const { data } = await axiosClient.post(EP.ORDERS, payload)
      const order = data.data ?? data

      // Refresh cart to reflect empty state
      await fetchCart()

      if (form.payment_method === 'vnpay') {
        // Redirect to VNPay initiate page
        navigate(`/payment/vnpay?order_id=${order.id}`)
      } else {
        // COD order created successfully
        showToast('Đặt hàng thành công!', 'success')
        navigate(`/orders/${order.id}?success=1`)
      }
    } catch (err) {
      if (err.validationErrors) {
        setErrors(err.validationErrors)
      }
      showToast(err.message || 'Không thể tạo đơn hàng, vui lòng thử lại', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  if (cartItems.length === 0) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-16 text-center space-y-4">
        <span className="material-symbols-outlined text-6xl text-outline">shopping_bag</span>
        <h2 className="text-xl font-bold text-on-surface">Giỏ hàng trống</h2>
        <p className="text-sm text-on-surface-variant">Vui lòng thêm sản phẩm vào giỏ trước khi thanh toán.</p>
        <Link to="/products" className="inline-block text-primary hover:underline">
          Quay lại cửa hàng
        </Link>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-8">
      {/* Title */}
      <div className="flex items-center gap-3">
        <span className="w-1 h-6 bg-primary rounded-full" />
        <h1 className="text-2xl font-bold text-on-surface">Thanh toán & Đặt hàng</h1>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Left Form: Shipping & Payment (7 cols) */}
        <div className="lg:col-span-7 space-y-6">
          {/* Shipping info */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4">
            <div className="flex items-center gap-2 text-base font-bold text-on-surface">
              <span className="material-symbols-outlined text-primary text-xl">local_shipping</span>
              Thông tin nhận hàng
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input
                label="Họ và tên người nhận"
                name="recipient_name"
                value={form.recipient_name}
                onChange={handleChange}
                placeholder="Nguyễn Văn A"
                error={errors.recipient_name?.[0]}
                required
              />
              <Input
                label="Số điện thoại"
                name="phone"
                value={form.phone}
                onChange={handleChange}
                placeholder="0912345678"
                error={errors.phone?.[0] || errors.recipient_phone?.[0]}
                required
              />
            </div>

            <Input
              label="Địa chỉ chi tiết (số nhà, đường, phường/xã)"
              name="shipping_address"
              value={form.shipping_address}
              onChange={handleChange}
              placeholder="Số 123 đường Cầu Giấy, Phường Dịch Vọng"
              error={errors.shipping_address?.[0]}
              required
            />

            <Input
              label="Tỉnh / Thành phố"
              name="city"
              value={form.city}
              onChange={handleChange}
              placeholder="Hà Nội, TP.HCM, ..."
              required
            />

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium text-on-surface-variant">Ghi chú đơn hàng (tùy chọn)</label>
              <textarea
                name="note"
                value={form.note}
                onChange={handleChange}
                rows={2}
                placeholder="Ví dụ: Giao giờ hành chính, gọi trước khi đến..."
                className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all resize-none"
              />
            </div>
          </div>

          {/* Payment Method */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4">
            <div className="flex items-center gap-2 text-base font-bold text-on-surface">
              <span className="material-symbols-outlined text-primary text-xl">payments</span>
              Phương thức thanh toán
            </div>

            <div className="space-y-3">
              <label
                className={[
                  'flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all',
                  form.payment_method === 'cod'
                    ? 'border-primary bg-primary/10'
                    : 'border-outline-variant/30 bg-surface-container-low hover:border-outline',
                ].join(' ')}
              >
                <div className="flex items-center gap-3">
                  <input
                    type="radio"
                    name="payment_method"
                    value="cod"
                    checked={form.payment_method === 'cod'}
                    onChange={handleChange}
                    className="text-primary focus:ring-primary"
                  />
                  <div>
                    <p className="text-sm font-semibold text-on-surface">Thanh toán khi nhận hàng (COD)</p>
                    <p className="text-xs text-on-surface-variant">Nhận hàng rồi thanh toán tiền mặt cho shipper</p>
                  </div>
                </div>
                <span className="material-symbols-outlined text-2xl text-on-surface-variant">local_atm</span>
              </label>

              <label
                className={[
                  'flex items-center justify-between p-4 rounded-xl border cursor-pointer transition-all',
                  form.payment_method === 'vnpay'
                    ? 'border-primary bg-primary/10'
                    : 'border-outline-variant/30 bg-surface-container-low hover:border-outline',
                ].join(' ')}
              >
                <div className="flex items-center gap-3">
                  <input
                    type="radio"
                    name="payment_method"
                    value="vnpay"
                    checked={form.payment_method === 'vnpay'}
                    onChange={handleChange}
                    className="text-primary focus:ring-primary"
                  />
                  <div>
                    <p className="text-sm font-semibold text-on-surface">Cổng thanh toán VNPay</p>
                    <p className="text-xs text-on-surface-variant">Thẻ ATM nội địa, Visa/Mastercard, VNPay QR</p>
                  </div>
                </div>
                <span className="material-symbols-outlined text-2xl text-primary">credit_card</span>
              </label>
            </div>
          </div>
        </div>

        {/* Right Order Review (5 cols) */}
        <div className="lg:col-span-5 space-y-4">
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6">
            <h2 className="text-lg font-bold text-on-surface">Chi tiết đơn hàng</h2>

            {/* Items list */}
            <div className="divide-y divide-outline-variant/10 max-h-64 overflow-y-auto pr-1">
              {cartItems.map((item) => {
                const variant = item.variant || item.product_variant || {}
                const product = variant.product || item.product || {}
                const price = item.price ?? variant.price ?? 0

                return (
                  <div key={item.id} className="py-3 flex items-center gap-3">
                    <div className="w-12 h-12 bg-surface-container-high rounded-lg overflow-hidden shrink-0 flex items-center justify-center p-1">
                      {variant.image_url || product.thumbnail ? (
                        <img
                          src={variant.image_url || product.thumbnail}
                          alt={product.name}
                          className="w-full h-full object-contain"
                        />
                      ) : (
                        <span className="material-symbols-outlined text-xl text-outline">devices</span>
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-semibold text-on-surface truncate">{product.name}</p>
                      <p className="text-[11px] text-outline">
                        x{item.quantity} • {variant.name || 'Mặc định'}
                      </p>
                    </div>
                    <span className="text-xs font-bold text-on-surface shrink-0">
                      {formatCurrencyVND(price * item.quantity)}
                    </span>
                  </div>
                )
              })}
            </div>

            {/* Calculations */}
            <div className="space-y-2.5 pt-4 border-t border-outline-variant/10 text-sm">
              <div className="flex justify-between text-on-surface-variant">
                <span>Tạm tính</span>
                <span className="text-on-surface font-medium">{formatCurrencyVND(cartSummary.subtotal)}</span>
              </div>

              {cartSummary.discount > 0 && (
                <div className="flex justify-between text-secondary">
                  <span>Giảm giá ({cartSummary.couponCode})</span>
                  <span>-{formatCurrencyVND(cartSummary.discount)}</span>
                </div>
              )}

              <div className="flex justify-between text-on-surface-variant">
                <span>Vận chuyển</span>
                <span className="text-secondary font-medium">Miễn phí</span>
              </div>

              <div className="pt-3 border-t border-outline-variant/10 flex justify-between items-baseline">
                <span className="text-base font-bold text-on-surface">Tổng thanh toán</span>
                <span className="text-2xl font-extrabold text-primary">
                  {formatCurrencyVND(cartSummary.total || cartSummary.subtotal - cartSummary.discount)}
                </span>
              </div>
            </div>

            <Button
              type="submit"
              variant="primary"
              size="lg"
              className="w-full"
              loading={submitting}
            >
              {form.payment_method === 'vnpay' ? 'Thanh toán qua VNPay' : 'Hoàn tất đặt hàng'}
            </Button>

            <p className="text-[11px] text-outline text-center">
              Bằng việc bấm đặt hàng, bạn đồng ý với Điều khoản dịch vụ và Chính sách của MobieX.
            </p>
          </div>
        </div>
      </form>
    </div>
  )
}
