// src/features/admin/AdminBannerPage.jsx
// Admin promotional banner CRUD management interface.

import { useEffect, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatDate } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AdminBannerPage() {
  const { showToast } = useToast()

  const [banners, setBanners] = useState([])
  const [loading, setLoading] = useState(true)

  // Create / Edit modal
  const [modalOpen, setModalOpen] = useState(false)
  const [editingBanner, setEditingBanner] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const [form, setForm] = useState({
    title: '',
    image_path: '',
    target_url: '',
    position: 'home',
    sort_order: '0',
    is_active: true,
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const fetchBanners = () => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_BANNERS)
      .then(({ data }) => {
        const payload = data.data ?? data
        setBanners(Array.isArray(payload) ? payload : (payload.data ?? []))
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách banner', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchBanners()
  }, [])

  const handleOpenCreate = () => {
    setEditingBanner(null)
    setForm({
      title: '',
      image_path: '',
      target_url: '',
      position: 'home',
      sort_order: '0',
      is_active: true,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (b) => {
    setEditingBanner(b)
    setForm({
      title: b.title || '',
      image_path: b.image_path || b.image_url || '',
      target_url: b.target_url || b.link_url || '',
      position: b.position || 'home',
      sort_order: String(b.sort_order ?? 0),
      is_active: !!b.is_active,
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      title: form.title.trim(),
      image_path: form.image_path.trim(),
      target_url: form.target_url.trim() || null,
      position: form.position || 'home',
      sort_order: Number(form.sort_order) || 0,
      is_active: Boolean(form.is_active),
    }

    try {
      if (editingBanner) {
        await axiosClient.put(EP.ADMIN_BANNER_DETAIL(editingBanner.id), payload)
        showToast('Cập nhật banner thành công!', 'success')
      } else {
        await axiosClient.post(EP.ADMIN_BANNERS, payload)
        showToast('Tạo banner mới thành công!', 'success')
      }
      setModalOpen(false)
      fetchBanners()
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi lưu banner', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(EP.ADMIN_BANNER_DETAIL(deleteTarget.id))
      showToast('Đã xóa banner!', 'info')
      setDeleteTarget(null)
      fetchBanners()
    } catch (err) {
      showToast(err.message || 'Không thể xóa banner', 'error')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Page Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Banner quảng cáo</h1>
          <p className="text-sm text-on-surface-variant">
            Điều chỉnh hình ảnh hero slider và chiến dịch quảng cáo ngoài storefront.
          </p>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <span className="material-symbols-outlined text-base">add</span>
          Thêm banner mới
        </Button>
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : banners.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">image</span>
            <p className="text-sm font-medium">Chưa có banner nào được tạo.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Ảnh xem trước</th>
                  <th className="px-5 py-3.5">Tiêu đề & Liên kết</th>
                  <th className="px-5 py-3.5">Vị trí</th>
                  <th className="px-5 py-3.5 text-center">Thứ tự</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {banners.map((b) => (
                  <tr key={b.id} className="hover:bg-surface-container-high/40 transition-colors">
                    <td className="px-5 py-3">
                      <div className="w-24 h-12 bg-surface-container-high rounded-lg overflow-hidden flex items-center justify-center border border-outline-variant/20">
                        {b.image_path || b.image_url ? (
                          <img
                            src={b.image_path || b.image_url}
                            alt={b.title}
                            className="w-full h-full object-cover"
                          />
                        ) : (
                          <span className="material-symbols-outlined text-outline text-lg">image</span>
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-3">
                      <p className="font-semibold text-on-surface">{b.title}</p>
                      {b.target_url && (
                        <a
                          href={b.target_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-xs text-primary hover:underline truncate max-w-xs block"
                        >
                          {b.target_url}
                        </a>
                      )}
                    </td>
                    <td className="px-5 py-3 text-xs uppercase font-mono text-on-surface-variant">
                      {b.position || 'home'}
                    </td>
                    <td className="px-5 py-3 text-center font-mono text-xs">
                      {b.sort_order ?? 0}
                    </td>
                    <td className="px-5 py-3 text-center">
                      <Badge variant={b.is_active ? 'success' : 'neutral'}>
                        {b.is_active ? 'Hiển thị' : 'Đang ẩn'}
                      </Badge>
                    </td>
                    <td className="px-5 py-3 text-right space-x-2">
                      <button
                        onClick={() => handleOpenEdit(b)}
                        className="px-2.5 py-1 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors"
                      >
                        Sửa
                      </button>
                      <button
                        onClick={() => setDeleteTarget(b)}
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

      {/* Modal Add / Edit */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingBanner ? `Sửa banner: ${editingBanner.title}` : 'Thêm banner mới'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Tiêu đề banner *"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            placeholder="Ví dụ: Đại tiệc Flagship iPhone 16 Pro Max"
            error={errors.title?.[0]}
            required
          />

          <Input
            label="Đường dẫn ảnh (URL) *"
            value={form.image_path}
            onChange={(e) => setForm({ ...form, image_path: e.target.value })}
            placeholder="https://example.com/banner.jpg"
            error={errors.image_path?.[0]}
            required
          />

          <Input
            label="Liên kết khi nhấn (URL đích)"
            value={form.target_url}
            onChange={(e) => setForm({ ...form, target_url: e.target.value })}
            placeholder="/products/1 hoặc https://..."
            error={errors.target_url?.[0]}
          />

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Vị trí hiển thị</label>
              <select
                value={form.position}
                onChange={(e) => setForm({ ...form, position: e.target.value })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="home">Trang chủ (Hero Slider)</option>
                <option value="catalog">Trang danh mục sản phẩm</option>
                <option value="sidebar">Thanh bên (Sidebar)</option>
              </select>
            </div>

            <Input
              label="Thứ tự ưu tiên"
              type="number"
              value={form.sort_order}
              onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
              placeholder="0"
              error={errors.sort_order?.[0]}
            />
          </div>

          <label className="flex items-center gap-2 text-sm text-on-surface cursor-pointer pt-1">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              className="rounded text-primary focus:ring-primary"
            />
            Kích hoạt hiển thị ngay
          </label>

          <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
              Hủy
            </Button>
            <Button type="submit" variant="primary" loading={submitting}>
              {editingBanner ? 'Lưu thay đổi' : 'Thêm mới'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển banner "${deleteTarget?.title}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmLabel="Chuyển vào Thùng rác"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />
    </div>
  )
}
