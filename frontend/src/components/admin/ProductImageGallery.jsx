// src/components/admin/ProductImageGallery.jsx
// Enterprise Product Image Gallery Manager for Admin
// Features:
// - Dual Mode: Live API Sync (when productId provided) & Draft Mode (when creating new product)
// - Drag-and-drop reorder (native HTML5 DnD) + Move Left / Move Right buttons
// - One-click Primary image toggle with visual badge and icon
// - Multi-file Drag & Drop upload zone with progress bar
// - Instant client-side preview for draft uploads
// - Safe deletion with confirmation modal
// - File type & size validation with friendly error feedback

import { useCallback, useEffect, useRef, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import ImageUploader from '../common/ImageUploader'

const PLACEHOLDER = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'

/**
 * @param {Object} props
 * @param {number|null} [props.productId]         - Product ID for live API management (null for draft mode)
 * @param {number|null} [props.variantId]         - Variant ID for variant-specific galleries
 * @param {string}      [props.title]             - Section title
 * @param {Function}    [props.onDraftChange]     - ({ files: File[], primaryIndex: number }) => void
 * @param {Function}    [props.onImagesChange]    - (images: Array) => void
 */
export default function ProductImageGallery({
  productId = null,
  variantId = null,
  title = 'Thư viện ảnh sản phẩm',
  onDraftChange = null,
  onImagesChange = null,
}) {
  const isDraftMode = !productId

  // Live mode state
  const [liveImages, setLiveImages] = useState([])
  const [loading, setLoading] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadProgress, setUploadProgress] = useState({}) // { fileName: percent }

  // Draft mode state: array of { id, file, previewUrl, isPrimary, sortOrder }
  const [draftList, setDraftList] = useState([])

  // Common UI state
  const [confirmDelete, setConfirmDelete] = useState(null)
  const [toast, setToast] = useState(null)

  const dragItem = useRef(null)
  const dragOverItem = useRef(null)

  // ── Helpers ──────────────────────────────────────────────────────────────
  const showToast = (msg, type = 'success') => {
    setToast({ msg, type })
    setTimeout(() => setToast(null), 3000)
  }

  // ── Fetch images for Live mode ───────────────────────────────────────────
  const fetchImages = useCallback(async () => {
    if (!productId) return
    setLoading(true)
    try {
      const params = { product_id: productId }
      if (variantId) params.product_variant_id = variantId

      const { data } = await axiosClient.get(EP.PRODUCT_IMAGES, { params })
      const list = (data.data ?? []).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
      setLiveImages(list)
      onImagesChange?.(list)
    } catch {
      showToast('Không thể tải danh sách ảnh.', 'error')
    } finally {
      setLoading(false)
    }
  }, [productId, variantId, onImagesChange])

  useEffect(() => {
    if (productId) {
      fetchImages()
    }
  }, [productId, fetchImages])

  // Clean up object URLs on unmount
  useEffect(() => {
    return () => {
      draftList.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
      })
    }
  }, [draftList])

  // Notify draft changes
  const notifyDraftChange = useCallback((newList) => {
    if (!onDraftChange) return
    const files = newList.map((item) => item.file)
    const primaryIndex = Math.max(0, newList.findIndex((item) => item.isPrimary))
    onDraftChange({ files, primaryIndex })
  }, [onDraftChange])

  // ── Upload / Add Files ────────────────────────────────────────────────────
  const handleUpload = useCallback(async (files) => {
    if (!files || files.length === 0) return

    if (isDraftMode) {
      // Draft Mode: Append files with local ObjectURLs
      setDraftList((prev) => {
        const remainingSlots = Math.max(0, 10 - prev.length)
        const allowedFiles = files.slice(0, remainingSlots)

        const newItems = allowedFiles.map((f, i) => ({
          id: `draft-${Date.now()}-${i}-${Math.random().toString(36).substring(2, 7)}`,
          file: f,
          previewUrl: URL.createObjectURL(f),
          isPrimary: prev.length === 0 && i === 0,
          sortOrder: prev.length + i,
        }))

        const combined = [...prev, ...newItems]
        // Ensure exactly one primary
        if (!combined.some((item) => item.isPrimary) && combined.length > 0) {
          combined[0].isPrimary = true
        }

        notifyDraftChange(combined)
        return combined
      })
      showToast(`Đã thêm ${files.length} ảnh vào danh sách tải lên.`)
      return
    }

    // Live Mode: Direct upload via API
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
  }, [isDraftMode, productId, variantId, fetchImages, notifyDraftChange])

  // ── Set Primary ───────────────────────────────────────────────────────────
  const handleSetPrimary = async (targetItem, index) => {
    if (isDraftMode) {
      setDraftList((prev) => {
        const next = prev.map((item, i) => ({
          ...item,
          isPrimary: i === index,
        }))
        notifyDraftChange(next)
        return next
      })
      showToast('Đã chọn làm ảnh đại diện chính.')
      return
    }

    // Live Mode
    try {
      await axiosClient.patch(EP.PRODUCT_IMAGE_PRIMARY(targetItem.id))
      showToast('Đã đặt làm ảnh đại diện.')
      await fetchImages()
    } catch {
      showToast('Không thể đặt ảnh đại diện.', 'error')
    }
  }

  // ── Delete Image ──────────────────────────────────────────────────────────
  const requestDelete = (item, index) => {
    if (isDraftMode) {
      // Instant delete for draft mode
      setDraftList((prev) => {
        const next = prev.filter((_, i) => i !== index)
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)

        // If the deleted image was primary, promote the first remaining
        if (item.isPrimary && next.length > 0) {
          next[0].isPrimary = true
        }

        const reindexed = next.map((el, i) => ({ ...el, sortOrder: i }))
        notifyDraftChange(reindexed)
        return reindexed
      })
      showToast('Đã bỏ ảnh khỏi danh sách.')
      return
    }

    // Live mode requires confirmation modal
    setConfirmDelete(item)
  }

  const handleConfirmLiveDelete = async () => {
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

  // ── Reorder: Move Left / Move Right ───────────────────────────────────────
  const moveItem = async (fromIndex, toIndex) => {
    const list = isDraftMode ? [...draftList] : [...liveImages]
    if (toIndex < 0 || toIndex >= list.length || fromIndex === toIndex) return

    const [moved] = list.splice(fromIndex, 1)
    list.splice(toIndex, 0, moved)
    const withOrder = list.map((img, i) => ({ ...img, sort_order: i, sortOrder: i }))

    if (isDraftMode) {
      setDraftList(withOrder)
      notifyDraftChange(withOrder)
      return
    }

    // Live Mode
    setLiveImages(withOrder) // optimistic
    try {
      await axiosClient.patch(EP.PRODUCT_IMAGE_REORDER, {
        images: withOrder.map(({ id, sort_order }) => ({ id, sort_order })),
      })
    } catch {
      showToast('Không thể lưu thứ tự ảnh.', 'error')
      await fetchImages()
    }
  }

  // ── Native HTML5 Drag & Drop ──────────────────────────────────────────────
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

    await moveItem(from, to)

    dragItem.current = null
    dragOverItem.current = null
  }

  // Current display list
  const currentImages = isDraftMode
    ? draftList.map((d, idx) => ({
        id: d.id,
        image_url: d.previewUrl,
        alt_text: d.file.name,
        is_primary: d.isPrimary,
        sort_order: idx,
        isDraft: true,
      }))
    : liveImages

  const totalCount = currentImages.length

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-semibold text-on-surface flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-base">photo_library</span>
            {title}
          </h3>
          <p className="text-xs text-outline mt-0.5">
            {isDraftMode
              ? 'Tải lên các góc chụp của sản phẩm (ảnh đầu tiên hoặc được đánh dấu sao sẽ là ảnh đại diện)'
              : 'Kéo thả hoặc dùng mũi tên để sắp xếp thứ tự ảnh. Ảnh chính có huy hiệu sao vàng.'}
          </p>
        </div>
        <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface-variant border border-outline-variant/30">
          {totalCount}/10 ảnh
        </span>
      </div>

      {/* Upload Dropzone */}
      <ImageUploader
        onUpload={handleUpload}
        disabled={uploading || totalCount >= 10}
        maxFiles={Math.max(0, 10 - totalCount)}
      />

      {/* Upload Progress (Live Mode) */}
      {uploading && Object.keys(uploadProgress).length > 0 && (
        <div className="space-y-2 p-3.5 rounded-xl bg-surface-container-low border border-primary/20 animate-pulse">
          <div className="flex items-center gap-2 text-xs font-medium text-primary mb-1">
            <span className="material-symbols-outlined text-sm animate-spin">sync</span>
            Đang tải lên máy chủ...
          </div>
          {Object.entries(uploadProgress).map(([name, pct]) => (
            <div key={name} className="text-xs text-on-surface-variant">
              <div className="flex justify-between mb-1">
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

      {/* Loading state for Live Mode */}
      {loading && (
        <div className="flex items-center justify-center py-8 text-outline text-sm gap-2">
          <span className="material-symbols-outlined animate-spin text-lg">progress_activity</span>
          Đang tải thư viện hình ảnh...
        </div>
      )}

      {/* Empty State */}
      {!loading && totalCount === 0 && (
        <div className="flex flex-col items-center justify-center py-8 px-4 gap-2 text-outline-variant rounded-2xl border-2 border-dashed border-outline-variant/30 bg-surface-container-lowest text-center">
          <span className="material-symbols-outlined text-5xl text-outline/60">add_photo_alternate</span>
          <p className="text-sm font-medium text-on-surface-variant">
            Chưa có hình ảnh nào được tải lên
          </p>
          <p className="text-xs text-outline max-w-sm">
            Kéo thả nhiều ảnh cùng lúc hoặc bấm vào khung phía trên để chọn file (JPG, PNG, WEBP tối đa 5MB).
          </p>
        </div>
      )}

      {/* Images Grid */}
      {totalCount > 0 && (
        <div className="space-y-2">
          <div className="flex items-center justify-between text-xs text-outline px-1">
            <span>Danh sách ảnh ({totalCount}):</span>
            <span className="text-[11px] italic">Kéo thả hoặc dùng nút mũi tên để đổi vị trí</span>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            {currentImages.map((img, index) => {
              const isPrimary = Boolean(img.is_primary)
              const imgSrc = img.image_url ?? img.image_path ?? PLACEHOLDER

              return (
                <div
                  key={img.id}
                  draggable
                  onDragStart={() => handleDragStart(index)}
                  onDragEnter={() => handleDragEnter(index)}
                  onDragEnd={handleDragEnd}
                  onDragOver={(e) => e.preventDefault()}
                  className={[
                    'relative group rounded-2xl overflow-hidden border transition-all duration-200 aspect-square bg-surface-container-low shadow-xs hover:shadow-md cursor-grab active:cursor-grabbing select-none',
                    isPrimary
                      ? 'border-primary ring-2 ring-primary/40'
                      : 'border-outline-variant/40 hover:border-primary/60',
                  ].join(' ')}
                >
                  <img
                    src={imgSrc}
                    alt={img.alt_text ?? `Ảnh sản phẩm ${index + 1}`}
                    className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    onError={(e) => {
                      e.target.onerror = null
                      e.target.src = PLACEHOLDER
                    }}
                  />

                  {/* Top-left: Sort order indicator */}
                  <span className="absolute top-2 left-2 bg-black/70 backdrop-blur-xs text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm z-10">
                    #{index + 1}
                  </span>

                  {/* Top-right: Primary Badge */}
                  {isPrimary && (
                    <span className="absolute top-2 right-2 bg-amber-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full shadow-md flex items-center gap-1 z-10">
                      <span className="material-symbols-outlined text-[12px] leading-none">star</span>
                      Ảnh chính
                    </span>
                  )}

                  {/* Hover action overlay */}
                  <div className="absolute inset-0 bg-black/60 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2 z-20">
                    {/* Top bar in overlay */}
                    <div className="flex justify-between items-center">
                      <span className="text-[10px] text-white/80 font-medium truncate max-w-[100px]">
                        {img.alt_text || `Ảnh #${index + 1}`}
                      </span>

                      {/* Delete button */}
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation()
                          requestDelete(img, index)
                        }}
                        title="Xóa ảnh này"
                        className="p-1.5 rounded-full bg-error/90 text-white hover:bg-error hover:scale-110 transition-all shadow"
                      >
                        <span className="material-symbols-outlined text-xs leading-none">delete</span>
                      </button>
                    </div>

                    {/* Middle: Set Primary button if not already primary */}
                    <div className="flex justify-center items-center py-1">
                      {!isPrimary ? (
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation()
                            handleSetPrimary(img, index)
                          }}
                          title="Đặt ảnh này làm ảnh đại diện chính"
                          className="px-2.5 py-1 rounded-full bg-amber-500 hover:bg-amber-400 text-white text-[11px] font-semibold flex items-center gap-1 shadow-md hover:scale-105 transition-all"
                        >
                          <span className="material-symbols-outlined text-xs leading-none">star</span>
                          Đặt làm ảnh chính
                        </button>
                      ) : (
                        <span className="text-[11px] text-amber-300 font-bold flex items-center gap-1 bg-black/40 px-2 py-0.5 rounded-full">
                          <span className="material-symbols-outlined text-xs">verified</span>
                          Đang là ảnh chính
                        </span>
                      )}
                    </div>

                    {/* Bottom: Reorder Arrows (Move Left / Move Right) */}
                    <div className="flex justify-between items-center pt-1 border-t border-white/20">
                      <button
                        type="button"
                        disabled={index === 0}
                        onClick={(e) => {
                          e.stopPropagation()
                          moveItem(index, index - 1)
                        }}
                        title="Di chuyển sang trái"
                        className="p-1 rounded-full bg-white/20 hover:bg-white/40 disabled:opacity-30 disabled:hover:bg-white/20 text-white transition-all"
                      >
                        <span className="material-symbols-outlined text-xs leading-none">arrow_back</span>
                      </button>

                      <span className="text-[10px] text-white/70">
                        {index + 1} / {totalCount}
                      </span>

                      <button
                        type="button"
                        disabled={index === totalCount - 1}
                        onClick={(e) => {
                          e.stopPropagation()
                          moveItem(index, index + 1)
                        }}
                        title="Di chuyển sang phải"
                        className="p-1 rounded-full bg-white/20 hover:bg-white/40 disabled:opacity-30 disabled:hover:bg-white/20 text-white transition-all"
                      >
                        <span className="material-symbols-outlined text-xs leading-none">arrow_forward</span>
                      </button>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal (Live mode only) */}
      {confirmDelete && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 animate-fade-in">
          <div className="bg-surface-container-high rounded-2xl p-6 max-w-sm w-full shadow-2xl border border-outline-variant/30 space-y-4">
            <div className="flex items-center gap-3 text-error">
              <span className="material-symbols-outlined text-3xl">warning</span>
              <h4 className="text-base font-bold text-on-surface">Xác nhận xóa ảnh?</h4>
            </div>
            <p className="text-sm text-on-surface-variant">
              Tệp ảnh này sẽ bị xóa hoàn toàn khỏi thư viện sản phẩm và bộ nhớ máy chủ. Thao tác không thể hoàn tác.
            </p>
            <div className="flex gap-2 justify-end pt-2">
              <button
                type="button"
                onClick={() => setConfirmDelete(null)}
                className="px-4 py-2 rounded-xl text-sm font-medium text-on-surface-variant hover:bg-surface-container transition-colors"
              >
                Hủy bỏ
              </button>
              <button
                type="button"
                onClick={handleConfirmLiveDelete}
                className="px-4 py-2 rounded-xl text-sm bg-error text-on-error hover:bg-error/90 font-semibold transition-colors shadow-sm"
              >
                Xác nhận xóa
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Toast Notification */}
      {toast && (
        <div
          className={[
            'fixed bottom-6 right-6 z-[300] flex items-center gap-2 px-4 py-3 rounded-xl shadow-xl text-sm font-semibold transition-all duration-300',
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
