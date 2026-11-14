// src/components/product/ProductDetailGallery.jsx
// Enterprise Product Detail Gallery with Desktop Hover Zoom Lens,
// Interactive Thumbnail Carousel, and Fullscreen Lightbox Modal (with Keyboard & Touch Swipe support).

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

const PLACEHOLDER_SVG =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'

/**
 * @param {Object} props
 * @param {Array}  [props.images]            - Array of image objects { id, image_url, image_path, is_primary, alt_text }
 * @param {string} [props.fallbackThumbnail] - Single thumbnail fallback (from product.thumbnail)
 * @param {string} [props.productName]       - Product title for alt tags
 * @param {boolean} [props.isDiscontinued]   - Product inactive badge overlay
 * @param {boolean} [props.isOutOfStock]     - Product out-of-stock badge overlay
 */
export default function ProductDetailGallery({
  images = [],
  fallbackThumbnail = null,
  productName = 'Sản phẩm',
  isDiscontinued = false,
  isOutOfStock = false,
}) {
  // Normalize gallery list into unique string URLs
  const galleryList = useMemo(() => {
    let list = []
    if (images && images.length > 0) {
      // Sort by sort_order
      const sorted = [...images].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
      list = sorted
        .map((img) => img.image_url || img.image_path)
        .filter(Boolean)
    }

    if (list.length === 0 && fallbackThumbnail) {
      list = [fallbackThumbnail]
    }

    return [...new Set(list)]
  }, [images, fallbackThumbnail])

  // Active index
  const [currentIndex, setCurrentIndex] = useState(0)

  // Sync index if active image is not found or list changes
  useEffect(() => {
    if (currentIndex >= galleryList.length) {
      setCurrentIndex(0)
    }
  }, [galleryList, currentIndex])

  const activeImage = galleryList[currentIndex] || fallbackThumbnail || null

  // ── Desktop Hover Zoom Lens ───────────────────────────────────────────────
  const [isZooming, setIsZooming] = useState(false)
  const [zoomCoords, setZoomCoords] = useState({ x: 50, y: 50 })
  const imageContainerRef = useRef(null)

  const handleMouseMove = (e) => {
    if (!imageContainerRef.current) return
    const rect = imageContainerRef.current.getBoundingClientRect()
    const x = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100))
    const y = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100))
    setZoomCoords({ x, y })
  }

  // ── Lightbox Modal ────────────────────────────────────────────────────────
  const [lightboxOpen, setLightboxOpen] = useState(false)
  const [lightboxIndex, setLightboxIndex] = useState(0)

  const openLightbox = (index = currentIndex) => {
    setLightboxIndex(index)
    setLightboxOpen(true)
  }

  const closeLightbox = useCallback(() => {
    setLightboxOpen(false)
  }, [])

  const nextImage = useCallback(() => {
    setLightboxIndex((prev) => (prev + 1) % Math.max(1, galleryList.length))
  }, [galleryList.length])

  const prevImage = useCallback(() => {
    setLightboxIndex((prev) => (prev - 1 + galleryList.length) % Math.max(1, galleryList.length))
  }, [galleryList.length])

  // Keyboard navigation for Lightbox
  useEffect(() => {
    if (!lightboxOpen) return

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') closeLightbox()
      else if (e.key === 'ArrowRight') nextImage()
      else if (e.key === 'ArrowLeft') prevImage()
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [lightboxOpen, closeLightbox, nextImage, prevImage])

  // Mobile Touch Swipe support
  const touchStartRef = useRef(0)
  const touchEndRef = useRef(0)

  const handleTouchStart = (e) => {
    touchStartRef.current = e.targetTouches[0].clientX
  }

  const handleTouchMove = (e) => {
    touchEndRef.current = e.targetTouches[0].clientX
  }

  const handleTouchEnd = () => {
    const delta = touchStartRef.current - touchEndRef.current
    if (Math.abs(delta) > 50) {
      if (delta > 0) nextImage() // swipe left -> next
      else prevImage() // swipe right -> prev
    }
    touchStartRef.current = 0
    touchEndRef.current = 0
  }

  // ── Thumbnail strip scrolling ─────────────────────────────────────────────
  const thumbStripRef = useRef(null)

  const scrollThumbs = (direction) => {
    if (!thumbStripRef.current) return
    const scrollAmount = direction === 'left' ? -200 : 200
    thumbStripRef.current.scrollBy({ left: scrollAmount, behavior: 'smooth' })
  }

  return (
    <div className="space-y-4 select-none">
      {/* ── Main Hero Image ──────────────────────────────────────────────── */}
      <div
        ref={imageContainerRef}
        onMouseEnter={() => setIsZooming(true)}
        onMouseLeave={() => setIsZooming(false)}
        onMouseMove={handleMouseMove}
        onClick={() => openLightbox(currentIndex)}
        className="relative aspect-square bg-surface-container rounded-2xl overflow-hidden border border-outline-variant/20 flex items-center justify-center p-6 cursor-zoom-in group shadow-xs hover:shadow-md transition-shadow"
      >
        {activeImage ? (
          <img
            src={activeImage}
            alt={productName}
            fetchPriority="high"
            style={{
              transformOrigin: `${zoomCoords.x}% ${zoomCoords.y}%`,
              transform: isZooming ? 'scale(2.2)' : 'scale(1)',
              transition: isZooming ? 'none' : 'transform 0.3s ease-out',
            }}
            className="w-full h-full object-contain pointer-events-none"
            onError={(e) => {
              e.target.onerror = null
              e.target.src = PLACEHOLDER_SVG
            }}
          />
        ) : (
          <div className="flex flex-col items-center justify-center text-outline gap-2">
            <span className="material-symbols-outlined text-7xl">smartphone</span>
            <span className="text-xs font-medium">Chưa có ảnh hiển thị</span>
          </div>
        )}

        {/* Hover zoom guide hint badge */}
        {activeImage && (
          <div className="absolute top-3 left-3 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 text-white text-[11px] font-medium px-2.5 py-1 rounded-full backdrop-blur-xs flex items-center gap-1.5 pointer-events-none">
            <span className="material-symbols-outlined text-xs">zoom_in</span>
            Rê chuột để soi chi tiết · Bấm để phóng to
          </div>
        )}

        {/* Fullscreen Button */}
        {activeImage && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              openLightbox(currentIndex)
            }}
            title="Xem ảnh toàn màn hình"
            className="absolute top-3 right-3 w-9 h-9 rounded-full bg-surface-container-high/90 hover:bg-surface-bright text-on-surface shadow-md flex items-center justify-center transition-all opacity-80 group-hover:opacity-100 hover:scale-110"
          >
            <span className="material-symbols-outlined text-base">fullscreen</span>
          </button>
        )}

        {/* Product Status Overlays */}
        {isDiscontinued ? (
          <div className="absolute inset-0 bg-background/80 backdrop-blur-xs flex items-center justify-center z-20 pointer-events-none">
            <span className="bg-slate-800 text-slate-200 border border-slate-600 text-sm font-bold px-4 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
              <span className="material-symbols-outlined text-base">do_not_disturb_on</span>
              Ngừng kinh doanh
            </span>
          </div>
        ) : isOutOfStock ? (
          <div className="absolute inset-0 bg-background/70 backdrop-blur-xs flex items-center justify-center z-20 pointer-events-none">
            <span className="bg-error text-white text-sm font-bold px-4 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
              <span className="material-symbols-outlined text-base">block</span>
              Tạm hết hàng
            </span>
          </div>
        ) : null}
      </div>

      {/* ── Thumbnails Strip ────────────────────────────────────────────── */}
      {galleryList.length > 1 && (
        <div className="relative group/thumbs">
          {/* Scroll Prev Button */}
          {galleryList.length > 4 && (
            <button
              type="button"
              onClick={() => scrollThumbs('left')}
              className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-2 z-10 w-7 h-7 rounded-full bg-surface-container-high/95 text-on-surface shadow-md flex items-center justify-center opacity-0 group-hover/thumbs:opacity-100 transition-opacity hover:scale-110 border border-outline-variant/30"
              title="Cuộn sang trái"
            >
              <span className="material-symbols-outlined text-sm leading-none">chevron_left</span>
            </button>
          )}

          <div
            ref={thumbStripRef}
            className="flex gap-2.5 overflow-x-auto pb-1 scrollbar-none scroll-smooth px-0.5"
          >
            {galleryList.map((imgSrc, idx) => {
              const isActive = idx === currentIndex

              return (
                <button
                  key={idx}
                  type="button"
                  onClick={() => setCurrentIndex(idx)}
                  onMouseEnter={() => setCurrentIndex(idx)}
                  className={[
                    'w-16 h-16 sm:w-18 sm:h-18 rounded-xl border-2 overflow-hidden shrink-0 transition-all bg-surface-container flex items-center justify-center p-1 cursor-pointer',
                    isActive
                      ? 'border-primary ring-2 ring-primary/40 scale-105 shadow-sm'
                      : 'border-outline-variant/30 hover:border-outline opacity-75 hover:opacity-100',
                  ].join(' ')}
                >
                  <img
                    src={imgSrc}
                    alt={`${productName} góc ${idx + 1}`}
                    loading="lazy"
                    className="w-full h-full object-contain pointer-events-none"
                    onError={(e) => {
                      e.target.onerror = null
                      e.target.src = PLACEHOLDER_SVG
                    }}
                  />
                </button>
              )
            })}
          </div>

          {/* Scroll Next Button */}
          {galleryList.length > 4 && (
            <button
              type="button"
              onClick={() => scrollThumbs('right')}
              className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-2 z-10 w-7 h-7 rounded-full bg-surface-container-high/95 text-on-surface shadow-md flex items-center justify-center opacity-0 group-hover/thumbs:opacity-100 transition-opacity hover:scale-110 border border-outline-variant/30"
              title="Cuộn sang phải"
            >
              <span className="material-symbols-outlined text-sm leading-none">chevron_right</span>
            </button>
          )}
        </div>
      )}

      {/* ── Fullscreen Lightbox Modal ────────────────────────────────────── */}
      {lightboxOpen && galleryList.length > 0 && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Xem ảnh sản phẩm toàn màn hình"
          onClick={closeLightbox}
          className="fixed inset-0 z-[999] bg-black/92 backdrop-blur-md flex flex-col justify-between p-4 sm:p-6 animate-fade-in"
        >
          {/* Header */}
          <div
            onClick={(e) => e.stopPropagation()}
            className="flex items-center justify-between text-white/90 z-20 max-w-6xl mx-auto w-full"
          >
            <div className="flex items-center gap-3">
              <span className="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full border border-white/20">
                {lightboxIndex + 1} / {galleryList.length}
              </span>
              <span className="text-sm font-medium truncate max-w-md hidden sm:inline">
                {productName}
              </span>
            </div>

            <div className="flex items-center gap-2">
              <span className="text-[11px] text-white/50 hidden md:inline">
                Phím: ESC để thoát · Mũi tên Trái/Phải để chuyển ảnh
              </span>
              <button
                type="button"
                onClick={closeLightbox}
                title="Đóng (ESC)"
                className="w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 text-white flex items-center justify-center transition-all hover:scale-110"
              >
                <span className="material-symbols-outlined text-xl">close</span>
              </button>
            </div>
          </div>

          {/* Main Lightbox Viewport */}
          <div
            onClick={(e) => e.stopPropagation()}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
            className="relative flex-1 flex items-center justify-center my-2 max-w-5xl mx-auto w-full select-none"
          >
            {/* Prev Button */}
            {galleryList.length > 1 && (
              <button
                type="button"
                onClick={prevImage}
                title="Ảnh trước (Mũi tên trái)"
                className="absolute left-2 sm:left-4 z-20 w-12 h-12 rounded-full bg-white/15 hover:bg-white/30 text-white flex items-center justify-center transition-all hover:scale-110 backdrop-blur-xs border border-white/20 shadow-xl"
              >
                <span className="material-symbols-outlined text-2xl">chevron_left</span>
              </button>
            )}

            {/* Display Image */}
            <div className="max-h-[75vh] max-w-full flex items-center justify-center p-2">
              <img
                src={galleryList[lightboxIndex]}
                alt={`${productName} góc ${lightboxIndex + 1}`}
                className="max-h-[75vh] max-w-full object-contain rounded-xl shadow-2xl transition-all duration-300"
                onError={(e) => {
                  e.target.onerror = null
                  e.target.src = PLACEHOLDER_SVG
                }}
              />
            </div>

            {/* Next Button */}
            {galleryList.length > 1 && (
              <button
                type="button"
                onClick={nextImage}
                title="Ảnh tiếp theo (Mũi tên phải)"
                className="absolute right-2 sm:right-4 z-20 w-12 h-12 rounded-full bg-white/15 hover:bg-white/30 text-white flex items-center justify-center transition-all hover:scale-110 backdrop-blur-xs border border-white/20 shadow-xl"
              >
                <span className="material-symbols-outlined text-2xl">chevron_right</span>
              </button>
            )}
          </div>

          {/* Footer: Bottom Thumbnail Bar in Lightbox */}
          {galleryList.length > 1 && (
            <div
              onClick={(e) => e.stopPropagation()}
              className="flex justify-center gap-2 overflow-x-auto py-2 z-20 max-w-3xl mx-auto w-full"
            >
              {galleryList.map((imgSrc, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => setLightboxIndex(idx)}
                  className={[
                    'w-12 h-12 rounded-lg border-2 overflow-hidden shrink-0 transition-all bg-white/5 p-0.5',
                    idx === lightboxIndex
                      ? 'border-primary ring-2 ring-primary scale-110 shadow-lg'
                      : 'border-white/20 hover:border-white/60 opacity-60 hover:opacity-100',
                  ].join(' ')}
                >
                  <img
                    src={imgSrc}
                    alt={`Thumbnail ${idx + 1}`}
                    className="w-full h-full object-contain pointer-events-none"
                    onError={(e) => {
                      e.target.onerror = null
                      e.target.src = PLACEHOLDER_SVG
                    }}
                  />
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
