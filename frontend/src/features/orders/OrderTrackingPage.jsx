// src/features/orders/OrderTrackingPage.jsx
// Public guest order tracking page: search order by order_code and recipient phone.

import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDateTime } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Badge from '../../components/common/Badge'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ xác nhận', variant: 'pending', icon: 'hourglass_empty', step: 1 },
  confirmed: { label: 'Đã xác nhận', variant: 'info', icon: 'check_circle', step: 2 },
  processing: { label: 'Đang đóng gói', variant: 'info', icon: 'inventory', step: 3 },
  shipping: { label: 'Đang giao hàng', variant: 'warning', icon: 'local_shipping', step: 4 },
  delivered: { label: 'Giao thành công', variant: 'success', icon: 'verified', step: 5 },
  cancelled: { label: 'Đã hủy', variant: 'error', icon: 'cancel', step: 0 },
}

const TIMELINE_STEPS = [
  { key: 'pending', label: 'Đặt hàng' },
  { key: 'confirmed', label: 'Xác nhận' },
  { key: 'processing', label: 'Đóng gói' },
  { key: 'shipping', label: 'Vận chuyển' },
  { key: 'delivered', label: 'Giao hàng' },
]

export default function OrderTrackingPage() {
  const [searchParams] = useSearchParams()
  const { showToast } = useToast()

  const [form, setForm] = useState({
    order_code: searchParams.get('code') || '',
    phone: searchParams.get('phone') || '',
  })
  const [errors, setErrors] = useState({})
  const [searching, setSearching] = useState(false)
  const [order, setOrder] = useState(null)
  const [searched, setSearched] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    const code = form.order_code.trim()
    const phone = form.phone.trim()

    const newErrors = {}
    if (!code) newErrors.order_code = 'Vui lòng nhập mã đơn hàng'
    if (!phone) newErrors.phone = 'Vui lòng nhập số điện thoại đặt hàng'

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors)
      return
    }

    setErrors({})
    setSearching(true)
    setSearched(true)

    try {
      const { data } = await axiosClient.post(EP.ORDER_LOOKUP, {
        order_code: code,
        phone,
      })
      const foundOrder = data.data ?? data
      setOrder(foundOrder)
      showToast('Đã tìm thấy thông tin đơn hàng!', 'success')
    } catch (err) {
      setOrder(null)
      const msg = err.response?.data?.message || 'Không tìm thấy đơn hàng phù hợp.'
      showToast(msg, 'error')
    } finally {
      setSearching(false)
    }
  }

  const currentCfg = order ? STATUS_CONFIG[order.status] || { label: order.status, variant: 'neutral' } : null
  const currentStep = currentCfg?.step ?? 0
  const isCancelled = order?.status === 'cancelled'

  return (
    <div className="max-w-4xl mx-auto px-4 md:px-8 py-10 space-y-8">
      {/* Page Header */}
      <div className="text-center space-y-2">
        <div className="inline-flex items-center gap-2 px-3 py-1 bg-primary-container/20 text-primary rounded-full text-xs font-semibold">
          <span className="material-symbols-outlined text-base">travel_explore</span>
          Tra cứu nhanh không cần đăng nhập
        </div>
        <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface">
          Tra cứu tiến trình đơn hàng
        </h1>
        <p className="text-sm text-on-surface-variant max-w-md mx-auto">
          Nhập mã đơn hàng và số điện thoại người nhận để kiểm tra trạng thái đóng gói và hành trình vận chuyển tức thì.
        </p>
      </div>

      {/* Lookup Form Card */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 md:p-8 shadow-sm">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input
              label="Mã đơn hàng *"
              placeholder="VD: ORD-202611-123456"
              value={form.order_code}
              onChange={(e) => setForm({ ...form, order_code: e.target.value.toUpperCase() })}
              error={errors.order_code}
              required
            />
            <Input
              label="Số điện thoại đặt hàng *"
              placeholder="VD: 0912345678"
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
              error={errors.phone}
              required
            />
          </div>

          <div className="flex justify-end pt-2">
            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={searching}
              className="w-full md:w-auto px-8 flex items-center justify-center gap-2"
            >
              <span className="material-symbols-outlined text-lg">search</span>
              Tra cứu đơn hàng
            </Button>
          </div>
        </form>
      </div>

      {/* Result Section */}
      {searching && (
        <div className="flex flex-col items-center justify-center py-16 gap-3">
          <Spinner size="lg" />
          <p className="text-sm text-on-surface-variant">Đang tra cứu dữ liệu đơn hàng...</p>
        </div>
      )}

      {!searching && searched && !order && (
        <div className="bg-surface-container rounded-2xl p-10 text-center space-y-3 border border-outline-variant/20">
          <span className="material-symbols-outlined text-5xl text-outline">search_off</span>
          <h3 className="text-base font-bold text-on-surface">Không tìm thấy đơn hàng</h3>
          <p className="text-xs text-on-surface-variant max-w-md mx-auto">
            Vui lòng kiểm tra lại chính xác Mã đơn hàng (bắt đầu bằng ORD-) và Số điện thoại đã nhập khi thanh toán.
          </p>
        </div>
      )}

      {!searching && order && (
        <div className="space-y-6 animate-fadeIn">
          {/* Status & Summary */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/10">
              <div>
                <span className="text-xs text-on-surface-variant font-medium">Mã đơn hàng</span>
                <h2 className="text-xl font-mono font-extrabold text-primary">#{order.order_code}</h2>
                <p className="text-xs text-outline mt-0.5">Đặt lúc: {formatDateTime(order.created_at)}</p>
              </div>
              <Badge variant={currentCfg.variant} className="text-sm px-4 py-1.5 flex items-center gap-1.5 font-bold">
                <span className="material-symbols-outlined text-base">{currentCfg.icon}</span>
                {currentCfg.label}
              </Badge>
            </div>

            {/* Visual Step Progress */}
            {!isCancelled ? (
              <div className="pt-2">
                <div className="grid grid-cols-5 gap-1 relative">
                  {TIMELINE_STEPS.map((step, idx) => {
                    const stepNum = idx + 1
                    const isDone = currentStep >= stepNum
                    const isCurrent = currentStep === stepNum
                    return (
                      <div key={step.key} className="flex flex-col items-center text-center">
                        <div
                          className={`w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold transition-all mb-2 ${
                            isDone
                              ? 'bg-primary text-on-primary shadow-md'
                              : 'bg-surface-container-high text-outline'
                          } ${isCurrent ? 'ring-4 ring-primary/20 scale-110' : ''}`}
                        >
                          {isDone ? (
                            <span className="material-symbols-outlined text-sm">check</span>
                          ) : (
                            stepNum
                          )}
                        </div>
                        <span
                          className={`text-xs ${
                            isDone ? 'font-bold text-on-surface' : 'text-outline'
                          }`}
                        >
                          {step.label}
                        </span>
                      </div>
                    )
                  })}
                </div>
              </div>
            ) : (
              <div className="p-4 rounded-xl bg-error-container/20 border border-error/30 text-error flex items-center gap-3">
                <span className="material-symbols-outlined text-2xl">cancel</span>
                <div>
                  <p className="text-sm font-bold">Đơn hàng này đã bị hủy</p>
                  <p className="text-xs opacity-90">{order.cancel_reason || 'Khách hàng hoặc quản trị viên đã hủy đơn.'}</p>
                </div>
              </div>
            )}

            {/* Shipping & Payment Meta */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-outline-variant/10 text-xs">
              <div className="bg-surface-container-low rounded-xl p-4 space-y-1.5">
                <p className="font-bold text-on-surface-variant uppercase tracking-wider text-[11px]">Người nhận hàng</p>
                <p className="font-semibold text-on-surface text-sm">{order.shipping_name}</p>
                <p className="text-on-surface-variant">{order.shipping_phone}</p>
                <p className="text-on-surface-variant leading-relaxed">{order.shipping_address_line || order.shipping_address}</p>
              </div>

              <div className="bg-surface-container-low rounded-xl p-4 space-y-1.5">
                <p className="font-bold text-on-surface-variant uppercase tracking-wider text-[11px]">Thanh toán & Vận chuyển</p>
                <p className="text-on-surface">Phương thức: <strong className="uppercase">{order.payment_method}</strong></p>
                <p className="text-on-surface">Trạng thái TT: <strong className="capitalize">{order.payment_status}</strong></p>
                <p className="text-on-surface">Hình thức giao: <strong>{order.shipping_method_name || 'Giao hàng tiêu chuẩn'}</strong></p>
              </div>
            </div>

            {/* Items List */}
            <div className="space-y-3 pt-4 border-t border-outline-variant/10">
              <h3 className="text-sm font-bold text-on-surface">Sản phẩm trong kiện hàng</h3>
              <div className="divide-y divide-outline-variant/10 bg-surface-container-low rounded-xl overflow-hidden">
                {(order.items ?? []).map((item) => (
                  <div key={item.id} className="p-4 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <div className="w-12 h-12 bg-surface-container rounded-lg flex items-center justify-center p-1 overflow-hidden shrink-0">
                        {item.variant?.image_url || item.product?.thumbnail ? (
                          <img
                            src={item.variant?.image_url || item.product?.thumbnail}
                            alt={item.product_name}
                            className="w-full h-full object-contain"
                          />
                        ) : (
                          <span className="material-symbols-outlined text-xl text-outline">smartphone</span>
                        )}
                      </div>
                      <div>
                        <p className="text-sm font-semibold text-on-surface line-clamp-1">{item.product_name}</p>
                        <p className="text-xs text-outline font-mono">
                          {item.variant_name || item.sku} • x{item.quantity}
                        </p>
                      </div>
                    </div>
                    <span className="text-sm font-extrabold text-primary shrink-0">
                      {formatCurrencyVND(item.price * item.quantity)}
                    </span>
                  </div>
                ))}
              </div>

              {/* Total Summary */}
              <div className="flex justify-between items-baseline pt-2 px-2 text-sm">
                <span className="text-on-surface-variant font-medium">Tổng thanh toán:</span>
                <span className="text-xl font-extrabold text-primary">
                  {formatCurrencyVND(order.total_amount ?? order.total)}
                </span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
