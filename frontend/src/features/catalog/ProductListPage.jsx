// src/features/catalog/ProductListPage.jsx
// Browse & filter product catalog with URL-synced filters.

import { useEffect, useState, useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Spinner from '../../components/common/Spinner'
import Pagination from '../../components/common/Pagination'
import useDebounce from '../../hooks/useDebounce'

// ── Skeleton ───────────────────────────────────────────────────────────────────
function SkeletonCard() {
  return (
    <div className="bg-surface-container rounded-xl p-4 animate-pulse">
      <div className="bg-surface-container-high rounded-lg h-48 mb-4" />
      <div className="bg-surface-container-high rounded h-4 w-3/4 mb-2" />
      <div className="bg-surface-container-high rounded h-4 w-1/2 mb-2" />
      <div className="bg-surface-container-high rounded h-5 w-1/3" />
    </div>
  )
}

// ── Product Card ───────────────────────────────────────────────────────────────
function ProductCard({ product }) {
  const price = product.min_price ?? product.base_price ?? 0
  const originalPrice = product.max_price ?? null

  return (
    <Link
      to={`/products/${product.id}`}
      className="group bg-surface-container rounded-xl overflow-hidden border border-outline-variant/10 hover:border-primary/30 hover:shadow-[0_0_30px_rgba(77,142,255,0.15)] transition-all duration-300"
    >
      <div className="relative h-48 bg-surface-container-high overflow-hidden">
        {product.thumbnail ? (
          <img
            src={product.thumbnail}
            alt={product.name}
            className="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <span className="material-symbols-outlined text-5xl text-outline">smartphone</span>
          </div>
        )}
        {product.discount_percent > 0 && (
          <span className="absolute top-2 left-2 bg-secondary-container text-on-secondary-container text-[10px] font-bold px-2 py-0.5 rounded-full">
            -{product.discount_percent}%
          </span>
        )}
      </div>
      <div className="p-4">
        <p className="text-xs text-primary font-medium mb-1">{product.brand?.name}</p>
        <h3 className="text-sm font-semibold text-on-surface leading-snug line-clamp-2 mb-2 group-hover:text-primary transition-colors">
          {product.name}
        </h3>
        <div className="flex items-baseline gap-2">
          <span className="text-base font-extrabold text-on-surface">{formatCurrencyVND(price)}</span>
          {originalPrice && originalPrice > price && (
            <span className="text-xs text-outline line-through">{formatCurrencyVND(originalPrice)}</span>
          )}
        </div>
      </div>
    </Link>
  )
}

// ── Price range filter options ─────────────────────────────────────────────────
const PRICE_RANGES = [
  { label: 'Dưới 5 triệu', min: 0, max: 5000000, key: 'under5m' },
  { label: '5 – 10 triệu', min: 5000000, max: 10000000, key: '5to10m' },
  { label: '10 – 20 triệu', min: 10000000, max: 20000000, key: '10to20m' },
  { label: 'Trên 20 triệu', min: 20000000, max: null, key: 'over20m' },
]

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function ProductListPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  // Filter state from URL
  const categoryId = searchParams.get('category_id') || ''
  const brandIdParam = searchParams.get('brand_id') || ''
  const qParam = searchParams.get('q') || ''
  const pageParam = parseInt(searchParams.get('page') || '1', 10)

  const [searchInput, setSearchInput] = useState(qParam)
  const debouncedSearch = useDebounce(searchInput, 400)

  const [selectedBrands, setSelectedBrands] = useState(() =>
    brandIdParam ? brandIdParam.split(',').filter(Boolean) : []
  )
  const [selectedPrice, setSelectedPrice] = useState(searchParams.get('price_key') || '')

  const [brands, setBrands] = useState([])
  const [products, setProducts] = useState([])
  const [meta, setMeta] = useState({ total: 0, last_page: 1 })
  const [loading, setLoading] = useState(true)
  const [brandsLoading, setBrandsLoading] = useState(true)

  // Fetch brands once
  useEffect(() => {
    axiosClient
      .get(EP.BRANDS)
      .then(({ data }) => setBrands(data.data ?? data ?? []))
      .catch(() => {})
      .finally(() => setBrandsLoading(false))
  }, [])

  // Build query params from state
  const buildParams = useCallback(() => {
    const params = { per_page: 12, page: pageParam }
    if (categoryId) params.category_id = categoryId
    if (selectedBrands.length) params.brand_id = selectedBrands.join(',')
    if (debouncedSearch) params.q = debouncedSearch
    const priceRange = PRICE_RANGES.find((r) => r.key === selectedPrice)
    if (priceRange) {
      params.min_price = priceRange.min
      if (priceRange.max) params.max_price = priceRange.max
    }
    return params
  }, [categoryId, selectedBrands, debouncedSearch, selectedPrice, pageParam])

  // Fetch products when filters change
  useEffect(() => {
    setLoading(true)
    axiosClient
      .get(EP.PRODUCTS, { params: buildParams() })
      .then(({ data }) => {
        const pd = data.data ?? data
        setProducts(pd.data ?? pd ?? [])
        setMeta({ total: pd.total ?? 0, last_page: pd.last_page ?? 1 })
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [buildParams])

  // Sync filter changes back to URL
  useEffect(() => {
    const next = {}
    if (categoryId) next.category_id = categoryId
    if (selectedBrands.length) next.brand_id = selectedBrands.join(',')
    if (debouncedSearch) next.q = debouncedSearch
    if (selectedPrice) next.price_key = selectedPrice
    if (pageParam > 1) next.page = String(pageParam)
    setSearchParams(next, { replace: true })
  }, [selectedBrands, debouncedSearch, selectedPrice, categoryId]) // eslint-disable-line

  const handleBrandToggle = (id) => {
    setSelectedBrands((prev) =>
      prev.includes(String(id)) ? prev.filter((b) => b !== String(id)) : [...prev, String(id)]
    )
    setSearchParams((sp) => { sp.set('page', '1'); return sp }, { replace: true })
  }

  const handlePriceToggle = (key) => {
    setSelectedPrice((prev) => (prev === key ? '' : key))
    setSearchParams((sp) => { sp.set('page', '1'); return sp }, { replace: true })
  }

  const handlePageChange = (p) => {
    setSearchParams((sp) => { sp.set('page', String(p)); return sp }, { replace: true })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const clearAllFilters = () => {
    setSelectedBrands([])
    setSelectedPrice('')
    setSearchInput('')
    setSearchParams({})
  }

  const hasFilters = selectedBrands.length > 0 || selectedPrice || debouncedSearch

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8">
      {/* Page header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-extrabold text-on-surface">Sản phẩm</h1>
          {!loading && (
            <p className="text-sm text-on-surface-variant mt-1">
              {meta.total} sản phẩm được tìm thấy
            </p>
          )}
        </div>
        {/* Search */}
        <div className="relative w-full sm:w-72">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none">
            search
          </span>
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Tìm kiếm sản phẩm..."
            className="w-full bg-surface-container border border-outline-variant/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-on-surface placeholder-on-surface-variant focus:outline-none focus:border-primary transition-colors"
          />
        </div>
      </div>

      <div className="flex gap-6">
        {/* ── Sidebar ────────────────────────────────────────────────────────── */}
        <aside className="hidden lg:block w-56 shrink-0 space-y-6">
          {/* Brand filter */}
          <div className="bg-surface-container rounded-xl p-4">
            <h2 className="text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
              <span className="material-symbols-outlined text-base text-primary">corporate_fare</span>
              Thương hiệu
            </h2>
            {brandsLoading ? (
              <div className="space-y-2">
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} className="bg-surface-container-high rounded h-4 animate-pulse" />
                ))}
              </div>
            ) : (
              <ul className="space-y-1">
                {brands.map((brand) => (
                  <li key={brand.id}>
                    <label className="flex items-center gap-2 cursor-pointer group py-1">
                      <input
                        type="checkbox"
                        checked={selectedBrands.includes(String(brand.id))}
                        onChange={() => handleBrandToggle(brand.id)}
                        className="rounded border-outline-variant accent-primary w-3.5 h-3.5"
                      />
                      <span className="text-sm text-on-surface-variant group-hover:text-on-surface transition-colors">
                        {brand.name}
                      </span>
                    </label>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Price range filter */}
          <div className="bg-surface-container rounded-xl p-4">
            <h2 className="text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
              <span className="material-symbols-outlined text-base text-primary">payments</span>
              Mức giá
            </h2>
            <ul className="space-y-1">
              {PRICE_RANGES.map((range) => (
                <li key={range.key}>
                  <label className="flex items-center gap-2 cursor-pointer group py-1">
                    <input
                      type="checkbox"
                      checked={selectedPrice === range.key}
                      onChange={() => handlePriceToggle(range.key)}
                      className="rounded border-outline-variant accent-primary w-3.5 h-3.5"
                    />
                    <span className="text-sm text-on-surface-variant group-hover:text-on-surface transition-colors">
                      {range.label}
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          </div>

          {/* Clear filters */}
          {hasFilters && (
            <button
              onClick={clearAllFilters}
              className="w-full text-sm text-error hover:text-on-error bg-error-container/20 hover:bg-error-container/40 rounded-xl py-2 transition-colors"
            >
              Xóa bộ lọc
            </button>
          )}
        </aside>

        {/* ── Product grid ───────────────────────────────────────────────────── */}
        <div className="flex-1 min-w-0">
          {/* Mobile filters row */}
          <div className="flex lg:hidden gap-2 mb-4 overflow-x-auto pb-1">
            {brands.slice(0, 6).map((brand) => (
              <button
                key={brand.id}
                onClick={() => handleBrandToggle(brand.id)}
                className={[
                  'shrink-0 px-3 py-1 rounded-full text-xs font-medium border transition-all',
                  selectedBrands.includes(String(brand.id))
                    ? 'bg-primary-container text-on-primary-container border-primary/30'
                    : 'bg-surface-container text-on-surface-variant border-outline-variant/20 hover:border-primary/30',
                ].join(' ')}
              >
                {brand.name}
              </button>
            ))}
            {PRICE_RANGES.map((range) => (
              <button
                key={range.key}
                onClick={() => handlePriceToggle(range.key)}
                className={[
                  'shrink-0 px-3 py-1 rounded-full text-xs font-medium border transition-all',
                  selectedPrice === range.key
                    ? 'bg-primary-container text-on-primary-container border-primary/30'
                    : 'bg-surface-container text-on-surface-variant border-outline-variant/20 hover:border-primary/30',
                ].join(' ')}
              >
                {range.label}
              </button>
            ))}
          </div>

          {loading ? (
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
              {Array.from({ length: 12 }).map((_, i) => (
                <SkeletonCard key={i} />
              ))}
            </div>
          ) : products.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-24 gap-4 text-on-surface-variant">
              <span className="material-symbols-outlined text-6xl">inventory_2</span>
              <p className="text-base font-medium">Không tìm thấy sản phẩm nào.</p>
              {hasFilters && (
                <button
                  onClick={clearAllFilters}
                  className="text-sm text-primary hover:underline"
                >
                  Xóa bộ lọc
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
                {products.map((p) => (
                  <ProductCard key={p.id} product={p} />
                ))}
              </div>
              {meta.last_page > 1 && (
                <div className="mt-8 flex justify-center">
                  <Pagination
                    currentPage={pageParam}
                    totalPages={meta.last_page}
                    onPageChange={handlePageChange}
                  />
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
