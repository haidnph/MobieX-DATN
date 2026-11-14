// src/features/admin/AdminProductCreatePage.jsx
// Dedicated page for creating a new product in Admin MobieX.
// URL: /admin/products/create

import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import ProductForm from './product/ProductForm'
import Spinner from '../../components/common/Spinner'

export default function AdminProductCreatePage() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [brands, setBrands] = useState([])
  const [categories, setCategories] = useState([])
  const [specDefinitions, setSpecDefinitions] = useState([])
  const [loadingMeta, setLoadingMeta] = useState(true)

  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  // Fetch metadata on mount
  useEffect(() => {
    setLoadingMeta(true)
    Promise.allSettled([
      axiosClient.get(EP.BRANDS),
      axiosClient.get(EP.CATEGORIES),
      axiosClient.get(EP.SPEC_DEFINITIONS),
    ])
      .then(([bRes, cRes, sRes]) => {
        if (bRes.status === 'fulfilled') {
          const bData = bRes.value.data.data ?? bRes.value.data
          setBrands(Array.isArray(bData) ? bData : bData?.data ?? [])
        }
        if (cRes.status === 'fulfilled') {
          const cData = cRes.value.data.data ?? cRes.value.data
          setCategories(Array.isArray(cData) ? cData : cData?.data ?? [])
        }
        if (sRes.status === 'fulfilled') {
          const sData = sRes.value.data.data ?? sRes.value.data
          setSpecDefinitions(Array.isArray(sData) ? sData : [])
        }
      })
      .catch(() => {
        showToast('Không thể tải danh mục hoặc thông số hệ thống', 'error')
      })
      .finally(() => setLoadingMeta(false))
  }, [showToast])

  const handleSubmit = async ({ form, specs, draftGallery }) => {
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
      const createRes = await axiosClient.post(EP.ADMIN_PRODUCTS, payload)
      const createdProduct = createRes.data?.data ?? createRes.data

      // Upload gallery images if any were selected in draft mode
      if (createdProduct?.id && draftGallery.files && draftGallery.files.length > 0) {
        try {
          const imgFormData = new FormData()
          imgFormData.append('product_id', createdProduct.id)
          imgFormData.append('primary_index', draftGallery.primaryIndex ?? 0)
          draftGallery.files.forEach((file) => imgFormData.append('images[]', file))

          await axiosClient.post(EP.PRODUCT_IMAGES, imgFormData, {
            headers: { 'Content-Type': 'multipart/form-data' },
          })
        } catch (imgErr) {
          console.warn('Lỗi khi tải ảnh gallery sau khi tạo sản phẩm:', imgErr)
        }
      }

      // Save specifications if any were configured
      const specsPayload = Object.entries(specs)
        .filter(([_, val]) => val !== '' && val !== null && val !== undefined)
        .map(([defId, val]) => ({
          definition_id: Number(defId),
          value: val,
        }))

      if (createdProduct?.id && specsPayload.length > 0) {
        try {
          await axiosClient.post(EP.ADMIN_PRODUCT_SPECIFICATIONS(createdProduct.id), {
            specs: specsPayload,
          })
        } catch (specErr) {
          console.warn('Lỗi khi lưu thông số kỹ thuật:', specErr)
        }
      }

      showToast(`Thêm sản phẩm "${createdProduct.name}" thành công!`, 'success')
      navigate('/admin/products')
      return true
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi tạo sản phẩm', 'error')
      return false
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/products')
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Breadcrumb Navigation */}
      <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-on-surface-variant">
        <Link to="/admin" className="hover:text-primary transition-colors flex items-center gap-1">
          <span className="material-symbols-outlined text-sm">home</span>
          Tổng quan
        </Link>
        <span className="text-outline">/</span>
        <Link to="/admin/products" className="hover:text-primary transition-colors">
          Sản phẩm
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold">Thêm sản phẩm mới</span>
      </nav>

      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-outline-variant/20">
        <div>
          <div className="flex items-center gap-2.5">
            <Link
              to="/admin/products"
              className="p-1.5 rounded-xl hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors"
              title="Quay lại danh sách"
            >
              <span className="material-symbols-outlined text-xl leading-none">arrow_back</span>
            </Link>
            <h1 className="text-2xl font-bold text-on-surface">Thêm sản phẩm mới</h1>
          </div>
          <p className="text-sm text-on-surface-variant mt-1 pl-9">
            Tạo mới điện thoại hoặc phụ kiện công nghệ vào hệ thống cửa hàng MobieX.
          </p>
        </div>
      </div>

      {/* Loading state for metadata */}
      {loadingMeta ? (
        <div className="flex flex-col items-center justify-center min-h-[40vh] gap-3">
          <Spinner size="lg" />
          <p className="text-sm text-on-surface-variant">Đang tải biểu mẫu tạo sản phẩm...</p>
        </div>
      ) : (
        <ProductForm
          isEdit={false}
          brands={brands}
          categories={categories}
          specDefinitions={specDefinitions}
          submitting={submitting}
          errors={errors}
          onSubmit={handleSubmit}
          onCancel={handleCancel}
        />
      )}
    </div>
  )
}
