// src/features/admin/coupon/CouponForm.jsx
// Reusable form component for both Create and Edit Dedicated Pages of Admin Coupon.
// Features: 2-column responsive layout, live rule preview, dirty state tracking,
// usage statistics panel, start/end datetime pickers, and sticky bottom action bar.

import { useEffect, useMemo, useState } from 'react'
import Button from '../../../components/common/Button'
import Input from '../../../components/common/Input'
import Badge from '../../../components/common/Badge'
import ConfirmDialog from '../../../components/feedback/ConfirmDialog'
import { formatCurrencyVND } from '../../../utils/formatCurrencyVND'

function toDatetimeLocal(isoString) {
  if (!isoString) return ''
  try {
    const d = new Date(isoString)
    if (isNaN(d.getTime())) return ''
    const pad = (n) => String(n).padStart(2, '0')
    const yyyy = d.getFullYear()
    const mm = pad(d.getMonth() + 1)
    const dd = pad(d.getDate())
    const hh = pad(d.getHours())
    const min = pad(d.getMinutes())
    return `${yyyy}-${mm}-${dd}T${hh}:${min}`
  } catch {
    return ''
  }
}

function generateRandomCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  let result = 'MX'
  for (let i = 0; i < 6; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  return result
}

/**
 * @param {Object} props
 * @param {Object|null} props.initialData - Existing coupon object for edit mode
 * @param {boolean} props.isEdit - Whether form is in edit mode
 * @param {boolean} props.submitting - Whether form is currently saving
 * @param {Object} props.errors - Backend validation error dictionary
 * @param {Function} props.onSubmit - Async callback when form is submitted
 * @param {Function} props.onCancel - Navigation callback when user cancels
 */
