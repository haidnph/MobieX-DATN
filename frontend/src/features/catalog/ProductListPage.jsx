// src/features/catalog/ProductListPage.jsx
// Browse & filter product catalog with URL-synced multi-faceted filters, sorting, and categories.

import { useEffect, useState, useCallback, useMemo } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Pagination from '../../components/common/Pagination'
import ProductCard from '../../components/common/ProductCard'
import useDebounce from '../../hooks/useDebounce'

// ── Skeleton Loader ──────────────────────────────────────────────────────────
function SkeletonCard() {
  return (
    <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/15 animate-pulse">
      <div className="bg-surface-container-high rounded-xl h-48 mb-4" />
      <div className="bg-surface-container-high rounded-md h-3.5 w-1/4 mb-2" />
      <div className="bg-surface-container-high rounded-md h-4 w-3/4 mb-2" />
      <div className="bg-surface-container-high rounded-md h-4 w-1/2 mb-4" />
      <div className="bg-surface-container-high rounded-md h-5 w-1/3" />
    </div>
  )
}


// ── Filter Presets ───────────────────────────────────────────────────────────
const PRICE_RANGES = [
  { label: 'Dưới 5 triệu', min: 0, max: 5000000, key: 'under5m' },
  { label: '5 – 10 triệu', min: 5000000, max: 10000000, key: '5to10m' },
  { label: '10 – 20 triệu', min: 10000000, max: 20000000, key: '10to20m' },
  { label: 'Trên 20 triệu', min: 20000000, max: null, key: 'over20m' },
]

const SORT_OPTIONS = [
  { value: 'newest', label: 'Mới nhất', icon: 'schedule' },
  { value: 'popular', label: 'Phổ biến / Đánh giá', icon: 'trending_up' },
  { value: 'price_asc', label: 'Giá: Thấp đến Cao', icon: 'arrow_upward' },
  { value: 'price_desc', label: 'Giá: Cao đến Thấp', icon: 'arrow_downward' },
  { value: 'name_asc', label: 'Tên: A → Z', icon: 'sort_by_alpha' },
  { value: 'name_desc', label: 'Tên: Z → A', icon: 'sort_by_alpha' },
  { value: 'oldest', label: 'Cũ nhất', icon: 'history' },
]

