// src/features/admin/homepage/AdminHomepagePage.jsx
// Central Admin Homepage CMS manager: Section layout, ordering, configuration, preview & publish.
// URL: /admin/homepage

import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../../api/axiosClient'
import EP from '../../../api/endpoints'
import { useToast } from '../../../contexts/ToastContext'
import Button from '../../../components/common/Button'
import Spinner from '../../../components/common/Spinner'
import Badge from '../../../components/common/Badge'
import SectionEditModal from './SectionEditModal'
import HomepagePreviewModal from './HomepagePreviewModal'
import HomepageAuditLogsModal from './HomepageAuditLogsModal'
import ConfirmDialog from '../../../components/feedback/ConfirmDialog'

const SECTION_TYPE_META = {
  banner_slider:   { label: 'Hero Banner Slider', icon: 'view_carousel', color: 'text-primary' },
  categories:      { label: 'Danh mục sản phẩm', icon: 'category', color: 'text-secondary' },
  flash_sale:      { label: 'Flash Sale Giờ Vàng', icon: 'local_fire_department', color: 'text-error' },
  products:        { label: 'Sản phẩm nổi bật / Mới về', icon: 'inventory_2', color: 'text-tertiary' },
  banner_promo:    { label: 'Banner quảng bá / CTA', icon: 'featured_play_list', color: 'text-primary' },
  brands:          { label: 'Thương hiệu đối tác', icon: 'store', color: 'text-secondary' },
  trust_services:  { label: 'Cam kết & Dịch vụ', icon: 'verified', color: 'text-success' },
  recently_viewed: { label: 'Sản phẩm vừa xem', icon: 'history', color: 'text-outline' },
}