export default function CouponForm({
  initialData = null,
  isEdit = false,
  submitting = false,
  errors = {},
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    code: '',
    discount_type: 'fixed', // 'fixed' | 'percent'
    discount_value: '',
    min_order_amount: '0',
    max_discount: '',
    usage_limit: '',
    start_at: '',
    end_at: '',
    is_active: true,
  })

  const [isDirty, setIsDirty] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)
  const [clientErrors, setClientErrors] = useState({})

  // Populate data when editing
  useEffect(() => {
    if (initialData) {
      setForm({
        code: initialData.code || '',
        discount_type: initialData.discount_type || 'fixed',
        discount_value:
          initialData.discount_value != null ? String(initialData.discount_value) : '',
        min_order_amount:
          initialData.min_order_amount != null ? String(initialData.min_order_amount) : '0',
        max_discount:
          initialData.max_discount != null ? String(initialData.max_discount) : '',
        usage_limit:
          initialData.usage_limit != null ? String(initialData.usage_limit) : '',
        start_at: toDatetimeLocal(initialData.start_at),
        end_at: toDatetimeLocal(initialData.end_at),
        is_active: Boolean(initialData.is_active ?? true),
      })
      setIsDirty(false)
    }
  }, [initialData])

  // Warn on page unload if changes are unsaved
  useEffect(() => {
    const handleBeforeUnload = (e) => {
      if (isDirty) {
        e.preventDefault()
        e.returnValue = ''
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [isDirty])

  const handleFieldChange = (name, value) => {
    setForm((prev) => ({ ...prev, [name]: value }))
    setIsDirty(true)
    if (clientErrors[name]) {
      setClientErrors((prev) => ({ ...prev, [name]: '' }))
    }
  }

  const handleRandomCode = () => {
    const newCode = generateRandomCode()
    handleFieldChange('code', newCode)
  }

  // Pre-validate before submitting
  const validate = () => {
    const errs = {}
    if (!form.code.trim()) {
      errs.code = 'Mã giảm giá không được để trống.'
    } else if (form.code.trim().length < 3) {
      errs.code = 'Mã giảm giá phải có tối thiểu 3 ký tự.'
    }

    const val = Number(form.discount_value)
    if (!form.discount_value || isNaN(val) || val <= 0) {
      errs.discount_value = 'Giá trị giảm phải là số lớn hơn 0.'
    } else if (form.discount_type === 'percent' && val > 100) {
      errs.discount_value = 'Mức giảm theo phần trăm không thể vượt quá 100%.'
    }

    if (form.min_order_amount && Number(form.min_order_amount) < 0) {
      errs.min_order_amount = 'Đơn hàng tối thiểu không thể âm.'
    }

    if (form.max_discount && Number(form.max_discount) < 0) {
      errs.max_discount = 'Mức giảm tối đa không thể âm.'
    }

    if (form.usage_limit && Number(form.usage_limit) < 1) {
      errs.usage_limit = 'Giới hạn lượt dùng phải từ 1 trở lên.'
    }

    if (form.start_at && form.end_at) {
      const start = new Date(form.start_at).getTime()
      const end = new Date(form.end_at).getTime()
      if (end < start) {
        errs.end_at = 'Thời gian kết thúc phải sau hoặc bằng thời gian bắt đầu.'
      }
    }

    setClientErrors(errs)
    return Object.keys(errs).length === 0
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (!validate()) return

    const payload = {
      code: form.code.trim().toUpperCase(),
      discount_type: form.discount_type,
      discount_value: Number(form.discount_value),
      min_order_amount: form.min_order_amount ? Number(form.min_order_amount) : 0,
      max_discount:
        form.discount_type === 'percent' && form.max_discount
          ? Number(form.max_discount)
          : null,
      usage_limit: form.usage_limit ? Number(form.usage_limit) : null,
      start_at: form.start_at ? form.start_at.replace('T', ' ') + ':00' : null,
      end_at: form.end_at ? form.end_at.replace('T', ' ') + ':00' : null,
      is_active: Boolean(form.is_active),
    }

    if (onSubmit) {
      onSubmit(payload)
    }
  }

  const handleCancelClick = () => {
    if (isDirty) {
      setShowCancelConfirm(true)
    } else if (onCancel) {
      onCancel()
    }
  }

  // Combined field error reader
  const getFieldError = (name) => {
    return clientErrors[name] || errors[name]?.[0] || errors[name] || null
  }

  // Live Rule Summary Preview
  const previewSummary = useMemo(() => {
    const val = Number(form.discount_value) || 0
    let discountStr = ''
    if (form.discount_type === 'percent') {
      discountStr = `Giảm ${val}%`
      if (form.max_discount && Number(form.max_discount) > 0) {
        discountStr += ` (tối đa ${formatCurrencyVND(Number(form.max_discount))})`
      }
    } else {
      discountStr = `Giảm ${formatCurrencyVND(val)}`
    }

    const minStr =
      Number(form.min_order_amount) > 0
        ? `đơn từ ${formatCurrencyVND(Number(form.min_order_amount))}`
        : 'mọi đơn hàng'

    let limitStr = form.usage_limit
      ? `Tối đa ${form.usage_limit} lượt dùng`
      : 'Không giới hạn lượt dùng'

    let timeStr = 'Áp dụng vô thời hạn'
    if (form.start_at && form.end_at) {
      timeStr = `Từ ${new Date(form.start_at).toLocaleDateString('vi-VN')} đến ${new Date(
        form.end_at
      ).toLocaleDateString('vi-VN')}`
    } else if (form.end_at) {
      timeStr = `Hạn dùng đến ${new Date(form.end_at).toLocaleDateString('vi-VN')}`
    } else if (form.start_at) {
      timeStr = `Bắt đầu từ ${new Date(form.start_at).toLocaleDateString('vi-VN')}`
    }

    return { discountStr, minStr, limitStr, timeStr }
  }, [form])

  // Time validity status helper
  const timeValidityBadge = useMemo(() => {
    const now = Date.now()
    if (form.start_at && new Date(form.start_at).getTime() > now) {
      return { label: 'Sắp diễn ra', variant: 'warning' }
    }
    if (form.end_at && new Date(form.end_at).getTime() < now) {
      return { label: 'Đã hết hạn', variant: 'error' }
    }
    return { label: 'Đang có hiệu lực', variant: 'success' }
  }, [form.start_at, form.end_at])

  return (
    <form onSubmit={handleSubmit} className="space-y-8 pb-24">
      {/* ── 2-COLUMN MAIN LAYOUT ── */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* LEFT COLUMN: Main Configuration Cards (8 cols) */}
        <div className="lg:col-span-8 space-y-6">
          {/* Card 1: Thông tin cơ bản */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6 shadow-sm">
            <div className="flex items-center gap-2.5 pb-3 border-b border-outline-variant/15">
              <span className="material-symbols-outlined text-primary text-xl">local_offer</span>
              <h2 className="text-base font-bold text-on-surface">Thông tin mã giảm giá</h2>
            </div>

            {/* Code Field with Generator */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Mã Voucher (Code) <span className="text-error">*</span>
                </label>
                <button
                  type="button"
                  onClick={handleRandomCode}
                  className="text-xs text-primary hover:underline flex items-center gap-1 font-medium transition-colors"
                >
                  <span className="material-symbols-outlined text-xs">autorenew</span>
                  Tạo mã ngẫu nhiên
                </button>
              </div>
              <div className="relative">
                <Input
                  name="code"
                  value={form.code}
                  onChange={(e) => handleFieldChange('code', e.target.value.toUpperCase())}
                  placeholder="VÍ DỤ: MOBIEX100K, SALE50..."
                  error={getFieldError('code')}
                  required
                  className="uppercase font-mono tracking-wider font-bold text-base"
                />
              </div>
              <p className="text-xs text-outline">
                Mã duy nhất viết hoa không dấu, khách hàng sẽ nhập chuỗi này tại trang Giỏ hàng & Thanh toán.
              </p>
            </div>

            {/* Discount Type: Segmented Cards */}
            <div className="space-y-2">
              <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                Hình thức giảm giá <span className="text-error">*</span>
              </label>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div
                  onClick={() => handleFieldChange('discount_type', 'fixed')}
                  className={[
                    'p-4 rounded-xl border cursor-pointer transition-all flex items-start gap-3.5',
                    form.discount_type === 'fixed'
                      ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-sm'
                      : 'border-outline-variant/30 bg-surface-container-low hover:border-outline',
                  ].join(' ')}
                >
                  <div
                    className={[
                      'w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
                      form.discount_type === 'fixed'
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container-high text-on-surface-variant',
                    ].join(' ')}
                  >
                    <span className="material-symbols-outlined text-xl">payments</span>
                  </div>
                  <div className="space-y-0.5">
                    <p className="text-sm font-bold text-on-surface">Số tiền cố định (VND)</p>
                    <p className="text-xs text-on-surface-variant">
                      Trừ thẳng một khoản tiền cụ thể vào tổng đơn hàng (ví dụ: 100.000 ₫).
                    </p>
                  </div>
                </div>

                <div
                  onClick={() => handleFieldChange('discount_type', 'percent')}
                  className={[
                    'p-4 rounded-xl border cursor-pointer transition-all flex items-start gap-3.5',
                    form.discount_type === 'percent'
                      ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-sm'
                      : 'border-outline-variant/30 bg-surface-container-low hover:border-outline',
                  ].join(' ')}
                >
                  <div
                    className={[
                      'w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
                      form.discount_type === 'percent'
                        ? 'bg-primary text-on-primary'
                        : 'bg-surface-container-high text-on-surface-variant',
                    ].join(' ')}
                  >
                    <span className="material-symbols-outlined text-xl">percent</span>
                  </div>
                  <div className="space-y-0.5">
                    <p className="text-sm font-bold text-on-surface">Phần trăm (%)</p>
                    <p className="text-xs text-on-surface-variant">
                      Giảm theo tỷ lệ % giá trị đơn hàng, có thể đặt mức trần tối đa.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Discount Value */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Giá trị giảm ({form.discount_type === 'percent' ? '%' : 'VND'}){' '}
                  <span className="text-error">*</span>
                </label>
                {form.discount_type === 'percent' && (
                  <div className="flex items-center gap-1.5">
                    {[5, 10, 15, 20, 50].map((pct) => (
                      <button
                        key={pct}
                        type="button"
                        onClick={() => handleFieldChange('discount_value', String(pct))}
                        className="px-2 py-0.5 rounded text-[11px] font-semibold bg-surface-container-high hover:bg-primary/20 hover:text-primary transition-colors text-on-surface-variant"
                      >
                        {pct}%
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div className="relative">
                <Input
                  type="number"
                  name="discount_value"
                  value={form.discount_value}
                  onChange={(e) => handleFieldChange('discount_value', e.target.value)}
                  placeholder={form.discount_type === 'percent' ? '10' : '100000'}
                  error={getFieldError('discount_value')}
                  min="0"
                  max={form.discount_type === 'percent' ? '100' : undefined}
                  step={form.discount_type === 'percent' ? '1' : '1000'}
                  required
                />
              </div>

              {Number(form.discount_value) > 0 && (
                <p className="text-xs text-secondary font-medium">
                  {form.discount_type === 'percent'
                    ? `Áp dụng giảm: ${form.discount_value}% tổng giá trị sản phẩm`
                    : `Áp dụng giảm trực tiếp: ${formatCurrencyVND(Number(form.discount_value))}`}
                </p>
              )}
            </div>
          </div>

          {/* Card 2: Điều kiện áp dụng & Giới hạn */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6 shadow-sm">
            <div className="flex items-center gap-2.5 pb-3 border-b border-outline-variant/15">
              <span className="material-symbols-outlined text-primary text-xl">tune</span>
              <h2 className="text-base font-bold text-on-surface">Điều kiện & Giới hạn áp dụng</h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Min Order Amount */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Giá trị đơn hàng tối thiểu (VND)
                </label>
                <Input
                  type="number"
                  name="min_order_amount"
                  value={form.min_order_amount}
                  onChange={(e) => handleFieldChange('min_order_amount', e.target.value)}
                  placeholder="0"
                  error={getFieldError('min_order_amount')}
                  min="0"
                  step="10000"
                />
                <p className="text-[11px] text-outline">
                  {Number(form.min_order_amount) > 0
                    ? `Chỉ áp dụng khi đơn hàng đạt từ ${formatCurrencyVND(
                        Number(form.min_order_amount)
                      )} trở lên.`
                    : 'Áp dụng cho mọi giá trị đơn hàng (không giới hạn tối thiểu).'}
                </p>
              </div>

              {/* Max Discount (Applicable for percent) */}
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Mức giảm tối đa (VND)
                  {form.discount_type === 'fixed' && (
                    <span className="text-[10px] text-outline font-normal lowercase ml-1">
                      (chỉ áp dụng cho %)
                    </span>
                  )}
                </label>
                <Input
                  type="number"
                  name="max_discount"
                  value={form.max_discount}
                  onChange={(e) => handleFieldChange('max_discount', e.target.value)}
                  placeholder="Để trống nếu không giới hạn"
                  error={getFieldError('max_discount')}
                  disabled={form.discount_type === 'fixed'}
                  min="0"
                  step="10000"
                />
                <p className="text-[11px] text-outline">
                  {form.discount_type === 'percent'
                    ? form.max_discount && Number(form.max_discount) > 0
                      ? `Giới hạn trần tối đa ${formatCurrencyVND(Number(form.max_discount))}.`
                      : 'Giảm theo đúng % không giới hạn số tiền tối đa.'
                    : 'Không khả dụng cho hình thức giảm số tiền cố định.'}
                </p>
              </div>
            </div>

            {/* Usage Limit */}
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                Tổng số lượt sử dụng tối đa
              </label>
              <Input
                type="number"
                name="usage_limit"
                value={form.usage_limit}
                onChange={(e) => handleFieldChange('usage_limit', e.target.value)}
                placeholder="Ví dụ: 100 (để trống nếu không giới hạn)"
                error={getFieldError('usage_limit')}
                min="1"
              />
              <p className="text-[11px] text-outline">
                Tổng số lượt khách hàng có thể sử dụng mã này trên toàn hệ thống MobieX.
              </p>
            </div>
          </div>

          {/* Card 3: Thời gian hiệu lực */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6 shadow-sm">
            <div className="flex items-center justify-between pb-3 border-b border-outline-variant/15">
              <div className="flex items-center gap-2.5">
                <span className="material-symbols-outlined text-primary text-xl">date_range</span>
                <h2 className="text-base font-bold text-on-surface">Thời gian hiệu lực</h2>
              </div>
              <Badge variant={timeValidityBadge.variant}>{timeValidityBadge.label}</Badge>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Thời gian bắt đầu (tùy chọn)
                </label>
                <input
                  type="datetime-local"
                  name="start_at"
                  value={form.start_at}
                  onChange={(e) => handleFieldChange('start_at', e.target.value)}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                />
                {getFieldError('start_at') && (
                  <p className="text-xs text-error mt-1">{getFieldError('start_at')}</p>
                )}
                <p className="text-[11px] text-outline">
                  Thời điểm mã bắt đầu có thể áp dụng. Để trống để kích hoạt ngay.
                </p>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
                  Thời gian kết thúc (tùy chọn)
                </label>
                <input
                  type="datetime-local"
                  name="end_at"
                  value={form.end_at}
                  onChange={(e) => handleFieldChange('end_at', e.target.value)}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                />
                {getFieldError('end_at') && (
                  <p className="text-xs text-error mt-1">{getFieldError('end_at')}</p>
                )}
                <p className="text-[11px] text-outline">
                  Thời điểm mã hết hạn. Để trống để áp dụng vĩnh viễn cho đến khi hết lượt.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* RIGHT COLUMN: Sidebar Cards (4 cols) */}
        <div className="lg:col-span-4 space-y-6">
          {/* Card: Trạng thái kích hoạt */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-on-surface uppercase tracking-wider">
              Trạng thái mã giảm giá
            </h3>

            <div
              onClick={() => handleFieldChange('is_active', !form.is_active)}
              className={[
                'p-4 rounded-xl border cursor-pointer transition-all flex items-center justify-between',
                form.is_active
                  ? 'border-emerald-500/40 bg-emerald-500/10'
                  : 'border-outline-variant/40 bg-surface-container-low',
              ].join(' ')}
            >
              <div className="space-y-0.5">
                <p className="text-sm font-bold text-on-surface">
                  {form.is_active ? 'Đang kích hoạt' : 'Tạm dừng'}
                </p>
                <p className="text-xs text-on-surface-variant">
                  {form.is_active
                    ? 'Khách hàng có thể nhập và áp dụng mã này.'
                    : 'Mã bị vô hiệu hóa tạm thời.'}
                </p>
              </div>
              <div
                className={[
                  'w-12 h-6 rounded-full transition-colors relative flex items-center px-0.5',
                  form.is_active ? 'bg-emerald-600' : 'bg-outline-variant',
                ].join(' ')}
              >
                <div
                  className={[
                    'w-5 h-5 rounded-full bg-white shadow-md transition-transform',
                    form.is_active ? 'translate-x-6' : 'translate-x-0',
                  ].join(' ')}
                />
              </div>
            </div>
          </div>

          {/* Card: Live Rule Summary Preview */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4 shadow-sm">
            <div className="flex items-center gap-2 text-primary">
              <span className="material-symbols-outlined text-lg">preview</span>
              <h3 className="text-sm font-bold text-on-surface uppercase tracking-wider">
                Xem trước thể lệ
              </h3>
            </div>

            <div className="bg-surface-container-low rounded-xl p-4 border border-outline-variant/20 space-y-3">
              <div className="flex items-center justify-between">
                <span className="font-mono font-bold text-base text-primary">
                  {form.code.trim() || 'MÃ_VOUCHER'}
                </span>
                <Badge variant={form.is_active ? 'success' : 'neutral'}>
                  {form.is_active ? 'Hoạt động' : 'Tạm dừng'}
                </Badge>
              </div>

              <div className="text-xs space-y-2 text-on-surface-variant pt-2 border-t border-outline-variant/15">
                <div className="flex items-start gap-2">
                  <span className="material-symbols-outlined text-sm text-secondary shrink-0 mt-0.5">
                    check_circle
                  </span>
                  <span>
                    <strong>{previewSummary.discountStr}</strong> cho {previewSummary.minStr}.
                  </span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="material-symbols-outlined text-sm text-secondary shrink-0 mt-0.5">
                    pin
                  </span>
                  <span>{previewSummary.limitStr}.</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="material-symbols-outlined text-sm text-secondary shrink-0 mt-0.5">
                    schedule
                  </span>
                  <span>{previewSummary.timeStr}.</span>
                </div>
              </div>
            </div>
          </div>

          {/* Card: Thống kê sử dụng (Chỉ hiển thị khi Edit) */}
          {isEdit && initialData && (
            <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4 shadow-sm">
              <h3 className="text-sm font-bold text-on-surface uppercase tracking-wider flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-base">analytics</span>
                Thống kê sử dụng
              </h3>

              <div className="space-y-3 text-xs text-on-surface-variant">
                <div className="flex justify-between py-1.5 border-b border-outline-variant/10">
                  <span>ID hệ thống:</span>
                  <span className="font-mono font-bold text-on-surface">#{initialData.id}</span>
                </div>
                <div className="flex justify-between py-1.5 border-b border-outline-variant/10">
                  <span>Lượt đã sử dụng:</span>
                  <span className="font-bold text-secondary text-sm">
                    {initialData.used_count ?? 0}
                    {initialData.usage_limit ? ` / ${initialData.usage_limit}` : ' (không giới hạn)'}
                  </span>
                </div>
                <div className="flex justify-between py-1.5 border-b border-outline-variant/10">
                  <span>Lượt đang giữ chỗ (Reserved):</span>
                  <span className="font-bold text-amber-500">{initialData.reserved_count ?? 0}</span>
                </div>
                <div className="flex justify-between py-1.5 border-b border-outline-variant/10">
                  <span>Đơn hàng liên kết:</span>
                  <span className="font-bold text-on-surface">{initialData.orders_count ?? 0} đơn</span>
                </div>
                <div className="flex justify-between py-1.5">
                  <span>Ngày khởi tạo:</span>
                  <span className="text-on-surface">
                    {initialData.created_at
                      ? new Date(initialData.created_at).toLocaleDateString('vi-VN')
                      : '—'}
                  </span>
                </div>
              </div>

              {/* Progress bar if usage_limit is set */}
              {initialData.usage_limit && (
                <div className="space-y-1 pt-2 border-t border-outline-variant/10">
                  <div className="flex justify-between text-[11px] text-outline">
                    <span>Tiến độ sử dụng</span>
                    <span>
                      {Math.min(
                        100,
                        Math.round(((initialData.used_count ?? 0) / initialData.usage_limit) * 100)
                      )}
                      %
                    </span>
                  </div>
                  <div className="w-full bg-surface-container-high h-2 rounded-full overflow-hidden">
                    <div
                      className="bg-primary h-full transition-all"
                      style={{
                        width: `${Math.min(
                          100,
                          Math.round(
                            ((initialData.used_count ?? 0) / initialData.usage_limit) * 100
                          )
                        )}%`,
                      }}
                    />
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* ── STICKY BOTTOM ACTION BAR ── */}
      <div className="fixed bottom-0 left-0 right-0 z-40 bg-surface-container-high/95 backdrop-blur-md border-t border-outline-variant/30 py-3.5 px-6 shadow-lg">
        <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-xs sm:text-sm">
            {isDirty ? (
              <span className="flex items-center gap-1.5 text-amber-500 font-medium">
                <span className="material-symbols-outlined text-base">edit_note</span>
                <span className="hidden sm:inline">Bạn có thay đổi chưa lưu</span>
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-outline">
                <span className="material-symbols-outlined text-base">check_circle</span>
                <span className="hidden sm:inline">Dữ liệu sẵn sàng</span>
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={handleCancelClick}
              disabled={submitting}
              className="cursor-pointer"
            >
              Hủy bỏ
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={submitting}
              className="flex items-center gap-1.5 shadow-md cursor-pointer"
            >
              <span className="material-symbols-outlined text-base">
                {isEdit ? 'save' : 'add_circle'}
              </span>
              {isEdit ? 'Lưu thay đổi' : 'Tạo mã giảm giá'}
            </Button>
          </div>
        </div>
      </div>

      {/* Cancel Confirmation Dialog */}
      <ConfirmDialog
        open={showCancelConfirm}
        title="Rời khỏi trang?"
        message="Các thông tin bạn vừa chỉnh sửa chưa được lưu lại. Bạn có chắc chắn muốn hủy bỏ và quay lại danh sách mã giảm giá không?"
        confirmLabel="Đồng ý rời đi"
        cancelLabel="Tiếp tục chỉnh sửa"
        onConfirm={() => {
          setShowCancelConfirm(false)
          setIsDirty(false)
          if (onCancel) onCancel()
        }}
        onCancel={() => setShowCancelConfirm(false)}
      />
    </form>
  )
}
