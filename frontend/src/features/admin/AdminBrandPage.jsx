// src/features/admin/AdminBrandPage.jsx
// Admin brand catalog management: listing, creation, modification, and safe deletion.

import { useCallback, useEffect, useMemo, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
}

export default function AdminBrandPage() {
  const { showToast } = useToast()

  const [brands, setBrands] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')

  // Create / Edit modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editingBrand, setEditingBrand] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const [form, setForm] = useState({
    name: '',
    slug: '',
    logo: '',
    description: '',
    status: true,
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // ── Fetch Brands ───────────────────────────────────────────────────────────
  const fetchBrands = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.BRANDS)
      const list = data.data ?? data
      setBrands(Array.isArray(list) ? list : [])
    } catch {
      showToast('Không thể tải danh sách thương hiệu.', 'error')
    } finally {
      setLoading(false)
    }
  }, [showToast])

  useEffect(() => {
    fetchBrands()
  }, [fetchBrands])

  // ── Modal Handlers ────────────────────────────────────────────────────────
  const handleOpenCreate = () => {
    setEditingBrand(null)
    setForm({
      name: '',
      slug: '',
      logo: '',
      description: '',
      status: true,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (b) => {
    setEditingBrand(b)
    setForm({
      name: b.name ?? '',
      slug: b.slug ?? '',
      logo: b.logo ?? b.logo_url ?? '',
      description: b.description ?? '',
      status: Boolean(b.status ?? b.is_active ?? true),
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleNameChange = (val) => {
    setForm((prev) => ({
      ...prev,
      name: val,
      slug: editingBrand ? prev.slug : slugify(val),
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      logo: form.logo ? form.logo.trim() : null,
      description: form.description ? form.description.trim() : null,
      status: Boolean(form.status),
    }

    try {
      if (editingBrand) {
        await axiosClient.patch(`${EP.BRANDS}/${editingBrand.id}`, payload)
        showToast('Cập nhật thương hiệu thành công!')
      } else {
        await axiosClient.post(EP.BRANDS, payload)
        showToast('Tạo thương hiệu mới thành công!')
      }
      setModalOpen(false)
      fetchBrands()
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data?.errors ?? {})
      } else {
        const msg = err.response?.data?.message ?? 'Đã có lỗi xảy ra khi lưu thương hiệu.'
        showToast(msg, 'error')
      }
    } finally {
      setSubmitting(false)
    }
  }

  // ── Delete Handler ────────────────────────────────────────────────────────
  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(`${EP.BRANDS}/${deleteTarget.id}`)
      showToast('Đã xóa thương hiệu thành công!')
      setDeleteTarget(null)
      fetchBrands()
    } catch (err) {
      const msg = err.response?.data?.message || err.response?.data?.error || 'Không thể xóa thương hiệu này.'
      showToast(msg, 'error')
    } finally {
      setDeleting(false)
    }
  }

  // ── Filtered Brands ───────────────────────────────────────────────────────
  const filteredBrands = useMemo(() => {
    if (!search.trim()) return brands
    const q = search.toLowerCase().trim()
    return brands.filter(
      (b) =>
        b.name?.toLowerCase().includes(q) ||
        b.slug?.toLowerCase().includes(q) ||
        b.description?.toLowerCase().includes(q)
    )
  }, [brands, search])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Thương hiệu</h1>
          <p className="text-sm text-outline mt-1">
            Quản lý danh sách các nhà sản xuất, đối tác cung cấp thiết bị và phụ kiện
          </p>
        </div>
        <Button onClick={handleOpenCreate} variant="primary" className="flex items-center gap-2">
          <span className="material-symbols-outlined text-base">add</span>
          Thêm thương hiệu
        </Button>
      </div>

      {/* Filter bar */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div className="relative w-full sm:w-80">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">
            search
          </span>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Tìm theo tên hãng, slug..."
            className="w-full pl-9 pr-4 py-2 bg-surface-container-low text-on-surface text-sm rounded-xl border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
        <div className="text-xs text-outline">
          Tổng số: <span className="font-semibold text-on-surface">{brands.length}</span> thương hiệu
        </div>
      </div>

      {/* Grid Cards / Table View */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner size="lg" />
          </div>
        ) : filteredBrands.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
            <span className="material-symbols-outlined text-4xl">store</span>
            <p className="text-sm">Không tìm thấy thương hiệu nào phù hợp</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                  <th className="py-3 px-4">Thương hiệu</th>
                  <th className="py-3 px-4">Slug</th>
                  <th className="py-3 px-4">Mô tả</th>
                  <th className="py-3 px-4 text-center">Trạng thái</th>
                  <th className="py-3 px-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                {filteredBrands.map((b) => {
                  const isActive = b.status ?? b.is_active ?? true
                  const logoUrl = b.logo ?? b.logo_url

                  return (
                    <tr key={b.id} className="hover:bg-surface-container-high/30 transition-colors">
                      <td className="py-3.5 px-4 font-semibold text-on-surface">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant/20 overflow-hidden flex items-center justify-center shrink-0 p-1">
                            {logoUrl ? (
                              <img
                                src={logoUrl}
                                alt={b.name}
                                className="w-full h-full object-contain"
                                onError={(e) => {
                                  e.target.onerror = null
                                  e.target.src =
                                    'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjZTNlMmU2Ii8+PHRleHQgeD0iMjAiIHk9IjI1IiBmb250LXNpemU9IjEyIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjN2E3Nzc5Ij7wn4+APC90ZXh0Pjwvc3ZnPg=='
                                }}
                              />
                            ) : (
                              <span className="material-symbols-outlined text-outline text-xl">branding_watermark</span>
                            )}
                          </div>
                          <div>
                            <p className="font-semibold text-on-surface">{b.name}</p>
                            <p className="text-xs text-outline font-mono">ID: #{b.id}</p>
                          </div>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 font-mono text-xs text-outline">{b.slug}</td>
                      <td className="py-3.5 px-4 text-xs text-on-surface-variant max-w-xs truncate">
                        {b.description || '—'}
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <Badge variant={isActive ? 'success' : 'neutral'}>
                          {isActive ? 'Hoạt động' : 'Tạm ẩn'}
                        </Badge>
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() => handleOpenEdit(b)}
                            title="Chỉnh sửa"
                            className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors"
                          >
                            <span className="material-symbols-outlined text-lg">edit</span>
                          </button>
                          <button
                            onClick={() => setDeleteTarget(b)}
                            title="Xóa thương hiệu"
                            className="p-1.5 rounded-lg hover:bg-error/10 text-on-surface-variant hover:text-error transition-colors"
                          >
                            <span className="material-symbols-outlined text-lg">delete</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Create / Edit Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingBrand ? `Chỉnh sửa: ${editingBrand.name}` : 'Thêm thương hiệu mới'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Tên thương hiệu *"
            value={form.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder="Ví dụ: Apple, Samsung, Xiaomi..."
            error={errors.name?.[0]}
            required
          />

          <Input
            label="Đường dẫn tĩnh (Slug) *"
            value={form.slug}
            onChange={(e) => setForm({ ...form, slug: e.target.value })}
            placeholder="apple, samsung..."
            error={errors.slug?.[0]}
            required
          />

          <Input
            label="Đường dẫn Logo (URL)"
            value={form.logo}
            onChange={(e) => setForm({ ...form, logo: e.target.value })}
            placeholder="https://example.com/logo.png hoặc để trống"
            error={errors.logo?.[0]}
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-on-surface-variant">Trạng thái kinh doanh</label>
            <select
              value={form.status ? '1' : '0'}
              onChange={(e) => setForm({ ...form, status: e.target.value === '1' })}
              className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="1">Hoạt động (Cho phép bán sản phẩm)</option>
              <option value="0">Tạm ẩn</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-on-surface-variant">Mô tả thương hiệu</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              rows={3}
              placeholder="Nhập thông tin giới thiệu về thương hiệu, xuất xứ..."
              className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary resize-none"
            />
            {errors.description && <p className="text-xs text-error">{errors.description[0]}</p>}
          </div>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editingBrand ? 'Lưu thay đổi' : 'Tạo mới'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển thương hiệu "${deleteTarget?.name}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmText="Chuyển vào Thùng rác"
        variant="error"
        loading={deleting}
      />
    </div>
  )
}
