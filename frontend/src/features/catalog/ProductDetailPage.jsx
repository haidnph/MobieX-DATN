// src/features/catalog/ProductDetailPage.jsx
// Detailed product page with variant selector, stock display, gallery, and approved reviews.

import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDate } from '../../utils/formatDate'
import Button from '../../components/common/Button'
import Badge from '../../components/common/Badge'
import Spinner from '../../components/common/Spinner'

export default function ProductDetailPage() {
  const { id } = useParams()
  const { addToCart } = useCart()
  const { showToast } = useToast()

  const [product, setProduct] = useState(null)
  const [selectedVariant, setSelectedVariant] = useState(null)
  const [activeImage, setActiveImage] = useState(null)
  const [quantity, setQuantity] = useState(1)
  const [reviews, setReviews] = useState([])
  const [loading, setLoading] = useState(true)
  const [addingToCart, setAddingToCart] = useState(false)

  useEffect(() => {
    setLoading(true)
    Promise.allSettled([
      axiosClient.get(EP.PRODUCT_DETAIL(id)),
      axiosClient.get(EP.PRODUCT_REVIEWS(id)),
    ])
      .then(([prodRes, revRes]) => {
        if (prodRes.status === 'fulfilled') {
          const prodData = prodRes.value.data.data ?? prodRes.value.data
          setProduct(prodData)
          // Default to first available or active variant
          const firstVariant =
            prodData.variants?.find((v) => (v.is_active ?? true) && ((v.inventory?.available_quantity ?? v.available_quantity ?? 0) > 0)) ||
            prodData.variants?.[0] ||
            null
          setSelectedVariant(firstVariant)
          const defaultImg =
            prodData.thumbnail ||
            prodData.images?.find((img) => img.is_primary)?.image_path ||
            prodData.images?.[0]?.image_path ||
            firstVariant?.image_url ||
            null
          setActiveImage(defaultImg)
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

  const handleVariantSelect = (variant) => {
    setSelectedVariant(variant)
    setQuantity(1)
    if (variant.image_url) {
      setActiveImage(variant.image_url)
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
              />
            ) : (
              <span className="material-symbols-outlined text-8xl text-outline">smartphone</span>
            )}
            {isOutOfStock && (
              <div className="absolute inset-0 bg-background/70 backdrop-blur-xs flex items-center justify-center">
                <Badge variant="error" className="text-sm px-4 py-1">Tạm hết hàng</Badge>
              </div>
            )}
          </div>

          {/* Thumbnails */}
          {(() => {
            const gallery = (product.images && product.images.length > 0)
              ? product.images.map((img) => img.image_path || img.image_url).filter(Boolean)
              : [product.thumbnail].filter(Boolean)

            if (gallery.length <= 1) return null

            return (
              <div className="flex gap-2 overflow-x-auto pb-2">
                {gallery.map((imgSrc, idx) => (
                  <button
                    key={idx}
                    onClick={() => setActiveImage(imgSrc)}
                    className={[
                      'w-16 h-16 rounded-xl border-2 overflow-hidden shrink-0 transition-all bg-surface-container flex items-center justify-center p-1',
                      activeImage === imgSrc
                        ? 'border-primary ring-2 ring-primary/30'
                        : 'border-outline-variant/30 hover:border-outline',
                    ].join(' ')}
                  >
                    <img src={imgSrc} alt={`${product.name} ${idx + 1}`} className="w-full h-full object-contain" />
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
    </div>
  )
}
