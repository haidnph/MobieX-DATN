// src/features/catalog/HomePage.jsx
// MobileX Premium Storefront Homepage
// Fully synchronized with Admin Homepage CMS (Dynamic Sections, Layouts, Products, Categories, Banners & Brands).

import { useEffect, useState, useRef } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { resolveImageUrl } from '../../utils/resolveImageUrl'
import Spinner from '../../components/common/Spinner'
import ProductCard from '../../components/common/ProductCard'
import { useRecentlyViewed } from '../../hooks/useRecentlyViewed'
import FlashSaleSection from './FlashSaleSection'

// ── Skeletons ────────────────────────────────────────────────────────
function SkeletonCard() {
  return (
    <div className="bg-surface-container rounded-xl p-4 animate-pulse">
      <div className="bg-surface-container-high rounded-lg h-48 mb-4" />
      <div className="bg-surface-container-high rounded h-4 w-3/4 mb-2" />
      <div className="bg-surface-container-high rounded h-4 w-1/2" />
    </div>
  )
}

function PageLoadingSkeleton() {
  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-6 space-y-12 animate-pulse">
      <div className="h-72 md:h-96 rounded-2xl bg-surface-container" />
      <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="h-24 rounded-xl bg-surface-container" />
        ))}
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <SkeletonCard key={i} />
        ))}
      </div>
    </div>
  )
}

// ── 1. Hero Banner Carousel Section ──────────────────────────────────
function HeroBannerCarousel({ banners = [], autoplayInterval = 4000 }) {
  const [current, setCurrent] = useState(0)
  const [isHovered, setIsHovered] = useState(false)

  useEffect(() => {
    if (banners.length <= 1 || isHovered) return
    const id = setInterval(() => setCurrent((c) => (c + 1) % banners.length), autoplayInterval)
    return () => clearInterval(id)
  }, [banners.length, isHovered, autoplayInterval])

  if (!banners || banners.length === 0) {
    return (
      <div className="relative h-72 md:h-96 bg-gradient-to-br from-surface-container via-surface-container-high to-primary-container/20 rounded-2xl overflow-hidden flex items-center px-8 md:px-16">
        <div className="relative z-10 max-w-lg">
          <div className="inline-flex items-center gap-2 bg-secondary-container/20 px-3 py-1 rounded-full mb-4">
            <span className="w-2 h-2 rounded-full bg-secondary animate-pulse" />
            <span className="text-secondary text-xs font-bold tracking-widest uppercase">Flagship Store</span>
          </div>
          <h1 className="text-4xl md:text-5xl font-extrabold text-on-surface leading-none tracking-tight mb-4">
            Mobile<span className="text-primary">X</span>
          </h1>
          <p className="text-on-surface-variant text-base mb-6">
            Hệ sinh thái điện thoại và phụ kiện công nghệ chính hãng hàng đầu
          </p>
          <Link
            to="/products"
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-semibold hover:bg-primary-dark transition-all active:scale-95 shadow-md"
          >
            Khám phá ngay
            <span className="material-symbols-outlined text-lg">arrow_forward</span>
          </Link>
        </div>
      </div>
    )
  }

  const banner = banners[current]
  const desktopImg = resolveImageUrl(banner.image_url || banner.image_path)
  const mobileImg = resolveImageUrl(banner.mobile_image_url || banner.mobile_image_path || desktopImg)
  const ctaLink = banner.target_url || banner.link_url || '/products'
  const ctaText = banner.cta_label || 'Khám phá ngay'

  return (
    <div
      className="relative h-72 md:h-96 rounded-2xl overflow-hidden bg-surface-container shadow-sm group select-none"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {/* Responsive Picture: Desktop vs Mobile */}
      <picture className="w-full h-full block">
        {mobileImg && mobileImg !== desktopImg && (
          <source media="(max-width: 640px)" srcSet={mobileImg} />
        )}
        <img
          src={desktopImg}
          alt={banner.alt_text || banner.title || 'Banner'}
          className="w-full h-full object-cover transition-opacity duration-700"
        />
      </picture>

      {/* Dark overlay gradient for contrast */}
      <div className="absolute inset-0 bg-gradient-to-r from-background/90 via-background/40 to-transparent" />

      {/* Content */}
      <div className="absolute inset-0 flex items-center px-8 md:px-16 z-10">
        <div className="max-w-lg">
          <div className="inline-flex items-center gap-2 bg-primary/20 backdrop-blur-md px-3 py-1 rounded-full mb-3 border border-primary/30">
            <span className="w-2 h-2 rounded-full bg-primary animate-pulse" />
            <span className="text-primary text-[11px] font-extrabold tracking-widest uppercase">
              {banner.position === 'home' ? 'Ưu đãi đặc biệt' : banner.position}
            </span>
          </div>

          <h2 className="text-2xl sm:text-3xl md:text-4xl font-black text-on-surface leading-tight mb-2 tracking-tight">
            {banner.title}
          </h2>

          {banner.description && (
            <p className="text-on-surface-variant text-sm md:text-base mb-6 line-clamp-2 max-w-md">
              {banner.description}
            </p>
          )}

          <Link
            to={ctaLink}
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-bold hover:bg-primary-dark transition-all active:scale-95 shadow-md"
          >
            {ctaText}
            <span className="material-symbols-outlined text-lg">arrow_forward</span>
          </Link>
        </div>
      </div>

      {/* Navigation Arrows */}
      {banners.length > 1 && (
        <>
          <button
            type="button"
            onClick={() => setCurrent((c) => (c - 1 + banners.length) % banners.length)}
            className="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-background/60 hover:bg-background text-on-surface flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm cursor-pointer border border-outline-variant/30 shadow-md"
            aria-label="Banner trước"
          >
            <span className="material-symbols-outlined text-lg">chevron_left</span>
          </button>
          <button
            type="button"
            onClick={() => setCurrent((c) => (c + 1) % banners.length)}
            className="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-background/60 hover:bg-background text-on-surface flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm cursor-pointer border border-outline-variant/30 shadow-md"
            aria-label="Banner tiếp theo"
          >
            <span className="material-symbols-outlined text-lg">chevron_right</span>
          </button>

          {/* Dots Indicator */}
          <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2">
            {banners.map((_, idx) => (
              <button
                key={idx}
                type="button"
                onClick={() => setCurrent(idx)}
                className={`transition-all rounded-full cursor-pointer ${
                  idx === current ? 'w-8 h-2 bg-primary' : 'w-2 h-2 bg-on-surface/40 hover:bg-on-surface/70'
                }`}
                aria-label={`Chuyển tới banner ${idx + 1}`}
              />
            ))}
          </div>
        </>
      )}
    </div>
  )
}

