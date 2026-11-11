// src/features/admin/AdminCategoryPage.jsx
// Admin category hierarchy management: listing, tree view, creation, editing, safe deletion.

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

export default function AdminCategoryPage() {
  const { showToast } = useToast()

  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')

  // Create / Edit modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const [form, setForm] = useState({
    name: '',
    slug: '',
    parent_id: '',
    description: '',
    image: '',
    sort_order: '0',
    status: true,
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // ── Fetch Categories ───────────────────────────────────────────────────────
  const fetchCategories = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.CATEGORIES)
      const list = data.data ?? data
      setCategories(Array.isArray(list) ? list : [])
    } catch {
      showToast('Không thể tải danh sách danh mục.', 'error')
    } finally {
      setLoading(false)
    }
  }, [showToast])

  useEffect(() => {
    fetchCategories()
  }, [fetchCategories])

  // ── Modal Handlers ────────────────────────────────────────────────────────
  const handleOpenCreate = (parentId = '') => {
    setEditingCategory(null)
    setForm({
      name: '',
      slug: '',
      parent_id: parentId ? String(parentId) : '',
      description: '',
      image: '',
      sort_order: '0',
      status: true,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (cat) => {
    setEditingCategory(cat)
    setForm({
      name: cat.name ?? '',
      slug: cat.slug ?? '',
      parent_id: cat.parent_id ? String(cat.parent_id) : '',
      description: cat.description ?? '',
      image: cat.image ?? cat.image_path ?? '',
      sort_order: String(cat.sort_order ?? 0),
      status: Boolean(cat.status ?? cat.is_active ?? true),
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleNameChange = (val) => {
    setForm((prev) => ({
      ...prev,
      name: val,
      slug: editingCategory ? prev.slug : slugify(val),
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      parent_id: form.parent_id ? Number(form.parent_id) : null,
      description: form.description ? form.description.trim() : null,
      image: form.image ? form.image.trim() : null,
      sort_order: Number(form.sort_order) || 0,
      status: Boolean(form.status),
    }

    try {
      if (editingCategory) {
        await axiosClient.patch(`${EP.CATEGORIES}/${editingCategory.id}`, payload)
        showToast('Cập nhật danh mục thành công!')
      } else {
        await axiosClient.post(EP.CATEGORIES, payload)
        showToast('Tạo danh mục mới thành công!')
      }
      setModalOpen(false)
      fetchCategories()
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data?.errors ?? {})
      } else {
        const msg = err.response?.data?.message ?? 'Đã có lỗi xảy ra khi lưu danh mục.'
        showToast(msg, 'error')
      }
    } finally {
      setSubmitting(false)
    }
  }

  // ── Delete Handler (Safe Deletion with 409 Conflict guard) ─────────────────
  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(`${EP.CATEGORIES}/${deleteTarget.id}`)
      showToast('Đã xóa danh mục thành công!')
      setDeleteTarget(null)
      fetchCategories()
    } catch (err) {
      const msg = err.response?.data?.message || err.response?.data?.error || 'Không thể xóa danh mục này.'
      showToast(msg, 'error')
    } finally {
      setDeleting(false)
    }
  }

  // ── Filtered Categories ───────────────────────────────────────────────────
  const filteredCategories = useMemo(() => {
    if (!search.trim()) return categories
    const q = search.toLowerCase().trim()
    return categories.filter(
      (cat) =>
        cat.name?.toLowerCase().includes(q) ||
        cat.slug?.toLowerCase().includes(q) ||
        cat.description?.toLowerCase().includes(q)
    )
  }, [categories, search])

  // Get parent map for lookup
  const categoryMap = useMemo(() => {
    const map = {}
    categories.forEach((cat) => {
      map[cat.id] = cat.name
    })
    return map
  }, [categories])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Danh mục</h1>
          <p className="text-sm text-outline mt-1">
            Phân loại cấu trúc danh mục sản phẩm đa cấp, hiển thị menu và bộ lọc
          </p>
        </div>
        <Button onClick={() => handleOpenCreate()} variant="primary" className="flex items-center gap-2">
          <span className="material-symbols-outlined text-base">add</span>
          Thêm danh mục
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
            placeholder="Tìm theo tên, đường dẫn slug..."
            className="w-full pl-9 pr-4 py-2 bg-surface-container-low text-on-surface text-sm rounded-xl border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
        <div className="text-xs text-outline">
          Tổng số: <span className="font-semibold text-on-surface">{categories.length}</span> danh mục
        </div>
      </div>

      {/* Table Content */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner size="lg" />
          </div>
        ) : filteredCategories.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
            <span className="material-symbols-outlined text-4xl">category</span>
            <p className="text-sm">Không tìm thấy danh mục nào phù hợp</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                  <th className="py-3 px-4">Tên danh mục</th>
                  <th className="py-3 px-4">Slug</th>
                  <th className="py-3 px-4">Cấp danh mục</th>
                  <th className="py-3 px-4 text-center">Thứ tự</th>
                  <th className="py-3 px-4 text-center">Trạng thái</th>
                  <th className="py-3 px-4 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                {filteredCategories.map((cat) => {
                  const isSub = Boolean(cat.parent_id)
                  const parentName = cat.parent_id ? categoryMap[cat.parent_id] : null
                  const isActive = cat.status ?? cat.is_active ?? true

                  return (
                    <tr key={cat.id} className="hover:bg-surface-container-high/30 transition-colors">
                      <td className="py-3.5 px-4 font-medium text-on-surface">
                        <div className="flex items-center gap-2">
                          {isSub && (
                            <span className="text-outline text-xs select-none pl-4">↳</span>
                          )}
                          <span className={isSub ? 'text-on-surface-variant' : 'font-semibold text-on-surface'}>
                            {cat.name}
                          </span>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 font-mono text-xs text-outline">{cat.slug}</td>
                      <td className="py-3.5 px-4">
                        {isSub ? (
                          <span className="text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                            Thuộc: {parentName || `#${cat.parent_id}`}
                          </span>
                        ) : (
                          <span className="text-xs text-outline bg-surface-container-high px-2 py-0.5 rounded-md font-medium">
                            Danh mục gốc
                          </span>
                        )}
                      </td>
                      <td className="py-3.5 px-4 text-center text-xs font-mono">{cat.sort_order ?? 0}</td>
                      <td className="py-3.5 px-4 text-center">
                        <Badge variant={isActive ? 'success' : 'neutral'}>
                          {isActive ? 'Hoạt động' : 'Tạm ẩn'}
                        </Badge>
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() => handleOpenCreate(cat.id)}
                            title="Thêm danh mục con"
                            className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors"
                          >
                            <span className="material-symbols-outlined text-lg">add_circle</span>
                          </button>
                          <button
                            onClick={() => handleOpenEdit(cat)}
                            title="Chỉnh sửa"
                            className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors"
                          >
                            <span className="material-symbols-outlined text-lg">edit</span>
                          </button>
                          <button
                            onClick={() => setDeleteTarget(cat)}
                            title="Xóa danh mục"
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
        title={editingCategory ? `Chỉnh sửa: ${editingCategory.name}` : 'Thêm danh mục mới'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Tên danh mục *"
            value={form.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder="Ví dụ: Điện thoại di động, Phụ kiện..."
            error={errors.name?.[0]}
            required
          />

          <Input
            label="Đường dẫn tĩnh (Slug) *"
            value={form.slug}
            onChange={(e) => setForm({ ...form, slug: e.target.value })}
            placeholder="dien-thoai-di-dong"
            error={errors.slug?.[0]}
            required
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-on-surface-variant">Danh mục cha</label>
            <select
              value={form.parent_id}
              onChange={(e) => setForm({ ...form, parent_id: e.target.value })}
              className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="">(Không có - Danh mục gốc)</option>
              {categories
                .filter((c) => !editingCategory || c.id !== editingCategory.id)
                .map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.parent_id ? `— ${c.name}` : c.name}
                  </option>
                ))}
            </select>
            {errors.parent_id && <p className="text-xs text-error">{errors.parent_id[0]}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Thứ tự hiển thị"
              type="number"
              min="0"
              value={form.sort_order}
              onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
              error={errors.sort_order?.[0]}
            />

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium text-on-surface-variant">Trạng thái</label>
              <select
                value={form.status ? '1' : '0'}
                onChange={(e) => setForm({ ...form, status: e.target.value === '1' })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="1">Hoạt động (Hiển thị)</option>
                <option value="0">Tạm ẩn</option>
              </select>
            </div>
          </div>

          <Input
            label="Đường dẫn ảnh biểu trưng (URL)"
            value={form.image}
            onChange={(e) => setForm({ ...form, image: e.target.value })}
            placeholder="https://example.com/icon.png hoặc để trống"
            error={errors.image?.[0]}
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-on-surface-variant">Mô tả ngắn</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              rows={3}
              placeholder="Mô tả danh mục hỗ trợ SEO..."
              className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary resize-none"
            />
            {errors.description && <p className="text-xs text-error">{errors.description[0]}</p>}
          </div>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editingCategory ? 'Lưu thay đổi' : 'Tạo mới'}
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
        message={`Bạn có chắc chắn muốn chuyển danh mục "${deleteTarget?.name}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmText="Chuyển vào Thùng rác"
        variant="error"
        loading={deleting}
      />
    </div>
  )
}
