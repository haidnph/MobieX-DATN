// src/features/admin/AdminTrashPage.jsx
// Admin Recycle Bin (Thùng rác) management: view trashed items, restore single/all, force delete permanently.

import { useCallback, useEffect, useMemo, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

const TRASH_TYPES = [
  { key: 'products',   label: 'Sản phẩm',     icon: 'inventory_2' },
  { key: 'categories', label: 'Danh mục',     icon: 'category' },
  { key: 'brands',     label: 'Thương hiệu',  icon: 'store' },
  { key: 'banners',    label: 'Banner',       icon: 'image' },
  { key: 'coupons',    label: 'Mã giảm giá',  icon: 'local_offer' },
]

export default function AdminTrashPage() {
  const { showToast } = useToast()

  const [activeType, setActiveType] = useState('products')
  const [items, setItems] = useState([])
  const [summary, setSummary] = useState({})
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')

  // Dialog actions
  const [actionLoading, setActionLoading] = useState(false)
  const [restoreTarget, setRestoreTarget] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [confirmRestoreAll, setConfirmRestoreAll] = useState(false)
  const [confirmEmptyTrash, setConfirmEmptyTrash] = useState(false)

  // ── Fetch Summary ──────────────────────────────────────────────────────────
  const fetchSummary = useCallback(async () => {
    try {
      const { data } = await axiosClient.get(EP.ADMIN_TRASH_SUMMARY)
      setSummary(data.data ?? {})
    } catch {
      // Ignore background summary errors
    }
  }, [])

  // ── Fetch Trashed Items ────────────────────────────────────────────────────
  const fetchItems = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.ADMIN_TRASH_LIST(activeType))
      setItems(data.data ?? [])
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể tải danh sách thùng rác.'
      showToast(msg, 'error')
    } finally {
      setLoading(false)
    }
  }, [activeType, showToast])

  useEffect(() => {
    fetchItems()
    fetchSummary()
  }, [fetchItems, fetchSummary])

  // ── Filtered items ─────────────────────────────────────────────────────────
  const filteredItems = useMemo(() => {
    if (!search.trim()) return items
    const q = search.toLowerCase()
    return items.filter((item) => {
      const name = item.name || item.title || item.code || ''
      const slug = item.slug || ''
      return name.toLowerCase().includes(q) || slug.toLowerCase().includes(q)
    })
  }, [items, search])

  // ── Action: Restore single ─────────────────────────────────────────────────
  const handleRestore = async (item) => {
    setActionLoading(true)
    try {
      const { data } = await axiosClient.post(EP.ADMIN_TRASH_RESTORE(activeType, item.id))
      showToast(data.message || 'Khôi phục thành công.', 'success')
      setRestoreTarget(null)
      fetchItems()
      fetchSummary()
    } catch (err) {
      showToast(err.response?.data?.message || 'Khôi phục thất bại.', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  // ── Action: Restore All ────────────────────────────────────────────────────
  const handleRestoreAll = async () => {
    setActionLoading(true)
    try {
      const { data } = await axiosClient.post(EP.ADMIN_TRASH_RESTORE_ALL(activeType))
      showToast(data.message || 'Đã khôi phục tất cả.', 'success')
      setConfirmRestoreAll(false)
      fetchItems()
      fetchSummary()
    } catch (err) {
      showToast(err.response?.data?.message || 'Khôi phục tất cả thất bại.', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  // ── Action: Force Delete single ────────────────────────────────────────────
  const handleForceDelete = async (item) => {
    setActionLoading(true)
    try {
      const { data } = await axiosClient.delete(EP.ADMIN_TRASH_FORCE_DELETE(activeType, item.id))
      showToast(data.message || 'Đã xóa vĩnh viễn.', 'success')
      setDeleteTarget(null)
      fetchItems()
      fetchSummary()
    } catch (err) {
      showToast(err.response?.data?.message || 'Không thể xóa vĩnh viễn.', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  // ── Action: Empty Trash ────────────────────────────────────────────────────
  const handleEmptyTrash = async () => {
    setActionLoading(true)
    try {
      const { data } = await axiosClient.delete(EP.ADMIN_TRASH_EMPTY(activeType))
      showToast(data.message || 'Đã dọn sạch thùng rác.', 'success')
      setConfirmEmptyTrash(false)
      fetchItems()
      fetchSummary()
    } catch (err) {
      showToast(err.response?.data?.message || 'Dọn sạch thất bại.', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  const currentTypeConfig = TRASH_TYPES.find((t) => t.key === activeType) || TRASH_TYPES[0]
  const currentCount = items.length

  return (
    <div className="p-6 space-y-6">
      {/* ── Page Header ── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-error-container/20 text-error flex items-center justify-center">
              <span className="material-symbols-outlined text-2xl">auto_delete</span>
            </div>
            <div>
              <h1 className="text-xl font-bold text-on-surface">Thùng rác hệ thống</h1>
              <p className="text-xs text-on-surface-variant">
                Quản lý và khôi phục các dữ liệu đã xóa, hoặc xóa vĩnh viễn để giải phóng bộ nhớ.
              </p>
            </div>
          </div>
        </div>

        {/* Global actions for current tab */}
        {currentCount > 0 && (
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setConfirmRestoreAll(true)}
              className="gap-1.5 text-xs text-primary border-primary/30 hover:bg-primary-container/20"
            >
              <span className="material-symbols-outlined text-base">restore</span>
              Khôi phục tất cả ({currentCount})
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => setConfirmEmptyTrash(true)}
              className="gap-1.5 text-xs"
            >
              <span className="material-symbols-outlined text-base">delete_forever</span>
              Dọn sạch thùng rác
            </Button>
          </div>
        )}
      </div>

      {/* ── Tabs Navigation ── */}
      <div className="flex items-center gap-2 overflow-x-auto pb-2 border-b border-outline-variant/20">
        {TRASH_TYPES.map((type) => {
          const isActive = activeType === type.key
          const count = summary[type.key] ?? 0
          return (
            <button
              key={type.key}
              onClick={() => {
                setActiveType(type.key)
                setSearch('')
              }}
              className={[
                'flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-all shrink-0',
                isActive
                  ? 'bg-primary text-on-primary shadow-sm'
                  : 'bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface',
              ].join(' ')}
            >
              <span className="material-symbols-outlined text-lg">{type.icon}</span>
              <span>{type.label}</span>
              {count > 0 && (
                <span
                  className={[
                    'px-2 py-0.5 rounded-full text-xs font-semibold',
                    isActive
                      ? 'bg-on-primary/20 text-on-primary'
                      : 'bg-error-container text-on-error-container',
                  ].join(' ')}
                >
                  {count}
                </span>
              )}
            </button>
          )
        })}
      </div>

      {/* ── Toolbar & Search ── */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-4">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="w-full sm:w-80">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={`Tìm trong thùng rác ${currentTypeConfig.label.toLowerCase()}...`}
              leftIcon="search"
              size="sm"
            />
          </div>
          <div className="text-xs text-on-surface-variant flex items-center gap-1.5 self-end sm:self-center">
            <span className="material-symbols-outlined text-sm">info</span>
            Mục trong thùng rác sẽ không hiển thị trên giao diện người dùng.
          </div>
        </div>
      </div>

      {/* ── Table Content ── */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-16 gap-3">
            <Spinner size="lg" />
            <p className="text-xs text-on-surface-variant">Đang tải dữ liệu thùng rác...</p>
          </div>
        ) : filteredItems.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 px-4 text-center">
            <div className="w-16 h-16 rounded-2xl bg-surface-container-high flex items-center justify-center text-on-surface-variant/40 mb-3">
              <span className="material-symbols-outlined text-3xl">delete_outline</span>
            </div>
            <h3 className="text-base font-semibold text-on-surface">Thùng rác trống</h3>
            <p className="text-xs text-on-surface-variant mt-1 max-w-sm">
              {search
                ? 'Không tìm thấy kết quả phù hợp với từ khóa tìm kiếm.'
                : `Không có ${currentTypeConfig.label.toLowerCase()} nào trong thùng rác.`}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high/60 text-xs text-on-surface-variant uppercase tracking-wider border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Mục</th>
                  <th className="px-5 py-3.5">Thông tin chi tiết</th>
                  <th className="px-5 py-3.5">Thời gian xóa</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-on-surface">
                {filteredItems.map((item) => {
                  const title = item.name || item.title || item.code || `#${item.id}`
                  const deletedDate = item.deleted_at
                    ? new Date(item.deleted_at).toLocaleString('vi-VN')
                    : '—'

                  return (
                    <tr key={item.id} className="hover:bg-surface-container-high/40 transition-colors">
                      {/* Name / Title */}
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-lg bg-surface-container-high flex items-center justify-center shrink-0 text-on-surface-variant">
                            <span className="material-symbols-outlined text-xl">
                              {currentTypeConfig.icon}
                            </span>
                          </div>
                          <div className="min-w-0">
                            <div className="font-semibold text-on-surface truncate max-w-xs sm:max-w-md">
                              {title}
                            </div>
                            {item.slug && (
                              <div className="text-xs text-on-surface-variant font-mono truncate max-w-xs">
                                {item.slug}
                              </div>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Details depending on type */}
                      <td className="px-5 py-4 text-xs text-on-surface-variant">
                        {activeType === 'products' && (
                          <div className="space-y-0.5">
                            <div>Loại: <span className="font-medium text-on-surface">{item.product_type === 'phone' ? 'Điện thoại' : 'Phụ kiện'}</span></div>
                            <div>Giá: <span className="font-semibold text-primary">{formatCurrencyVND(item.base_price || 0)}</span></div>
                          </div>
                        )}
                        {activeType === 'categories' && (
                          <div>
                            {item.parent_id ? `Danh mục con của #${item.parent_id}` : 'Danh mục gốc'}
                          </div>
                        )}
                        {activeType === 'brands' && (
                          <div>
                            Trạng thái: <Badge variant={item.status ? 'success' : 'neutral'}>{item.status ? 'Hiện' : 'Ẩn'}</Badge>
                          </div>
                        )}
                        {activeType === 'banners' && (
                          <div className="space-y-0.5">
                            <div>Vị trí: <span className="font-mono text-on-surface">{item.position}</span></div>
                            <div>Thứ tự: {item.sort_order ?? 0}</div>
                          </div>
                        )}
                        {activeType === 'coupons' && (
                          <div className="space-y-0.5">
                            <div>Loại: {item.discount_type === 'percent' ? 'Giảm %' : 'Giảm tiền mặt'}</div>
                            <div>Giá trị: <span className="font-semibold text-primary">{item.discount_type === 'percent' ? `${item.discount_value}%` : formatCurrencyVND(item.discount_value)}</span></div>
                          </div>
                        )}
                      </td>

                      {/* Deleted Date */}
                      <td className="px-5 py-4 text-xs text-on-surface-variant whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <span className="material-symbols-outlined text-sm text-error/70">delete</span>
                          <span>{deletedDate}</span>
                        </div>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() => setRestoreTarget(item)}
                            title="Khôi phục lại mục này"
                            className="p-1.5 rounded-lg text-primary hover:bg-primary-container/30 transition-colors flex items-center gap-1 text-xs font-medium px-2.5"
                          >
                            <span className="material-symbols-outlined text-base">restore</span>
                            <span>Khôi phục</span>
                          </button>
                          <button
                            onClick={() => setDeleteTarget(item)}
                            title="Xóa vĩnh viễn không thể khôi phục"
                            className="p-1.5 rounded-lg text-error hover:bg-error-container/30 transition-colors flex items-center gap-1 text-xs font-medium px-2.5"
                          >
                            <span className="material-symbols-outlined text-base">delete_forever</span>
                            <span>Xóa vĩnh viễn</span>
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

      {/* ── Dialog: Confirm Restore Single ── */}
      <ConfirmDialog
        open={Boolean(restoreTarget)}
        title="Khôi phục mục này?"
        message={`Bạn có chắc chắn muốn khôi phục "${restoreTarget?.name || restoreTarget?.title || restoreTarget?.code || 'mục này'}"? Mục sẽ xuất hiện trở lại trong danh sách hoạt động.`}
        confirmText="Khôi phục"
        cancelText="Hủy"
        variant="primary"
        loading={actionLoading}
        onConfirm={() => handleRestore(restoreTarget)}
        onCancel={() => setRestoreTarget(null)}
      />

      {/* ── Dialog: Confirm Force Delete Single ── */}
      <ConfirmDialog
        open={Boolean(deleteTarget)}
        title="Xóa vĩnh viễn?"
        message={`CẢNH BÁO: Bạn đang chuẩn bị xóa vĩnh viễn "${deleteTarget?.name || deleteTarget?.title || deleteTarget?.code || 'mục này'}". Dữ liệu này sẽ KHÔNG THỂ KHÔI PHỤC được nữa.`}
        confirmText="Xóa vĩnh viễn"
        cancelText="Hủy"
        variant="danger"
        loading={actionLoading}
        onConfirm={() => handleForceDelete(deleteTarget)}
        onCancel={() => setDeleteTarget(null)}
      />

      {/* ── Dialog: Confirm Restore All ── */}
      <ConfirmDialog
        open={confirmRestoreAll}
        title={`Khôi phục toàn bộ ${currentTypeConfig.label.toLowerCase()}?`}
        message={`Hệ thống sẽ đưa tất cả ${currentCount} ${currentTypeConfig.label.toLowerCase()} trong thùng rác trở lại trạng thái hoạt động.`}
        confirmText="Khôi phục tất cả"
        cancelText="Hủy"
        variant="primary"
        loading={actionLoading}
        onConfirm={handleRestoreAll}
        onCancel={() => setConfirmRestoreAll(false)}
      />

      {/* ── Dialog: Confirm Empty Trash ── */}
      <ConfirmDialog
        open={confirmEmptyTrash}
        title={`Dọn sạch thùng rác ${currentTypeConfig.label.toLowerCase()}?`}
        message={`NGUY HIỂM: Tất cả ${currentCount} ${currentTypeConfig.label.toLowerCase()} trong thùng rác sẽ bị xóa hoàn toàn khỏi cơ sở dữ liệu. Hành động này không thể hoàn tác!`}
        confirmText="Dọn sạch thùng rác"
        cancelText="Hủy"
        variant="danger"
        loading={actionLoading}
        onConfirm={handleEmptyTrash}
        onCancel={() => setConfirmEmptyTrash(false)}
      />
    </div>
  )
}
