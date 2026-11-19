// src/features/admin/banner/BannerForm.jsx
// Enterprise form component for creating and editing promotional banners in MobieX Admin.
// Features: Drag & Drop upload with preview, desktop & mobile images, real-time live preview.

import { useEffect, useState, useRef } from 'react'
import axiosClient from '../../../api/axiosClient'
import EP from '../../../api/endpoints'
import Input from '../../../components/common/Input'
import Button from '../../../components/common/Button'
import { resolveImageUrl } from '../../../utils/resolveImageUrl'

export default function BannerForm({
  initialData = null,
  isEdit = false,
  submitting = false,
  errors = {},
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    title: '',
    name: '',
    description: '',
    image_path: '',
    mobile_image_path: '',
    alt_text: '',
    cta_label: 'Mua ngay',
    target_url: '',
    position: 'home',
    sort_order: '0',
    start_at: '',
    end_at: '',
    status: 'published',
  })

  // Previews
  const [desktopPreview, setDesktopPreview] = useState('')
  const [mobilePreview, setMobilePreview] = useState('')

  // Uploading states
  const [uploadingDesktop, setUploadingDesktop] = useState(false)
  const [uploadingMobile, setUploadingMobile] = useState(false)
  const [desktopUploadProgress, setDesktopUploadProgress] = useState(0)
  const [mobileUploadProgress, setMobileUploadProgress] = useState(0)
  const [uploadError, setUploadError] = useState('')

  // Drag states
  const [desktopDragOver, setDesktopDragOver] = useState(false)
  const [mobileDragOver, setMobileDragOver] = useState(false)

  // Live preview device tab: 'desktop' | 'mobile'
  const [previewDevice, setPreviewDevice] = useState('desktop')

  const desktopInputRef = useRef(null)
  const mobileInputRef = useRef(null)

  useEffect(() => {
    if (initialData) {
      const formatDateForInput = (dt) => {
        if (!dt) return ''
        const d = new Date(dt)
        if (isNaN(d.getTime())) return ''
        return d.toISOString().slice(0, 16)
      }

      const imgPath = initialData.image_path || initialData.image_url || ''
      const mobPath = initialData.mobile_image_path || initialData.mobile_image_url || ''

      setForm({
        title: initialData.title || '',
        name: initialData.name || initialData.title || '',
        description: initialData.description || '',
        image_path: imgPath,
        mobile_image_path: mobPath,
        alt_text: initialData.alt_text || '',
        cta_label: initialData.cta_label || 'Mua ngay',
        target_url: initialData.target_url || initialData.link_url || '',
        position: initialData.position || 'home',
        sort_order: String(initialData.sort_order ?? 0),
        start_at: formatDateForInput(initialData.start_at),
        end_at: formatDateForInput(initialData.end_at),
        status: initialData.status || (initialData.is_active ? 'published' : 'inactive'),
      })

      if (imgPath) setDesktopPreview(resolveImageUrl(imgPath))
      if (mobPath) setMobilePreview(resolveImageUrl(mobPath))
    }
  }, [initialData])

  const handleFieldChange = (field, val) => {
    setForm((prev) => ({ ...prev, [field]: val }))
  }

  // Handle file upload
  const uploadFile = async (file, isMobile = false) => {
    setUploadError('')

    // Validate size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      setUploadError(`File "${file.name}" vượt quá 5MB. Vui lòng chọn file nhẹ hơn.`)
      return
    }

    // Validate mime
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'].includes(file.type)) {
      setUploadError(`Định dạng "${file.name}" không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP, SVG.`)
      return
    }

    const localBlobUrl = URL.createObjectURL(file)
    if (isMobile) {
      setMobilePreview(localBlobUrl)
      setUploadingMobile(true)
      setMobileUploadProgress(20)
    } else {
      setDesktopPreview(localBlobUrl)
      setUploadingDesktop(true)
      setDesktopUploadProgress(20)
    }

    try {
      const formData = new FormData()
      formData.append('image', file)

      const response = await axiosClient.post(EP.ADMIN_BANNER_UPLOAD, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
          if (isMobile) setMobileUploadProgress(percent)
          else setDesktopUploadProgress(percent)
        },
      })

      const path = response.data?.data?.path || response.data?.data?.url
      if (path) {
        if (isMobile) {
          handleFieldChange('mobile_image_path', path)
        } else {
          handleFieldChange('image_path', path)
        }
      }
    } catch (err) {
      setUploadError(err.message || 'Lỗi khi tải ảnh lên server.')
    } finally {
      if (isMobile) setUploadingMobile(false)
      else setUploadingDesktop(false)
    }
  }

  const handleFormSubmit = (e) => {
    e.preventDefault()

    const payload = {
      title: form.title.trim(),
      name: form.name.trim() || form.title.trim(),
      description: form.description?.trim() || null,
      image_path: form.image_path.trim(),
      mobile_image_path: form.mobile_image_path?.trim() || null,
      alt_text: form.alt_text?.trim() || null,
      cta_label: form.cta_label?.trim() || null,
      target_url: form.target_url?.trim() || null,
      position: form.position || 'home',
      sort_order: Number(form.sort_order) || 0,
      start_at: form.start_at ? new Date(form.start_at).toISOString() : null,
      end_at: form.end_at ? new Date(form.end_at).toISOString() : null,
      status: form.status,
      is_active: form.status !== 'inactive',
    }

    onSubmit(payload)
  }

  return (
    <form onSubmit={handleFormSubmit} className="space-y-8">
      {uploadError && (
        <div className="p-4 rounded-xl bg-error-container/20 border border-error/30 text-error flex items-center gap-3 text-sm">
          <span className="material-symbols-outlined text-xl shrink-0">error</span>
          <span>{uploadError}</span>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left Column: Form Inputs (2 cols) */}
        <div className="lg:col-span-2 space-y-6">
          {/* Card: Basic Information */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-5">
            <div className="flex items-center gap-2 pb-3 border-b border-outline-variant/10">
              <span className="material-symbols-outlined text-primary text-xl">info</span>
              <h2 className="text-base font-bold text-on-surface">Thông tin nội dung Banner</h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Tiêu đề hiển thị (Storefront) *"
                value={form.title}
                onChange={(e) => handleFieldChange('title', e.target.value)}
                placeholder="Ví dụ: iPhone 16 Pro Max — Titanium Đỉnh Cao"
                error={errors.title?.[0]}
                required
              />

              <Input
                label="Tên quản trị nội bộ"
                value={form.name}
                onChange={(e) => handleFieldChange('name', e.target.value)}
                placeholder="Ví dụ: Campaign Tết 2026 - Hero Banner 1"
                error={errors.name?.[0]}
              />
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Mô tả phụ / Slogan</label>
              <textarea
                rows={2}
                value={form.description}
                onChange={(e) => handleFieldChange('description', e.target.value)}
                placeholder="Nhập mô tả ngắn gọn hoặc chương trình khuyến mãi nổi bật..."
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
              />
              {errors.description?.[0] && (
                <p className="text-xs text-error">{errors.description[0]}</p>
              )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Nhãn nút bấm (CTA Label)"
                value={form.cta_label}
                onChange={(e) => handleFieldChange('cta_label', e.target.value)}
                placeholder="Ví dụ: Mua ngay, Khám phá ngay"
                error={errors.cta_label?.[0]}
              />

              <Input
                label="Liên kết điều hướng (Destination URL)"
                value={form.target_url}
                onChange={(e) => handleFieldChange('target_url', e.target.value)}
                placeholder="/products hoặc /products?category=1"
                error={errors.target_url?.[0]}
              />
            </div>

            <Input
              label="Văn bản thay thế (Alt text - Hỗ trợ SEO & Accessibility)"
              value={form.alt_text}
              onChange={(e) => handleFieldChange('alt_text', e.target.value)}
              placeholder="Mô tả hình ảnh cho công cụ tìm kiếm"
              error={errors.alt_text?.[0]}
            />
          </div>

          {/* Card: Media & Images */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-6">
            <div className="flex items-center gap-2 pb-3 border-b border-outline-variant/10">
              <span className="material-symbols-outlined text-primary text-xl">image</span>
              <h2 className="text-base font-bold text-on-surface">Quản lý hình ảnh Banner</h2>
            </div>

            {/* Desktop Image Dropzone */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-sm font-semibold text-on-surface flex items-center gap-1.5">
                  <span>Ảnh Desktop (Bắt buộc)</span>
                  <span className="text-error">*</span>
                </label>
                <span className="text-xs text-primary font-medium">Khuyến nghị: 1920 x 600 px (~16:5)</span>
              </div>

              <div
                role="button"
                tabIndex={0}
                onDragOver={(e) => { e.preventDefault(); setDesktopDragOver(true) }}
                onDragLeave={() => setDesktopDragOver(false)}
                onDrop={(e) => {
                  e.preventDefault()
                  setDesktopDragOver(false)
                  if (e.dataTransfer.files?.[0]) uploadFile(e.dataTransfer.files[0], false)
                }}
                onClick={() => desktopInputRef.current?.click()}
                className={[
                  'relative border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2',
                  desktopDragOver
                    ? 'border-primary bg-primary/5 scale-[1.01]'
                    : 'border-outline-variant/40 hover:border-primary/60 hover:bg-surface-container-high/40',
                ].join(' ')}
              >
                {desktopPreview ? (
                  <div className="relative w-full rounded-xl overflow-hidden max-h-48 bg-surface-container-low group">
                    <img
                      src={desktopPreview}
                      alt="Desktop Banner Preview"
                      className="w-full h-44 object-cover rounded-xl"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 text-white text-xs font-semibold">
                      <span className="material-symbols-outlined text-lg">edit</span>
                      Nhấn để thay thế ảnh Desktop
                    </div>
                  </div>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-4xl text-primary">cloud_upload</span>
                    <p className="text-sm text-on-surface font-semibold">
                      Kéo thả ảnh Desktop vào đây hoặc <span className="text-primary underline">chọn từ máy tính</span>
                    </p>
                    <p className="text-xs text-on-surface-variant">Hỗ trợ JPG, PNG, WEBP (Tối đa 5MB)</p>
                  </>
                )}

                {uploadingDesktop && (
                  <div className="w-full max-w-xs mt-2 space-y-1">
                    <div className="h-1.5 w-full bg-surface-container-high rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary transition-all duration-300"
                        style={{ width: `${desktopUploadProgress}%` }}
                      />
                    </div>
                    <span className="text-[11px] text-on-surface-variant">Đang tải lên {desktopUploadProgress}%</span>
                  </div>
                )}

                <input
                  ref={desktopInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/svg+xml"
                  className="hidden"
                  onChange={(e) => {
                    if (e.target.files?.[0]) uploadFile(e.target.files[0], false)
                  }}
                />
              </div>

              {/* Direct URL input fallback */}
              <details className="text-xs text-outline cursor-pointer pt-1">
                <summary className="hover:text-primary transition-colors">Hoặc nhập URL hình ảnh Desktop trực tiếp</summary>
                <div className="pt-2">
                  <Input
                    value={form.image_path}
                    onChange={(e) => {
                      handleFieldChange('image_path', e.target.value)
                      if (e.target.value) setDesktopPreview(resolveImageUrl(e.target.value))
                    }}
                    placeholder="https://images.unsplash.com/... hoặc /storage/banners/..."
                    error={errors.image_path?.[0]}
                  />
                </div>
              </details>
              {errors.image_path?.[0] && (
                <p className="text-xs text-error font-medium">{errors.image_path[0]}</p>
              )}
            </div>

            {/* Mobile Image Dropzone (Optional) */}
            <div className="space-y-2 pt-2 border-t border-outline-variant/10">
              <div className="flex items-center justify-between">
                <label className="text-sm font-semibold text-on-surface flex items-center gap-1.5">
                  <span>Ảnh Mobile (Tùy chọn)</span>
                </label>
                <span className="text-xs text-on-surface-variant">Khuyến nghị: 800 x 800 px (1:1)</span>
              </div>

              <div
                role="button"
                tabIndex={0}
                onDragOver={(e) => { e.preventDefault(); setMobileDragOver(true) }}
                onDragLeave={() => setMobileDragOver(false)}
                onDrop={(e) => {
                  e.preventDefault()
                  setMobileDragOver(false)
                  if (e.dataTransfer.files?.[0]) uploadFile(e.dataTransfer.files[0], true)
                }}
                onClick={() => mobileInputRef.current?.click()}
                className={[
                  'relative border-2 border-dashed rounded-2xl p-4 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2',
                  mobileDragOver
                    ? 'border-primary bg-primary/5 scale-[1.01]'
                    : 'border-outline-variant/40 hover:border-primary/60 hover:bg-surface-container-high/40',
                ].join(' ')}
              >
                {mobilePreview ? (
                  <div className="relative w-full rounded-xl overflow-hidden max-h-40 bg-surface-container-low group flex justify-center">
                    <img
                      src={mobilePreview}
                      alt="Mobile Banner Preview"
                      className="h-36 object-contain rounded-xl"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 text-white text-xs font-semibold">
                      <span className="material-symbols-outlined text-lg">edit</span>
                      Nhấn để thay thế ảnh Mobile
                    </div>
                  </div>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-3xl text-outline">phone_iphone</span>
                    <p className="text-xs text-on-surface font-medium">
                      Kéo thả ảnh tối ưu cho di động hoặc <span className="text-primary underline">chọn từ máy tính</span>
                    </p>
                    <p className="text-[11px] text-on-surface-variant">Nếu để trống, hệ thống tự động sử dụng ảnh Desktop</p>
                  </>
                )}

                {uploadingMobile && (
                  <div className="w-full max-w-xs mt-1 space-y-1">
                    <div className="h-1.5 w-full bg-surface-container-high rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary transition-all duration-300"
                        style={{ width: `${mobileUploadProgress}%` }}
                      />
                    </div>
                    <span className="text-[11px] text-on-surface-variant">Đang tải lên {mobileUploadProgress}%</span>
                  </div>
                )}

                <input
                  ref={mobileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/svg+xml"
                  className="hidden"
                  onChange={(e) => {
                    if (e.target.files?.[0]) uploadFile(e.target.files[0], true)
                  }}
                />
              </div>

              {mobilePreview && (
                <button
                  type="button"
                  onClick={() => {
                    setMobilePreview('')
                    handleFieldChange('mobile_image_path', '')
                  }}
                  className="text-xs text-error hover:underline flex items-center gap-1 mt-1 cursor-pointer"
                >
                  <span className="material-symbols-outlined text-sm">delete</span>
                  Gỡ ảnh Mobile (dùng ảnh Desktop)
                </button>
              )}
            </div>
          </div>
        </div>

        {/* Right Column: Settings, Display Lifecycle & Live Preview (1 col) */}
        <div className="space-y-6">
          {/* Card: Display Rules & Lifecycle */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 space-y-5">
            <div className="flex items-center gap-2 pb-3 border-b border-outline-variant/10">
              <span className="material-symbols-outlined text-primary text-xl">schedule</span>
              <h2 className="text-base font-bold text-on-surface">Vị trí & Lịch hiển thị</h2>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Vị trí xuất hiện</label>
              <select
                value={form.position}
                onChange={(e) => handleFieldChange('position', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="home">Trang chủ (Hero Slider)</option>
                <option value="catalog">Trang danh mục sản phẩm</option>
                <option value="sidebar">Thanh bên (Sidebar)</option>
                <option value="footer_promo">Banner chân trang</option>
              </select>
            </div>

            <Input
              label="Thứ tự ưu tiên (Sort Order)"
              type="number"
              value={form.sort_order}
              onChange={(e) => handleFieldChange('sort_order', e.target.value)}
              placeholder="0"
              error={errors.sort_order?.[0]}
            />

            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Thời gian bắt đầu hiển thị</label>
              <input
                type="datetime-local"
                value={form.start_at}
                onChange={(e) => handleFieldChange('start_at', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              />
              <p className="text-[11px] text-on-surface-variant">Để trống nếu muốn hiển thị ngay lập tức</p>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Thời gian kết thúc hiển thị</label>
              <input
                type="datetime-local"
                value={form.end_at}
                onChange={(e) => handleFieldChange('end_at', e.target.value)}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              />
              <p className="text-[11px] text-on-surface-variant">Để trống nếu không giới hạn thời gian (vĩnh viễn)</p>
            </div>

            {/* Status Radio */}
            <div className="pt-2 border-t border-outline-variant/10 space-y-2">
              <label className="text-sm font-semibold text-on-surface block">Trạng thái xuất bản</label>
              <div className="grid grid-cols-3 gap-2">
                {[
                  { id: 'published', label: 'Xuất bản', icon: 'check_circle', color: 'text-success' },
                  { id: 'draft',     label: 'Bản nháp', icon: 'edit_note',    color: 'text-warning' },
                  { id: 'inactive',  label: 'Tạm dừng', icon: 'pause_circle', color: 'text-outline' },
                ].map((st) => (
                  <button
                    key={st.id}
                    type="button"
                    onClick={() => handleFieldChange('status', st.id)}
                    className={[
                      'flex flex-col items-center gap-1 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer',
                      form.status === st.id
                        ? 'border-primary bg-primary/10 text-primary shadow-xs'
                        : 'border-outline-variant/30 hover:bg-surface-container-high text-on-surface-variant',
                    ].join(' ')}
                  >
                    <span className={`material-symbols-outlined text-lg ${st.color}`}>{st.icon}</span>
                    <span>{st.label}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Card: Live Storefront Preview Box */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-5 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-lg">preview</span>
                <span className="text-sm font-bold text-on-surface">Xem trước hiển thị</span>
              </div>
              <div className="flex items-center gap-1 bg-surface-container-low p-1 rounded-lg border border-outline-variant/20">
                <button
                  type="button"
                  onClick={() => setPreviewDevice('desktop')}
                  className={`px-2 py-0.5 rounded text-xs font-semibold cursor-pointer transition-all ${
                    previewDevice === 'desktop' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
                  }`}
                >
                  Desktop
                </button>
                <button
                  type="button"
                  onClick={() => setPreviewDevice('mobile')}
                  className={`px-2 py-0.5 rounded text-xs font-semibold cursor-pointer transition-all ${
                    previewDevice === 'mobile' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'
                  }`}
                >
                  Mobile
                </button>
              </div>
            </div>

            {/* Preview Box Container */}
            <div className="relative rounded-xl overflow-hidden bg-surface-container-lowest border border-outline-variant/30 flex items-center justify-center min-h-[160px]">
              {desktopPreview ? (
                <div className="relative w-full h-full min-h-[160px]">
                  <img
                    src={previewDevice === 'mobile' && mobilePreview ? mobilePreview : desktopPreview}
                    alt={form.title || 'Preview'}
                    className="w-full h-44 object-cover"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-4 flex flex-col justify-end text-white">
                    <span className="text-[10px] uppercase font-bold tracking-wider text-primary-container mb-0.5">
                      {form.position === 'home' ? 'Hero Banner' : form.position}
                    </span>
                    <h3 className="text-sm font-extrabold line-clamp-1">{form.title || 'Tiêu đề banner'}</h3>
                    {form.description && (
                      <p className="text-xs text-white/80 line-clamp-1">{form.description}</p>
                    )}
                    {form.cta_label && (
                      <div className="mt-2">
                        <span className="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-primary text-on-primary text-[11px] font-bold">
                          {form.cta_label}
                          <span className="material-symbols-outlined text-xs">arrow_forward</span>
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div className="p-6 text-center text-on-surface-variant space-y-1">
                  <span className="material-symbols-outlined text-3xl text-outline">image_not_supported</span>
                  <p className="text-xs">Chưa có ảnh để xem trước</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Sticky Bottom Action Bar */}
      <div className="sticky bottom-4 z-20 bg-surface-container-high/95 backdrop-blur-md rounded-2xl p-4 border border-outline-variant/30 shadow-lg flex items-center justify-between gap-4">
        <Button type="button" variant="ghost" onClick={onCancel} disabled={submitting}>
          <span className="material-symbols-outlined text-base">arrow_back</span>
          Hủy bỏ
        </Button>

        <div className="flex items-center gap-3">
          <Button type="submit" variant="primary" loading={submitting}>
            <span className="material-symbols-outlined text-base">check</span>
            {isEdit ? 'Lưu thay đổi Banner' : 'Tạo mới & Xuất bản'}
          </Button>
        </div>
      </div>
    </form>
  )
}
