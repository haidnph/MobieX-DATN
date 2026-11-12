import { memo } from 'react'
import { Link } from 'react-router-dom'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useCompare } from '../../contexts/CompareContext'
import { useWishlist } from '../../contexts/WishlistContext'

const FALLBACK_SVG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2UyZThmMCIvPjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk0YTNiOCI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'

/**
 * Standardized ProductCard component across all client catalog views.
 *
 * Handles:
 * - Active / Inactive (Ngừng kinh doanh)
 * - Out of Stock (Tạm hết hàng)
 * - Low Stock (Chỉ còn X chiếc)
 * - Sale Discount (-X%)
 * - Wishlist & Compare toggles
 * - Long title line-clamping & image fallback
 */
function ProductCard({ product }) {
  const { toggleCompare, isInCompare } = useCompare()
  const { toggleWishlist, isInWishlist } = useWishlist()

  if (!product) return null

  const isFavorited = isInWishlist(product.id)
  const isCompared = isInCompare(product.id)

  const thumbnail =
    product.images?.find((img) => img.is_primary)?.image_url ||
    product.images?.find((img) => img.is_primary)?.image_path ||
    product.primary_image_url ||
    product.thumbnail ||
    product.images?.[0]?.image_url ||
    product.images?.[0]?.image_path ||
    product.images?.[0]?.url ||
    null

  const price =
    product.min_price && Number(product.min_price) > 0
      ? Number(product.min_price)
      : product.variants?.length
        ? Math.min(...product.variants.map((v) => Number(v.price) || 0))
        : Number(product.base_price) || 0

  const originalPrice =
    product.max_price && Number(product.max_price) > 0
      ? Number(product.max_price)
      : product.variants?.length
        ? Math.max(...product.variants.map((v) => Number(v.compare_price) || 0))
        : null

  const discountPercent =
    originalPrice && originalPrice > price
      ? Math.round(((originalPrice - price) / originalPrice) * 100)
      : (product.discount_percent || 0)

  const totalStock = product.variants?.length
    ? product.variants.reduce(
        (sum, v) => sum + (v.inventory?.available_quantity ?? v.available_quantity ?? v.stock_quantity ?? 0),
        0
      )
    : (product.stock_quantity ?? product.total_stock ?? 0)

  const isDiscontinued = product.status !== 'active'
  const isOutOfStock = !isDiscontinued && product.variants?.length > 0 && totalStock <= 0
  const isLowStock = !isDiscontinued && !isOutOfStock && totalStock > 0 && totalStock <= 3

  return (
    <Link
      to={`/products/${product.id}`}
      aria-label={`Chi tiết sản phẩm ${product.name}`}
      className={`group flex flex-col bg-surface-container rounded-2xl overflow-hidden border border-outline-variant/15 hover:border-primary/40 hover:shadow-[0_8px_30px_rgba(77,142,255,0.12)] transition-all duration-300 focus-visible:outline-2 focus-visible:outline-primary ${
        isDiscontinued ? 'opacity-70 grayscale-50' : isOutOfStock ? 'opacity-85' : ''
      }`}
    >
      <div className="relative h-48 bg-surface-container-high/60 overflow-hidden flex items-center justify-center">
        {/* Discontinued / Out of Stock Overlay Badge */}
        {isDiscontinued ? (
          <div className="absolute inset-0 bg-background/70 backdrop-blur-[1px] flex items-center justify-center z-20 pointer-events-none">
            <span className="bg-slate-800 text-slate-200 border border-slate-600 text-[11px] font-bold px-3 py-1 rounded-full shadow-lg flex items-center gap-1">
              <span className="material-symbols-outlined text-xs">do_not_disturb_on</span>
              Ngừng kinh doanh
            </span>
          </div>
        ) : isOutOfStock ? (
          <div className="absolute inset-0 bg-background/60 backdrop-blur-[1px] flex items-center justify-center z-20 pointer-events-none">
            <span className="bg-error text-white text-[11px] font-bold px-3 py-1 rounded-full shadow-lg flex items-center gap-1">
              <span className="material-symbols-outlined text-xs">block</span>
              Tạm hết hàng
            </span>
          </div>
        ) : null}

        {/* Thumbnail Image */}
        {thumbnail ? (
          <img
            src={thumbnail}
            alt={product.name}
            loading="lazy"
            className="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500"
            onError={(e) => {
              e.target.onerror = null
              e.target.src = FALLBACK_SVG
            }}
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-outline">
            <span className="material-symbols-outlined text-6xl">smartphone</span>
          </div>
        )}

        {/* Top Badges */}
        <div className="absolute top-2.5 left-2.5 flex flex-col gap-1 z-10 pointer-events-none">
          {discountPercent > 0 && !isOutOfStock && !isDiscontinued && (
            <span className="bg-error text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md shadow-xs">
              -{discountPercent}%
            </span>
          )}
          {isLowStock && (
            <span className="bg-amber-500/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-md shadow-xs flex items-center gap-0.5">
              <span className="material-symbols-outlined text-[10px]">timer</span>
              Còn {totalStock} máy
            </span>
          )}
        </div>

        {/* Quick Action Buttons (Wishlist & Compare) */}
        <div className="absolute top-2.5 right-2.5 flex flex-col gap-1.5 z-20 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
          <button
            type="button"
            title={isFavorited ? 'Bỏ yêu thích' : 'Thêm vào yêu thích'}
            aria-label={isFavorited ? 'Bỏ yêu thích' : 'Thêm vào yêu thích'}
            onClick={(e) => {
              e.preventDefault()
              e.stopPropagation()
              toggleWishlist(product)
            }}
            className={`w-8 h-8 rounded-full flex items-center justify-center transition-transform hover:scale-110 shadow-md ${
              isFavorited
                ? 'bg-error text-white'
                : 'bg-surface-container/90 text-on-surface-variant hover:text-error hover:bg-surface-container-highest'
            }`}
          >
            <span
              className="material-symbols-outlined text-base"
              style={{ fontVariationSettings: isFavorited ? "'FILL' 1" : "'FILL' 0" }}
            >
              favorite
            </span>
          </button>

          <button
            type="button"
            title={isCompared ? 'Bỏ so sánh' : 'Thêm vào so sánh'}
            aria-label={isCompared ? 'Bỏ so sánh' : 'Thêm vào so sánh'}
            onClick={(e) => {
              e.preventDefault()
              e.stopPropagation()
              toggleCompare(product)
            }}
            className={`w-8 h-8 rounded-full flex items-center justify-center transition-transform hover:scale-110 shadow-md ${
              isCompared
                ? 'bg-primary text-on-primary'
                : 'bg-surface-container/90 text-on-surface-variant hover:text-primary hover:bg-surface-container-highest'
            }`}
          >
            <span className="material-symbols-outlined text-base">balance</span>
          </button>
        </div>
      </div>

      {/* Product Details */}
      <div className="p-4 flex flex-col flex-1 justify-between gap-3">
        <div>
          {product.brand?.name && (
            <span className="text-[11px] font-bold text-primary uppercase tracking-wider block mb-1">
              {product.brand.name}
            </span>
          )}
          <h3
            className="text-sm font-semibold text-on-surface line-clamp-2 leading-snug group-hover:text-primary transition-colors"
            title={product.name}
          >
            {product.name}
          </h3>
        </div>

        <div className="pt-2 border-t border-outline-variant/10 flex items-baseline justify-between gap-2">
          <div className="flex flex-col">
            <span className="text-base font-extrabold text-primary">
              {formatCurrencyVND(price)}
            </span>
            {originalPrice && originalPrice > price && (
              <span className="text-[11px] text-outline line-through">
                {formatCurrencyVND(originalPrice)}
              </span>
            )}
          </div>
          {product.variants?.length > 1 && (
            <span className="text-[10px] text-on-surface-variant bg-surface-container-high px-1.5 py-0.5 rounded text-nowrap">
              {product.variants.length} bản
            </span>
          )}
        </div>
      </div>
    </Link>
  )
}

export default memo(ProductCard)
