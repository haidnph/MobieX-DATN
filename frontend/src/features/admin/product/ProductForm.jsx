// src/features/admin/product/ProductForm.jsx
// Reusable product form for both Create and Edit Dedicated Pages.
// Features: 2-column responsive layout, dirty state tracking, slug generation,
// integrated ProductImageGallery (draft & live), specification editor, and sticky action bar.

import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import Button from '../../../components/common/Button'
import Input from '../../../components/common/Input'
import Spinner from '../../../components/common/Spinner'
import ConfirmDialog from '../../../components/feedback/ConfirmDialog'
import ProductImageGallery from '../../../components/admin/ProductImageGallery'
import VariantImageManager from '../../../components/admin/VariantImageManager'

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

/**
 * @param {Object} props
 * @param {Object|null} props.initialData        - Existing product data for edit mode
 * @param {Object}      props.initialSpecs       - Initial specs map { [defId]: val }
 * @param {boolean}     props.isEdit             - Whether this is editing an existing product
 * @param {Array}       props.brands             - List of brand objects
 * @param {Array}       props.categories         - List of category objects
 * @param {Array}       props.specDefinitions    - Specification definitions
 * @param {boolean}     props.loadingSpecs       - Whether specifications are loading
 * @param {boolean}     props.submitting         - Submission loading indicator
 * @param {Object}      props.errors             - Field-level validation errors
 * @param {Function}    props.onSubmit           - ({ form, specs, draftGallery }) => Promise<void>
 * @param {Function}    props.onCancel           - Navigation handler when user cancels
 */
