// src/components/admin/ProductImageGallery.jsx
// Product image gallery manager for Admin:
// - Lists images by sort_order
// - Upload new images via ImageUploader
// - Set primary image
// - Drag-and-drop reorder (native HTML5 DnD, no external library required)
// - Safe delete with confirmation dialog

import { useCallback, useEffect, useRef, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import ImageUploader from '../common/ImageUploader'

const PLACEHOLDER = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'

/**
 * @param {Object}  props
 * @param {number}  props.productId    - Required: product ID to manage images for
 * @param {number}  [props.variantId]  - Optional: variant ID for variant-specific images
 * @param {string}  [props.title]      - Section heading (default: "Thư viện ảnh sản phẩm")
 */
export default function ProductImageGallery({ productId, variantId = null, title = 'Thư viện ảnh sản phẩm' }) {
  const [images, setImages] = useState([])
  const [loading, setLoading] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadProgress, setUploadProgress] = useState({}) // { fileName: percent }
  const [confirmDelete, setConfirmDelete] = useState(null) // image object
  const [toast, setToast] = useState(null)
  const dragItem = useRef(null)
  const dragOverItem = useRef(null)

  // ── Helpers ──────────────────────────────────────────────────────────────
  const showToast = (msg, type = 'success') => {
    setToast({ msg, type })
    setTimeout(() => setToast(null), 3000)
  }

  // ── Fetch images ──────────────────────────────────────────────────────────
  const fetchImages = useCallback(async () => {
    if (!productId) return
    setLoading(true)
    try {
      const params = { product_id: productId }
      if (variantId) params.product_variant_id = variantId

      const { data } = await axiosClient.get(EP.PRODUCT_IMAGES, { params })
      const list = (data.data ?? []).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
      setImages(list)
    } catch {
      showToast('Không thể tải danh sách ảnh.', 'error')
    } finally {
      setLoading(false)
    }
  }, [productId, variantId])

  useEffect(() => {
    fetchImages()
  }, [fetchImages])

  // ── Upload ────────────────────────────────────────────────────────────────
  const handleUpload = useCallback(async (files) => {
    setUploading(true)
    const progress = {}
    files.forEach((f) => { progress[f.name] = 0 })
    setUploadProgress(progress)

    try {
      const formData = new FormData()
      formData.append('product_id', productId)
      if (variantId) formData.append('variant_id', variantId)
      files.forEach((f) => formData.append('images[]', f))

      await axiosClient.post(EP.PRODUCT_IMAGES, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          const pct = Math.round((e.loaded * 100) / (e.total || 1))
          setUploadProgress((prev) => {
            const next = { ...prev }
            files.forEach((f) => { next[f.name] = pct })
            return next
          })
        },
      })

      showToast(`Đã tải lên ${files.length} ảnh thành công.`)
      await fetchImages()
    } catch (err) {
      const msg = err.response?.data?.message ?? 'Upload thất bại.'
      showToast(msg, 'error')
    } finally {
      setUploading(false)
      setUploadProgress({})
    }
  }, [productId, variantId, fetchImages])

  // ── Set Primary ───────────────────────────────────────────────────────────
  const handleSetPrimary = async (image) => {
    try {
      await axiosClient.patch(EP.PRODUCT_IMAGE_PRIMARY(image.id))
      showToast('Đã đặt làm ảnh đại diện.')
      await fetchImages()
    } catch {
      showToast('Không thể đặt ảnh đại diện.', 'error')
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────
  const handleDelete = async () => {
    if (!confirmDelete) return
    try {
      await axiosClient.delete(EP.PRODUCT_IMAGE_DETAIL(confirmDelete.id))
      showToast('Đã xóa ảnh thành công.')
      setConfirmDelete(null)
      await fetchImages()
    } catch {
      showToast('Không thể xóa ảnh.', 'error')
    }
  }

  // ── Drag & Drop Reorder (native HTML5) ───────────────────────────────────
  const handleDragStart = (index) => {
    dragItem.current = index
  }

  const handleDragEnter = (index) => {
    dragOverItem.current = index
  }

  const handleDragEnd = async () => {
    const from = dragItem.current
    const to = dragOverItem.current
    if (from === null || to === null || from === to) return

    const reordered = [...images]
    const [moved] = reordered.splice(from, 1)
    reordered.splice(to, 0, moved)

    // Assign sequential sort_order
    const withOrder = reordered.map((img, i) => ({ ...img, sort_order: i }))
    setImages(withOrder) // optimistic update

    try {
      await axiosClient.patch(EP.PRODUCT_IMAGE_REORDER, {
        images: withOrder.map(({ id, sort_order }) => ({ id, sort_order })),
      })
    } catch {
      showToast('Không thể lưu thứ tự ảnh.', 'error')
      await fetchImages() // revert on error
    }

    dragItem.current = null
    dragOverItem.current = null
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-on-surface">{title}</h3>
        <span className="text-xs text-outline">{images.length}/10 ảnh</span>
      </div>

      {/* Upload Zone */}
      <ImageUploader
        onUpload={handleUpload}
        disabled={uploading || images.length >= 10}
        maxFiles={Math.max(0, 10 - images.length)}
      />

      {/* Upload Progress */}
      {uploading && Object.keys(uploadProgress).length > 0 && (
        <div className="space-y-1.5 p-3 rounded-xl bg-surface-container-low border border-outline-variant/20">
          {Object.entries(uploadProgress).map(([name, pct]) => (
            <div key={name} className="text-xs text-on-surface-variant">
              <div className="flex justify-between mb-0.5">
                <span className="truncate max-w-[220px] font-medium">{name}</span>
                <span className="text-primary font-semibold">{pct}%</span>
              </div>
              <div className="h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary transition-all duration-300 rounded-full"
                  style={{ width: `${pct}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-8 text-outline text-sm gap-2">
          <span className="material-symbols-outlined animate-spin text-lg">progress_activity</span>
          Đang tải hình ảnh...
        </div>
      )}

      {/* Empty State */}
      {!loading && images.length === 0 && (
        <div className="flex flex-col items-center justify-center py-6 gap-2 text-outline-variant rounded-xl border border-dashed border-outline-variant/30">
          <span className="material-symbols-outlined text-4xl">image_not_supported</span>
          <p className="text-sm text-center">
            Chưa có ảnh — Hãy tải lên ít nhất một ảnh để sản phẩm hiển thị trên cửa hàng
          </p>
        </div>
      )}

      {/* Image Grid */}
      {images.length > 0 && (
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
          {images.map((img, index) => (
            <div
              key={img.id}
              draggable
              onDragStart={() => handleDragStart(index)}
              onDragEnter={() => handleDragEnter(index)}
              onDragEnd={handleDragEnd}
              onDragOver={(e) => e.preventDefault()}
              className="relative group rounded-xl overflow-hidden border border-outline-variant/30 cursor-grab active:cursor-grabbing aspect-square bg-surface-container-low shadow-sm hover:shadow-md transition-shadow"
            >
              <img
                src={img.image_url ?? img.image_path ?? PLACEHOLDER}
                alt={img.alt_text ?? 'Ảnh sản phẩm'}
                className="w-full h-full object-cover"
                onError={(e) => {
                  e.target.src = PLACEHOLDER
                }}
              />

              {/* Primary badge */}
              {img.is_primary && (
                <span className="absolute top-1.5 left-1.5 bg-primary text-on-primary text-[10px] font-bold px-2 py-0.5 rounded-full shadow">
                  Ảnh chính
                </span>
              )}

              {/* Drag handle hint */}
              <span className="absolute top-1.5 right-1.5 material-symbols-outlined text-xs text-white/80 opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 rounded p-0.5 select-none">
                drag_indicator
              </span>

              {/* Actions overlay */}
              <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                {!img.is_primary && (
                  <button
                    type="button"
                    onClick={() => handleSetPrimary(img)}
                    title="Đặt làm ảnh đại diện"
                    className="p-2 rounded-full bg-primary text-on-primary hover:scale-110 transition-transform shadow"
                  >
                    <span className="material-symbols-outlined text-sm">star</span>
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => setConfirmDelete(img)}
                  title="Xóa ảnh"
                  className="p-2 rounded-full bg-error text-on-error hover:scale-110 transition-transform shadow"
                >
                  <span className="material-symbols-outlined text-sm">delete</span>
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {confirmDelete && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="bg-surface-container-high rounded-2xl p-6 max-w-sm w-full shadow-2xl border border-outline-variant/20">
            <h4 className="text-base font-semibold text-on-surface mb-2">Xác nhận xóa ảnh?</h4>
            <p className="text-sm text-on-surface-variant mb-4">
              File ảnh sẽ bị xóa vĩnh viễn khỏi máy chủ và cơ sở dữ liệu. Bạn có chắc chắn muốn xóa không?
            </p>
            <div className="flex gap-3 justify-end">
              <button
                type="button"
                onClick={() => setConfirmDelete(null)}
                className="px-4 py-2 rounded-xl text-sm text-on-surface-variant hover:bg-surface-container transition-colors"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleDelete}
                className="px-4 py-2 rounded-xl text-sm bg-error text-on-error hover:bg-error/90 font-semibold transition-colors shadow"
              >
                Xóa vĩnh viễn
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Toast Notification */}
      {toast && (
        <div
          className={[
            'fixed bottom-6 right-6 z-[300] flex items-center gap-2 px-4 py-3 rounded-xl shadow-xl text-sm font-medium transition-all duration-300',
            toast.type === 'error'
              ? 'bg-error-container text-on-error-container border border-error/30'
              : 'bg-primary-container text-on-primary-container border border-primary/30',
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-base">
            {toast.type === 'error' ? 'error' : 'check_circle'}
          </span>
          {toast.msg}
        </div>
      )}
    </div>
  )
}
