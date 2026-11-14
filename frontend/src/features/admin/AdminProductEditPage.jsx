// src/features/admin/AdminProductEditPage.jsx
// Dedicated page for editing an existing product in Admin MobieX.
// URL: /admin/products/:id/edit

import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import ProductForm from './product/ProductForm'
import Spinner from '../../components/common/Spinner'
import Button from '../../components/common/Button'

export default function AdminProductEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [product, setProduct] = useState(null)
  const [initialSpecs, setInitialSpecs] = useState({})
  const [brands, setBrands] = useState([])
  const [categories, setCategories] = useState([])
  const [specDefinitions, setSpecDefinitions] = useState([])

  const [loading, setLoading] = useState(true)
  const [loadingSpecs, setLoadingSpecs] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [notFound, setNotFound] = useState(false)

  // Fetch product data and system metadata
  useEffect(() => {
    if (!id) return
    setLoading(true)
    setNotFound(false)

    Promise.allSettled([
      axiosClient.get(EP.PRODUCT_DETAIL(id)),
      axiosClient.get(EP.BRANDS),
      axiosClient.get(EP.CATEGORIES),
      axiosClient.get(EP.SPEC_DEFINITIONS),
    ])
      .then(([pRes, bRes, cRes, sRes]) => {
        // Handle Product detail
        if (pRes.status === 'fulfilled') {
          const prodData = pRes.value.data.data ?? pRes.value.data
          setProduct(prodData)

          // Build specs map from existing product specifications
          const map = {}
          if (prodData.specifications && Array.isArray(prodData.specifications)) {
            prodData.specifications.forEach((s) => {
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
          setInitialSpecs(map)
        } else {
          setNotFound(true)
          showToast('Không tìm thấy sản phẩm yêu cầu', 'error')
        }

        // Handle metadata
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
      .catch((err) => {
        showToast(err.message || 'Lỗi khi tải dữ liệu sản phẩm', 'error')
      })
      .finally(() => setLoading(false))
  }, [id, showToast])

  const handleSubmit = async ({ form, specs }) => {
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
      await axiosClient.put(EP.ADMIN_PRODUCT_DETAIL(id), payload)

      // Sync specifications
      const specsPayload = Object.entries(specs)
        .filter(([_, val]) => val !== '' && val !== null && val !== undefined)
        .map(([defId, val]) => ({
          definition_id: Number(defId),
          value: val,
        }))

      if (specsPayload.length > 0) {
        try {
          await axiosClient.post(EP.ADMIN_PRODUCT_SPECIFICATIONS(id), {
            specs: specsPayload,
          })
        } catch (specErr) {
          console.warn('Lỗi khi cập nhật thông số kỹ thuật:', specErr)
        }
      }

      showToast(`Cập nhật thông tin sản phẩm #${id} thành công!`, 'success')
      // Refetch latest product details
      const refreshRes = await axiosClient.get(EP.PRODUCT_DETAIL(id))
      const updated = refreshRes.data?.data ?? refreshRes.data
      setProduct(updated)
      return true
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Lỗi khi cập nhật sản phẩm', 'error')
      return false
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/products')
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] gap-3">
        <Spinner size="lg" />
        <p className="text-sm text-on-surface-variant">Đang tải thông tin sản phẩm #{id}...</p>
      </div>
    )
  }

  if (notFound || !product) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center max-w-md mx-auto">
        <span className="material-symbols-outlined text-5xl text-outline">search_off</span>
        <h2 className="text-xl font-bold text-on-surface">Không tìm thấy sản phẩm</h2>
        <p className="text-sm text-on-surface-variant">
          Sản phẩm có mã ID #{id} không tồn tại hoặc đã bị xóa khỏi hệ thống.
        </p>
        <Button variant="primary" onClick={() => navigate('/admin/products')}>
          Quay lại danh sách sản phẩm
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Breadcrumb Navigation */}
      <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-on-surface-variant flex-wrap">
        <Link to="/admin" className="hover:text-primary transition-colors flex items-center gap-1">
          <span className="material-symbols-outlined text-sm">home</span>
          Tổng quan
        </Link>
        <span className="text-outline">/</span>
        <Link to="/admin/products" className="hover:text-primary transition-colors">
          Sản phẩm
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold line-clamp-1 max-w-[200px] sm:max-w-none">
          Chỉnh sửa: {product.name}
        </span>
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
            <h1 className="text-2xl font-bold text-on-surface flex items-center gap-2">
              <span className="line-clamp-1">{product.name}</span>
              <span className="text-xs font-mono font-normal px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant shrink-0">
                #{product.id}
              </span>
            </h1>
          </div>
          <p className="text-sm text-on-surface-variant mt-1 pl-9">
            Cập nhật nội dung, giá bán, tồn kho, thư viện ảnh và thông số kỹ thuật.
          </p>
        </div>

        <div className="flex items-center gap-2 pl-9 sm:pl-0">
          <a
            href={`/products/${product.slug || product.id}`}
            target="_blank"
            rel="noreferrer"
            className="px-3 py-1.5 rounded-xl text-xs font-semibold bg-surface-container-high hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors flex items-center gap-1.5 border border-outline-variant/30"
          >
            <span className="material-symbols-outlined text-sm">visibility</span>
            Xem trên cửa hàng
          </a>
        </div>
      </div>

      {/* Product Form */}
      <ProductForm
        initialData={product}
        initialSpecs={initialSpecs}
        isEdit={true}
        brands={brands}
        categories={categories}
        specDefinitions={specDefinitions}
        loadingSpecs={loadingSpecs}
        submitting={submitting}
        errors={errors}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  )
}