// ── 2. Category Discovery Section ────────────────────────────────────
function CategoryDiscoverySection({ section }) {
  const categories = section.items || []
  if (categories.length === 0) return null

  return (
    <section className="space-y-4">
      <div>
        <h2 className="text-xl font-bold text-on-surface">{section.title}</h2>
        {section.subtitle && (
          <p className="text-xs text-on-surface-variant mt-0.5">{section.subtitle}</p>
        )}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        {categories.map((cat) => {
          const imgUrl = resolveImageUrl(cat.image_path || cat.image)
          return (
            <Link
              key={cat.id}
              to={`/products?category_id=${cat.id}`}
              className="flex flex-col items-center gap-2 p-4 bg-surface-container rounded-2xl border border-outline-variant/15 hover:border-primary/40 hover:bg-surface-container-high transition-all group shadow-xs"
            >
              <div className="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center overflow-hidden group-hover:scale-110 transition-transform">
                {imgUrl ? (
                  <img src={imgUrl} alt={cat.name} className="w-full h-full object-contain p-1" />
                ) : (
                  <span className="material-symbols-outlined text-2xl text-primary">
                    {cat.slug?.includes('phone') ? 'smartphone' : 'devices_other'}
                  </span>
                )}
              </div>
              <span className="text-xs font-semibold text-on-surface text-center leading-tight line-clamp-1 group-hover:text-primary transition-colors">
                {cat.name}
              </span>
            </Link>
          )
        })}
      </div>
    </section>
  )
}

