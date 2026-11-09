// src/features/admin/AdminSpecPage.jsx
// Admin CRUD page for ProductSpecificationDefinition — same pattern as AdminCategoryPage.

import { useCallback, useEffect, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

const VALUE_TYPE_LABELS = {
  text: 'Văn bản',
  number: 'Số',
  boolean: 'Có / Không',
}

const APPLIES_TO_LABELS = {
  all: 'Tất cả',
  phone: 'Điện thoại',
  accessory: 'Phụ kiện',
}

const APPLIES_TO_VARIANTS = {
  all: 'neutral',
  phone: 'primary',
  accessory: 'secondary',
}

const EMPTY_FORM = {
  name: '',
  unit: '',
  value_type: 'text',
  applies_to: 'all',
  sort_order: '0',
  is_active: true,
}

export default function AdminSpecPage() {
  const { showToast } = useToast()

  const [defs, setDefs] = useState([])
  const [loading, setLoading] = useState(true)
  const [filterAppliesTo, setFilterAppliesTo] = useState('')

  // Modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [form, setForm] = useState({ ...EMPTY_FORM })

  // Delete
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // ── Fetch ─────────────────────────────────────────────────────────────────
  const fetchDefs = useCallback(async () => {
    setLoading(true)
    try {
      const params = {}
      if (filterAppliesTo) params.applies_to = filterAppliesTo
      const { data } = await axiosClient.get(EP.SPEC_DEFINITIONS, { params })
      setDefs(Array.isArray(data) ? data : (data.data ?? []))
    } catch {
      showToast('Không thể tải danh sách thông số kỹ thuật.', 'error')
    } finally {
      setLoading(false)
    }
  }, [showToast, filterAppliesTo])

  useEffect(() => {
    fetchDefs()
  }, [fetchDefs])

  // ── Modal handlers ─────────────────────────────────────────────────────────
  const handleOpenCreate = () => {
    setEditing(null)
    setForm({ ...EMPTY_FORM })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (def) => {
    setEditing(def)
    setForm({
      name: def.name || '',
      unit: def.unit || '',
      value_type: def.value_type || 'text',
      applies_to: def.applies_to || 'all',
      sort_order: def.sort_order != null ? String(def.sort_order) : '0',
      is_active: def.is_active !== false,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      name: form.name.trim(),
      unit: form.unit.trim() || null,
      value_type: form.value_type,
      applies_to: form.applies_to,
      sort_order: parseInt(form.sort_order, 10) || 0,
      is_active: form.is_active,
    }

    try {
      if (editing) {
        await axiosClient.put(EP.SPEC_DEFINITIONS_BY_ID(editing.id), payload)
        showToast('Cập nhật thông số kỹ thuật thành công!', 'success')
      } else {
        await axiosClient.post(EP.SPEC_DEFINITIONS, payload)
        showToast('Thêm thông số kỹ thuật thành công!', 'success')
      }
      setModalOpen(false)
      fetchDefs()
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi lưu thông số kỹ thuật', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(EP.SPEC_DEFINITIONS_BY_ID(deleteTarget.id))
      showToast('Đã xóa thông số kỹ thuật.', 'success')
      setDeleteTarget(null)
      fetchDefs()
    } catch (err) {
      showToast(err.message || 'Không thể xóa thông số kỹ thuật', 'error')
    } finally {
      setDeleting(false)
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Thông số kỹ thuật</h1>
          <p className="text-sm text-on-surface-variant">
            Quản lý định nghĩa thông số (tiêu chí) áp dụng cho sản phẩm.
          </p>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <span className="material-symbols-outlined text-base">add</span>
          Thêm thông số
        </Button>
      </div>

      {/* Filter */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-wrap gap-3 items-center">
        <span className="text-xs font-semibold text-on-surface-variant uppercase">Áp dụng cho:</span>
        {[
          { value: '', label: 'Tất cả' },
          { value: 'phone', label: 'Điện thoại' },
          { value: 'accessory', label: 'Phụ kiện' },
        ].map(({ value, label }) => (
          <button
            key={value}
            onClick={() => setFilterAppliesTo(value)}
            className={[
              'px-4 py-1.5 rounded-xl text-sm font-medium border transition-colors',
              filterAppliesTo === value
                ? 'bg-primary-container text-on-primary-container border-primary/30'
                : 'bg-surface-container-low text-on-surface-variant border-outline-variant/30 hover:border-outline',
            ].join(' ')}
          >
            {label}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : defs.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">tune</span>
            <p className="text-sm font-medium">Chưa có thông số kỹ thuật nào.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Tên thông số</th>
                  <th className="px-5 py-3.5">Đơn vị</th>
                  <th className="px-5 py-3.5">Kiểu dữ liệu</th>
                  <th className="px-5 py-3.5">Áp dụng cho</th>
                  <th className="px-5 py-3.5 text-center">Thứ tự</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {defs.map((def) => (
                  <tr key={def.id} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="px-5 py-3 font-semibold text-on-surface">{def.name}</td>
                    <td className="px-5 py-3 text-on-surface-variant font-mono text-xs">
                      {def.unit || <span className="text-outline italic">—</span>}
                    </td>
                    <td className="px-5 py-3">
                      <span className="px-2 py-0.5 bg-surface-container-high rounded-full text-xs font-medium text-on-surface">
                        {VALUE_TYPE_LABELS[def.value_type] || def.value_type}
                      </span>
                    </td>
                    <td className="px-5 py-3">
                      <Badge variant={APPLIES_TO_VARIANTS[def.applies_to] || 'neutral'}>
                        {APPLIES_TO_LABELS[def.applies_to] || def.applies_to}
                      </Badge>
                    </td>
                    <td className="px-5 py-3 text-center">
                      <span className="font-mono text-xs text-on-surface-variant">{def.sort_order ?? 0}</span>
                    </td>
                    <td className="px-5 py-3 text-center">
                      <Badge variant={def.is_active !== false ? 'success' : 'neutral'}>
                        {def.is_active !== false ? 'Hoạt động' : 'Ẩn'}
                      </Badge>
                    </td>
                    <td className="px-5 py-3 text-right space-x-2 whitespace-nowrap">
                      <button
                        onClick={() => handleOpenEdit(def)}
                        className="px-2.5 py-1 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors"
                      >
                        Sửa
                      </button>
                      <button
                        onClick={() => setDeleteTarget(def)}
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
        title={editing ? `Chỉnh sửa: ${editing.name}` : 'Thêm thông số kỹ thuật'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Tên thông số *"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            placeholder="Ví dụ: Dung lượng RAM, Màn hình..."
            error={errors.name?.[0]}
            required
          />

          <Input
            label="Đơn vị (nếu có)"
            value={form.unit}
            onChange={(e) => setForm({ ...form, unit: e.target.value })}
            placeholder="Ví dụ: GB, inch, mAh..."
            error={errors.unit?.[0]}
          />

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Kiểu dữ liệu *</label>
              <select
                value={form.value_type}
                onChange={(e) => setForm({ ...form, value_type: e.target.value })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="text">Văn bản (text)</option>
                <option value="number">Số (number)</option>
                <option value="boolean">Có / Không (boolean)</option>
              </select>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Áp dụng cho *</label>
              <select
                value={form.applies_to}
                onChange={(e) => setForm({ ...form, applies_to: e.target.value })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="all">Tất cả sản phẩm</option>
                <option value="phone">Điện thoại</option>
                <option value="accessory">Phụ kiện</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Thứ tự hiển thị"
              type="number"
              min="0"
              value={form.sort_order}
              onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
              placeholder="0"
              error={errors.sort_order?.[0]}
            />

            <div className="space-y-1 flex flex-col justify-end pb-0.5">
              <label className="text-sm font-medium text-on-surface-variant">Trạng thái</label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                  className="w-4 h-4 rounded accent-primary cursor-pointer"
                />
                <span className="text-sm text-on-surface">Đang hoạt động</span>
              </label>
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editing ? 'Lưu thay đổi' : 'Tạo mới'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Xóa thông số kỹ thuật"
        message={`Bạn có chắc chắn muốn xóa thông số "${deleteTarget?.name}"? Dữ liệu spec của các sản phẩm liên quan cũng sẽ bị ảnh hưởng.`}
        confirmLabel="Xác nhận xóa"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />
    </div>
  )
}