export default function ProductForm({
  initialData = null,
  initialSpecs = {},
  isEdit = false,
  brands = [],
  categories = [],
  specDefinitions = [],
  loadingSpecs = false,
  submitting = false,
  errors = {},
  onSubmit,
  onCancel,
}) {
  // Form fields
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

  // Specs state { [definition_id]: value }
  const [productSpecs, setProductSpecs] = useState({})

  // Draft gallery for create mode { files: File[], primaryIndex: number }
  const [draftGallery, setDraftGallery] = useState({ files: [], primaryIndex: 0 })

  // Active gallery view for edit mode: 'general' | 'variants'
  const [galleryView, setGalleryView] = useState('general')

  // Dirty state tracking to prevent accidental navigation loss
  const [isDirty, setIsDirty] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)
  const [autoSlug, setAutoSlug] = useState(!isEdit)

  // Populate initial data when available
  useEffect(() => {
    if (initialData) {
      const firstVar = initialData.variants?.[0]
      const primaryImg =
        initialData.images?.find((img) => img.is_primary) || initialData.images?.[0]

      setForm({
        name: initialData.name || '',
        slug: initialData.slug || '',
        brand_id: initialData.brand_id ? String(initialData.brand_id) : '',
        category_id: initialData.category_id ? String(initialData.category_id) : '',
        status: initialData.status || 'active',
        description: initialData.description || '',
        price: firstVar?.price
          ? String(firstVar.price)
          : initialData.min_price
          ? String(initialData.min_price)
          : '',
        compare_price: firstVar?.compare_price
          ? String(firstVar.compare_price)
          : initialData.max_price
          ? String(initialData.max_price)
          : '',
        stock_quantity:
          firstVar?.inventory?.on_hand_quantity != null
            ? String(firstVar.inventory.on_hand_quantity)
            : '50',
        image_url: primaryImg?.image_path || initialData.thumbnail || '',
      })
      setAutoSlug(false)
    } else {
      // Default selections for create mode
      setForm((prev) => ({
        ...prev,
        brand_id: prev.brand_id || (brands[0]?.id ? String(brands[0].id) : ''),
        category_id: prev.category_id || (categories[0]?.id ? String(categories[0].id) : ''),
      }))
    }
  }, [initialData, brands, categories])

  // Populate initial specs
  useEffect(() => {
    if (initialSpecs && Object.keys(initialSpecs).length > 0) {
      setProductSpecs(initialSpecs)
    }
  }, [initialSpecs])

  // Browser navigation warning on unsaved changes
  useEffect(() => {
    const handleBeforeUnload = (e) => {
      if (isDirty) {
        e.preventDefault()
        e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời đi?'
        return e.returnValue
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [isDirty])

  // Form field handlers
  const handleFieldChange = (key, value) => {
    setIsDirty(true)
    setForm((prev) => ({ ...prev, [key]: value }))
  }

  const handleNameChange = (e) => {
    setIsDirty(true)
    const val = e.target.value
    setForm((prev) => ({
      ...prev,
      name: val,
      slug: autoSlug ? slugify(val) : prev.slug,
    }))
  }

  const handleRegenerateSlug = () => {
    if (!form.name) return
    setIsDirty(true)
    setForm((prev) => ({ ...prev, slug: slugify(prev.name) }))
    setAutoSlug(true)
  }

  const handleSpecChange = (defId, value) => {
    setIsDirty(true)
    setProductSpecs((prev) => ({ ...prev, [defId]: value }))
  }

  const handleSpecRemove = (defId) => {
    setIsDirty(true)
    setProductSpecs((prev) => {
      const next = { ...prev }
      delete next[defId]
      return next
    })
  }

  const handleDraftGalleryChange = (draft) => {
    setIsDirty(true)
    setDraftGallery(draft)
  }

  // Submit handler
  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!onSubmit) return

    const success = await onSubmit({
      form,
      specs: productSpecs,
      draftGallery,
    })

    if (success !== false) {
      setIsDirty(false)
    }
  }

  // Safe Cancel handler
  const handleCancelClick = () => {
    if (isDirty) {
      setShowCancelConfirm(true)
    } else if (onCancel) {
      onCancel()
    }
  }

  const configuredSpecsCount = useMemo(() => {
    return Object.values(productSpecs).filter((v) => v !== '' && v !== null && v !== undefined)
      .length
  }, [productSpecs])

  const variants = initialData?.variants || []

  return (
    <form onSubmit={handleSubmit} className="space-y-6 pb-24">
      {/* 2-Column Responsive Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* ── LEFT COLUMN (Main Content: 8/12) ── */}
        <div className="lg:col-span-8 space-y-6">
          {/* Section 1: Basic Information */}
          <section className="bg-surface-container-high rounded-2xl p-5 sm:p-6 border border-outline-variant/30 shadow-xs space-y-4">
            <div className="flex items-center gap-2 pb-2 border-b border-outline-variant/20">
              <span className="material-symbols-outlined text-primary text-xl">info</span>
              <div>
                <h2 className="text-base font-bold text-on-surface">Thông tin cơ bản</h2>
                <p className="text-xs text-on-surface-variant">
                  Tên sản phẩm, đường dẫn SEO và mô tả chi tiết sản phẩm.
                </p>
              </div>
            </div>

            <div className="space-y-4">
              <Input
                label="Tên sản phẩm *"
                value={form.name}
                onChange={handleNameChange}
                placeholder="Ví dụ: iPhone 16 Pro Max 256GB Titan Tự Nhiên"
                error={errors.name?.[0]}
                required
              />

              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <label className="text-sm font-medium text-on-surface-variant">
                    Đường dẫn URL (Slug) *
                  </label>
                  <button
                    type="button"
                    onClick={handleRegenerateSlug}
                    className="text-xs text-primary hover:underline flex items-center gap-1 cursor-pointer"
                  >
                    <span className="material-symbols-outlined text-xs">sync</span>
                    Tạo lại từ tên
                  </button>
                </div>
                <Input
                  value={form.slug}
                  onChange={(e) => {
                    setAutoSlug(false)
                    handleFieldChange('slug', e.target.value)
                  }}
                  placeholder="iphone-16-pro-max-256gb"
                  error={errors.slug?.[0]}
                  required
                  className="font-mono text-xs"
                />
                <p className="text-[11px] text-outline">
                  Đường dẫn hiển thị trên thanh địa chỉ trình duyệt:{' '}
                  <span className="font-mono text-primary">
                    /products/{form.slug || 'duong-dan-san-pham'}
                  </span>
                </p>
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-on-surface-variant">
                  Mô tả sản phẩm (HTML hoặc Text)
                </label>
                <textarea
                  value={form.description}
                  onChange={(e) => handleFieldChange('description', e.target.value)}
                  rows={6}
                  placeholder="Nhập thông tin giới thiệu, các đặc điểm nổi bật, thiết kế, hiệu năng..."
                  className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl p-3 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary leading-relaxed"
                />
              </div>
            </div>
          </section>

          {/* Section 2: Product Image Gallery */}
          <section className="bg-surface-container-high rounded-2xl p-5 sm:p-6 border border-outline-variant/30 shadow-xs space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-outline-variant/20">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-xl">photo_library</span>
                <div>
                  <h2 className="text-base font-bold text-on-surface">Thư viện hình ảnh sản phẩm</h2>
                  <p className="text-xs text-on-surface-variant">
                    Kéo thả nhiều ảnh, chọn ảnh đại diện bằng biểu tượng sao, sắp xếp thứ tự hiển thị.
                  </p>
                </div>
              </div>

              {/* Mode switch for edit mode: General vs Variant images */}
              {isEdit && variants.length > 0 && (
                <div className="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-outline-variant/30 self-start sm:self-auto">
                  <button
                    type="button"
                    onClick={() => setGalleryView('general')}
                    className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-all flex items-center gap-1 ${
                      galleryView === 'general'
                        ? 'bg-primary text-on-primary shadow-xs'
                        : 'text-on-surface-variant hover:text-on-surface'
                    }`}
                  >
                    <span className="material-symbols-outlined text-sm">auto_awesome_motion</span>
                    Ảnh chung
                  </button>
                  <button
                    type="button"
                    onClick={() => setGalleryView('variants')}
                    className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-all flex items-center gap-1 ${
                      galleryView === 'variants'
                        ? 'bg-primary text-on-primary shadow-xs'
                        : 'text-on-surface-variant hover:text-on-surface'
                    }`}
                  >
                    <span className="material-symbols-outlined text-sm">palette</span>
                    Theo phiên bản ({variants.length})
                  </button>
                </div>
              )}
            </div>

            {/* Gallery Component */}
            {!isEdit ? (
              <div className="space-y-4">
                <ProductImageGallery
                  productId={null}
                  title="Tải ảnh lên thư viện (Tối đa 10 ảnh)"
                  onDraftChange={handleDraftGalleryChange}
                />
                <details className="text-xs text-outline cursor-pointer group pt-2 border-t border-outline-variant/20">
                  <summary className="font-semibold text-on-surface-variant hover:text-primary transition-colors select-none flex items-center gap-1.5 py-1">
                    <span className="material-symbols-outlined text-sm transition-transform group-open:rotate-90">
                      chevron_right
                    </span>
                    Tùy chọn nâng cao: Nhập URL ảnh đại diện trực tiếp
                  </summary>
                  <div className="mt-2 pl-4">
                    <Input
                      label="Đường dẫn ảnh đại diện (URL)"
                      value={form.image_url}
                      onChange={(e) => handleFieldChange('image_url', e.target.value)}
                      placeholder="https://example.com/image.jpg hoặc để trống dùng ảnh tải lên"
                      error={errors.image_url?.[0]}
                    />
                  </div>
                </details>
              </div>
            ) : galleryView === 'variants' && variants.length > 0 ? (
              <VariantImageManager productId={initialData.id} variants={variants} />
            ) : (
              <ProductImageGallery
                productId={initialData.id}
                title="Ảnh chung của sản phẩm"
                onImagesChange={(imgs) => {
                  const primary = imgs.find((img) => img.is_primary) || imgs[0]
                  if (primary) {
                    setForm((prev) => ({
                      ...prev,
                      image_url: primary.image_url || primary.image_path || prev.image_url,
                    }))
                  }
                }}
              />
            )}
          </section>

          {/* Section 3: Technical Specifications */}
          <section className="bg-surface-container-high rounded-2xl p-5 sm:p-6 border border-outline-variant/30 shadow-xs space-y-4">
            <div className="flex items-center justify-between pb-2 border-b border-outline-variant/20">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-xl">tune</span>
                <div>
                  <h2 className="text-base font-bold text-on-surface">Thông số kỹ thuật</h2>
                  <p className="text-xs text-on-surface-variant">
                    Cấu hình thông số chi tiết để hiển thị trên trang sản phẩm và bảng so sánh.
                  </p>
                </div>
              </div>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary border border-primary/20">
                {configuredSpecsCount}/{specDefinitions.length} thông số
              </span>
            </div>

            {loadingSpecs ? (
              <div className="py-8 flex justify-center items-center gap-2 text-on-surface-variant text-sm">
                <Spinner size="md" />
                <span>Đang tải danh mục thông số...</span>
              </div>
            ) : specDefinitions.length === 0 ? (
              <div className="text-center py-6 text-on-surface-variant text-sm bg-surface-container-low rounded-xl">
                Chưa có định nghĩa thông số nào trong hệ thống.{' '}
                <Link to="/admin/specs" className="text-primary hover:underline font-semibold">
                  Tới trang quản lý định nghĩa thông số →
                </Link>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                {specDefinitions.map((def) => {
                  const val = productSpecs[def.id] ?? ''
                  return (
                    <div
                      key={def.id}
                      className="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant/30 space-y-2 hover:border-outline-variant/60 transition-colors"
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
                              onChange={() => handleSpecChange(def.id, true)}
                              className="accent-primary"
                            />
                            <span>Có</span>
                          </label>
                          <label className="flex items-center gap-1.5 cursor-pointer text-xs text-on-surface">
                            <input
                              type="radio"
                              name={`spec_${def.id}`}
                              checked={val === false || val === '0' || val === 0}
                              onChange={() => handleSpecChange(def.id, false)}
                              className="accent-primary"
                            />
                            <span>Không</span>
                          </label>
                          {val !== '' && (
                            <button
                              type="button"
                              onClick={() => handleSpecRemove(def.id)}
                              className="text-[11px] text-outline hover:text-error ml-auto cursor-pointer"
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
                          onChange={(e) => handleSpecChange(def.id, e.target.value)}
                          placeholder={`Nhập ${def.name.toLowerCase()}...`}
                          className="w-full bg-surface-container text-on-surface rounded-lg px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                      ) : (
                        <input
                          type="text"
                          value={val}
                          onChange={(e) => handleSpecChange(def.id, e.target.value)}
                          placeholder={`Nhập ${def.name.toLowerCase()}...`}
                          className="w-full bg-surface-container text-on-surface rounded-lg px-3 py-1.5 text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                      )}
                    </div>
                  )
                })}
              </div>
            )}
          </section>

          {/* Section 4: Variants Overview (Edit Mode Only) */}
          {isEdit && variants.length > 0 && (
            <section className="bg-surface-container-high rounded-2xl p-5 sm:p-6 border border-outline-variant/30 shadow-xs space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-outline-variant/20">
                <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-primary text-xl">layers</span>
                  <div>
                    <h2 className="text-base font-bold text-on-surface">Các phiên bản cấu hình (Variants)</h2>
                    <p className="text-xs text-on-surface-variant">
                      Tổng cộng {variants.length} phiên bản SKU được tạo cho sản phẩm này.
                    </p>
                  </div>
                </div>
                <Link
                  to="/admin/inventories"
                  className="text-xs text-primary hover:underline flex items-center gap-1 font-medium"
                >
                  Quản lý kho chi tiết
                  <span className="material-symbols-outlined text-xs">arrow_forward</span>
                </Link>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-surface-container-low text-on-surface-variant uppercase text-[10px] tracking-wider border-b border-outline-variant/20">
                    <tr>
                      <th className="px-3 py-2.5">Phiên bản</th>
                      <th className="px-3 py-2.5">SKU</th>
                      <th className="px-3 py-2.5 text-right">Giá bán</th>
                      <th className="px-3 py-2.5 text-right">Giá so sánh</th>
                      <th className="px-3 py-2.5 text-right">Tồn kho</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-outline-variant/10">
                    {variants.map((v) => (
                      <tr key={v.id} className="hover:bg-surface-container-low/50">
                        <td className="px-3 py-2 font-medium text-on-surface">
                          {v.option_signature || `Phiên bản #${v.id}`}
                        </td>
                        <td className="px-3 py-2 font-mono text-outline">{v.sku || '—'}</td>
                        <td className="px-3 py-2 text-right font-semibold text-primary">
                          {Number(v.price || 0).toLocaleString('vi-VN')} đ
                        </td>
                        <td className="px-3 py-2 text-right text-outline">
                          {v.compare_price
                            ? `${Number(v.compare_price).toLocaleString('vi-VN')} đ`
                            : '—'}
                        </td>
                        <td className="px-3 py-2 text-right font-medium">
                          {v.inventory?.on_hand_quantity ?? '50'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </div>

        {/* ── RIGHT COLUMN (Sidebar Attributes: 4/12) ── */}
        <div className="lg:col-span-4 space-y-6 lg:sticky lg:top-20">
          {/* Card 1: Status & Visibility */}
          <div className="bg-surface-container-high rounded-2xl p-5 border border-outline-variant/30 shadow-xs space-y-3">
            <h3 className="text-sm font-bold text-on-surface flex items-center gap-1.5 pb-2 border-b border-outline-variant/20">
              <span className="material-symbols-outlined text-primary text-base">toggle_on</span>
              Trạng thái kinh doanh
            </h3>
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-on-surface-variant">Trạng thái bán</label>
              <select
                value={form.status}
                onChange={(e) => handleFieldChange('status', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3.5 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
              >
                <option value="active">Đang bán (Hiển thị ngay cho khách hàng)</option>
                <option value="draft">Bản nháp (Chỉ quản trị viên xem được)</option>
                <option value="inactive">Tạm ẩn (Tạm ngưng kinh doanh)</option>
                <option value="out_of_stock">Hết hàng (Hiển thị nhãn hết hàng)</option>
              </select>
            </div>
          </div>

          {/* Card 2: Organization (Brand & Category) */}
          <div className="bg-surface-container-high rounded-2xl p-5 border border-outline-variant/30 shadow-xs space-y-4">
            <h3 className="text-sm font-bold text-on-surface flex items-center gap-1.5 pb-2 border-b border-outline-variant/20">
              <span className="material-symbols-outlined text-primary text-base">category</span>
              Phân loại sản phẩm
            </h3>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-medium text-on-surface-variant">Thương hiệu</label>
                <Link to="/admin/brands" className="text-[11px] text-primary hover:underline">
                  + Thêm mới
                </Link>
              </div>
              <select
                value={form.brand_id}
                onChange={(e) => handleFieldChange('brand_id', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3.5 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
              >
                <option value="">-- Chọn thương hiệu --</option>
                {brands.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-medium text-on-surface-variant">Danh mục</label>
                <Link to="/admin/categories" className="text-[11px] text-primary hover:underline">
                  + Thêm mới
                </Link>
              </div>
              <select
                value={form.category_id}
                onChange={(e) => handleFieldChange('category_id', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3.5 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
              >
                <option value="">-- Chọn danh mục --</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Card 3: Pricing & Inventory */}
          <div className="bg-surface-container-high rounded-2xl p-5 border border-outline-variant/30 shadow-xs space-y-4">
            <h3 className="text-sm font-bold text-on-surface flex items-center gap-1.5 pb-2 border-b border-outline-variant/20">
              <span className="material-symbols-outlined text-primary text-base">payments</span>
              Giá bán & Tồn kho
            </h3>

            <div className="space-y-3">
              <Input
                label="Giá bán (VNĐ) *"
                type="number"
                min="0"
                value={form.price}
                onChange={(e) => handleFieldChange('price', e.target.value)}
                placeholder="Ví dụ: 15990000"
                error={errors.price?.[0]}
                required
              />

              <Input
                label="Giá niêm yết / So sánh (VNĐ)"
                type="number"
                min="0"
                value={form.compare_price}
                onChange={(e) => handleFieldChange('compare_price', e.target.value)}
                placeholder="Ví dụ: 17990000"
                error={errors.compare_price?.[0]}
              />

              <Input
                label="Số lượng tồn kho ban đầu *"
                type="number"
                min="0"
                value={form.stock_quantity}
                onChange={(e) => handleFieldChange('stock_quantity', e.target.value)}
                placeholder="Ví dụ: 50"
                error={errors.stock_quantity?.[0]}
                required
              />
            </div>
          </div>

          {/* Card 4: Metadata (Edit mode only) */}
          {isEdit && initialData && (
            <div className="bg-surface-container-high rounded-2xl p-5 border border-outline-variant/30 shadow-xs space-y-2.5 text-xs text-on-surface-variant">
              <h3 className="text-sm font-bold text-on-surface flex items-center gap-1.5 pb-2 border-b border-outline-variant/20">
                <span className="material-symbols-outlined text-primary text-base">badge</span>
                Thông tin hệ thống
              </h3>
              <div className="flex justify-between py-1 border-b border-outline-variant/10">
                <span>Mã sản phẩm (ID):</span>
                <span className="font-mono font-bold text-on-surface">#{initialData.id}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-outline-variant/10">
                <span>Ngày tạo:</span>
                <span>
                  {initialData.created_at
                    ? new Date(initialData.created_at).toLocaleDateString('vi-VN')
                    : '—'}
                </span>
              </div>
              <div className="flex justify-between py-1 border-b border-outline-variant/10">
                <span>Cập nhật lần cuối:</span>
                <span>
                  {initialData.updated_at
                    ? new Date(initialData.updated_at).toLocaleDateString('vi-VN')
                    : '—'}
                </span>
              </div>
              <div className="flex justify-between py-1">
                <span>Lượt đánh giá:</span>
                <span className="font-semibold text-primary">
                  {initialData.reviews_count ?? 0} đánh giá
                </span>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── STICKY BOTTOM ACTION BAR ── */}
      <div className="fixed bottom-0 left-0 right-0 z-40 bg-surface-container-high/95 backdrop-blur-md border-t border-outline-variant/30 py-3.5 px-6 shadow-lg">
        <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-xs sm:text-sm">
            {isDirty ? (
              <span className="flex items-center gap-1.5 text-amber-500 font-medium">
                <span className="material-symbols-outlined text-base">edit_note</span>
                <span className="hidden sm:inline">Bạn có thay đổi chưa lưu</span>
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-outline">
                <span className="material-symbols-outlined text-base">check_circle</span>
                <span className="hidden sm:inline">Tất cả thay đổi đã được đồng bộ</span>
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={handleCancelClick}
              disabled={submitting}
              className="cursor-pointer"
            >
              Hủy bỏ
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={submitting}
              className="flex items-center gap-1.5 shadow-md cursor-pointer"
            >
              <span className="material-symbols-outlined text-base">
                {isEdit ? 'save' : 'add_circle'}
              </span>
              {isEdit ? 'Lưu thay đổi' : 'Tạo sản phẩm'}
            </Button>
          </div>
        </div>
      </div>

      {/* Cancel Confirmation Dialog */}
      <ConfirmDialog
        open={showCancelConfirm}
        title="Rời khỏi trang?"
        message="Các thông tin bạn vừa chỉnh sửa chưa được lưu lại. Bạn có chắc chắn muốn hủy bỏ và quay lại danh sách sản phẩm không?"
        confirmLabel="Đồng ý rời đi"
        cancelLabel="Tiếp tục chỉnh sửa"
        onConfirm={() => {
          setShowCancelConfirm(false)
          setIsDirty(false)
          if (onCancel) onCancel()
        }}
        onCancel={() => setShowCancelConfirm(false)}
      />
    </form>
  )
}
