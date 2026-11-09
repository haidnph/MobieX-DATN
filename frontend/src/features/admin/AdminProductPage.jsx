// src/features/admin/AdminProductPage.jsx
// Admin product catalog management: listing, creation, modification, and inventory overview.

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Pagination from '../../components/common/Pagination'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import ProductImageGallery from '../../components/admin/ProductImageGallery'
import VariantImageManager from '../../components/admin/VariantImageManager'
import BulkActionBar from '../../components/common/BulkActionBar'

const STATUS_BADGES = {
  active: { label: 'Đang bán', variant: 'success' },
  draft: { label: 'Bản nháp', variant: 'neutral' },
  inactive: { label: 'Tạm ẩn', variant: 'warning' },
  out_of_stock: { label: 'Hết hàng', variant: 'error' },
}

// Simple slugify helper
function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .replace(/[^a-z0-9 -]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
}

export default function AdminProductPage() {
  const { showToast } = useToast()

  const [products, setProducts] = useState([])
  const [brands, setBrands] = useState([])
  const [categories, setCategories] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [loading, setLoading] = useState(true)

  // Filter params
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)

  // Bulk actions selection
  const [selectedIds, setSelectedIds] = useState([])
  const [bulkLoading, setBulkLoading] = useState(false)
  const [bulkDeleteConfirmOpen, setBulkDeleteConfirmOpen] = useState(false)

  // Modal & Tabs
  const [modalOpen, setModalOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState(null)
  const [activeTab, setActiveTab] = useState('info') // 'info' | 'specs' | 'images'
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  // Specification state
  const [specDefinitions, setSpecDefinitions] = useState([])
  const [productSpecs, setProductSpecs] = useState({})
  const [loadingSpecs, setLoadingSpecs] = useState(false)
  const [savingSpecs, setSavingSpecs] = useState(false)

  const [form, setForm] = useState({
    name: '',
    slug: '',
    brand_id: '',
    category_id: '',
    status: 'active',
    description: '',
    price: '',
    compare_price: '',
    stock_quantity: '50',
    image_url: '',
  })

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  // Fetch brands, categories & spec definitions
  useEffect(() => {
    Promise.allSettled([
      axiosClient.get(EP.BRANDS),
      axiosClient.get(EP.CATEGORIES),
      axiosClient.get(EP.SPEC_DEFINITIONS),
    ]).then(([bRes, cRes, sRes]) => {
      if (bRes.status === 'fulfilled') {
        const bData = bRes.value.data.data ?? bRes.value.data
        setBrands(Array.isArray(bData) ? bData : (bData?.data ?? []))
      }
      if (cRes.status === 'fulfilled') {
        const cData = cRes.value.data.data ?? cRes.value.data
        setCategories(Array.isArray(cData) ? cData : (cData?.data ?? []))
      }
      if (sRes.status === 'fulfilled') {
        const sData = sRes.value.data.data ?? sRes.value.data
        setSpecDefinitions(Array.isArray(sData) ? sData : [])
      }
    })
  }, [])

  const fetchProducts = () => {
    setLoading(true)
    const params = { page, per_page: 10 }
    if (search.trim()) params.search = search.trim()
    if (statusFilter) params.status = statusFilter

    axiosClient
      .get(EP.PRODUCTS, { params })
      .then(({ data }) => {
        const payload = data.data ?? data
        const items = Array.isArray(payload) ? payload : (payload.data ?? [])
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
  }, [page, statusFilter])

  const handleSearchSubmit = (e) => {
    e.preventDefault()
    setPage(1)
    fetchProducts()
  }

  const handleOpenCreate = () => {
    setEditingProduct(null)
    setActiveTab('info')
    setProductSpecs({})
    setForm({
      name: '',
      slug: '',
      brand_id: brands[0]?.id ? String(brands[0].id) : '',
      category_id: categories[0]?.id ? String(categories[0].id) : '',
      status: 'active',
      description: '',
      price: '',
      compare_price: '',
      stock_quantity: '50',
      image_url: '',
    })
    setErrors({})
    setModalOpen(true)
  }

  const handleOpenEdit = (p) => {
    setEditingProduct(p)
    setActiveTab('info')
    setProductSpecs({})
    const firstVar = p.variants?.[0]
    const primaryImg = p.images?.find((img) => img.is_primary) || p.images?.[0]
    setForm({
      name: p.name || '',
      slug: p.slug || '',
      brand_id: p.brand_id ? String(p.brand_id) : '',
      category_id: p.category_id ? String(p.category_id) : '',
      status: p.status || 'active',
      description: p.description || '',
      price: firstVar?.price ? String(firstVar.price) : (p.min_price ? String(p.min_price) : ''),
      compare_price: firstVar?.compare_price ? String(firstVar.compare_price) : (p.max_price ? String(p.max_price) : ''),
      stock_quantity: firstVar?.inventory?.on_hand_quantity != null ? String(firstVar.inventory.on_hand_quantity) : '50',
      image_url: primaryImg?.image_path || p.thumbnail || '',
    })
    setErrors({})
    setModalOpen(true)

    // Load specifications for this product
    setLoadingSpecs(true)
    axiosClient
      .get(EP.PRODUCT_DETAIL(p.id))
      .then(({ data }) => {
        const prod = data.data ?? data
        const map = {}
        if (prod.specifications && Array.isArray(prod.specifications)) {
          prod.specifications.forEach((s) => {
            const defId = s.specification_definition_id || s.definition?.id
            if (defId) {
              map[defId] =
                s.value_text ??
                (s.numeric_value != null
                  ? String(s.numeric_value)
                  : s.value_boolean != null
                  ? s.value_boolean
                  : '')
            }
          })
        }
        setProductSpecs(map)
      })
      .catch(() => {})
      .finally(() => setLoadingSpecs(false))
  }

  const handleSaveSpecs = async () => {
    if (!editingProduct) return
    setSavingSpecs(true)
    try {
      const specsPayload = Object.entries(productSpecs)
        .filter(([_, val]) => val !== '' && val !== null && val !== undefined)
        .map(([defId, val]) => ({
          definition_id: Number(defId),
          value: val,
        }))
      await axiosClient.post(EP.ADMIN_PRODUCT_SPECIFICATIONS(editingProduct.id), {
        specs: specsPayload,
      })
      showToast('Đã lưu thông số kỹ thuật thành công!', 'success')
    } catch (err) {
      showToast(err.response?.data?.message || err.message || 'Lỗi khi lưu thông số', 'error')
    } finally {
      setSavingSpecs(false)
    }
  }

  const handleNameChange = (e) => {
    const val = e.target.value
    setForm((prev) => ({
      ...prev,
      name: val,
      slug: editingProduct ? prev.slug : slugify(val),
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      brand_id: form.brand_id ? Number(form.brand_id) : null,
      category_id: form.category_id ? Number(form.category_id) : null,
      status: form.status,
      description: form.description.trim() || null,
      price: form.price ? Number(form.price) : 0,
      compare_price: form.compare_price ? Number(form.compare_price) : null,
      stock_quantity: form.stock_quantity ? Number(form.stock_quantity) : 50,
      image_url: form.image_url.trim() || null,
    }

    try {
      if (editingProduct) {
        await axiosClient.put(EP.ADMIN_PRODUCT_DETAIL(editingProduct.id), payload)

        // Sync specs if any
        const specsPayload = Object.entries(productSpecs)
          .filter(([_, val]) => val !== '' && val !== null && val !== undefined)
          .map(([defId, val]) => ({
            definition_id: Number(defId),
            value: val,
          }))
        if (specsPayload.length > 0) {
          try {
            await axiosClient.post(EP.ADMIN_PRODUCT_SPECIFICATIONS(editingProduct.id), {
              specs: specsPayload,
            })
          } catch (_) {
            // Ignore non-blocking spec sync errors
          }
        }

        showToast('Cập nhật sản phẩm thành công!', 'success')
      } else {
        await axiosClient.post(EP.ADMIN_PRODUCTS, payload)
        showToast('Thêm sản phẩm mới thành công!', 'success')
      }
      setModalOpen(false)
      fetchProducts()
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi lưu sản phẩm', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await axiosClient.delete(EP.ADMIN_PRODUCT_DETAIL(deleteTarget.id))
      const data = res.data?.data ?? res.data
      const msg = res.data?.message || 'Đã xóa sản phẩm khỏi hệ thống!'
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

  // ── Bulk Actions Handlers ──────────────────────────────────────────────────
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
    <div className="space-y-6">
      {/* Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý sản phẩm</h1>
          <p className="text-sm text-on-surface-variant">
            Danh mục sản phẩm, biến thể và phân loại thiết bị công nghệ.
          </p>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <span className="material-symbols-outlined text-base">add</span>
          Thêm sản phẩm
        </Button>
      </div>

      {/* Filter and Search */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col md:flex-row gap-4 justify-between items-center">
        <form onSubmit={handleSearchSubmit} className="flex-1 w-full md:max-w-md flex gap-2">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Tìm theo tên sản phẩm..."
            className="flex-1 bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
          />
          <button
            type="submit"
            className="px-4 py-2 bg-primary-container text-on-primary-container rounded-xl text-sm font-semibold hover:bg-primary transition-colors flex items-center gap-1"
          >
            <span className="material-symbols-outlined text-base">search</span>
            Lọc
          </button>
        </form>

        <div className="flex items-center gap-3 w-full md:w-auto">
          <label className="text-xs font-semibold text-on-surface-variant uppercase shrink-0">
            Trạng thái:
          </label>
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value)
              setPage(1)
            }}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang bán</option>
            <option value="draft">Bản nháp</option>
            <option value="inactive">Tạm ẩn</option>
            <option value="out_of_stock">Hết hàng</option>
          </select>
        </div>
      </div>

      {/* Products Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : products.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">devices</span>
            <p className="text-sm font-medium">Không tìm thấy sản phẩm nào.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-4 py-3.5 w-10 text-center">
                    <input
                      type="checkbox"
                      checked={products.length > 0 && selectedIds.length === products.length}
                      onChange={(e) => {
                        if (e.target.checked) setSelectedIds(products.map((p) => p.id))
                        else setSelectedIds([])
                      }}
                      className="rounded accent-primary cursor-pointer w-4 h-4"
                    />
                  </th>
                  <th className="px-5 py-3.5">Ảnh</th>
                  <th className="px-5 py-3.5">Tên sản phẩm & Slug</th>
                  <th className="px-5 py-3.5">Thương hiệu / Nhóm</th>
                  <th className="px-5 py-3.5">Khoảng giá</th>
                  <th className="px-5 py-3.5 text-center">Biến thể</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {products.map((p) => {
                  const badge = STATUS_BADGES[p.status] || { label: p.status, variant: 'neutral' }
                  const variants = p.variants ?? []
                  const price = p.min_price ?? p.base_price ?? 0
                  const isSelected = selectedIds.includes(p.id)

                  return (
                    <tr
                      key={p.id}
                      className={[
                        'transition-colors',
                        isSelected ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-surface-container-high/40',
                      ].join(' ')}
                    >
                      <td className="px-4 py-3 text-center">
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={(e) => {
                            e.stopPropagation()
                            setSelectedIds((prev) =>
                              prev.includes(p.id) ? prev.filter((id) => id !== p.id) : [...prev, p.id]
                            )
                          }}
                          className="rounded accent-primary cursor-pointer w-4 h-4"
                        />
                      </td>
                      <td className="px-5 py-3">
                        <div className="w-12 h-12 bg-surface-container-high rounded-xl overflow-hidden flex items-center justify-center border border-outline-variant/20 p-1">
                          {p.thumbnail ? (
                            <img src={p.thumbnail} alt={p.name} className="w-full h-full object-contain" />
                          ) : (
                            <span className="material-symbols-outlined text-outline text-xl">smartphone</span>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-3 max-w-xs">
                        <Link
                          to={`/products/${p.id}`}
                          target="_blank"
                          className="font-semibold text-on-surface hover:text-primary transition-colors line-clamp-1 block"
                        >
                          {p.name}
                        </Link>
                        <p className="text-xs text-outline font-mono truncate">{p.slug}</p>
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
                        <button
                          onClick={() => handleOpenEdit(p)}
                          className="px-2.5 py-1 bg-surface-container-high hover:bg-surface-bright rounded-lg text-xs font-semibold text-on-surface transition-colors"
                        >
                          Sửa
                        </button>
                        <button
                          onClick={() => setDeleteTarget(p)}
                          className="px-2.5 py-1 bg-error-container/20 hover:bg-error-container/40 rounded-lg text-xs font-semibold text-error transition-colors"
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

      {/* Create / Edit Modal */}
      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingProduct ? `Chỉnh sửa sản phẩm: ${editingProduct.name}` : 'Thêm sản phẩm mới'}
        size="xl"
      >
        {/* Modal Tab Navigation for editing product */}
        {editingProduct && (
          <div className="flex border-b border-outline-variant/30 mb-5 gap-2">
            <button
              type="button"
              onClick={() => setActiveTab('info')}
              className={`pb-2.5 px-3 text-sm font-semibold transition-all border-b-2 flex items-center gap-1.5 ${
                activeTab === 'info'
                  ? 'border-primary text-primary'
                  : 'border-transparent text-on-surface-variant hover:text-on-surface'
              }`}
            >
              <span className="material-symbols-outlined text-base">info</span>
              Thông tin chung
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('specs')}
              className={`pb-2.5 px-3 text-sm font-semibold transition-all border-b-2 flex items-center gap-1.5 ${
                activeTab === 'specs'
                  ? 'border-primary text-primary'
                  : 'border-transparent text-on-surface-variant hover:text-on-surface'
              }`}
            >
              <span className="material-symbols-outlined text-base">tune</span>
              Thông số kỹ thuật
              {Object.keys(productSpecs).length > 0 && (
                <span className="ml-1 text-xs px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">
                  {Object.keys(productSpecs).length}
                </span>
              )}
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('images')}
              className={`pb-2.5 px-3 text-sm font-semibold transition-all border-b-2 flex items-center gap-1.5 ${
                activeTab === 'images'
                  ? 'border-primary text-primary'
                  : 'border-transparent text-on-surface-variant hover:text-on-surface'
              }`}
            >
              <span className="material-symbols-outlined text-base">photo_library</span>
              Thư viện ảnh
            </button>
          </div>
        )}

        {/* Tab 1: Thông tin chung */}
        {activeTab === 'info' && (
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="Tên sản phẩm *"
              value={form.name}
              onChange={handleNameChange}
              placeholder="Ví dụ: iPhone 16 Pro Max 256GB"
              error={errors.name?.[0]}
              required
            />

            <Input
              label="Đường dẫn URL (Slug) *"
              value={form.slug}
              onChange={(e) => setForm({ ...form, slug: e.target.value })}
              placeholder="iphone-16-pro-max-256gb"
              error={errors.slug?.[0]}
              required
              className="font-mono text-xs"
            />

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-1">
                <label className="text-sm font-medium text-on-surface-variant">Thương hiệu</label>
                <select
                  value={form.brand_id}
                  onChange={(e) => setForm({ ...form, brand_id: e.target.value })}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="">-- Chọn thương hiệu --</option>
                  {brands.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-sm font-medium text-on-surface-variant">Danh mục</label>
                <select
                  value={form.category_id}
                  onChange={(e) => setForm({ ...form, category_id: e.target.value })}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="">-- Chọn danh mục --</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-sm font-medium text-on-surface-variant">Trạng thái</label>
                <select
                  value={form.status}
                  onChange={(e) => setForm({ ...form, status: e.target.value })}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="active">Đang bán</option>
                  <option value="draft">Bản nháp</option>
                  <option value="inactive">Tạm ẩn</option>
                  <option value="out_of_stock">Hết hàng</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Input
                label="Giá bán (VNĐ) *"
                type="number"
                min="0"
                value={form.price}
                onChange={(e) => setForm({ ...form, price: e.target.value })}
                placeholder="Ví dụ: 15990000"
                error={errors.price?.[0]}
                required
              />

              <Input
                label="Giá niêm yết (VNĐ)"
                type="number"
                min="0"
                value={form.compare_price}
                onChange={(e) => setForm({ ...form, compare_price: e.target.value })}
                placeholder="Ví dụ: 17990000"
                error={errors.compare_price?.[0]}
              />

              <Input
                label="Số lượng tồn kho *"
                type="number"
                min="0"
                value={form.stock_quantity}
                onChange={(e) => setForm({ ...form, stock_quantity: e.target.value })}
                placeholder="Ví dụ: 50"
                error={errors.stock_quantity?.[0]}
                required
              />
            </div>

            <Input
              label="Đường dẫn ảnh đại diện (URL)"
              value={form.image_url}
              onChange={(e) => setForm({ ...form, image_url: e.target.value })}
              placeholder="https://example.com/image.jpg hoặc để trống dùng ảnh mẫu"
              error={errors.image_url?.[0]}
            />

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium text-on-surface-variant">Mô tả sản phẩm (HTML hoặc Text)</label>
              <textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                rows={4}
                placeholder="Nhập thông tin giới thiệu, thông số nổi bật..."
                className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary resize-none"
              />
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={submitting}>
                Hủy
              </Button>
              <Button type="submit" variant="primary" loading={submitting}>
                {editingProduct ? 'Lưu thay đổi' : 'Tạo mới'}
              </Button>
            </div>
          </form>
        )}

        {/* Tab 2: Thông số kỹ thuật */}
        {activeTab === 'specs' && editingProduct && (
          <div className="space-y-4">
            <div className="flex items-center justify-between pb-2 border-b border-outline-variant/20">
              <div>
                <h4 className="text-sm font-semibold text-on-surface">Cấu hình thông số kỹ thuật</h4>
                <p className="text-xs text-on-surface-variant">
                  Cập nhật các thông số để hiển thị trên trang chi tiết và hỗ trợ khách hàng so sánh sản phẩm.
                </p>
              </div>
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={handleSaveSpecs}
                loading={savingSpecs}
              >
                <span className="material-symbols-outlined text-sm">save</span>
                Lưu thông số
              </Button>
            </div>

            {loadingSpecs ? (
              <div className="py-12 flex justify-center">
                <Spinner size="md" />
              </div>
            ) : specDefinitions.length === 0 ? (
              <div className="text-center py-8 text-on-surface-variant text-sm bg-surface-container-low rounded-xl">
                Chưa có định nghĩa thông số nào trong hệ thống.{' '}
                <Link to="/admin/specs" className="text-primary hover:underline font-semibold">
                  Tới trang quản lý định nghĩa thông số →
                </Link>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[50vh] overflow-y-auto pr-1">
                {specDefinitions.map((def) => {
                  const val = productSpecs[def.id] ?? ''
                  return (
                    <div
                      key={def.id}
                      className="p-3 rounded-xl bg-surface-container-low border border-outline-variant/30 space-y-1.5"
                    >
                      <div className="flex items-center justify-between">
                        <label className="text-xs font-semibold text-on-surface flex items-center gap-1">
                          {def.name}
                          {def.unit && (
                            <span className="text-[11px] font-normal text-on-surface-variant">
                              ({def.unit})
                            </span>
                          )}
                        </label>
                        <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-surface-container text-on-surface-variant">
                          {def.value_type}
                        </span>
                      </div>

                      {def.value_type === 'boolean' ? (
                        <div className="flex items-center gap-3 pt-1">
                          <label className="flex items-center gap-1.5 cursor-pointer text-xs text-on-surface">
                            <input
                              type="radio"
                              name={`spec_${def.id}`}
                              checked={val === true || val === '1' || val === 1}
                              onChange={() =>
                                setProductSpecs((prev) => ({ ...prev, [def.id]: true }))
                              }
                              className="accent-primary"
                            />
                            <span>Có</span>
                          </label>
                          <label className="flex items-center gap-1.5 cursor-pointer text-xs text-on-surface">
                            <input
                              type="radio"
                              name={`spec_${def.id}`}
                              checked={val === false || val === '0' || val === 0}
                              onChange={() =>
                                setProductSpecs((prev) => ({ ...prev, [def.id]: false }))
                              }
                              className="accent-primary"
                            />
                            <span>Không</span>
                          </label>
                          {val !== '' && (
                            <button
                              type="button"
                              onClick={() =>
                                setProductSpecs((prev) => {
                                  const next = { ...prev }
                                  delete next[def.id]
                                  return next
                                })
                              }
                              className="text-[11px] text-outline hover:text-error ml-auto"
                            >
                              Xóa
                            </button>
                          )}
                        </div>
                      ) : def.value_type === 'number' ? (
                        <input
                          type="number"
                          step="any"
                          value={val}
                          onChange={(e) =>
                            setProductSpecs((prev) => ({ ...prev, [def.id]: e.target.value }))
                          }
                          placeholder={`Nhập ${def.name.toLowerCase()}...`}
                          className="w-full bg-surface-container text-on-surface rounded-lg px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                      ) : (
                        <input
                          type="text"
                          value={val}
                          onChange={(e) =>
                            setProductSpecs((prev) => ({ ...prev, [def.id]: e.target.value }))
                          }
                          placeholder={`Nhập ${def.name.toLowerCase()}...`}
                          className="w-full bg-surface-container text-on-surface rounded-lg px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                      )}
                    </div>
                  )
                })}
              </div>
            )}

            <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
                Đóng
              </Button>
              <Button
                type="button"
                variant="primary"
                onClick={handleSaveSpecs}
                loading={savingSpecs}
              >
                Lưu thông số kỹ thuật
              </Button>
            </div>
          </div>
        )}

        {/* Tab 3: Thư viện hình ảnh */}
        {activeTab === 'images' && editingProduct && (
          <div className="space-y-4">
            <VariantImageManager
              productId={editingProduct.id}
              variants={editingProduct.variants ?? []}
            />
            <div className="flex justify-end pt-3 border-t border-outline-variant/10">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
                Đóng
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Xóa sản phẩm"
        message={`Bạn có chắc chắn muốn xóa sản phẩm "${deleteTarget?.name}"? Tất cả hình ảnh và liên kết liên quan sẽ bị gỡ bỏ.`}
        confirmLabel="Xác nhận xóa"
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