// ── Main Page ────────────────────────────────────────────────────────────────
export default function ProductListPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  // Filter state from URL
  const categoryIdParam = searchParams.get('category_id') || ''
  const brandIdParam = searchParams.get('brand_id') || ''
  const qParam = searchParams.get('q') || ''
  const sortByParam = searchParams.get('sort_by') || 'newest'
  const priceKeyParam = searchParams.get('price_key') || ''
  const pageParam = parseInt(searchParams.get('page') || '1', 10)

  const [searchInput, setSearchInput] = useState(qParam)
  const debouncedSearch = useDebounce(searchInput, 400)

  const [selectedBrands, setSelectedBrands] = useState(() =>
    brandIdParam ? brandIdParam.split(',').filter(Boolean) : []
  )
  const [selectedPrice, setSelectedPrice] = useState(priceKeyParam)
  const [selectedSort, setSelectedSort] = useState(sortByParam)
  const [selectedCategory, setSelectedCategory] = useState(categoryIdParam)

  const [brands, setBrands] = useState([])
  const [categories, setCategories] = useState([])
  const [products, setProducts] = useState([])
  const [meta, setMeta] = useState({ total: 0, last_page: 1 })
  const [loading, setLoading] = useState(true)
  const [brandsLoading, setBrandsLoading] = useState(true)
  const [catsLoading, setCatsLoading] = useState(true)
  const [brandSearch, setBrandSearch] = useState('')

  // Sync state if URL changes externally
  useEffect(() => {
    setSelectedCategory(categoryIdParam)
  }, [categoryIdParam])

  useEffect(() => {
    setSelectedBrands(brandIdParam ? brandIdParam.split(',').filter(Boolean) : [])
  }, [brandIdParam])

  useEffect(() => {
    setSelectedPrice(priceKeyParam)
  }, [priceKeyParam])

  useEffect(() => {
    setSelectedSort(sortByParam)
  }, [sortByParam])

  // Fetch brands and categories on mount
  useEffect(() => {
    axiosClient
      .get(EP.BRANDS)
      .then(({ data }) => setBrands(data.data ?? data ?? []))
      .catch(() => {})
      .finally(() => setBrandsLoading(false))

    axiosClient
      .get(EP.CATEGORIES)
      .then(({ data }) => setCategories(data.data ?? data ?? []))
      .catch(() => {})
      .finally(() => setCatsLoading(false))
  }, [])

  // Build query params from state
  const buildParams = useCallback(() => {
    const params = { per_page: 12, page: pageParam }
    if (selectedCategory) params.category_id = selectedCategory
    if (selectedBrands.length) params.brand_id = selectedBrands.join(',')
    if (debouncedSearch) params.q = debouncedSearch
    if (selectedSort && selectedSort !== 'newest') params.sort_by = selectedSort

    const priceRange = PRICE_RANGES.find((r) => r.key === selectedPrice)
    if (priceRange) {
      params.min_price = priceRange.min
      if (priceRange.max) params.max_price = priceRange.max
    }
    return params
  }, [selectedCategory, selectedBrands, debouncedSearch, selectedSort, selectedPrice, pageParam])

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

  // Sync state back to URL
  const updateUrl = useCallback(
    (overrides = {}) => {
      const next = {}
      const cat = overrides.category_id !== undefined ? overrides.category_id : selectedCategory
      const br = overrides.brand_id !== undefined ? overrides.brand_id : selectedBrands.join(',')
      const q = overrides.q !== undefined ? overrides.q : debouncedSearch
      const pk = overrides.price_key !== undefined ? overrides.price_key : selectedPrice
      const sb = overrides.sort_by !== undefined ? overrides.sort_by : selectedSort
      const pg = overrides.page !== undefined ? overrides.page : 1

      if (cat) next.category_id = cat
      if (br) next.brand_id = br
      if (q) next.q = q
      if (pk) next.price_key = pk
      if (sb && sb !== 'newest') next.sort_by = sb
      if (pg > 1) next.page = String(pg)

      setSearchParams(next, { replace: true })
    },
    [selectedCategory, selectedBrands, debouncedSearch, selectedPrice, selectedSort, setSearchParams]
  )

  // Filter actions
  const handleCategorySelect = (id) => {
    const nextCat = selectedCategory === String(id) ? '' : String(id)
    setSelectedCategory(nextCat)
    updateUrl({ category_id: nextCat, page: 1 })
  }

  const handleBrandToggle = (id) => {
    const sId = String(id)
    const next = selectedBrands.includes(sId)
      ? selectedBrands.filter((b) => b !== sId)
      : [...selectedBrands, sId]
    setSelectedBrands(next)
    updateUrl({ brand_id: next.join(','), page: 1 })
  }

  const handlePriceToggle = (key) => {
    const nextPrice = selectedPrice === key ? '' : key
    setSelectedPrice(nextPrice)
    updateUrl({ price_key: nextPrice, page: 1 })
  }

  const handleSortChange = (newSort) => {
    setSelectedSort(newSort)
    updateUrl({ sort_by: newSort, page: 1 })
  }

  const handlePageChange = (p) => {
    updateUrl({ page: p })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const clearAllFilters = () => {
    setSelectedBrands([])
    setSelectedPrice('')
    setSelectedCategory('')
    setSelectedSort('newest')
    setSearchInput('')
    setSearchParams({})
  }

  const hasFilters = Boolean(
    selectedBrands.length > 0 ||
    selectedPrice ||
    debouncedSearch ||
    selectedCategory ||
    (selectedSort && selectedSort !== 'newest')
  )

  // Filtered brands for quick search in sidebar
  const filteredBrands = useMemo(() => {
    if (!brandSearch.trim()) return brands
    const term = brandSearch.toLowerCase()
    return brands.filter((b) => b.name?.toLowerCase().includes(term))
  }, [brands, brandSearch])

  // Find active category label
  const activeCategoryName = useMemo(() => {
    if (!selectedCategory) return null
    for (const c of categories) {
      if (String(c.id) === selectedCategory) return c.name
      if (c.children) {
        const sub = c.children.find((ch) => String(ch.id) === selectedCategory)
        if (sub) return `${c.name} > ${sub.name}`
      }
    }
    return null
  }, [categories, selectedCategory])

  return (
    <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-16 py-8">
      {/* ── Page Header ────────────────────────────────────────────────────── */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <div className="flex items-center gap-2 text-xs text-on-surface-variant mb-1">
            <Link to="/" className="hover:text-primary transition-colors">
              Trang chủ
            </Link>
            <span className="material-symbols-outlined text-xs">chevron_right</span>
            <span className="text-on-surface font-medium">Sản phẩm</span>
            {activeCategoryName && (
              <>
                <span className="material-symbols-outlined text-xs">chevron_right</span>
                <span className="text-primary font-semibold">{activeCategoryName}</span>
              </>
            )}
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface tracking-tight">
            Khám phá Sản phẩm
          </h1>
          {!loading && (
            <p className="text-xs md:text-sm text-on-surface-variant mt-1">
              Tìm thấy <span className="font-bold text-on-surface">{meta.total}</span> sản phẩm phù hợp
            </p>
          )}
        </div>

        {/* Search bar & Sort Controls on Top Right */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
          {/* Keyword Search */}
          <div className="relative w-full sm:w-64">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none">
              search
            </span>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => {
                setSearchInput(e.target.value)
                updateUrl({ q: e.target.value, page: 1 })
              }}
              placeholder="Tìm theo tên máy..."
              className="w-full bg-surface-container border border-outline-variant/30 rounded-xl pl-9 pr-8 py-2 text-sm text-on-surface placeholder-on-surface-variant focus:outline-none focus:border-primary transition-colors"
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => {
                  setSearchInput('')
                  updateUrl({ q: '', page: 1 })
                }}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface p-0.5"
              >
                <span className="material-symbols-outlined text-sm">close</span>
              </button>
            )}
          </div>

          {/* Sort Dropdown */}
          <div className="relative flex items-center bg-surface-container border border-outline-variant/30 rounded-xl px-3 py-2 text-sm">
            <span className="material-symbols-outlined text-outline text-base mr-1.5">
              swap_vert
            </span>
            <span className="text-xs text-on-surface-variant mr-1.5 whitespace-nowrap">Sắp xếp:</span>
            <select
              value={selectedSort}
              onChange={(e) => handleSortChange(e.target.value)}
              className="bg-transparent text-sm font-semibold text-on-surface focus:outline-none cursor-pointer pr-1"
            >
              {SORT_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value} className="bg-surface-container text-on-surface">
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* ── Active Filter Chips (Tags đang lọc) ─────────────────────────────── */}
      {hasFilters && (
        <div className="flex flex-wrap items-center gap-2 mb-6 p-3 rounded-2xl bg-surface-container/60 border border-outline-variant/15">
          <span className="text-xs font-bold text-on-surface-variant flex items-center gap-1 mr-1">
            <span className="material-symbols-outlined text-sm text-primary">filter_alt</span>
            Đang lọc:
          </span>

          {/* Category Chip */}
          {activeCategoryName && (
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary border border-primary/25">
              <span>{activeCategoryName}</span>
              <button
                type="button"
                onClick={() => handleCategorySelect(selectedCategory)}
                className="hover:text-error transition-colors"
              >
                <span className="material-symbols-outlined text-xs">close</span>
              </button>
            </span>
          )}

          {/* Brand Chips */}
          {selectedBrands.map((bId) => {
            const br = brands.find((b) => String(b.id) === bId)
            if (!br) return null
            return (
              <span
                key={bId}
                className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-secondary-container text-on-secondary-container"
              >
                <span>{br.name}</span>
                <button
                  type="button"
                  onClick={() => handleBrandToggle(bId)}
                  className="hover:text-error transition-colors"
                >
                  <span className="material-symbols-outlined text-xs">close</span>
                </button>
              </span>
            )
          })}

          {/* Price Chip */}
          {selectedPrice && (
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-tertiary-container text-on-tertiary-container">
              <span>{PRICE_RANGES.find((r) => r.key === selectedPrice)?.label}</span>
              <button
                type="button"
                onClick={() => handlePriceToggle(selectedPrice)}
                className="hover:text-error transition-colors"
              >
                <span className="material-symbols-outlined text-xs">close</span>
              </button>
            </span>
          )}

          {/* Search Term Chip */}
          {debouncedSearch && (
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-surface-container-high text-on-surface">
              <span>Từ khóa: &ldquo;{debouncedSearch}&rdquo;</span>
              <button
                type="button"
                onClick={() => {
                  setSearchInput('')
                  updateUrl({ q: '', page: 1 })
                }}
                className="hover:text-error transition-colors"
              >
                <span className="material-symbols-outlined text-xs">close</span>
              </button>
            </span>
          )}

          {/* Reset All Button */}
          <button
            type="button"
            onClick={clearAllFilters}
            className="text-xs font-bold text-error hover:text-white hover:bg-error px-3 py-1 rounded-full transition-colors ml-auto flex items-center gap-1 border border-error/30"
          >
            <span className="material-symbols-outlined text-xs">restart_alt</span>
            Xóa tất cả bộ lọc
          </button>
        </div>
      )}

      {/* ── Main Layout: Sidebar + Product Grid ─────────────────────────────── */}
      <div className="flex gap-6 items-start">
        {/* ── Desktop Sidebar ──────────────────────────────────────────────── */}
        <aside className="hidden lg:block w-64 shrink-0 space-y-5">
          {/* Categories Selector */}
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/15">
            <h2 className="text-sm font-bold text-on-surface mb-3 flex items-center justify-between">
              <span className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">category</span>
                Danh mục
              </span>
              {selectedCategory && (
                <button
                  type="button"
                  onClick={() => handleCategorySelect(selectedCategory)}
                  className="text-[11px] text-error hover:underline"
                >
                  Bỏ chọn
                </button>
              )}
            </h2>

            {catsLoading ? (
              <div className="space-y-2">
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="bg-surface-container-high rounded h-4 animate-pulse" />
                ))}
              </div>
            ) : (
              <div className="space-y-2">
                <button
                  type="button"
                  onClick={() => {
                    setSelectedCategory('')
                    updateUrl({ category_id: '', page: 1 })
                  }}
                  className={`w-full text-left px-3 py-1.5 rounded-xl text-xs font-medium transition-all flex items-center justify-between ${
                    !selectedCategory
                      ? 'bg-primary text-on-primary font-bold shadow-sm'
                      : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'
                  }`}
                >
                  <span>Tất cả danh mục</span>
                  {!selectedCategory && (
                    <span className="material-symbols-outlined text-xs">check</span>
                  )}
                </button>

                <div className="space-y-1.5 pt-1">
                  {categories.map((cat) => {
                    const isParentActive = selectedCategory === String(cat.id)
                    const isChildActive = cat.children?.some(
                      (ch) => selectedCategory === String(ch.id)
                    )

                    return (
                      <div key={cat.id} className="rounded-xl overflow-hidden">
                        <button
                          type="button"
                          onClick={() => handleCategorySelect(cat.id)}
                          className={`w-full text-left px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between ${
                            isParentActive
                              ? 'bg-primary-container text-on-primary-container font-bold'
                              : 'text-on-surface hover:bg-surface-container-high'
                          }`}
                        >
                          <span className="truncate">{cat.name}</span>
                          {isParentActive && (
                            <span className="material-symbols-outlined text-xs">check</span>
                          )}
                        </button>

                        {/* Child categories */}
                        {cat.children && cat.children.length > 0 && (
                          <div className="pl-3 py-1 space-y-0.5">
                            {cat.children.map((child) => {
                              const active = selectedCategory === String(child.id)
                              return (
                                <button
                                  key={child.id}
                                  type="button"
                                  onClick={() => handleCategorySelect(child.id)}
                                  className={`w-full text-left px-2.5 py-1 rounded-lg text-[11px] transition-all flex items-center justify-between ${
                                    active
                                      ? 'bg-primary text-on-primary font-bold'
                                      : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high'
                                  }`}
                                >
                                  <span className="truncate">• {child.name}</span>
                                  {active && (
                                    <span className="material-symbols-outlined text-[10px]">
                                      check
                                    </span>
                                  )}
                                </button>
                              )
                            })}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              </div>
            )}
          </div>

          {/* Brands Filter */}
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/15">
            <h2 className="text-sm font-bold text-on-surface mb-2.5 flex items-center justify-between">
              <span className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">corporate_fare</span>
                Thương hiệu
              </span>
              {selectedBrands.length > 0 && (
                <span className="text-[11px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full">
                  {selectedBrands.length} đã chọn
                </span>
              )}
            </h2>

            {/* Quick Brand Search Input */}
            {brands.length > 6 && (
              <div className="relative mb-2.5">
                <input
                  type="text"
                  value={brandSearch}
                  onChange={(e) => setBrandSearch(e.target.value)}
                  placeholder="Tìm thương hiệu..."
                  className="w-full bg-surface-container-low border border-outline-variant/20 rounded-lg px-2.5 py-1 text-xs text-on-surface placeholder-on-surface-variant focus:outline-none focus:border-primary"
                />
                {brandSearch && (
                  <button
                    type="button"
                    onClick={() => setBrandSearch('')}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                  >
                    <span className="material-symbols-outlined text-xs">close</span>
                  </button>
                )}
              </div>
            )}

            {brandsLoading ? (
              <div className="space-y-2">
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} className="bg-surface-container-high rounded h-4 animate-pulse" />
                ))}
              </div>
            ) : filteredBrands.length === 0 ? (
              <p className="text-xs text-on-surface-variant py-2 text-center">
                Không tìm thấy thương hiệu
              </p>
            ) : (
              <ul className="space-y-1 max-h-56 overflow-y-auto pr-1">
                {filteredBrands.map((brand) => {
                  const isChecked = selectedBrands.includes(String(brand.id))
                  return (
                    <li key={brand.id}>
                      <label className="flex items-center gap-2.5 cursor-pointer group py-1 px-1 rounded-lg hover:bg-surface-container-high/60 transition-colors">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => handleBrandToggle(brand.id)}
                          className="rounded border-outline-variant accent-primary w-4 h-4 cursor-pointer"
                        />
                        <span
                          className={`text-xs transition-colors ${
                            isChecked
                              ? 'font-bold text-primary'
                              : 'text-on-surface-variant group-hover:text-on-surface'
                          }`}
                        >
                          {brand.name}
                        </span>
                      </label>
                    </li>
                  )
                })}
              </ul>
            )}
          </div>

          {/* Price Range Filter */}
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/15">
            <h2 className="text-sm font-bold text-on-surface mb-3 flex items-center justify-between">
              <span className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base text-primary">payments</span>
                Mức giá
              </span>
              {selectedPrice && (
                <button
                  type="button"
                  onClick={() => handlePriceToggle(selectedPrice)}
                  className="text-[11px] text-error hover:underline"
                >
                  Bỏ chọn
                </button>
              )}
            </h2>
            <ul className="space-y-1">
              {PRICE_RANGES.map((range) => {
                const isSelected = selectedPrice === range.key
                return (
                  <li key={range.key}>
                    <label className="flex items-center gap-2.5 cursor-pointer group py-1 px-1 rounded-lg hover:bg-surface-container-high/60 transition-colors">
                      <input
                        type="radio"
                        name="price_range"
                        checked={isSelected}
                        onChange={() => handlePriceToggle(range.key)}
                        className="accent-primary w-4 h-4 cursor-pointer"
                      />
                      <span
                        className={`text-xs transition-colors ${
                          isSelected
                            ? 'font-bold text-primary'
                            : 'text-on-surface-variant group-hover:text-on-surface'
                        }`}
                      >
                        {range.label}
                      </span>
                    </label>
                  </li>
                )
              })}
            </ul>
          </div>
        </aside>

        {/* ── Product Grid Area ────────────────────────────────────────────── */}
        <div className="flex-1 min-w-0">
          {/* Mobile Filter Pills Row */}
          <div className="flex lg:hidden gap-2 mb-4 overflow-x-auto pb-1.5 scrollbar-thin">
            {/* Quick Category Pill */}
            {categories.slice(0, 5).map((cat) => (
              <button
                key={cat.id}
                type="button"
                onClick={() => handleCategorySelect(cat.id)}
                className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all ${
                  selectedCategory === String(cat.id)
                    ? 'bg-primary text-on-primary border-primary shadow-sm'
                    : 'bg-surface-container text-on-surface-variant border-outline-variant/20 hover:border-primary/40'
                }`}
              >
                {cat.name}
              </button>
            ))}

            {/* Quick Brand Pills */}
            {brands.slice(0, 6).map((brand) => (
              <button
                key={brand.id}
                type="button"
                onClick={() => handleBrandToggle(brand.id)}
                className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${
                  selectedBrands.includes(String(brand.id))
                    ? 'bg-secondary-container text-on-secondary-container border-secondary/40'
                    : 'bg-surface-container text-on-surface-variant border-outline-variant/20 hover:border-primary/40'
                }`}
              >
                {brand.name}
              </button>
            ))}

            {/* Price Pills */}
            {PRICE_RANGES.map((range) => (
              <button
                key={range.key}
                type="button"
                onClick={() => handlePriceToggle(range.key)}
                className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${
                  selectedPrice === range.key
                    ? 'bg-tertiary-container text-on-tertiary-container border-tertiary/40'
                    : 'bg-surface-container text-on-surface-variant border-outline-variant/20 hover:border-primary/40'
                }`}
              >
                {range.label}
              </button>
            ))}
          </div>

          {/* Product Cards Grid */}
          {loading ? (
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
              {Array.from({ length: 12 }).map((_, i) => (
                <SkeletonCard key={i} />
              ))}
            </div>
          ) : products.length === 0 ? (
            /* Empty State */
            <div className="flex flex-col items-center justify-center py-20 px-4 rounded-3xl bg-surface-container/40 border border-outline-variant/15 text-center">
              <div className="w-16 h-16 rounded-2xl bg-surface-container-high flex items-center justify-center text-outline mb-4">
                <span className="material-symbols-outlined text-4xl">search_off</span>
              </div>
              <h3 className="text-lg font-bold text-on-surface mb-1">
                Không tìm thấy sản phẩm phù hợp
              </h3>
              <p className="text-xs md:text-sm text-on-surface-variant max-w-md mb-6 leading-relaxed">
                Rất tiếc, không có sản phẩm nào khớp với tiêu chí tìm kiếm hoặc bộ lọc hiện tại của bạn.
                Hãy thử nới lỏng các điều kiện lọc hoặc tìm kiếm bằng từ khóa khác.
              </p>
              {hasFilters && (
                <button
                  type="button"
                  onClick={clearAllFilters}
                  className="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-on-primary font-bold text-sm px-5 py-2.5 rounded-xl shadow-lg transition-all"
                >
                  <span className="material-symbols-outlined text-base">restart_alt</span>
                  Đặt lại toàn bộ bộ lọc
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

              {/* Pagination */}
              {meta.last_page > 1 && (
                <div className="mt-10 flex justify-center">
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
