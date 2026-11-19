// src/features/admin/AdminBannerPage.jsx
// Enterprise banner list management interface with search, filters, preview, and dedicated page workflows.

import { useEffect, useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatDate } from '../../utils/formatDate'
import { resolveImageUrl } from '../../utils/resolveImageUrl'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AdminBannerPage() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [banners, setBanners] = useState([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 15,
  })

  // Filters
  const [search, setSearch] = useState('')
  const [positionFilter, setPositionFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  // Delete target
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // Preview target modal
  const [previewBanner, setPreviewBanner] = useState(null)
  const [previewDevice, setPreviewDevice] = useState('desktop')

  const fetchBanners = useCallback((page = 1) => {
    setLoading(true)
    const params = { page, per_page: 15 }
    if (search.trim()) params.search = search.trim()
    if (positionFilter) params.position = positionFilter
    if (statusFilter) params.status = statusFilter

    axiosClient
      .get(EP.ADMIN_BANNERS, { params })
      .then(({ data }) => {
        if (data.data && Array.isArray(data.data)) {
          setBanners(data.data)
          setPagination({
            currentPage: data.current_page || 1,
            lastPage: data.last_page || 1,
            total: data.total || data.data.length,
            perPage: data.per_page || 15,
          })
        } else {
          const list = Array.isArray(data) ? data : []
          setBanners(list)
          setPagination({ currentPage: 1, lastPage: 1, total: list.length, perPage: 15 })
        }
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách banner', 'error'))
      .finally(() => setLoading(false))
  }, [search, positionFilter, statusFilter, showToast])

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchBanners(1)
    }, 250)
    return () => clearTimeout(timer)
  }, [fetchBanners])

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(EP.ADMIN_BANNER_DETAIL(deleteTarget.id))
      showToast('Đã chuyển banner vào thùng rác!', 'info')
      setDeleteTarget(null)
      fetchBanners(pagination.currentPage)
    } catch (err) {
      showToast(err.message || 'Không thể xóa banner', 'error')
    } finally {
      setDeleting(false)
    }
  }

  const renderStatusBadge = (banner) => {
    const status = banner.status || (banner.is_active ? 'published' : 'inactive')
    if (status === 'published' && banner.is_active) {
      return <Badge variant="success">Đang hiển thị</Badge>
    }
    if (status === 'draft') {
      return <Badge variant="warning">Bản nháp</Badge>
    }
    return <Badge variant="neutral">Tạm ngừng</Badge>
  }

  const renderSchedule = (banner) => {
    if (!banner.start_at && !banner.end_at) {
      return <span className="text-xs text-on-surface-variant font-medium">Vĩnh viễn</span>
    }
    return (
      <div className="text-xs space-y-0.5">
        {banner.start_at && (
          <div className="text-on-surface">Từ: {formatDate(banner.start_at)}</div>
        )}
        {banner.end_at && (
          <div className="text-on-surface-variant">Đến: {formatDate(banner.end_at)}</div>
        )}
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Page Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Banner quảng cáo</h1>
          <p className="text-sm text-on-surface-variant">
            Điều chỉnh hình ảnh hero slider, banner phụ và các chiến dịch quảng cáo ngoài storefront.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Link to="/admin/homepage">
            <Button variant="outline">
              <span className="material-symbols-outlined text-base">view_quilt</span>
              Quản lý Trang chủ
            </Button>
          </Link>
          <Button variant="primary" onClick={() => navigate('/admin/banners/create')}>
            <span className="material-symbols-outlined text-base">add</span>
            Thêm banner mới
          </Button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        {/* Search Input */}
        <div className="relative w-full md:w-80">
          <span className="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none">
            search
          </span>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Tìm theo tiêu đề hoặc tên..."
            className="w-full bg-surface-container-low text-on-surface rounded-xl pl-10 pr-4 py-2 text-sm border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        {/* Filter Dropdowns */}
        <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
          <select
            value={positionFilter}
            onChange={(e) => setPositionFilter(e.target.value)}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả vị trí</option>
            <option value="home">Trang chủ (Hero Slider)</option>
            <option value="catalog">Trang danh mục</option>
            <option value="sidebar">Thanh bên</option>
          </select>

          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="published">Đang xuất bản</option>
            <option value="draft">Bản nháp</option>
            <option value="inactive">Tạm ngừng</option>
          </select>

          {(search || positionFilter || statusFilter) && (
            <button
              type="button"
              onClick={() => {
                setSearch('')
                setPositionFilter('')
                setStatusFilter('')
              }}
              className="text-xs text-primary hover:underline font-semibold cursor-pointer px-2"
            >
              Đặt lại
            </button>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-xs">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-24 gap-3">
            <Spinner size="lg" />
            <span className="text-sm text-on-surface-variant font-medium">Đang tải danh sách banner...</span>
          </div>
        ) : banners.length === 0 ? (
          <div className="text-center py-20 px-4 space-y-3">
            <span className="material-symbols-outlined text-5xl text-outline">photo_library</span>
            <p className="text-base font-semibold text-on-surface">Không tìm thấy banner nào</p>
            <p className="text-sm text-on-surface-variant max-w-sm mx-auto">
              Chưa có banner nào phù hợp với bộ lọc hoặc hệ thống chưa có dữ liệu banner.
            </p>
            <Button variant="primary" onClick={() => navigate('/admin/banners/create')}>
              Tạo banner đầu tiên
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-outline-variant/20 bg-surface-container-high/40 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">
                  <th className="py-3.5 px-4 w-16 text-center">Thứ tự</th>
                  <th className="py-3.5 px-4 w-44">Ảnh xem trước</th>
                  <th className="py-3.5 px-4">Tiêu đề & Chiến dịch</th>
                  <th className="py-3.5 px-4 w-32">Vị trí</th>
                  <th className="py-3.5 px-4 w-40">Lịch hiển thị</th>
                  <th className="py-3.5 px-4 w-32 text-center">Trạng thái</th>
                  <th className="py-3.5 px-4 w-36 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-sm">
                {banners.map((b) => {
                  const previewUrl = resolveImageUrl(b.image_path || b.image_url)
                  return (
                    <tr
                      key={b.id}
                      className="hover:bg-surface-container-high/40 transition-colors group"
                    >
                      {/* Sort Order */}
                      <td className="py-4 px-4 text-center font-mono text-xs font-bold text-on-surface-variant">
                        #{b.sort_order ?? 0}
                      </td>

                      {/* Thumbnail */}
                      <td className="py-4 px-4">
                        <div
                          role="button"
                          tabIndex={0}
                          onClick={() => setPreviewBanner(b)}
                          className="w-36 h-20 rounded-xl overflow-hidden bg-surface-container-low border border-outline-variant/30 flex items-center justify-center cursor-pointer relative group/img"
                        >
                          {previewUrl ? (
                            <img
                              src={previewUrl}
                              alt={b.title}
                              className="w-full h-full object-cover group-hover/img:scale-105 transition-transform"
                            />
                          ) : (
                            <span className="material-symbols-outlined text-outline text-2xl">image</span>
                          )}
                          <div className="absolute inset-0 bg-black/40 opacity-0 group-hover/img:opacity-100 transition-opacity flex items-center justify-center text-white">
                            <span className="material-symbols-outlined text-lg">visibility</span>
                          </div>
                        </div>
                      </td>

                      {/* Title & Campaign Name */}
                      <td className="py-4 px-4">
                        <div className="font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-1">
                          {b.title}
                        </div>
                        {b.name && b.name !== b.title && (
                          <div className="text-xs text-on-surface-variant mt-0.5 line-clamp-1">
                            Quản trị: {b.name}
                          </div>
                        )}
                        {b.target_url && (
                          <div className="text-[11px] text-outline font-mono mt-1 flex items-center gap-1 line-clamp-1">
                            <span className="material-symbols-outlined text-xs">link</span>
                            {b.target_url}
                          </div>
                        )}
                      </td>

                      {/* Position */}
                      <td className="py-4 px-4">
                        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-surface-container-high text-xs font-medium text-on-surface">
                          <span className="material-symbols-outlined text-sm text-primary">view_carousel</span>
                          {b.position === 'home' ? 'Trang chủ' : b.position}
                        </span>
                      </td>

                      {/* Schedule */}
                      <td className="py-4 px-4">
                        {renderSchedule(b)}
                      </td>

                      {/* Status */}
                      <td className="py-4 px-4 text-center">
                        {renderStatusBadge(b)}
                      </td>

                      {/* Actions */}
                      <td className="py-4 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            title="Xem trước"
                            onClick={() => setPreviewBanner(b)}
                            className="p-1.5 rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container-high cursor-pointer transition-all"
                          >
                            <span className="material-symbols-outlined text-lg">visibility</span>
                          </button>
                          <button
                            type="button"
                            title="Chỉnh sửa"
                            onClick={() => navigate(`/admin/banners/${b.id}/edit`)}
                            className="p-1.5 rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container-high cursor-pointer transition-all"
                          >
                            <span className="material-symbols-outlined text-lg">edit</span>
                          </button>
                          <button
                            type="button"
                            title="Xóa vào thùng rác"
                            onClick={() => setDeleteTarget(b)}
                            className="p-1.5 rounded-lg text-error hover:bg-error-container/20 cursor-pointer transition-all"
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

      {/* Preview Modal */}
      {previewBanner && (
        <Modal
          open={!!previewBanner}
          onClose={() => setPreviewBanner(null)}
          title={`Xem trước Banner: ${previewBanner.title}`}
          size="lg"
        >
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-xs text-on-surface-variant">
                Chế độ hiển thị mô phỏng thiết bị thực tế
              </span>
              <div className="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-outline-variant/30">
                <button
                  type="button"
                  onClick={() => setPreviewDevice('desktop')}
                  className={`px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-all ${
                    previewDevice === 'desktop' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
                  }`}
                >
                  Desktop
                </button>
                <button
                  type="button"
                  onClick={() => setPreviewDevice('mobile')}
                  className={`px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-all ${
                    previewDevice === 'mobile' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
                  }`}
                >
                  Mobile
                </button>
              </div>
            </div>

            <div className="flex justify-center bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/20">
              <div
                className={`relative overflow-hidden rounded-2xl shadow-md transition-all ${
                  previewDevice === 'desktop' ? 'w-full max-w-3xl h-64 md:h-80' : 'w-72 h-96'
                }`}
              >
                <img
                  src={
                    previewDevice === 'mobile' && previewBanner.mobile_image_path
                      ? resolveImageUrl(previewBanner.mobile_image_path)
                      : resolveImageUrl(previewBanner.image_path || previewBanner.image_url)
                  }
                  alt={previewBanner.title}
                  className="w-full h-full object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-6 flex flex-col justify-end text-white">
                  <span className="text-xs uppercase font-bold text-primary-container tracking-wider mb-1">
                    {previewBanner.position === 'home' ? 'Hero Banner' : previewBanner.position}
                  </span>
                  <h3 className="text-xl md:text-2xl font-black">{previewBanner.title}</h3>
                  {previewBanner.description && (
                    <p className="text-sm text-white/80 line-clamp-2 mt-1">{previewBanner.description}</p>
                  )}
                  {previewBanner.cta_label && (
                    <div className="mt-3">
                      <span className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-md">
                        {previewBanner.cta_label}
                        <span className="material-symbols-outlined text-sm">arrow_forward</span>
                      </span>
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-between items-center pt-2">
              <span className="text-xs text-on-surface-variant">
                Liên kết: <code className="font-mono text-primary">{previewBanner.target_url || 'Chưa thiết lập'}</code>
              </span>
              <Button variant="primary" onClick={() => {
                const bId = previewBanner.id
                setPreviewBanner(null)
                navigate(`/admin/banners/${bId}/edit`)
              }}>
                Chỉnh sửa Banner này
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển banner "${deleteTarget?.title}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào từ mục Thùng rác.`}
        confirmLabel="Chuyển vào Thùng rác"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />
    </div>
  )
}
