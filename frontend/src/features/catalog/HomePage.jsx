// src/features/catalog/HomePage.jsx
// Landing page: hero banner slider, featured categories, flash sale products.

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'

// ── Skeleton ─────────────────────────────────────────────────────────────────
function SkeletonCard() {
  return (
    <div className="bg-surface-container rounded-xl p-4 animate-pulse">
      <div className="bg-surface-container-high rounded-lg h-48 mb-4" />
      <div className="bg-surface-container-high rounded h-4 w-3/4 mb-2" />
      <div className="bg-surface-container-high rounded h-4 w-1/2" />
    </div>
  )
}

// ── Banner Slider ──────────────────────────────────────────────────────────────
function HeroBanner({ banners }) {
  const [current, setCurrent] = useState(0)

  useEffect(() => {
    if (banners.length <= 1) return
    const id = setInterval(() => setCurrent((c) => (c + 1) % banners.length), 4000)
    return () => clearInterval(id)
  }, [banners.length])

  if (banners.length === 0) {
    // Default fallback banner
    return (
      <div className="relative h-72 md:h-96 bg-gradient-to-br from-surface-container via-surface-container-high to-primary-container/20 rounded-2xl overflow-hidden flex items-center px-8 md:px-16">
        <div className="relative z-10 max-w-lg">
          <div className="inline-flex items-center gap-2 bg-secondary-container/20 px-3 py-1 rounded-full mb-4">
            <span className="w-2 h-2 rounded-full bg-secondary animate-pulse" />
            <span className="text-secondary text-xs font-bold tracking-widest uppercase">Flash Sale</span>
          </div>
          <h1 className="text-4xl md:text-5xl font-extrabold text-on-surface leading-none tracking-tight mb-4">
            Mobile<span className="text-primary">X</span>
          </h1>
          <p className="text-on-surface-variant text-base mb-6">
            Điện thoại chính hãng, giá tốt nhất thị trường
          </p>
          <Link
            to="/products"
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-semibold hover:bg-primary hover:text-on-primary transition-all"
          >
            Mua ngay
            <span className="material-symbols-outlined text-lg">arrow_forward</span>
          </Link>
        </div>
        {/* Decorative glow */}
        <div className="absolute -right-16 -top-16 w-96 h-96 rounded-full bg-primary-container/15 blur-3xl pointer-events-none" />
      </div>
    )
  }

  const banner = banners[current]
  return (
    <div className="relative h-72 md:h-96 rounded-2xl overflow-hidden bg-surface-container">
      {banner.image_url ? (
        <img
          src={banner.image_url}
          alt={banner.title}
          className="w-full h-full object-cover transition-opacity duration-500"
        />
      ) : (
        <div className="w-full h-full bg-gradient-to-br from-surface-container to-primary-container/20" />
      )}
      <div className="absolute inset-0 bg-gradient-to-r from-background/80 to-transparent" />
      <div className="absolute inset-0 flex items-center px-8 md:px-16">
        <div className="max-w-md">
          <h2 className="text-3xl md:text-4xl font-extrabold text-on-surface mb-3">{banner.title}</h2>
          {banner.subtitle && (
            <p className="text-on-surface-variant mb-5">{banner.subtitle}</p>
          )}
          {banner.link_url && (
            <a
              href={banner.link_url}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container rounded-xl font-semibold text-sm hover:bg-primary hover:text-on-primary transition-all"
            >
              Xem ngay
              <span className="material-symbols-outlined text-base">arrow_forward</span>
            </a>
          )}
        </div>
      </div>
      {/* Dots */}
      {banners.length > 1 && (
        <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
          {banners.map((_, i) => (
            <button
              key={i}
              onClick={() => setCurrent(i)}
              className={[
                'rounded-full transition-all',
                i === current ? 'w-8 h-2 bg-primary' : 'w-2 h-2 bg-surface-container-highest hover:bg-outline',
              ].join(' ')}
              aria-label={`Banner ${i + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  )
}

// ── Product Card ──────────────────────────────────────────────────────────────
function ProductCard({ product }) {
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

  return (
    <Link
      to={`/products/${product.id}`}
      className="group bg-surface-container rounded-xl overflow-hidden border border-outline-variant/10 hover:border-primary/30 hover:shadow-[0_0_30px_rgba(77,142,255,0.15)] transition-all duration-300"
    >
      <div className="relative h-48 bg-surface-container-high overflow-hidden">
        {thumbnail ? (
          <img
            src={thumbnail}
            alt={product.name}
            className="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500"
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
        {discountPercent > 0 && (
          <span className="absolute top-2 left-2 bg-secondary-container text-on-secondary-container text-[10px] font-bold px-2 py-0.5 rounded-full">
            -{discountPercent}%
          </span>
        )}
      </div>
      <div className="p-4">
        <p className="text-xs text-primary font-medium mb-1">{product.brand?.name}</p>
        <h3 className="text-sm font-semibold text-on-surface leading-snug line-clamp-2 mb-2 group-hover:text-primary transition-colors">
          {product.name}
        </h3>
        <div className="flex items-baseline gap-2">
          <span className="text-base font-extrabold text-on-surface">
            {formatCurrencyVND(price)}
          </span>
          {originalPrice && originalPrice > price && (
            <span className="text-xs text-outline line-through">
              {formatCurrencyVND(originalPrice)}
            </span>
          )}
        </div>
      </div>
    </Link>
  )
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function HomePage() {
  const [banners, setBanners] = useState([])
  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchAll = async () => {
      setLoading(true)
      try {
        const [bannersRes, productsRes, categoriesRes] = await Promise.allSettled([
          axiosClient.get(EP.BANNERS),
          axiosClient.get(EP.PRODUCTS, { params: { per_page: 8 } }),
          axiosClient.get(EP.CATEGORIES),
        ])

        if (bannersRes.status === 'fulfilled') {
          setBanners(bannersRes.value.data.data ?? bannersRes.value.data ?? [])
        }
        if (productsRes.status === 'fulfilled') {
          const pd = productsRes.value.data
          setProducts(pd.data?.data ?? pd.data ?? pd ?? [])
        }
        if (categoriesRes.status === 'fulfilled') {
          setCategories(categoriesRes.value.data.data ?? categoriesRes.value.data ?? [])
        }
      } finally {
        setLoading(false)
      }
    }
    fetchAll()
  }, [])

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-6 space-y-12">
      {/* Hero Banner */}
      <section>
        <HeroBanner banners={banners} />
      </section>

      {/* Categories */}
      {categories.length > 0 && (
        <section>
          <h2 className="text-xl font-bold text-on-surface mb-5">Danh mục sản phẩm</h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                to={`/products?category_id=${cat.id}`}
                className="flex flex-col items-center gap-2 p-4 bg-surface-container rounded-xl border border-outline-variant/10 hover:border-primary/30 hover:bg-surface-container-high transition-all group"
              >
                <span className="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">
                  {cat.slug?.includes('phone') ? 'smartphone' : 'devices_other'}
                </span>
                <span className="text-xs text-on-surface-variant text-center leading-tight">
                  {cat.name}
                </span>
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* Featured Products */}
      <section>
        <div className="flex items-center justify-between mb-5">
          <div className="flex items-center gap-3">
            <span className="w-1 h-6 bg-primary rounded-full" />
            <h2 className="text-xl font-bold text-on-surface">Sản phẩm nổi bật</h2>
          </div>
          <Link
            to="/products"
            className="text-sm text-primary hover:text-primary-fixed-dim font-medium flex items-center gap-1 transition-colors"
          >
            Xem tất cả
            <span className="material-symbols-outlined text-base">arrow_forward</span>
          </Link>
        </div>

        {loading ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {Array.from({ length: 8 }).map((_, i) => <SkeletonCard key={i} />)}
          </div>
        ) : products.length > 0 ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {products.map((p) => <ProductCard key={p.id} product={p} />)}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center py-16 gap-3 text-on-surface-variant">
            <span className="material-symbols-outlined text-5xl">inventory_2</span>
            <p className="text-sm">Chưa có sản phẩm nào.</p>
          </div>
        )}
      </section>

      {/* CTA Banner */}
      <section className="bg-gradient-to-r from-primary-container/20 to-surface-container rounded-2xl p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h2 className="text-2xl font-bold text-on-surface mb-2">Thanh toán nhanh với VNPay</h2>
          <p className="text-on-surface-variant text-sm">
            Hỗ trợ ví điện tử, thẻ ATM, thẻ tín dụng. Giao hàng toàn quốc.
          </p>
        </div>
        <Link
          to="/products"
          className="shrink-0 inline-flex items-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-semibold hover:bg-primary hover:text-on-primary transition-all"
        >
          Mua sắm ngay
          <span className="material-symbols-outlined text-lg">shopping_bag</span>
        </Link>
      </section>
    </div>
  )
}
