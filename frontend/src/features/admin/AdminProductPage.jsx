// src/features/admin/AdminProductPage.jsx
// Admin product catalog management: listing, filtering, pagination, and bulk actions.
// Creation and modification are routed to dedicated pages:
// - /admin/products/create
// - /admin/products/:id/edit

import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Pagination from '../../components/common/Pagination'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import BulkActionBar from '../../components/common/BulkActionBar'

const STATUS_BADGES = {
  active: { label: 'Đang bán', variant: 'success' },
  draft: { label: 'Bản nháp', variant: 'neutral' },
  inactive: { label: 'Tạm ẩn', variant: 'warning' },
  out_of_stock: { label: 'Hết hàng', variant: 'error' },
}

export default function AdminProductPage() {
  const navigate = useNavigate()
  const { showToast } = useToast()
  const [searchParams] = useSearchParams()
  const qParam = searchParams.get('q') || ''
  const actionParam = searchParams.get('action') || ''

  const [products, setProducts] = useState([])
  const [brands, setBrands] = useState([])
  const [categories, setCategories] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)

  // Filter params
  const [search, setSearch] = useState(qParam)
  const [statusFilter, setStatusFilter] = useState('')
  const [brandFilter, setBrandFilter] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')
  const [showFilters, setShowFilters] = useState(false)
  const [page, setPage] = useState(1)

  // Bulk actions selection
  const [selectedIds, setSelectedIds] = useState([])
  const [bulkLoading, setBulkLoading] = useState(false)
  const [bulkDeleteConfirmOpen, setBulkDeleteConfirmOpen] = useState(false)

  // Single delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // If query action=create, redirect to dedicated create page
  useEffect(() => {
    if (actionParam === 'create') {
      navigate('/admin/products/create', { replace: true })
    }
  }, [actionParam, navigate])

  // Fetch brands & categories for filters
  useEffect(() => {
    Promise.allSettled([
      axiosClient.get(EP.BRANDS),
      axiosClient.get(EP.CATEGORIES),
    ]).then(([bRes, cRes]) => {
      if (bRes.status === 'fulfilled') {
        const bData = bRes.value.data.data ?? bRes.value.data
        setBrands(Array.isArray(bData) ? bData : bData?.data ?? [])
      }
      if (cRes.status === 'fulfilled') {
        const cData = cRes.value.data.data ?? cRes.value.data
        setCategories(Array.isArray(cData) ? cData : cData?.data ?? [])
      }
    })
  }, [])

  const fetchProducts = () => {
    setLoading(true)
    const params = { page, per_page: 10 }
    if (search.trim()) params.search = search.trim()
    if (statusFilter) params.status = statusFilter
    if (brandFilter) params.brand_id = brandFilter
    if (categoryFilter) params.category_id = categoryFilter

    axiosClient
      .get(EP.PRODUCTS, { params })
      .then(({ data }) => {
        const payload = data.data ?? data
        const items = Array.isArray(payload) ? payload : payload.data ?? []
        setProducts(items)
        if (payload.current_page) {
          setMeta({
            current_page: payload.current_page,
            last_page: payload.last_page,
            total: payload.total,
          })
        }
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách sản phẩm', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchProducts()
  }, [page, statusFilter, brandFilter, categoryFilter])

  const handleSearchSubmit = (e) => {
    e.preventDefault()
    setPage(1)
    fetchProducts()
  }

  const activeProductFilters = useMemo(() => {
    const list = []
    if (statusFilter) {
      list.push({ key: 'status', label: `Trạng thái: ${STATUS_BADGES[statusFilter]?.label || statusFilter}` })
    }
    if (search.trim()) {
      list.push({ key: 'search', label: `Tìm: "${search.trim()}"` })
    }
    if (categoryFilter) {
      const cat = categories.find((c) => String(c.id) === String(categoryFilter))
      list.push({ key: 'category', label: `Danh mục: ${cat?.name || categoryFilter}` })
    }
    if (brandFilter) {
      const br = brands.find((b) => String(b.id) === String(brandFilter))
      list.push({ key: 'brand', label: `Thương hiệu: ${br?.name || brandFilter}` })
    }
    return list
  }, [statusFilter, search, categoryFilter, brandFilter, categories, brands])

  const handleResetProductFilters = () => {
    setSearch('')
    setStatusFilter('')
    setCategoryFilter('')
    setBrandFilter('')
    setPage(1)
  }

  const removeProductFilter = (key) => {
    if (key === 'status') setStatusFilter('')
    if (key === 'search') setSearch('')
    if (key === 'category') setCategoryFilter('')
    if (key === 'brand') setBrandFilter('')
    setPage(1)
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await axiosClient.delete(EP.ADMIN_PRODUCT_DETAIL(deleteTarget.id))
      const msg = res.data?.message || 'Đã chuyển sản phẩm vào thùng rác!'
      const toastType = res.data?.is_deactivated ? 'warning' : 'info'
      showToast(msg, toastType)
      setDeleteTarget(null)
      fetchProducts()
    } catch (err) {
      showToast(err.message || 'Không thể xóa sản phẩm', 'error')
    } finally {
      setDeleting(false)
    }
  }

  // Bulk Actions Handlers
  const handleBulkStatus = async (status) => {
    if (selectedIds.length === 0) return
    setBulkLoading(true)
    try {
      await axiosClient.post(EP.ADMIN_PRODUCTS_BULK_STATUS, {
        ids: selectedIds,
        status,
      })
      showToast(`Đã cập nhật trạng thái cho ${selectedIds.length} sản phẩm thành công!`)
      setSelectedIds([])
      fetchProducts()
    } catch (err) {
      showToast(err.response?.data?.message || 'Lỗi khi cập nhật hàng loạt', 'error')
    } finally {
      setBulkLoading(false)
    }
  }

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0) return
    setBulkLoading(true)
    try {
      const { data } = await axiosClient.post(EP.ADMIN_PRODUCTS_BULK_DELETE, {
        ids: selectedIds,
      })
      showToast(data.message || `Đã xử lý xóa ${selectedIds.length} sản phẩm!`)
      setSelectedIds([])
      setBulkDeleteConfirmOpen(false)
      fetchProducts()
    } catch (err) {
      showToast(err.response?.data?.message || 'Lỗi khi xóa hàng loạt', 'error')
    } finally {
      setBulkLoading(false)
    }
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý sản phẩm</h1>
          <p className="text-sm text-on-surface-variant">
            Danh mục sản phẩm, biến thể và phân loại thiết bị công nghệ.
          </p>
        </div>
        <Link to="/admin/products/create">
          <Button variant="primary" className="flex items-center gap-1.5 shadow-sm cursor-pointer">
            <span className="material-symbols-outlined text-base">add</span>
            Thêm sản phẩm
          </Button>
        </Link>
      </div>

      {/* Smart Status Tabs */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none border-b border-outline-variant/20">
        {[
          { key: '', label: 'Tất cả' },
          { key: 'active', label: 'Đang bán' },
          { key: 'draft', label: 'Bản nháp' },
          { key: 'inactive', label: 'Tạm ẩn' },
          { key: 'out_of_stock', label: 'Hết hàng' },
        ].map((tab) => {
          const isSelected = statusFilter === tab.key
          return (
            <button
              key={tab.key}
              onClick={() => {
                setStatusFilter(tab.key)
                setPage(1)
              }}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer ${
                isSelected
                  ? 'bg-primary text-on-primary shadow-xs'
                  : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'
              }`}
            >
              {tab.label}
            </button>
          )
        })}
      </div>

      {/* Search & Filter Bar */}
      <div className="bg-surface-container-high rounded-2xl p-4 border border-outline-variant/30 space-y-3">
        <div className="flex flex-col md:flex-row gap-3">
          <form onSubmit={handleSearchSubmit} className="flex-1 flex gap-2">
            <div className="flex-1">
              <Input
                placeholder="Tìm theo tên sản phẩm, slug..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full text-xs"
              />
            </div>
            <Button type="submit" variant="secondary" size="md">
              <span className="material-symbols-outlined text-base">search</span>
              <span className="hidden sm:inline">Tìm</span>
            </Button>
          </form>

          <div className="flex items-center gap-2">
            <Button
              variant={showFilters ? 'primary' : 'outline'}
              size="md"
              onClick={() => setShowFilters(!showFilters)}
              className="flex items-center gap-1.5 text-xs cursor-pointer"
            >
              <span className="material-symbols-outlined text-base">filter_list</span>
              Bộ lọc nâng cao
              {(categoryFilter || brandFilter) && (
                <span className="w-2 h-2 rounded-full bg-primary-container" />
              )}
            </Button>
            {activeProductFilters.length > 0 && (
              <Button
                variant="ghost"
                size="md"
                onClick={handleResetProductFilters}
                className="text-xs text-outline hover:text-error cursor-pointer"
              >
                Đặt lại
              </Button>
            )}
          </div>
        </div>

        {/* Collapsible Advanced Filters */}
        {showFilters && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-outline-variant/20 animate-fade-in">
            <div className="space-y-1">
              <label className="text-xs font-medium text-on-surface-variant">Danh mục</label>
              <select
                value={categoryFilter}
                onChange={(e) => {
                  setCategoryFilter(e.target.value)
                  setPage(1)
                }}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
              >
                <option value="">Tất cả danh mục</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-medium text-on-surface-variant">Thương hiệu</label>
              <select
                value={brandFilter}
                onChange={(e) => {
                  setBrandFilter(e.target.value)
                  setPage(1)
                }}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
              >
                <option value="">Tất cả thương hiệu</option>
                {brands.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        )}

        {/* Active Filter Chips */}
        {activeProductFilters.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5 pt-1">
            <span className="text-[11px] text-outline">Đang lọc:</span>
            {activeProductFilters.map((f) => (
              <span
                key={f.key}
                className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs bg-surface-container text-on-surface-variant border border-outline-variant/30"
              >
                {f.label}
                <button
                  type="button"
                  onClick={() => removeProductFilter(f.key)}
                  className="hover:text-error cursor-pointer"
                >
                  <span className="material-symbols-outlined text-[13px] leading-none">close</span>
                </button>
              </span>
            ))}
          </div>
        )}
      </div>

      {/* Product List Table */}
      <div className="bg-surface-container-high rounded-2xl border border-outline-variant/30 overflow-hidden shadow-xs">
        {loading ? (
          <div className="py-20 flex flex-col items-center justify-center gap-3">
            <Spinner size="lg" />
            <p className="text-sm text-on-surface-variant">Đang tải danh sách sản phẩm...</p>
          </div>
        ) : products.length === 0 ? (
          <div className="py-16 text-center text-on-surface-variant space-y-3">
            <span className="material-symbols-outlined text-4xl text-outline">inventory_2</span>
            <p className="text-base font-semibold text-on-surface">Không tìm thấy sản phẩm nào</p>
            <p className="text-xs text-outline max-w-sm mx-auto">
              Không có sản phẩm nào phù hợp với bộ lọc hiện tại. Thử thay đổi từ khóa hoặc bộ lọc của bạn.
            </p>
            <div className="pt-2">
              <Link to="/admin/products/create">
                <Button variant="primary" size="sm">
                  + Thêm sản phẩm mới
                </Button>
              </Link>
            </div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-surface-container-low text-on-surface-variant uppercase text-xs tracking-wider border-b border-outline-variant/20 select-none">
                <tr>
                  <th className="px-4 py-3.5 w-10">
                    <input
                      type="checkbox"
                      checked={
                        products.length > 0 &&
                        products.every((p) => selectedIds.includes(p.id))
                      }
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedIds(products.map((p) => p.id))
                        } else {
                          setSelectedIds([])
                        }
                      }}
                      className="rounded border-outline-variant/50 accent-primary cursor-pointer"
                    />
                  </th>
                  <th className="px-5 py-3.5">Sản phẩm</th>
                  <th className="px-5 py-3.5">Phân loại</th>
                  <th className="px-5 py-3.5">Giá bán</th>
                  <th className="px-5 py-3.5 text-center">Biến thể</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {products.map((p) => {
                  const variants = p.variants ?? []
                  const firstVar = variants[0]
                  const price = firstVar?.price ?? p.min_price ?? 0
                  const isChecked = selectedIds.includes(p.id)
                  const primaryImg = p.images?.find((img) => img.is_primary) || p.images?.[0]
                  const displayImg = primaryImg?.image_url || primaryImg?.image_path || p.thumbnail || p.image_url
                  const badge = STATUS_BADGES[p.status] ?? {
                    label: p.status,
                    variant: 'neutral',
                  }

                  return (
                    <tr
                      key={p.id}
                      className={`hover:bg-surface-container-low/50 transition-colors ${
                        isChecked ? 'bg-primary-container/10' : ''
                      }`}
                    >
                      <td className="px-4 py-3">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setSelectedIds((prev) => [...prev, p.id])
                            } else {
                              setSelectedIds((prev) => prev.filter((id) => id !== p.id))
                            }
                          }}
                          className="rounded border-outline-variant/50 accent-primary cursor-pointer"
                        />
                      </td>
                      <td className="px-5 py-3 flex items-center gap-3">
                        <div className="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center overflow-hidden shrink-0 border border-outline-variant/20">
                          {displayImg ? (
                            <img
                              src={displayImg}
                              alt={p.name}
                              className="w-full h-full object-cover"
                              onError={(e) => {
                                e.target.onerror = null
                                e.target.style.display = 'none'
                              }}
                            />
                          ) : (
                            <span className="material-symbols-outlined text-outline text-xl">
                              image
                            </span>
                          )}
                        </div>
                        <div className="min-w-0">
                          <Link
                            to={`/products/${p.slug || p.id}`}
                            target="_blank"
                            className="font-semibold text-on-surface hover:text-primary transition-colors line-clamp-1 block"
                          >
                            {p.name}
                          </Link>
                          <p className="text-xs text-outline font-mono truncate">{p.slug}</p>
                        </div>
                      </td>
                      <td className="px-5 py-3">
                        <p className="text-xs font-semibold text-primary">{p.brand?.name || '—'}</p>
                        <p className="text-xs text-on-surface-variant">{p.category?.name || '—'}</p>
                      </td>
                      <td className="px-5 py-3 font-extrabold text-on-surface">
                        {formatCurrencyVND(price)}
                      </td>
                      <td className="px-5 py-3 text-center">
                        <span className="px-2 py-0.5 rounded-full bg-surface-container-high text-xs font-mono font-bold text-on-surface">
                          {variants.length}
                        </span>
                      </td>
                      <td className="px-5 py-3 text-center">
                        <Badge variant={badge.variant}>{badge.label}</Badge>
                      </td>
                      <td className="px-5 py-3 text-right space-x-2 whitespace-nowrap">
                        <Link
                          to={`/admin/products/${p.id}/edit`}
                          className="inline-flex items-center px-2.5 py-1 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors cursor-pointer"
                        >
                          Sửa
                        </Link>
                        <button
                          onClick={() => setDeleteTarget(p)}
                          className="px-2.5 py-1 bg-error-container/20 hover:bg-error-container/40 rounded-lg text-xs font-semibold text-error transition-colors cursor-pointer"
                        >
                          Xóa
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {meta.last_page > 1 && (
        <div className="flex justify-center pt-2">
          <Pagination
            currentPage={meta.current_page}
            totalPages={meta.last_page}
            onPageChange={(p) => setPage(p)}
          />
        </div>
      )}

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển sản phẩm "${deleteTarget?.name}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmLabel="Chuyển vào Thùng rác"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />

      {/* Bulk Action Floating CTA Bar */}
      <BulkActionBar
        selectedCount={selectedIds.length}
        onClear={() => setSelectedIds([])}
        onActivate={() => handleBulkStatus('active')}
        onDeactivate={() => handleBulkStatus('inactive')}
        onDelete={() => setBulkDeleteConfirmOpen(true)}
        loading={bulkLoading}
      />

      {/* Bulk Delete Confirmation Dialog */}
      <ConfirmDialog
        open={bulkDeleteConfirmOpen}
        title={`Xác nhận xóa ${selectedIds.length} sản phẩm`}
        message={`Bạn có chắc chắn muốn xóa ${selectedIds.length} sản phẩm đã chọn? Các sản phẩm đã từng có đơn hàng sẽ được hệ thống tự động chuyển sang trạng thái "Ngừng kinh doanh" để bảo vệ toàn vẹn dữ liệu.`}
        confirmLabel="Xóa hàng loạt"
        onConfirm={handleBulkDelete}
        onCancel={() => setBulkDeleteConfirmOpen(false)}
        loading={bulkLoading}
      />
    </div>
  )
}
