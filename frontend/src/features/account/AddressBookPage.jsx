// src/features/account/AddressBookPage.jsx
// Customer address book management: list, add, edit, delete, and set default shipping address.

import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AddressBookPage() {
  const { showToast } = useToast()

  const [addresses, setAddresses] = useState([])
  const [loading, setLoading] = useState(true)

  // Create / Edit Modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editingAddress, setEditingAddress] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const [form, setForm] = useState({
    label: 'Nhà riêng',
    full_name: '',
    phone: '',
    city: 'Hà Nội',
    district: 'Cầu Giấy',
    ward: 'Dịch Vọng Hậu',
    address_line: '',
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // ── Fetch Addresses ────────────────────────────────────────────────────────
  const fetchAddresses = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.ADDRESSES)
      const list = data.data ?? data
      setAddresses(Array.isArray(list) ? list : (list.data ?? []))
    } catch {
      showToast('Không thể tải danh sách địa chỉ.', 'error')
    } finally {
      setLoading(false)
    }
  }, [showToast])

  useEffect(() => {
    fetchAddresses()
  }, [fetchAddresses])

  // ── Open Create / Edit ─────────────────────────────────────────────────────
  const handleOpenCreate = () => {
    setEditingAddress(null)
    setForm({
      label: 'Nhà riêng',
      full_name: '',
      phone: '',
      city: 'Hà Nội',
      district: '',
      ward: '',
      address_line: '',
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (addr) => {
    setEditingAddress(addr)
    setForm({
      label: addr.label ?? 'Nhà riêng',
      full_name: addr.full_name ?? '',
      phone: addr.phone ?? '',
      city: addr.city ?? '',
      district: addr.district ?? '',
      ward: addr.ward ?? '',
      address_line: addr.address_line ?? '',
    })
    setErrors({})
    setModalOpen(true)
  }

  // ── Submit Address ─────────────────────────────────────────────────────────
  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      label: form.label.trim() || null,
      full_name: form.full_name.trim(),
      phone: form.phone.trim(),
      city: form.city.trim(),
      district: form.district.trim() || null,
      ward: form.ward.trim() || null,
      address_line: form.address_line.trim(),
    }

    try {
      if (editingAddress) {
        await axiosClient.patch(`${EP.ADDRESSES}/${editingAddress.id}`, payload)
        showToast('Cập nhật địa chỉ nhận hàng thành công!')
      } else {
        await axiosClient.post(EP.ADDRESSES, payload)
        showToast('Thêm địa chỉ nhận hàng mới thành công!')
      }
      setModalOpen(false)
      fetchAddresses()
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data?.errors ?? {})
      } else {
        const msg = err.response?.data?.message || 'Không thể lưu địa chỉ.'
        showToast(msg, 'error')
      }
    } finally {
      setSubmitting(false)
    }
  }

  // ── Set Default Address ────────────────────────────────────────────────────
  const handleSetDefault = async (addr) => {
    try {
      await axiosClient.patch(EP.ADDRESS_DEFAULT(addr.id))
      showToast('Đã đặt làm địa chỉ giao hàng mặc định.')
      fetchAddresses()
    } catch {
      showToast('Không thể thay đổi địa chỉ mặc định.', 'error')
    }
  }

  // ── Delete Address ─────────────────────────────────────────────────────────
  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(`${EP.ADDRESSES}/${deleteTarget.id}`)
      showToast('Đã xóa địa chỉ thành công.')
      setDeleteTarget(null)
      fetchAddresses()
    } catch {
      showToast('Không thể xóa địa chỉ này.', 'error')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-8 space-y-6">
      {/* Breadcrumb & Navigation */}
      <div className="flex items-center gap-2 text-sm text-outline">
        <Link to="/" className="hover:text-primary transition-colors">Trang chủ</Link>
        <span>/</span>
        <Link to="/account/profile" className="hover:text-primary transition-colors">Tài khoản</Link>
        <span>/</span>
        <span className="text-on-surface font-medium">Sổ địa chỉ</span>
      </div>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-outline-variant/20 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Sổ Địa Chỉ Nhận Hàng</h1>
          <p className="text-sm text-outline mt-1">
            Lưu sẵn địa chỉ giao hàng để thanh toán nhanh chóng chỉ với 1 click
          </p>
        </div>
        <Button onClick={handleOpenCreate} variant="primary" className="flex items-center gap-2">
          <span className="material-symbols-outlined text-base">add_location_alt</span>
          Thêm địa chỉ mới
        </Button>
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : addresses.length === 0 ? (
        <div className="bg-surface-container rounded-2xl p-12 text-center border border-outline-variant/20 flex flex-col items-center gap-3">
          <span className="material-symbols-outlined text-5xl text-outline">location_off</span>
          <h3 className="text-lg font-semibold text-on-surface">Bạn chưa có địa chỉ nhận hàng nào</h3>
          <p className="text-sm text-outline max-w-md">
            Thêm địa chỉ nhà riêng hoặc cơ quan để đơn hàng được giao đến đúng nơi một cách thuận tiện nhất.
          </p>
          <Button onClick={handleOpenCreate} variant="primary" className="mt-2">
            Thêm địa chỉ đầu tiên
          </Button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {addresses.map((addr) => {
            const isDefault = Boolean(addr.is_default)

            return (
              <div
                key={addr.id}
                className={[
                  'rounded-2xl p-5 border transition-all flex flex-col justify-between bg-surface-container relative',
                  isDefault
                    ? 'border-primary ring-1 ring-primary/30 shadow-md'
                    : 'border-outline-variant/30 hover:border-outline',
                ].join(' ')}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-2">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-on-surface text-base">{addr.full_name}</span>
                      {addr.label && (
                        <span className="text-[11px] font-semibold bg-surface-container-high px-2 py-0.5 rounded text-outline">
                          {addr.label}
                        </span>
                      )}
                    </div>
                    {isDefault && (
                      <Badge variant="success" className="text-[10px]">Mặc định</Badge>
                    )}
                  </div>

                  <p className="text-sm text-on-surface-variant flex items-center gap-1.5 mb-1">
                    <span className="material-symbols-outlined text-sm text-outline">phone</span>
                    <span className="font-mono">{addr.phone}</span>
                  </p>

                  <p className="text-sm text-on-surface leading-relaxed mt-2 flex items-start gap-1.5">
                    <span className="material-symbols-outlined text-sm text-outline mt-0.5 shrink-0">pin_drop</span>
                    <span>
                      {addr.address_line}
                      {addr.ward && `, ${addr.ward}`}
                      {addr.district && `, ${addr.district}`}
                      {addr.city && `, ${addr.city}`}
                    </span>
                  </p>
                </div>

                <div className="flex items-center justify-between pt-4 mt-4 border-t border-outline-variant/15 text-xs">
                  <div>
                    {!isDefault && (
                      <button
                        type="button"
                        onClick={() => handleSetDefault(addr)}
                        className="text-primary hover:underline font-semibold"
                      >
                        Thiết lập mặc định
                      </button>
                    )}
                  </div>
                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={() => handleOpenEdit(addr)}
                      className="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-0.5"
                    >
                      <span className="material-symbols-outlined text-sm">edit</span>
                      Sửa
                    </button>
                    <button
                      type="button"
                      onClick={() => setDeleteTarget(addr)}
                      className="text-on-surface-variant hover:text-error transition-colors flex items-center gap-0.5"
                    >
                      <span className="material-symbols-outlined text-sm">delete</span>
                      Xóa
                    </button>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}

      {/* Create / Edit Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingAddress ? 'Chỉnh sửa địa chỉ nhận hàng' : 'Thêm địa chỉ nhận hàng mới'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Họ và tên người nhận *"
              value={form.full_name}
              onChange={(e) => setForm({ ...form, full_name: e.target.value })}
              placeholder="Ví dụ: Nguyễn Văn A"
              error={errors.full_name?.[0]}
              required
            />
            <Input
              label="Số điện thoại liên hệ *"
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
              placeholder="Ví dụ: 0912345678"
              error={errors.phone?.[0]}
              required
            />
          </div>

          <div className="grid grid-cols-3 gap-3">
            <Input
              label="Tỉnh / Thành phố *"
              value={form.city}
              onChange={(e) => setForm({ ...form, city: e.target.value })}
              placeholder="Hà Nội, TP.HCM..."
              error={errors.city?.[0]}
              required
            />
            <Input
              label="Quận / Huyện"
              value={form.district}
              onChange={(e) => setForm({ ...form, district: e.target.value })}
              placeholder="Cầu Giấy, Quận 1..."
              error={errors.district?.[0]}
            />
            <Input
              label="Phường / Xã"
              value={form.ward}
              onChange={(e) => setForm({ ...form, ward: e.target.value })}
              placeholder="Dịch Vọng, Bến Nghé..."
              error={errors.ward?.[0]}
            />
          </div>

          <Input
            label="Địa chỉ chi tiết (Số nhà, tên đường, tòa nhà) *"
            value={form.address_line}
            onChange={(e) => setForm({ ...form, address_line: e.target.value })}
            placeholder="Số 123 Đường Cầu Giấy, Tòa nhà FPT"
            error={errors.address_line?.[0]}
            required
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-on-surface-variant">Loại địa chỉ</label>
            <div className="flex gap-4 mt-1">
              {['Nhà riêng', 'Văn phòng / Cơ quan'].map((lbl) => (
                <label key={lbl} className="flex items-center gap-2 text-sm text-on-surface cursor-pointer">
                  <input
                    type="radio"
                    name="addressLabel"
                    checked={form.label === lbl}
                    onChange={() => setForm({ ...form, label: lbl })}
                    className="accent-primary"
                  />
                  {lbl}
                </label>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editingAddress ? 'Lưu thay đổi' : 'Thêm địa chỉ'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Xác nhận xóa địa chỉ"
        message={`Bạn có chắc muốn xóa địa chỉ của người nhận "${deleteTarget?.full_name}"?`}
        confirmText="Xóa địa chỉ"
        variant="error"
        loading={deleting}
      />
    </div>
  )
}