export default function AdminHomepagePage() {
  const { showToast } = useToast()

  const [sections, setSections] = useState([])
  const [loading, setLoading] = useState(true)
  const [publishing, setPublishing] = useState(false)
  const [savingOrder, setSavingOrder] = useState(false)

  // Metas for section pickers
  const [allCategories, setAllCategories] = useState([])
  const [allBrands, setAllBrands] = useState([])

  // Modals
  const [editingSection, setEditingSection] = useState(null)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [auditLogsOpen, setAuditLogsOpen] = useState(false)
  const [resetConfirmOpen, setResetConfirmOpen] = useState(false)
  const [revertConfirmOpen, setRevertConfirmOpen] = useState(false)

  // Dirty state tracking (reorder or draft updates)
  const [hasPendingChanges, setHasPendingChanges] = useState(false)

  const fetchSections = useCallback(() => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_HOMEPAGE)
      .then(({ data }) => {
        const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
        // Sort by sort_order
        list.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        setSections(list)
      })
      .catch((err) => showToast(err.message || 'Không thể tải cấu hình trang chủ', 'error'))
      .finally(() => setLoading(false))
  }, [showToast])

  useEffect(() => {
    fetchSections()

    // Fetch categories and brands metadata for picker options
    Promise.allSettled([
      axiosClient.get(EP.CATEGORIES),
      axiosClient.get(EP.BRANDS),
    ]).then(([catRes, brandRes]) => {
      if (catRes.status === 'fulfilled') {
        const cData = catRes.value.data?.data ?? catRes.value.data ?? []
        setAllCategories(Array.isArray(cData) ? cData : [])
      }
      if (brandRes.status === 'fulfilled') {
        const bData = brandRes.value.data?.data ?? brandRes.value.data ?? []
        setAllBrands(Array.isArray(bData) ? bData : [])
      }
    })
  }, [fetchSections])

  // Move section Up or Down
  const moveSection = (index, direction) => {
    const targetIndex = index + direction
    if (targetIndex < 0 || targetIndex >= sections.length) return

    const newSections = [...sections]
    const [moved] = newSections.splice(index, 1)
    newSections.splice(targetIndex, 0, moved)

    // Reassign sort orders
    const updated = newSections.map((sec, idx) => ({
      ...sec,
      sort_order: idx + 1,
    }))

    setSections(updated)
    setHasPendingChanges(true)
  }

  // Toggle is_active
  const toggleSectionActive = (id) => {
    setSections((prev) =>
      prev.map((s) => (s.id === id ? { ...s, is_active: !s.is_active } : s))
    )
    setHasPendingChanges(true)
  }

  // Save current order & active states to backend
  const handleSaveReorder = async () => {
    setSavingOrder(true)
    try {
      const orders = sections.map((s, idx) => ({
        id: s.id,
        sort_order: idx + 1,
        is_active: Boolean(s.is_active),
      }))

      await axiosClient.post(EP.ADMIN_HOMEPAGE_REORDER, { orders })
      showToast('Đã lưu thứ tự và trạng thái các section!', 'success')
      setHasPendingChanges(true)
      fetchSections()
    } catch (err) {
      showToast(err.message || 'Lỗi khi lưu thứ tự', 'error')
    } finally {
      setSavingOrder(false)
    }
  }

  // Save updated section content
  const handleSaveSection = async (id, updatedPayload) => {
    try {
      await axiosClient.put(EP.ADMIN_HOMEPAGE_SECTION(id), updatedPayload)
      showToast('Cập nhật nội dung section thành công!', 'success')
      setHasPendingChanges(true)
      fetchSections()
    } catch (err) {
      showToast(err.message || 'Lỗi khi cập nhật section', 'error')
    }
  }

  // Publish all draft changes to live storefront
  const handlePublish = async () => {
    setPublishing(true)
    try {
      // If there are reorder changes, sync order first
      const orders = sections.map((s, idx) => ({
        id: s.id,
        sort_order: idx + 1,
        is_active: Boolean(s.is_active),
      }))
      await axiosClient.post(EP.ADMIN_HOMEPAGE_REORDER, { orders })

      await axiosClient.post(EP.ADMIN_HOMEPAGE_PUBLISH)
      showToast('Xuất bản thành công! Client Storefront đã nhận nội dung mới.', 'success')
      setHasPendingChanges(false)
      fetchSections()
    } catch (err) {
      showToast(err.message || 'Lỗi khi xuất bản trang chủ', 'error')
    } finally {
      setPublishing(false)
    }
  }

  // Discard draft changes and revert to last published
  const handleRevert = async () => {
    try {
      await axiosClient.post(EP.ADMIN_HOMEPAGE_REVERT)
      showToast('Đã khôi phục về bản xuất bản gần nhất.', 'info')
      setRevertConfirmOpen(false)
      setHasPendingChanges(false)
      fetchSections()
    } catch (err) {
      showToast(err.message || 'Lỗi khi khôi phục cấu hình', 'error')
    }
  }

  // Reset to default
  const handleResetDefaults = async () => {
    try {
      await axiosClient.post(EP.ADMIN_HOMEPAGE_RESET_DEFAULTS)
      showToast('Đã khôi phục toàn bộ section về mặc định MobieX.', 'success')
      setResetConfirmOpen(false)
      setHasPendingChanges(false)
      fetchSections()
    } catch (err) {
      showToast(err.message || 'Lỗi khi đặt lại mặc định', 'error')
    }
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Top Breadcrumb & Title */}
      <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-on-surface-variant">
        <Link to="/admin" className="hover:text-primary transition-colors flex items-center gap-1">
          <span className="material-symbols-outlined text-sm">home</span>
          Tổng quan
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold">Quản lý Trang chủ</span>
      </nav>

      {/* Header Bar */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý nội dung Trang chủ (Homepage CMS)</h1>
          <p className="text-sm text-on-surface-variant">
            Điều chỉnh cấu trúc section, thứ tự hiển thị, bật/tắt, chọn sản phẩm và xuất bản trực tiếp ra Storefront.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button variant="ghost" size="sm" onClick={() => setAuditLogsOpen(true)}>
            <span className="material-symbols-outlined text-base">history</span>
            Lịch sử
          </Button>

          <Button variant="ghost" size="sm" onClick={() => setResetConfirmOpen(true)}>
            <span className="material-symbols-outlined text-base">restart_alt</span>
            Đặt lại mặc định
          </Button>

          <Button variant="outline" size="sm" onClick={() => setPreviewOpen(true)}>
            <span className="material-symbols-outlined text-base">visibility</span>
            Xem trước
          </Button>

          <Button
            variant="primary"
            size="sm"
            onClick={handlePublish}
            loading={publishing}
          >
            <span className="material-symbols-outlined text-base">rocket_launch</span>
            Xuất bản ra Storefront
          </Button>
        </div>
      </div>

      {/* Draft Notification Banner */}
      {hasPendingChanges && (
        <div className="p-4 rounded-2xl bg-warning-container/20 border border-warning/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div className="flex items-center gap-2 text-warning">
            <span className="material-symbols-outlined text-lg shrink-0">pending_actions</span>
            <span className="font-semibold text-on-surface">
              Bạn có các thay đổi chưa được xuất bản. Storefront vẫn đang hiển thị bản snapshot đã xuất bản gần nhất.
            </span>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={() => setRevertConfirmOpen(true)}
              className="text-error hover:underline font-semibold cursor-pointer px-2"
            >
              Hủy bỏ thay đổi
            </button>
            <Button variant="primary" size="xs" onClick={handlePublish} loading={publishing}>
              Xuất bản ngay
            </Button>
          </div>
        </div>
      )}

      {/* Sections List */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-4 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-outline-variant/15">
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-xl">reorder</span>
            <h2 className="text-base font-bold text-on-surface">Danh sách các Section Trang chủ</h2>
            <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-surface-container-high text-on-surface-variant">
              {sections.length} sections
            </span>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="xs"
              onClick={handleSaveReorder}
              loading={savingOrder}
            >
              <span className="material-symbols-outlined text-sm">save</span>
              Lưu thứ tự
            </Button>
            <Link to="/admin/banners">
              <Button variant="ghost" size="xs">
                <span className="material-symbols-outlined text-sm">photo_library</span>
                Quản lý Banners
              </Button>
            </Link>
          </div>
        </div>

        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3">
            <Spinner size="lg" />
            <span className="text-sm text-on-surface-variant font-medium">Đang tải danh sách section...</span>
          </div>
        ) : sections.length === 0 ? (
          <div className="text-center py-16 space-y-3">
            <span className="material-symbols-outlined text-5xl text-outline">view_quilt</span>
            <p className="text-base font-semibold text-on-surface">Chưa có section nào</p>
            <Button variant="primary" onClick={handleResetDefaults}>
              Khôi phục cấu hình mặc định MobieX
            </Button>
          </div>
        ) : (
          <div className="space-y-3">
            {sections.map((section, idx) => {
              const meta = SECTION_TYPE_META[section.section_type] || {
                label: section.section_type,
                icon: 'widgets',
                color: 'text-primary',
              }

              return (
                <div
                  key={section.id}
                  className={[
                    'flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl border transition-all gap-4',
                    section.is_active
                      ? 'bg-surface-container-high/60 border-outline-variant/30 hover:border-primary/40'
                      : 'bg-surface-container-low/60 border-outline-variant/10 opacity-60',
                  ].join(' ')}
                >
                  {/* Left: Reorder Controls & Section Meta */}
                  <div className="flex items-center gap-3 min-w-0">
                    {/* Up / Down Controls */}
                    <div className="flex flex-col items-center gap-0.5 shrink-0">
                      <button
                        type="button"
                        disabled={idx === 0}
                        onClick={() => moveSection(idx, -1)}
                        className="p-1 rounded hover:bg-surface-container-highest disabled:opacity-30 disabled:hover:bg-transparent cursor-pointer transition-all"
                        title="Di chuyển lên trên"
                      >
                        <span className="material-symbols-outlined text-sm text-on-surface">arrow_upward</span>
                      </button>
                      <span className="text-[10px] font-mono font-bold text-outline">#{idx + 1}</span>
                      <button
                        type="button"
                        disabled={idx === sections.length - 1}
                        onClick={() => moveSection(idx, 1)}
                        className="p-1 rounded hover:bg-surface-container-highest disabled:opacity-30 disabled:hover:bg-transparent cursor-pointer transition-all"
                        title="Di chuyển xuống dưới"
                      >
                        <span className="material-symbols-outlined text-sm text-on-surface">arrow_downward</span>
                      </button>
                    </div>

                    {/* Section Type Icon */}
                    <div className="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center shrink-0 border border-outline-variant/20">
                      <span className={`material-symbols-outlined text-xl ${meta.color}`}>{meta.icon}</span>
                    </div>

                    {/* Titles */}
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="text-sm font-bold text-on-surface truncate">
                          {section.title || section.section_key}
                        </h3>
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-surface-container-lowest text-on-surface-variant border border-outline-variant/20">
                          {meta.label}
                        </span>
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-surface-container-lowest text-primary border border-primary/20 uppercase">
                          {section.layout_type}
                        </span>
                      </div>
                      {section.subtitle && (
                        <p className="text-xs text-on-surface-variant line-clamp-1 mt-0.5">
                          {section.subtitle}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Right: Status Switch & Edit Button */}
                  <div className="flex items-center gap-3 shrink-0 self-end sm:self-auto">
                    {/* Active Toggle Switch */}
                    <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-on-surface select-none">
                      <input
                        type="checkbox"
                        checked={section.is_active}
                        onChange={() => toggleSectionActive(section.id)}
                        className="rounded text-primary focus:ring-primary"
                      />
                      <span className={section.is_active ? 'text-success' : 'text-outline'}>
                        {section.is_active ? 'Đang bật' : 'Đang tắt'}
                      </span>
                    </label>

                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setEditingSection(section)}
                    >
                      <span className="material-symbols-outlined text-sm">settings</span>
                      Cấu hình
                    </Button>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* Edit Section Modal */}
      {editingSection && (
        <SectionEditModal
          open={!!editingSection}
          onClose={() => setEditingSection(null)}
          section={editingSection}
          allCategories={allCategories}
          allBrands={allBrands}
          onSave={handleSaveSection}
        />
      )}

      {/* Preview Modal */}
      <HomepagePreviewModal
        open={previewOpen}
        onClose={() => setPreviewOpen(false)}
      />

      {/* Audit Logs Modal */}
      <HomepageAuditLogsModal
        open={auditLogsOpen}
        onClose={() => setAuditLogsOpen(false)}
      />

      {/* Reset Defaults Confirmation */}
      <ConfirmDialog
        open={resetConfirmOpen}
        title="Khôi phục cấu hình mặc định"
        message="Hành động này sẽ thiết lập lại toàn bộ cấu trúc các section trang chủ về cấu hình chuẩn ban đầu của MobieX. Bạn có chắc chắn muốn tiếp tục?"
        confirmLabel="Đặt lại mặc định"
        onConfirm={handleResetDefaults}
        onCancel={() => setResetConfirmOpen(false)}
      />

      {/* Revert Changes Confirmation */}
      <ConfirmDialog
        open={revertConfirmOpen}
        title="Hủy bỏ các thay đổi nháp"
        message="Hành động này sẽ khôi phục lại cấu hình trang chủ về trạng thái đã xuất bản gần nhất, hủy bỏ tất cả các thay đổi nháp hiện tại."
        confirmLabel="Khôi phục bản xuất bản"
        onConfirm={handleRevert}
        onCancel={() => setRevertConfirmOpen(false)}
      />
    </div>
  )
}