// ── 3. Products Section (Supports Grid & Carousel) ────────────────────
function ProductsSection({ section }) {
  const products = section.items || []
  const scrollRef = useRef(null)

  if (products.length === 0) return null

  const isCarousel = section.layout_type === 'carousel'

  const scrollLeft = () => {
    if (scrollRef.current) {
      scrollRef.current.scrollBy({ left: -320, behavior: 'smooth' })
    }
  }

  const scrollRight = () => {
    if (scrollRef.current) {
      scrollRef.current.scrollBy({ left: 320, behavior: 'smooth' })
    }
  }

  return (
    <section className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="w-1.5 h-6 bg-primary rounded-full" />
          <div>
            <h2 className="text-xl font-bold text-on-surface">{section.title}</h2>
            {section.subtitle && (
              <p className="text-xs text-on-surface-variant mt-0.5">{section.subtitle}</p>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2">
          {isCarousel && products.length > 4 && (
            <div className="hidden sm:flex items-center gap-1 mr-2">
              <button
                type="button"
                onClick={scrollLeft}
                className="w-8 h-8 rounded-full bg-surface-container-high hover:bg-surface-bright text-on-surface flex items-center justify-center border border-outline-variant/30 cursor-pointer transition-all"
                aria-label="Cuộn trái"
              >
                <span className="material-symbols-outlined text-sm">chevron_left</span>
              </button>
              <button
                type="button"
                onClick={scrollRight}
                className="w-8 h-8 rounded-full bg-surface-container-high hover:bg-surface-bright text-on-surface flex items-center justify-center border border-outline-variant/30 cursor-pointer transition-all"
                aria-label="Cuộn phải"
              >
                <span className="material-symbols-outlined text-sm">chevron_right</span>
              </button>
            </div>
          )}

          <Link
            to="/products"
            className="text-xs sm:text-sm text-primary hover:underline font-semibold flex items-center gap-1 transition-colors"
          >
            Xem tất cả
            <span className="material-symbols-outlined text-base">arrow_forward</span>
          </Link>
        </div>
      </div>

      {isCarousel ? (
        <div
          ref={scrollRef}
          className="flex gap-4 overflow-x-auto pb-2 scrollbar-none snap-x"
        >
          {products.map((p) => (
            <div key={p.id} className="w-64 sm:w-72 shrink-0 snap-start">
              <ProductCard product={p} />
            </div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {products.map((p) => (
            <ProductCard key={p.id} product={p} />
          ))}
        </div>
      )}
    </section>
  )
}

// ── 4. Promotional Banner Section ────────────────────────────────────
function PromotionalBannerSection({ section }) {
  const items = section.items || {}
  const ctaLabel = items.cta_label || 'Khám phá ngay'
  const ctaUrl = items.cta_url || '/products'
  const badge = items.badge || 'Khuyến mãi đặc quyền'

  return (
    <section className="bg-gradient-to-r from-primary-container/30 via-surface-container to-secondary-container/20 rounded-2xl p-8 md:p-12 border border-outline-variant/20 flex flex-col md:flex-row items-center justify-between gap-6 relative overflow-hidden shadow-sm">
      <div className="relative z-10 max-w-xl">
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-[11px] font-bold uppercase tracking-wider mb-3">
          <span className="material-symbols-outlined text-sm">local_offer</span>
          {badge}
        </div>
        <h2 className="text-2xl md:text-3xl font-extrabold text-on-surface mb-2">{section.title}</h2>
        {section.subtitle && (
          <p className="text-on-surface-variant text-sm leading-relaxed">{section.subtitle}</p>
        )}
      </div>

      <div className="relative z-10 shrink-0">
        <Link
          to={ctaUrl}
          className="inline-flex items-center gap-2 px-6 py-3.5 bg-primary text-on-primary rounded-xl font-bold hover:bg-primary-dark transition-all active:scale-95 shadow-md"
        >
          {ctaLabel}
          <span className="material-symbols-outlined text-lg">arrow_forward</span>
        </Link>
      </div>

      {/* Decorative ambient lights */}
      <div className="absolute -right-20 -bottom-20 w-80 h-80 rounded-full bg-primary/10 blur-3xl pointer-events-none" />
      <div className="absolute -left-20 -top-20 w-80 h-80 rounded-full bg-secondary/10 blur-3xl pointer-events-none" />
    </section>
  )
}

// ── 5. Featured Brands Section ───────────────────────────────────────
function FeaturedBrandsSection({ section }) {
  const brands = section.items || []
  if (brands.length === 0) return null

  return (
    <section className="space-y-4">
      <div>
        <h2 className="text-xl font-bold text-on-surface">{section.title}</h2>
        {section.subtitle && (
          <p className="text-xs text-on-surface-variant mt-0.5">{section.subtitle}</p>
        )}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
        {brands.map((brand) => {
          const logo = resolveImageUrl(brand.logo_path || brand.logo)
          return (
            <Link
              key={brand.id}
              to={`/products?brand_id=${brand.id}`}
              className="flex flex-col items-center justify-center p-4 h-24 bg-surface-container rounded-2xl border border-outline-variant/15 hover:border-primary/40 hover:bg-surface-container-high transition-all group shadow-xs"
            >
              {logo ? (
                <img
                  src={logo}
                  alt={brand.name}
                  className="max-h-10 max-w-full object-contain filter grayscale group-hover:grayscale-0 group-hover:scale-105 transition-all"
                />
              ) : (
                <span className="text-sm font-bold text-on-surface group-hover:text-primary transition-colors">
                  {brand.name}
                </span>
              )}
            </Link>
          )
        })}
      </div>
    </section>
  )
}

// ── 6. Trust Services Section ────────────────────────────────────────
function TrustServicesSection({ section }) {
  const items = section.items || []
  if (items.length === 0) return null

  return (
    <section className="bg-surface-container/60 rounded-2xl border border-outline-variant/20 p-6 md:p-8">
      <div className="text-center max-w-xl mx-auto mb-8">
        <h2 className="text-lg md:text-xl font-bold text-on-surface">{section.title}</h2>
        {section.subtitle && (
          <p className="text-xs text-on-surface-variant mt-1">{section.subtitle}</p>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {items.map((item, idx) => (
          <div
            key={idx}
            className="flex items-start gap-4 p-4 rounded-xl bg-surface-container-high/40 border border-outline-variant/15 hover:border-primary/30 transition-colors"
          >
            <div className="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <span className="material-symbols-outlined text-2xl">{item.icon || 'verified'}</span>
            </div>
            <div>
              <h4 className="text-sm font-bold text-on-surface mb-1">{item.title}</h4>
              <p className="text-xs text-on-surface-variant leading-relaxed">{item.description}</p>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}

// ── 7. Recently Viewed Row ───────────────────────────────────────────
function RecentlyViewedSection({ section }) {
  const { recentlyViewed } = useRecentlyViewed()
  if (!recentlyViewed || recentlyViewed.length === 0) return null

  return (
    <section className="space-y-4">
      <div className="flex items-center gap-3">
        <span className="w-1.5 h-6 bg-outline rounded-full" />
        <div>
          <h2 className="text-xl font-bold text-on-surface">
            {section?.title || 'Sản phẩm bạn vừa xem'}
          </h2>
          {section?.subtitle && (
            <p className="text-xs text-on-surface-variant mt-0.5">{section.subtitle}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
        {recentlyViewed.slice(0, 6).map((item) => (
          <Link
            key={item.id}
            to={`/products/${item.id}`}
            className="group bg-surface-container rounded-xl overflow-hidden border border-outline-variant/20 hover:border-primary/40 transition-all flex flex-col"
          >
            <div className="h-32 bg-surface-container-low p-2 flex items-center justify-center overflow-hidden">
              {item.primary_image ? (
                <img
                  src={item.primary_image}
                  alt={item.name}
                  className="w-full h-full object-contain group-hover:scale-105 transition-transform"
                />
              ) : (
                <span className="material-symbols-outlined text-3xl text-outline">smartphone</span>
              )}
            </div>
            <div className="p-2.5">
              <p className="text-xs font-semibold text-on-surface line-clamp-2 group-hover:text-primary transition-colors mb-1">
                {item.name}
              </p>
              {item.price != null && (
                <p className="text-xs font-bold text-primary">{formatCurrencyVND(Number(item.price))}</p>
              )}
            </div>
          </Link>
        ))}
      </div>
    </section>
  )
}

// ── MAIN HOMEPAGE COMPONENT ───────────────────────────────────────────
export default function HomePage() {
  const [sections, setSections] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let mounted = true
    const fetchHomepage = async () => {
      setLoading(true)
      try {
        const { data } = await axiosClient.get(EP.HOMEPAGE)
        if (mounted && data?.data?.sections) {
          setSections(data.data.sections)
        }
      } catch (err) {
        console.error('Lỗi tải homepage dynamic data:', err)
      } finally {
        if (mounted) setLoading(false)
      }
    }
    fetchHomepage()
    return () => { mounted = false }
  }, [])

  if (loading) {
    return <PageLoadingSkeleton />
  }

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-6 space-y-12">
      {sections.map((section, idx) => {
        // Enforce rule: Section disabled in Admin must NOT appear
        if (!section.is_active) return null

        switch (section.section_type) {
          case 'banner_slider':
            return (
              <section key={section.id || idx}>
                <HeroBannerCarousel
                  banners={section.items}
                  autoplayInterval={section.settings?.autoplay_interval || 4000}
                />
              </section>
            )

          case 'categories':
            return <CategoryDiscoverySection key={section.id || idx} section={section} />

          case 'flash_sale':
            return (
              <section key={section.id || idx}>
                <FlashSaleSection products={section.items} />
              </section>
            )

          case 'products':
            return <ProductsSection key={section.id || idx} section={section} />

          case 'banner_promo':
            return <PromotionalBannerSection key={section.id || idx} section={section} />

          case 'brands':
            return <FeaturedBrandsSection key={section.id || idx} section={section} />

          case 'trust_services':
            return <TrustServicesSection key={section.id || idx} section={section} />

          case 'recently_viewed':
            return <RecentlyViewedSection key={section.id || idx} section={section} />

          default:
            return null
        }
      })}
    </div>
  )
}
