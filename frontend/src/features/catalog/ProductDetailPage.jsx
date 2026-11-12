// src/features/catalog/ProductDetailPage.jsx
// Detailed product page with variant selector, stock display, gallery, and approved reviews.

import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useCart } from '../../contexts/CartContext'
import { useCompare } from '../../contexts/CompareContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDate } from '../../utils/formatDate'
import Button from '../../components/common/Button'
import Badge from '../../components/common/Badge'
import Spinner from '../../components/common/Spinner'
import { useRecentlyViewed } from '../../hooks/useRecentlyViewed'

export default function ProductDetailPage() {
  const { id } = useParams()
  const { addToCart } = useCart()
  const { toggleCompare, isInCompare } = useCompare()
  const { toggleWishlist, isInWishlist } = useWishlist()
  const { showToast } = useToast()
  const { addViewed } = useRecentlyViewed()

  const [product, setProduct] = useState(null)
  const [selectedVariant, setSelectedVariant] = useState(null)
  const [activeImage, setActiveImage] = useState(null)
  const [productImages, setProductImages] = useState([])
  const [quantity, setQuantity] = useState(1)
  const [reviews, setReviews] = useState([])
  const [loading, setLoading] = useState(true)
  const [addingToCart, setAddingToCart] = useState(false)
  const [relatedProducts, setRelatedProducts] = useState([])

  useEffect(() => {
    setLoading(true)
    Promise.allSettled([
      axiosClient.get(EP.PRODUCT_DETAIL(id)),
      axiosClient.get(EP.PRODUCT_REVIEWS(id)),
      axiosClient.get(EP.PRODUCT_IMAGES, { params: { product_id: id } }),
    ])
      .then(([prodRes, revRes, imgRes]) => {
        let imgs = []
        if (imgRes.status === 'fulfilled') {
          const raw = imgRes.value.data?.data ?? imgRes.value.data
          if (Array.isArray(raw)) {
            imgs = [...raw].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
          }
        }

        if (prodRes.status === 'fulfilled') {
          const prodData = prodRes.value.data.data ?? prodRes.value.data
          setProduct(prodData)
          addViewed(prodData)

          if (imgs.length === 0 && prodData.images?.length > 0) {
            imgs = [...prodData.images].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
          }
          setProductImages(imgs)

          // Default to first available or active variant
          const firstVariant =
            prodData.variants?.find((v) => (v.is_active ?? true) && ((v.inventory?.available_quantity ?? v.available_quantity ?? 0) > 0)) ||
            prodData.variants?.[0] ||
            null
          setSelectedVariant(firstVariant)

          const primaryImg =
            imgs.find((img) => img.is_primary)?.image_url ||
            imgs.find((img) => img.is_primary)?.image_path ||
            imgs[0]?.image_url ||
            imgs[0]?.image_path ||
            prodData.thumbnail ||
            firstVariant?.image_url ||
            null
          setActiveImage(primaryImg)

          // Fetch related products by category
          if (prodData.category_id) {
            axiosClient
              .get(EP.PRODUCTS, { params: { category_id: prodData.category_id, per_page: 5 } })
              .then(({ data }) => {
                const list = data.data?.data ?? data.data ?? data ?? []
                const items = Array.isArray(list) ? list : []
                setRelatedProducts(items.filter((p) => p.id !== prodData.id).slice(0, 4))
              })
              .catch(() => {})
          }
        }
        if (revRes.status === 'fulfilled') {
          const revData = revRes.value.data.data ?? revRes.value.data
          setReviews(Array.isArray(revData) ? revData : (revData?.data ?? []))
        }
      })
      .finally(() => setLoading(false))
  }, [id])

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" />
      </div>
    )
  }

  if (!product) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4">
        <span className="material-symbols-outlined text-6xl text-outline">search_off</span>
        <h2 className="text-xl font-bold text-on-surface">Không tìm thấy sản phẩm</h2>
        <Link to="/products" className="text-primary hover:underline">
          Quay lại danh sách sản phẩm
        </Link>
      </div>
    )
  }

  const variants = product.variants ?? []
  const hasNoVariants = variants.length === 0
  const availableStock = selectedVariant?.inventory?.available_quantity ?? selectedVariant?.available_quantity ?? 0
  const currentPrice = selectedVariant?.price ?? product.min_price ?? product.base_price ?? 0
  const originalPrice = selectedVariant?.compare_price ?? selectedVariant?.original_price ?? product.max_price ?? null
  const isOutOfStock = hasNoVariants || availableStock <= 0 || product.status !== 'active'

  const handleVariantSelect = async (variant) => {
    setSelectedVariant(variant)
    setQuantity(1)

    if (variant?.id && product?.id) {
      try {
        const { data } = await axiosClient.get(EP.PRODUCT_IMAGES, {
          params: { product_id: product.id, product_variant_id: variant.id },
        })
        const variantImages = (data.data ?? []).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        if (variantImages.length > 0) {
          setProductImages(variantImages)
          const primary = variantImages.find((img) => img.is_primary) || variantImages[0]
          setActiveImage(primary.image_url ?? primary.image_path)
          return
        }
      } catch {
        // Fallback to variant image_url or general product images
      }
    }

    if (variant?.image_url) {
      setActiveImage(variant.image_url)
    } else if (product?.id) {
      try {
        const { data } = await axiosClient.get(EP.PRODUCT_IMAGES, {
          params: { product_id: product.id },
        })
        const generalImages = (data.data ?? []).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        if (generalImages.length > 0) {
          setProductImages(generalImages)
          const primary = generalImages.find((img) => img.is_primary) || generalImages[0]
          setActiveImage(primary.image_url ?? primary.image_path)
        }
      } catch {}
    }
  }

  const handleAddToCart = async () => {
    if (hasNoVariants || !selectedVariant) {
      showToast('Sản phẩm hiện chưa có phiên bản kinh doanh', 'warning')
      return
    }
    if (availableStock <= 0) {
      showToast('Sản phẩm tạm thời hết hàng', 'error')
      return
    }

    setAddingToCart(true)
    try {
      await addToCart(selectedVariant.id, quantity)
      showToast('Đã thêm sản phẩm vào giỏ hàng thành công!', 'success')
    } catch (err) {
      showToast(err.message || 'Không thể thêm vào giỏ hàng', 'error')
    } finally {
      setAddingToCart(false)
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8 space-y-12">
      {/* Breadcrumbs */}
      <nav className="flex items-center gap-2 text-sm text-on-surface-variant">
        <Link to="/" className="hover:text-on-surface transition-colors">Trang chủ</Link>
        <span>/</span>
        <Link to="/products" className="hover:text-on-surface transition-colors">Sản phẩm</Link>
        <span>/</span>
        <span className="text-on-surface truncate max-w-xs">{product.name}</span>
      </nav>

      {/* Main product view */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Left: Gallery (5 cols) */}
        <div className="lg:col-span-5 space-y-4">
          <div className="relative aspect-square bg-surface-container rounded-2xl overflow-hidden border border-outline-variant/20 flex items-center justify-center p-6">
            {activeImage ? (
              <img
                src={activeImage}
                alt={product.name}
                className="w-full h-full object-contain transition-all duration-300"
                onError={(e) => {
                  e.target.onerror = null
                  e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'
                }}
              />
            ) : (
              <span className="material-symbols-outlined text-8xl text-outline">smartphone</span>
            )}
            {isOutOfStock && (
              <div className="absolute inset-0 bg-background/70 backdrop-blur-xs flex items-center justify-center z-20">
                <span className="bg-error text-white text-sm font-bold px-4 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
                  <span className="material-symbols-outlined text-base">block</span>
                  Tạm hết hàng
                </span>
              </div>
            )}
          </div>

          {/* Thumbnails */}
          {(() => {
            const gallery = (productImages && productImages.length > 0)
              ? productImages.map((img) => img.image_url || img.image_path).filter(Boolean)
              : [product.thumbnail, activeImage].filter(Boolean)

            const uniqueGallery = [...new Set(gallery)]

            if (uniqueGallery.length <= 1) return null

            return (
              <div className="flex gap-2 overflow-x-auto pb-2">
                {uniqueGallery.map((imgSrc, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => setActiveImage(imgSrc)}
                    className={[
                      'w-16 h-16 rounded-xl border-2 overflow-hidden shrink-0 transition-all bg-surface-container flex items-center justify-center p-1',
                      activeImage === imgSrc
                        ? 'border-primary ring-2 ring-primary/30 scale-105'
                        : 'border-outline-variant/30 hover:border-outline',
                    ].join(' ')}
                  >
                    <img
                      src={imgSrc}
                      alt={`${product.name} ${idx + 1}`}
                      className="w-full h-full object-contain"
                      onError={(e) => {
                        e.target.onerror = null
                        e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'
                      }}
                    />
                  </button>
                ))}
              </div>
            )
          })()}
        </div>

        {/* Right: Details (7 cols) */}
        <div className="lg:col-span-7 space-y-6">
          <div>
            {product.brand && (
              <span className="text-sm font-semibold text-primary uppercase tracking-wider">
                {product.brand.name}
              </span>
            )}
            <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface mt-1 leading-snug">
              {product.name}
            </h1>
            <div className="flex items-center gap-4 mt-2">
              <span className="text-xs text-on-surface-variant">
                SKU: <span className="font-mono text-on-surface">{selectedVariant?.sku || product.sku || '—'}</span>
              </span>
              <span className="text-xs text-outline">•</span>
              <span className="text-xs">
                Kho: {hasNoVariants ? (
                  <span className="text-warning font-medium">Đang cập nhật</span>
                ) : isOutOfStock ? (
                  <span className="text-error font-medium">Hết hàng</span>
                ) : (
                  <span className="text-secondary font-medium">Còn {availableStock} sản phẩm</span>
                )}
              </span>
            </div>
          </div>

          {hasNoVariants && (
            <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3.5 flex items-center gap-3 text-amber-500">
              <span className="material-symbols-outlined text-xl">info</span>
              <p className="text-sm font-medium">Sản phẩm đang được cập nhật phiên bản kinh doanh. Vui lòng quay lại sau.</p>
            </div>
          )}

          {/* Price */}
          <div className="bg-surface-container-low rounded-xl p-4 flex items-baseline gap-3">
            <span className="text-3xl font-extrabold text-primary">
              {formatCurrencyVND(currentPrice)}
            </span>
            {originalPrice && originalPrice > currentPrice && (
              <span className="text-sm text-outline line-through">
                {formatCurrencyVND(originalPrice)}
              </span>
            )}
          </div>

          {/* Variants Selector */}
          {variants.length > 0 && (
            <div className="space-y-3">
              <label className="text-sm font-medium text-on-surface-variant block">
                Chọn phiên bản:
              </label>
              <div className="flex flex-wrap gap-2">
                {variants.map((v) => {
                  const isSelected = selectedVariant?.id === v.id
                  const vStock = v.inventory?.available_quantity ?? v.available_quantity ?? 0
                  return (
                    <button
                      key={v.id}
                      onClick={() => handleVariantSelect(v)}
                      className={[
                        'px-4 py-2.5 rounded-xl text-sm font-medium border transition-all text-left flex flex-col gap-0.5',
                        isSelected
                          ? 'border-primary bg-primary/10 text-primary shadow-[0_0_15px_rgba(77,142,255,0.2)]'
                          : 'border-outline-variant/30 bg-surface-container text-on-surface-variant hover:border-outline hover:text-on-surface',
                        vStock <= 0 ? 'opacity-50' : '',
                      ].join(' ')}
                    >
                      <div className="font-semibold flex items-center gap-2">
                        <span>
                          {v.name || (v.option_signature
                            ? v.option_signature
                                .split('|')
                                .map((part) => part.split(':')[1] || part)
                                .join(' • ')
                                .toUpperCase()
                            : `Phiên bản #${v.id}`)}
                        </span>
                        {vStock <= 0 ? (
                          <span className="text-[10px] px-1.5 py-0.2 rounded bg-error/20 text-error">Hết hàng</span>
                        ) : (
                          <span className="text-[10px] px-1.5 py-0.2 rounded bg-secondary/20 text-secondary">Còn {vStock}</span>
                        )}
                      </div>
                      <div className="text-xs text-outline">{formatCurrencyVND(v.price)}</div>
                    </button>
                  )
                })}
              </div>
            </div>
          )}

          {/* Quantity & Actions */}
          <div className="space-y-4 pt-2">
            <div className="flex items-center gap-4">
              <label className="text-sm font-medium text-on-surface-variant">Số lượng:</label>
              <div className="flex items-center border border-outline-variant/40 rounded-xl overflow-hidden bg-surface-container-low">
                <button
                  type="button"
                  onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                  disabled={quantity <= 1 || isOutOfStock || hasNoVariants}
                  className="px-3 py-2 text-on-surface hover:bg-surface-container-high transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  <span className="material-symbols-outlined text-base">remove</span>
                </button>
                <span className="px-4 py-2 text-sm font-semibold text-on-surface min-w-[2.5rem] text-center">
                  {quantity}
                </span>
                <button
                  type="button"
                  onClick={() => setQuantity((q) => Math.min(availableStock || 1, q + 1))}
                  disabled={quantity >= availableStock || isOutOfStock || hasNoVariants}
                  className="px-3 py-2 text-on-surface hover:bg-surface-container-high transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  <span className="material-symbols-outlined text-base">add</span>
                </button>
              </div>
            </div>

            <div className="flex gap-3">
              <Button
                variant="primary"
                size="lg"
                className="flex-1"
                disabled={isOutOfStock || hasNoVariants || addingToCart}
                loading={addingToCart}
                onClick={handleAddToCart}
              >
                <span className="material-symbols-outlined text-xl">shopping_cart</span>
                {hasNoVariants ? 'Chưa có phiên bản' : isOutOfStock ? 'Tạm hết hàng' : 'Thêm vào giỏ hàng'}
              </Button>

              <Button
                variant={isInCompare(product.id) ? 'outline' : 'secondary'}
                size="lg"
                type="button"
                className={`px-4 rounded-xl ${isInCompare(product.id) ? 'border-primary text-primary bg-primary/10' : ''}`}
                title={isInCompare(product.id) ? 'Đã thêm vào so sánh (bấm để bỏ)' : 'Thêm vào so sánh cấu hình'}
                onClick={() => toggleCompare(product)}
              >
                <span className="material-symbols-outlined text-xl">compare_arrows</span>
                <span className="hidden sm:inline">
                  {isInCompare(product.id) ? 'Đang so sánh' : 'So sánh'}
                </span>
              </Button>

              <Button
                variant={isInWishlist(product.id) ? 'outline' : 'secondary'}
                size="lg"
                type="button"
                className={`px-4 rounded-xl ${isInWishlist(product.id) ? 'border-error text-error bg-error/10' : ''}`}
                title={isInWishlist(product.id) ? 'Đã lưu yêu thích (bấm để bỏ)' : 'Lưu vào yêu thích'}
                onClick={() => toggleWishlist(product)}
              >
                <span className="material-symbols-outlined text-xl">
                  {isInWishlist(product.id) ? 'favorite' : 'favorite_border'}
                </span>
              </Button>
            </div>
          </div>

          {/* Highlights / Specs */}
          {product.description && (
            <div className="pt-4 border-t border-outline-variant/20">
              <h3 className="text-sm font-semibold text-on-surface mb-2">Mô tả sản phẩm</h3>
              <div
                className="text-sm text-on-surface-variant leading-relaxed space-y-2 whitespace-pre-line"
                dangerouslySetInnerHTML={{ __html: product.description }}
              />
            </div>
          )}
        </div>
      </div>

      {/* Specifications Section */}
      {product.specifications && product.specifications.length > 0 && (
        <section className="pt-8 border-t border-outline-variant/20">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-3">
              <span className="w-1 h-6 bg-primary rounded-full" />
              <h2 className="text-xl font-bold text-on-surface">Thông số kỹ thuật</h2>
            </div>
            <Link
              to="/compare"
              className="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
            >
              <span>So sánh cấu hình với máy khác</span>
              <span className="material-symbols-outlined text-sm">arrow_forward</span>
            </Link>
          </div>

          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
            <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-outline-variant/15">
              {/* Left col */}
              <div className="divide-y divide-outline-variant/10">
                {product.specifications
                  .slice(0, Math.ceil(product.specifications.length / 2))
                  .map((spec) => (
                    <div key={spec.id} className="p-4 flex items-center justify-between gap-4">
                      <span className="text-xs md:text-sm font-medium text-on-surface-variant">
                        {spec.definition?.name || spec.name || 'Thông số'}
                      </span>
                      <span className="text-xs md:text-sm font-semibold text-on-surface text-right">
                        {spec.numeric_value !== null && spec.numeric_value !== undefined
                          ? `${Number(spec.numeric_value)} ${spec.definition?.unit || ''}`
                          : spec.value_boolean !== null && spec.value_boolean !== undefined
                          ? (spec.value_boolean ? 'Có' : 'Không')
                          : spec.value_text || '—'}
                      </span>
                    </div>
                  ))}
              </div>

              {/* Right col */}
              <div className="divide-y divide-outline-variant/10">
                {product.specifications
                  .slice(Math.ceil(product.specifications.length / 2))
                  .map((spec) => (
                    <div key={spec.id} className="p-4 flex items-center justify-between gap-4">
                      <span className="text-xs md:text-sm font-medium text-on-surface-variant">
                        {spec.definition?.name || spec.name || 'Thông số'}
                      </span>
                      <span className="text-xs md:text-sm font-semibold text-on-surface text-right">
                        {spec.numeric_value !== null && spec.numeric_value !== undefined
                          ? `${Number(spec.numeric_value)} ${spec.definition?.unit || ''}`
                          : spec.value_boolean !== null && spec.value_boolean !== undefined
                          ? (spec.value_boolean ? 'Có' : 'Không')
                          : spec.value_text || '—'}
                      </span>
                    </div>
                  ))}
              </div>
            </div>
          </div>
        </section>
      )}

      {/* Reviews Section */}
      <section className="pt-8 border-t border-outline-variant/20">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <span className="w-1 h-6 bg-secondary rounded-full" />
            <h2 className="text-xl font-bold text-on-surface">Đánh giá từ khách hàng ({reviews.length})</h2>
          </div>
        </div>

        {reviews.length === 0 ? (
          <div className="bg-surface-container rounded-2xl p-8 text-center text-on-surface-variant">
            <span className="material-symbols-outlined text-5xl text-outline mb-2">rate_review</span>
            <p className="text-sm">Chưa có đánh giá nào cho sản phẩm này.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {reviews.map((rev) => (
              <div
                key={rev.id}
                className="bg-surface-container rounded-2xl p-5 border border-outline-variant/10 space-y-3"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="material-symbols-outlined text-2xl text-primary">account_circle</span>
                    <div>
                      <p className="text-sm font-semibold text-on-surface">{rev.user?.name || 'Khách hàng'}</p>
                      <p className="text-xs text-outline">{formatDate(rev.created_at)}</p>
                    </div>
                  </div>
                  <div className="flex text-secondary text-sm">
                    {Array.from({ length: 5 }).map((_, i) => (
                      <span key={i} className="material-symbols-outlined text-sm">
                        {i < rev.rating ? 'star' : 'star_outline'}
                      </span>
                    ))}
                  </div>
                </div>

                {rev.title && (
                  <h4 className="text-sm font-bold text-on-surface">{rev.title}</h4>
                )}
                <p className="text-sm text-on-surface-variant leading-relaxed">{rev.content}</p>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Related Products Section */}
      {relatedProducts.length > 0 && (
        <section className="pt-8 border-t border-outline-variant/20">
          <div className="flex items-center gap-3 mb-6">
            <span className="w-1 h-6 bg-primary rounded-full" />
            <h2 className="text-xl font-bold text-on-surface">Sản phẩm liên quan</h2>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {relatedProducts.map((p) => {
              const thumb =
                p.images?.find((img) => img.is_primary)?.image_url ||
                p.images?.[0]?.image_url ||
                p.thumbnail ||
                null
              const price =
                p.min_price && Number(p.min_price) > 0
                  ? Number(p.min_price)
                  : Number(p.base_price) || 0
              return (
                <Link
                  key={p.id}
                  to={`/products/${p.id}`}
                  className="group bg-surface-container rounded-xl overflow-hidden border border-outline-variant/10 hover:border-primary/30 hover:shadow-[0_0_30px_rgba(77,142,255,0.15)] transition-all duration-300"
                >
                  <div className="relative h-40 bg-surface-container-high overflow-hidden">
                    {thumb ? (
                      <img
                        src={thumb}
                        alt={p.name}
                        className="w-full h-full object-contain p-3 group-hover:scale-105 transition-transform duration-500"
                        onError={(e) => {
                          e.target.onerror = null
                          e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'
                        }}
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center">
                        <span className="material-symbols-outlined text-5xl text-outline">smartphone</span>
                      </div>
                    )}
                  </div>
                  <div className="p-3">
                    <p className="text-xs text-primary font-medium mb-0.5">{p.brand?.name}</p>
                    <h3 className="text-sm font-semibold text-on-surface leading-snug line-clamp-2 mb-1.5 group-hover:text-primary transition-colors">
                      {p.name}
                    </h3>
                    <span className="text-sm font-extrabold text-on-surface">
                      {formatCurrencyVND(price)}
                    </span>
                  </div>
                </Link>
              )
            })}
          </div>
        </section>
      )}
    </div>
  )
}
