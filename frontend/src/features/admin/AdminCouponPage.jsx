// src/features/admin/AdminCouponPage.jsx
// Admin promotional coupon code CRUD management interface.

import { useEffect, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDate } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AdminCouponPage() {
  const { showToast } = useToast()

  const [coupons, setCoupons] = useState([])
  const [loading, setLoading] = useState(true)

  // Modal create/edit
  const [modalOpen, setModalOpen] = useState(false)
  const [editingCoupon, setEditingCoupon] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const [form, setForm] = useState({
    code: '',
    type: 'fixed', // 'fixed' or 'percent'
    value: '',
    min_order_amount: '0',
    max_discount_amount: '',
    usage_limit: '100',
    expires_at: '',
    is_active: true,
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const fetchCoupons = () => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_COUPONS)
      .then(({ data }) => {
        const payload = data.data ?? data
        setCoupons(Array.isArray(payload) ? payload : (payload.data ?? []))
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách mã giảm giá', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchCoupons()
  }, [])

  const handleOpenCreate = () => {
    setEditingCoupon(null)
    setForm({
      code: '',
      type: 'fixed',
      value: '',
      min_order_amount: '0',
      max_discount_amount: '',
      usage_limit: '100',
      expires_at: '',
      is_active: true,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (coupon) => {
    setEditingCoupon(coupon)
    setForm({
      code: coupon.code,
      type: coupon.type,
      value: String(coupon.value),
      min_order_amount: String(coupon.min_order_amount ?? 0),
      max_discount_amount: coupon.max_discount_amount ? String(coupon.max_discount_amount) : '',
      usage_limit: String(coupon.usage_limit ?? 100),
      expires_at: coupon.expires_at ? coupon.expires_at.substring(0, 10) : '',
      is_active: !!coupon.is_active,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      code: form.code.toUpperCase().trim(),
      type: form.type,
      value: Number(form.value),
      min_order_amount: Number(form.min_order_amount) || 0,
      max_discount_amount: form.max_discount_amount ? Number(form.max_discount_amount) : null,
      usage_limit: Number(form.usage_limit) || null,
      expires_at: form.expires_at || null,
      is_active: Boolean(form.is_active),
    }

    try {
      if (editingCoupon) {
        await axiosClient.put(EP.ADMIN_COUPON_DETAIL(editingCoupon.id), payload)
        showToast('Cập nhật mã giảm giá thành công!', 'success')
      } else {
        await axiosClient.post(EP.ADMIN_COUPONS, payload)
        showToast('Tạo mã giảm giá mới thành công!', 'success')
      }
      setModalOpen(false)
      fetchCoupons()
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi lưu mã giảm giá', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(EP.ADMIN_COUPON_DETAIL(deleteTarget.id))
      showToast('Đã xóa mã giảm giá!', 'info')
      setDeleteTarget(null)
      fetchCoupons()
    } catch (err) {
      showToast(err.message || 'Không thể xóa mã giảm giá', 'error')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Page Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Mã giảm giá (Coupons)</h1>
          <p className="text-sm text-on-surface-variant">
            Quản lý chiến dịch khuyến mãi, voucher chiết khấu đơn hàng.
          </p>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <span className="material-symbols-outlined text-base">add</span>
          Tạo mã mới
        </Button>
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : coupons.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">local_offer</span>
            <p className="text-sm font-medium">Chưa có mã giảm giá nào được tạo.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Mã Code</th>
                  <th className="px-5 py-3.5">Loại & Mức giảm</th>
                  <th className="px-5 py-3.5">Đơn tối thiểu</th>
                  <th className="px-5 py-3.5 text-center">Lượt dùng</th>
                  <th className="px-5 py-3.5">Hạn dùng</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {coupons.map((c) => (
                  <tr key={c.id} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-primary">{c.code}</td>
                    <td className="px-5 py-4">
                      {c.type === 'percent' ? (
                        <span className="text-secondary font-bold">{c.value}%</span>
                      ) : (
                        <span className="text-secondary font-bold">{formatCurrencyVND(c.value)}</span>
                      )}
                    </td>
                    <td className="px-5 py-4 text-xs text-on-surface-variant">
                      {Number(c.min_order_amount) > 0 ? formatCurrencyVND(c.min_order_amount) : 'Không'}
                    </td>
                    <td className="px-5 py-4 text-center font-mono text-xs">
                      {c.used_count ?? 0} / {c.usage_limit ?? '∞'}
                    </td>
                    <td className="px-5 py-4 text-xs text-on-surface-variant">
                      {c.expires_at ? formatDate(c.expires_at) : 'Vĩnh viễn'}
                    </td>
                    <td className="px-5 py-4 text-center">
                      <Badge variant={c.is_active ? 'success' : 'neutral'}>
                        {c.is_active ? 'Hoạt động' : 'Tạm dừng'}
                      </Badge>
                    </td>
                    <td className="px-5 py-4 text-right space-x-2">
                      <button
                        onClick={() => handleOpenEdit(c)}
                        className="px-2.5 py-1 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors"
                      >
                        Sửa
                      </button>
                      <button
                        onClick={() => setDeleteTarget(c)}
                        className="px-2.5 py-1 bg-error-container/20 hover:bg-error-container/40 rounded-lg text-xs font-semibold text-error transition-colors"
                      >
                        Xóa
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Create / Edit Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingCoupon ? `Chỉnh sửa mã: ${editingCoupon.code}` : 'Tạo mã giảm giá mới'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Mã giảm giá (Code) *"
            value={form.code}
            onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
            placeholder="VÍ DỤ: MOBIEX100K"
            error={errors.code?.[0]}
            required
            className="uppercase font-mono"
          />

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Loại giảm giá</label>
              <select
                value={form.type}
                onChange={(e) => setForm({ ...form, type: e.target.value })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="fixed">Số tiền cố định (VND)</option>
                <option value="percent">Phần trăm (%)</option>
              </select>
            </div>

            <Input
              label="Giá trị giảm *"
              type="number"
              value={form.value}
              onChange={(e) => setForm({ ...form, value: e.target.value })}
              placeholder={form.type === 'percent' ? '10' : '100000'}
              error={errors.value?.[0]}
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Đơn tối thiểu (VND)"
              type="number"
              value={form.min_order_amount}
              onChange={(e) => setForm({ ...form, min_order_amount: e.target.value })}
              placeholder="0"
              error={errors.min_order_amount?.[0]}
            />
            <Input
              label="Giới hạn số lượt dùng"
              type="number"
              value={form.usage_limit}
              onChange={(e) => setForm({ ...form, usage_limit: e.target.value })}
              placeholder="100"
              error={errors.usage_limit?.[0]}
            />
          </div>

          <Input
            label="Ngày hết hạn"
            type="date"
            value={form.expires_at}
            onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
            error={errors.expires_at?.[0]}
          />

          <label className="flex items-center gap-2 text-sm text-on-surface cursor-pointer pt-1">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              className="rounded text-primary focus:ring-primary"
            />
            Kích hoạt sử dụng ngay
          </label>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editingCoupon ? 'Lưu thay đổi' : 'Tạo mới'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển mã "${deleteTarget?.code}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmLabel="Chuyển vào Thùng rác"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />
    </div>
  )
}
