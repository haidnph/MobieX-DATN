// src/components/layout/Header.jsx
// MobileX Storefront Header — Redesigned & Refactored
// Visual hierarchy: Group A (Brand, Search, Navigation) | Group B (Utilities)
// Integrates official GTranslate widget, unified red badges, and responsive 2-row mobile layout.

import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useAuth } from '../../contexts/AuthContext'
import { useCart } from '../../contexts/CartContext'
import { useCompare } from '../../contexts/CompareContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { useTheme } from '../../contexts/ThemeContext'
import { useToast } from '../../contexts/ToastContext'
import { useLanguage } from '../../contexts/LanguageContext'
import { useDebounce } from '../../hooks/useDebounce'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import {
  getRecentSearches,
  addRecentSearch,
  removeRecentSearch,
  clearRecentSearches,
  POPULAR_SEARCHES,
} from '../../utils/searchHistory'
import LanguageSelector from './LanguageSelector'
import CategoryMegaMenu, {
  buildCategoryTree,
  getCategoryIcon,
  getLocalizedCategoryName,
  CATEGORY_UI,
} from './CategoryMegaMenu'

/**
 * Reusable dropdown for Search Autocomplete, Search History & Trending Searches.
 */
function SearchDropdownPanel({
  searchQuery,
  suggestions,
  loadingSuggestions,
  recentSearches,
  selectedIndex,
  onSelectSearch,
  onSelectBrand,
  onSelectCategory,
  onRemoveHistory,
  onClearHistory,
  onClose,
  lang,
  isMobile = false,
}) {
  const isShortQuery = searchQuery.trim().length < 2
  const products = suggestions.products || []
  const brands = suggestions.brands || []
  const categories = suggestions.categories || []
  const total = suggestions.total || products.length

  return (
    <div
      className={`absolute top-full left-0 right-0 mt-2 bg-surface-container-high/98 backdrop-blur-xl rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50 text-left ${
        isMobile ? 'text-xs' : 'text-sm'
      }`}
    >
      {/* ── CASE 1: Query < 2 (Lịch sử tìm kiếm & Xu hướng) ── */}
      {isShortQuery ? (
        <div className="p-3.5 space-y-4">
          {recentSearches.length > 0 && (
            <div>
              <div className="flex items-center justify-between text-xs text-on-surface-variant font-bold mb-2 px-1">
                <span className="flex items-center gap-1.5">
                  <span className="material-symbols-outlined text-sm text-primary">history</span>
                  {lang === 'zh-CN' ? '最近搜索' : lang === 'en' ? 'Recent Searches' : 'Lịch sử tìm kiếm'}
                </span>
                <button
                  type="button"
                  onClick={onClearHistory}
                  className="text-[11px] text-error hover:underline transition-colors font-medium"
                >
                  {lang === 'zh-CN' ? '清空' : lang === 'en' ? 'Clear all' : 'Xóa tất cả'}
                </button>
              </div>
              <div className="space-y-0.5">
                {recentSearches.map((item, idx) => {
                  const isHighlighted = selectedIndex === idx
                  return (
                    <div
                      key={item}
                      className={`flex items-center justify-between px-2.5 py-1.5 rounded-xl group transition-colors cursor-pointer ${
                        isHighlighted
                          ? 'bg-primary/10 text-primary font-bold'
                          : 'hover:bg-surface-bright text-on-surface'
                      }`}
                    >
                      <button
                        type="button"
                        onClick={() => onSelectSearch(item)}
                        className="flex items-center gap-2 flex-1 text-left min-w-0"
                      >
                        <span className="material-symbols-outlined text-sm text-outline group-hover:text-primary">
                          history
                        </span>
                        <span className="truncate text-xs">{item}</span>
                      </button>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation()
                          onRemoveHistory(item)
                        }}
                        className="p-1 text-outline hover:text-error opacity-60 group-hover:opacity-100 transition-opacity"
                        aria-label={`Xóa ${item} khỏi lịch sử`}
                      >
                        <span className="material-symbols-outlined text-xs">close</span>
                      </button>
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          {/* Popular searches section */}
          <div>
            <div className="flex items-center gap-1.5 text-xs text-on-surface-variant font-bold mb-2.5 px-1">
              <span className="material-symbols-outlined text-sm text-tertiary">trending_up</span>
              <span>{lang === 'zh-CN' ? '热门搜索' : lang === 'en' ? 'Trending Searches' : 'Xu hướng tìm kiếm'}</span>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {POPULAR_SEARCHES.map((tag) => (
                <button
                  key={tag}
                  type="button"
                  onClick={() => onSelectSearch(tag)}
                  className="px-3 py-1 rounded-full text-xs font-semibold bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant border border-outline-variant/20 transition-all flex items-center gap-1"
                >
                  <span>{tag}</span>
                </button>
              ))}
            </div>
          </div>
        </div>
      ) : (
        /* ── CASE 2: Query >= 2 (Autocomplete Results) ── */
        <div>
          {/* Header bar */}
          <div className="px-4 py-2 border-b border-outline-variant/20 flex items-center justify-between text-xs text-on-surface-variant font-medium bg-surface-container/40">
            <span>
              {lang === 'zh-CN' ? '搜索建议' : lang === 'en' ? 'Suggestions' : 'Gợi ý tìm kiếm'} ({total})
            </span>
            {loadingSuggestions && (
              <span className="flex items-center gap-1.5 text-primary text-[11px]">
                <span className="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                {lang === 'zh-CN' ? '正在搜索...' : lang === 'en' ? 'Searching...' : 'Đang tìm...'}
              </span>
            )}
          </div>

          {/* Typo Correction Suggestion */}
          {suggestions.has_typo && suggestions.suggested_query && (
            <div className="px-3.5 py-2 bg-primary/10 border-b border-primary/20 flex items-center justify-between gap-2 text-xs">
              <span className="flex items-center gap-1.5 text-on-surface">
                <span className="material-symbols-outlined text-primary text-sm">auto_fix_high</span>
                <span>
                  Có phải bạn muốn tìm:{' '}
                  <strong className="text-primary">&ldquo;{suggestions.suggested_query}&rdquo;</strong>?
                </span>
              </span>
              <button
                type="button"
                onClick={() => onSelectSearch(suggestions.suggested_query)}
                className="text-[11px] font-bold text-primary hover:underline cursor-pointer"
              >
                Chọn từ này
              </button>
            </div>
          )}

          {/* Model Alias Pill */}
          {suggestions.model_alias && (
            <div className="px-3.5 py-1.5 bg-secondary-container/40 border-b border-secondary/20 flex items-center gap-1.5 text-xs text-on-secondary-container font-medium">
              <span className="material-symbols-outlined text-secondary text-sm">smartphone</span>
              <span>
                Thiết bị nhận diện: <strong className="text-on-surface">{suggestions.model_alias}</strong>
              </span>
            </div>
          )}

          {/* Quick matching brands and categories chips */}
          {(brands.length > 0 || categories.length > 0) && (
            <div className="px-3.5 py-2.5 border-b border-outline-variant/15 bg-surface-container-low/50 flex flex-wrap gap-1.5 items-center">
              <span className="text-[11px] text-on-surface-variant font-bold mr-1">Khám phá:</span>
              {brands.map((b) => (
                <button
                  key={`brand-${b.id}`}
                  type="button"
                  onClick={() => onSelectBrand(b)}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-secondary-container/70 text-on-secondary-container hover:bg-secondary-container transition-colors border border-secondary/20"
                >
                  <span className="material-symbols-outlined text-xs">verified</span>
                  <span>{b.name}</span>
                </button>
              ))}
              {categories.map((c) => (
                <button
                  key={`cat-${c.id}`}
                  type="button"
                  onClick={() => onSelectCategory(c)}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-tertiary-container/70 text-on-tertiary-container hover:bg-tertiary-container transition-colors border border-tertiary/20"
                >
                  <span className="material-symbols-outlined text-xs">category</span>
                  <span>{c.name}</span>
                </button>
              ))}
            </div>
          )}

          {/* Loading empty state */}
          {loadingSuggestions && products.length === 0 ? (
            <div className="py-8 text-center text-xs text-on-surface-variant flex flex-col items-center justify-center gap-2">
              <span className="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
              <span>Đang tìm sản phẩm phù hợp...</span>
            </div>
          ) : products.length === 0 && brands.length === 0 && categories.length === 0 ? (
            /* Zero results empty notice */
            <div className="py-7 px-4 text-center">
              <div className="w-10 h-10 rounded-xl bg-surface-container mx-auto flex items-center justify-center text-outline mb-2">
                <span className="material-symbols-outlined text-xl">search_off</span>
              </div>
              <p className="text-xs font-bold text-on-surface mb-1">
                Không tìm thấy sản phẩm khớp với &ldquo;{searchQuery}&rdquo;
              </p>
              <p className="text-[11px] text-on-surface-variant mb-3">
                Thử kiểm tra lỗi chính tả hoặc tìm theo thương hiệu/danh mục khác.
              </p>
              <div className="flex flex-wrap justify-center gap-1.5">
                {POPULAR_SEARCHES.slice(0, 4).map((tag) => (
                  <button
                    key={tag}
                    type="button"
                    onClick={() => onSelectSearch(tag)}
                    className="px-2.5 py-0.5 rounded-full text-[11px] bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface-variant transition-colors"
                  >
                    {tag}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            /* Products list */
            <div className="divide-y divide-outline-variant/10 max-h-80 overflow-y-auto">
              {products.map((item, idx) => {
                const img =
                  item.images?.find((i) => i.is_primary)?.image_path ||
                  item.thumbnail ||
                  item.images?.[0]?.image_path
                const price =
                  item.min_price ||
                  item.price ||
                  item.base_price ||
                  item.variants?.[0]?.price
                const isHighlighted = selectedIndex === idx

                return (
                  <Link
                    key={item.id}
                    to={`/products/${item.slug ?? item.id}`}
                    onClick={() => {
                      addRecentSearch(item.name)
                      onClose()
                    }}
                    className={`flex items-center gap-3 px-4 py-2.5 transition-colors group ${
                      isHighlighted ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-surface-bright'
                    }`}
                  >
                    <div className="w-10 h-10 rounded-xl bg-surface-container shrink-0 overflow-hidden border border-outline-variant/20 flex items-center justify-center p-1">
                      {img ? (
                        <img
                          src={img}
                          alt={item.name}
                          className="w-full h-full object-contain"
                          loading="lazy"
                        />
                      ) : (
                        <span className="material-symbols-outlined text-outline text-lg">
                          smartphone
                        </span>
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-semibold text-on-surface truncate group-hover:text-primary transition-colors">
                        {item.name}
                      </p>
                      <div className="flex items-center gap-2 mt-0.5">
                        {price && (
                          <span className="text-[11px] font-extrabold text-primary">
                            {formatCurrencyVND(price)}
                          </span>
                        )}
                        {item.brand?.name && (
                          <span className="text-[10px] px-1.5 py-0.2 rounded bg-surface-container text-on-surface-variant font-medium">
                            {item.brand.name}
                          </span>
                        )}
                      </div>
                    </div>
                    <span className="material-symbols-outlined text-outline group-hover:text-primary text-base">
                      chevron_right
                    </span>
                  </Link>
                )
              })}
            </div>
          )}

          {/* View all button */}
          <button
            type="button"
            onClick={() => onSelectSearch(searchQuery)}
            className={`w-full px-4 py-2.5 text-xs text-primary font-bold text-center border-t border-outline-variant/20 transition-colors flex items-center justify-center gap-1.5 ${
              selectedIndex === products.length
                ? 'bg-primary text-on-primary'
                : 'bg-surface-container-low hover:bg-surface-container'
            }`}
          >
            <span>
              {lang === 'zh-CN' ? '查看全部结果' : lang === 'en' ? 'View all results for' : 'Xem tất cả kết quả cho'} &ldquo;{searchQuery}&rdquo; {total > 0 ? `(${total})` : ''}
            </span>
            <span className="material-symbols-outlined text-sm">
              arrow_forward
            </span>
          </button>
        </div>
      )}
    </div>
  )
}

export default function Header() {
  const { user, logout, isAdmin, canAccessAdmin } = useAuth()
  const { itemCount, cartBadgeCount } = useCart()
  const { compareCount } = useCompare()
  const { wishlistCount } = useWishlist()
  const { isDark, toggleTheme } = useTheme()
  const { showToast } = useToast()
  const { lang } = useLanguage()
  const ui = CATEGORY_UI[lang] || CATEGORY_UI.vi
  const navigate = useNavigate()

  const [categories, setCategories] = useState([])
  const categoryTree = useMemo(() => buildCategoryTree(categories), [categories])
  const [searchQuery, setSearchQuery] = useState('')
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [categoryMenuOpen, setCategoryMenuOpen] = useState(false)
  const [mobileCatOpen, setMobileCatOpen] = useState(false)

  // Autocomplete search states
  const [searchSuggestions, setSearchSuggestions] = useState({
    products: [],
    brands: [],
    categories: [],
    total: 0,
  })
  const [loadingSuggestions, setLoadingSuggestions] = useState(false)
  const [showSearchDropdown, setShowSearchDropdown] = useState(false)
  const [recentSearches, setRecentSearches] = useState(() => getRecentSearches())
  const [selectedIndex, setSelectedIndex] = useState(-1)

  // Voice Search states
  const [isListening, setIsListening] = useState(false)
  const recognitionRef = useRef(null)

  const userMenuRef = useRef(null)
  const categoryMenuRef = useRef(null)
  const searchRef = useRef(null)
  const searchInputRef = useRef(null)
  const mobileSearchRef = useRef(null)
  const mobileSearchInputRef = useRef(null)
  const abortControllerRef = useRef(null)

  const debouncedSearch = useDebounce(searchQuery, 300)

  // Fetch categories for navigation dropdown
  useEffect(() => {
    axiosClient
      .get(EP.CATEGORIES)
      .then(({ data }) => setCategories(data.data ?? data))
      .catch(() => {})
  }, [])

  // Global "/" hotkey to focus search
  useEffect(() => {
    const handleGlobalSlash = (e) => {
      if (
        e.key === '/' &&
        !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) &&
        !document.activeElement?.isContentEditable
      ) {
        e.preventDefault()
        if (window.innerWidth >= 1024) {
          searchInputRef.current?.focus()
        } else {
          mobileSearchInputRef.current?.focus()
        }
        setShowSearchDropdown(true)
      }
    }
    window.addEventListener('keydown', handleGlobalSlash)
    return () => window.removeEventListener('keydown', handleGlobalSlash)
  }, [])

  // Live autocomplete search query with AbortController
  useEffect(() => {
    const query = debouncedSearch.trim()
    if (query.length < 2) {
      setSearchSuggestions({ products: [], brands: [], categories: [], total: 0 })
      setSelectedIndex(-1)
      return
    }

    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
    }
    const controller = new AbortController()
    abortControllerRef.current = controller

    setLoadingSuggestions(true)
    setShowSearchDropdown(true)
    setSelectedIndex(-1)

    axiosClient
      .get(EP.PRODUCTS, {
        params: { keyword: query, suggest: 1, per_page: 5, status: 'active' },
        signal: controller.signal,
      })
      .then(({ data }) => {
        const payload = data.data ?? data
        if (
          payload &&
          typeof payload === 'object' &&
          ('products' in payload || 'brands' in payload)
        ) {
          setSearchSuggestions({
            products: payload.products ?? [],
            brands: payload.brands ?? [],
            categories: payload.categories ?? [],
            total: payload.total ?? (payload.products?.length || 0),
            intent: payload.intent ?? null,
            has_typo: payload.has_typo ?? false,
            suggested_query: payload.suggested_query ?? null,
            model_alias: payload.model_alias ?? null,
          })
        } else {
          const items = Array.isArray(payload) ? payload : payload.data ?? []
          setSearchSuggestions({
            products: items,
            brands: [],
            categories: [],
            total: items.length,
            intent: null,
            has_typo: false,
            suggested_query: null,
            model_alias: null,
          })
        }
      })
      .catch((err) => {
        if (err?.code !== 'ERR_CANCELED' && err?.name !== 'CanceledError') {
          setSearchSuggestions({ products: [], brands: [], categories: [], total: 0 })
        }
      })
      .finally(() => {
        setLoadingSuggestions(false)
      })

    return () => {
      controller.abort()
    }
  }, [debouncedSearch])

  // Execute search and save to history
  const executeSearch = (query) => {
    const clean = typeof query === 'string' ? query.trim() : ''
    if (!clean) return
    addRecentSearch(clean)
    setRecentSearches(getRecentSearches())
    setShowSearchDropdown(false)
    setSelectedIndex(-1)
    navigate(`/products?q=${encodeURIComponent(clean)}`)
  }

  // Handle search submission
  const handleSearch = (e) => {
    e.preventDefault()
    executeSearch(searchQuery)
  }

  // Handle keyboard navigation inside search input
  const handleSearchKeyDown = (e) => {
    if (!showSearchDropdown) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        setShowSearchDropdown(true)
      }
      return
    }

    const isShortQuery = searchQuery.trim().length < 2
    const productsCount = searchSuggestions.products?.length || 0
    const totalItems = isShortQuery ? recentSearches.length : productsCount + 1

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      if (totalItems > 0) {
        setSelectedIndex((prev) => (prev + 1 >= totalItems ? 0 : prev + 1))
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      if (totalItems > 0) {
        setSelectedIndex((prev) => (prev <= 0 ? totalItems - 1 : prev - 1))
      }
    } else if (e.key === 'Enter') {
      if (selectedIndex >= 0) {
        e.preventDefault()
        if (isShortQuery) {
          const selected = recentSearches[selectedIndex]
          if (selected) executeSearch(selected)
        } else {
          if (selectedIndex < productsCount) {
            const prod = searchSuggestions.products[selectedIndex]
            if (prod) {
              addRecentSearch(prod.name)
              setShowSearchDropdown(false)
              navigate(`/products/${prod.slug ?? prod.id}`)
            }
          } else {
            // "View all" item selected
            executeSearch(searchQuery)
          }
        }
      }
    } else if (e.key === 'Escape') {
      setShowSearchDropdown(false)
      setSelectedIndex(-1)
    }
  }

  // Voice search via Web Speech API
  const handleVoiceSearch = () => {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SpeechRecognition) {
      showToast(
        'Trình duyệt không hỗ trợ nhận diện giọng nói. Vui lòng dùng Chrome hoặc Edge.',
        'warning'
      )
      return
    }

    if (isListening) {
      if (recognitionRef.current) {
        recognitionRef.current.stop()
      }
      setIsListening(false)
      return
    }

    try {
      const recognition = new SpeechRecognition()
      recognition.lang = 'vi-VN'
      recognition.continuous = false
      recognition.interimResults = false

      recognition.onstart = () => {
        setIsListening(true)
        showToast(
          'Đang lắng nghe... Hãy nói tên thiết bị (VD: iPhone 16 Pro)',
          'info'
        )
      }

      recognition.onresult = (event) => {
        const transcript = event.results?.[0]?.[0]?.transcript || ''
        if (transcript.trim()) {
          setSearchQuery(transcript.trim())
          setShowSearchDropdown(true)
          showToast(`Đã nhận diện giọng nói: "${transcript.trim()}"`, 'success')
        }
      }

      recognition.onerror = (e) => {
        setIsListening(false)
        if (e.error !== 'no-speech') {
          showToast(
            'Không nhận diện được âm thanh. Vui lòng thử lại.',
            'warning'
          )
        }
      }

      recognition.onend = () => {
        setIsListening(false)
      }

      recognitionRef.current = recognition
      recognition.start()
    } catch {
      setIsListening(false)
      showToast('Không thể kích hoạt micro.', 'error')
    }
  }

  // Close menus on outside click or ESC key
  useEffect(() => {
    const clickHandler = (e) => {
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setUserMenuOpen(false)
      }
      if (
        categoryMenuRef.current &&
        !categoryMenuRef.current.contains(e.target)
      ) {
        setCategoryMenuOpen(false)
      }
      if (
        searchRef.current &&
        !searchRef.current.contains(e.target) &&
        (!mobileSearchRef.current || !mobileSearchRef.current.contains(e.target))
      ) {
        setShowSearchDropdown(false)
        setSelectedIndex(-1)
      }
    }

    const keyHandler = (e) => {
      if (e.key === 'Escape') {
        setShowSearchDropdown(false)
        setSelectedIndex(-1)
        setUserMenuOpen(false)
        setCategoryMenuOpen(false)
        setMobileMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', clickHandler)
    document.addEventListener('keydown', keyHandler)
    return () => {
      document.removeEventListener('mousedown', clickHandler)
      document.removeEventListener('keydown', keyHandler)
    }
  }, [])

  const handleLogout = async () => {
    await logout()
    showToast('Đã đăng xuất thành công', 'success')
    const protectedPrefixes = ['/account', '/checkout', '/orders']
    if (protectedPrefixes.some((p) => window.location.pathname.startsWith(p))) {
      navigate('/login')
    }
  }

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-surface/90 backdrop-blur-xl border-b border-outline-variant/20 shadow-sm transition-colors duration-200">
      {/* ── DESKTOP & TABLET HEADER (Single Row, Height 80px) ── */}
      <div className="hidden lg:flex h-20 w-full max-w-7xl mx-auto px-4 md:px-8 lg:px-12 items-center justify-between gap-4">
        {/* ── GROUP A: LOGO, SEARCH & NAVIGATION ── */}
        <div className="flex items-center gap-3 xl:gap-5 flex-1 min-w-0">
          {/* 1. MobileX Logo (Navigates to Home; Home menu item is removed) */}
          <Link
            to="/"
            translate="no"
            className="flex items-center gap-2 shrink-0 group focus-visible:outline-2 focus-visible:outline-primary rounded-xl notranslate"
            aria-label="MobileX — Trang chủ"
          >
            <span translate="no" className="text-2xl font-extrabold tracking-tight text-on-surface group-hover:text-primary transition-colors notranslate">
              Mobile<span className="text-primary font-black">X</span>
            </span>
          </Link>

          {/* 2. Flexible Search Bar with Autocomplete, History & Voice Search */}
          <div
            ref={searchRef}
            className="relative flex-1 max-w-md xl:max-w-xl mx-1"
          >
            <form onSubmit={handleSearch}>
              <div className="relative flex items-center w-full bg-surface-container-low/90 hover:bg-surface-container border border-outline-variant/30 rounded-2xl px-3.5 py-2 transition-all duration-200 focus-within:ring-2 focus-within:ring-primary/40 focus-within:border-primary/50 focus-within:bg-surface-container">
                <span className="material-symbols-outlined text-outline mr-2 text-lg select-none">
                  search
                </span>
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value)
                    setSelectedIndex(-1)
                    if (!showSearchDropdown) setShowSearchDropdown(true)
                  }}
                  onFocus={() => {
                    setShowSearchDropdown(true)
                    setSelectedIndex(-1)
                  }}
                  onKeyDown={handleSearchKeyDown}
                  className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline/80 focus:outline-none"
                  placeholder={lang === 'zh-CN' ? '搜索商品... (按 / 聚焦)' : lang === 'en' ? 'Search products... (Press /)' : 'Tìm kiếm sản phẩm... (Nhấn /)'}
                  aria-label={lang === 'zh-CN' ? '搜索商品' : lang === 'en' ? 'Search products' : 'Tìm kiếm sản phẩm'}
                />
                <kbd className="hidden xl:inline-block px-1.5 py-0.5 text-[10px] font-mono font-semibold text-outline bg-surface-container rounded border border-outline-variant/30 mr-1 select-none">
                  /
                </kbd>
                {searchQuery && (
                  <button
                    type="button"
                    onClick={() => {
                      setSearchQuery('')
                      setSearchSuggestions({ products: [], brands: [], categories: [], total: 0 })
                      setSelectedIndex(-1)
                    }}
                    className="text-outline hover:text-on-surface text-sm ml-1 p-0.5 rounded-md transition-colors"
                    aria-label="Xóa từ khóa tìm kiếm"
                  >
                    <span className="material-symbols-outlined text-base">
                      close
                    </span>
                  </button>
                )}
                {/* Voice Search Button */}
                <button
                  type="button"
                  onClick={handleVoiceSearch}
                  aria-label="Tìm kiếm bằng giọng nói"
                  title={
                    isListening
                      ? 'Đang lắng nghe... Bấm để dừng'
                      : 'Tìm kiếm bằng giọng nói'
                  }
                  className={`p-1.5 ml-1 rounded-xl transition-all flex items-center justify-center ${
                    isListening
                      ? 'text-error animate-pulse bg-error-container/20 ring-2 ring-error/50'
                      : 'text-outline hover:text-primary hover:bg-surface-container-high'
                  }`}
                >
                  <span className="material-symbols-outlined text-lg">
                    {isListening ? 'mic' : 'mic_none'}
                  </span>
                </button>
              </div>
            </form>

            {/* Smart Search Dropdown (History / Popular / Autocomplete) */}
            {showSearchDropdown && (
              <SearchDropdownPanel
                searchQuery={searchQuery}
                suggestions={searchSuggestions}
                loadingSuggestions={loadingSuggestions}
                recentSearches={recentSearches}
                selectedIndex={selectedIndex}
                onSelectSearch={executeSearch}
                onSelectBrand={(brand) => {
                  addRecentSearch(brand.name)
                  setRecentSearches(getRecentSearches())
                  setShowSearchDropdown(false)
                  navigate(`/products?brand_id=${brand.id}`)
                }}
                onSelectCategory={(cat) => {
                  addRecentSearch(cat.name)
                  setRecentSearches(getRecentSearches())
                  setShowSearchDropdown(false)
                  navigate(`/products?category_id=${cat.id}`)
                }}
                onRemoveHistory={(term) => {
                  setRecentSearches(removeRecentSearch(term))
                }}
                onClearHistory={() => {
                  setRecentSearches(clearRecentSearches())
                }}
                onClose={() => {
                  setShowSearchDropdown(false)
                  setSelectedIndex(-1)
                }}
                lang={lang}
                isMobile={false}
              />
            )}
          </div>

          {/* 3. Category Mega Menu (Compact, Clean, Master-Detail Two-Column Layout) */}
          <CategoryMegaMenu
            categories={categories}
            isOpen={categoryMenuOpen}
            onToggle={() => setCategoryMenuOpen((v) => !v)}
            onClose={() => setCategoryMenuOpen(false)}
            categoryMenuRef={categoryMenuRef}
          />

          {/* 4. Products Navigation Link */}
          <Link
            to="/products"
            className="px-3 py-2 rounded-xl text-xs xl:text-sm font-semibold text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors shrink-0 whitespace-nowrap"
          >
            {ui.categoryTitle ? (lang === 'zh-CN' ? '全部商品' : lang === 'en' ? 'Products' : 'Sản phẩm') : 'Sản phẩm'}
          </Link>

          {/* 5. Order Tracking Icon Button (Refactored from text to clean icon with tooltip) */}
          <Link
            to="/tracking"
            aria-label={lang === 'zh-CN' ? '查询订单' : lang === 'en' ? 'Track Order' : 'Tra cứu đơn hàng'}
            title={lang === 'zh-CN' ? '查询订单' : lang === 'en' ? 'Track Order' : 'Tra cứu đơn hàng'}
            className="h-9 w-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-container transition-colors shrink-0 focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span className="material-symbols-outlined text-xl">
              local_shipping
            </span>
          </Link>
        </div>

        {/* ── GROUP B: UTILITIES (Exact Order: Language -> Theme -> Compare -> Wishlist -> Cart -> Account) ── */}
        <div className="flex flex-nowrap items-center gap-1.5 xl:gap-2.5 shrink-0">
          {/* 1. Language Selector (GTranslate Official Integration) */}
          <LanguageSelector />

          {/* 2. Theme Toggle (Light / Dark mode) */}
          <button
            type="button"
            onClick={toggleTheme}
            aria-label={
              isDark
                ? 'Chuyển sang giao diện sáng'
                : 'Chuyển sang giao diện tối'
            }
            title={
              isDark
                ? 'Chuyển sang giao diện sáng'
                : 'Chuyển sang giao diện tối'
            }
            className="h-9 w-9 rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-primary transition-colors flex items-center justify-center shrink-0 focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span className="material-symbols-outlined text-xl">
              {isDark ? 'light_mode' : 'dark_mode'}
            </span>
          </button>

          {/* 3. Product Compare with count badge */}
          <Link
            to="/compare"
            aria-label="So sánh sản phẩm"
            title="So sánh sản phẩm"
            className="relative h-9 w-9 rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-primary transition-colors flex items-center justify-center shrink-0 focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span className="material-symbols-outlined text-xl">
              compare_arrows
            </span>
            {compareCount > 0 && (
              <span translate="no" className="absolute -top-1 -right-1 flex min-w-[1.125rem] h-[1.125rem] px-1 items-center justify-center rounded-full bg-primary text-on-primary text-[10px] font-bold shadow-sm notranslate">
                {compareCount}
              </span>
            )}
          </Link>

          {/* 4. Wishlist with unified RED badge (#EF4444) */}
          <Link
            to="/wishlist"
            aria-label="Danh sách yêu thích"
            title="Danh sách yêu thích"
            className="relative h-9 w-9 rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-primary transition-colors flex items-center justify-center shrink-0 focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span className="material-symbols-outlined text-xl">
              favorite_border
            </span>
            {wishlistCount > 0 && (
              <span translate="no" className="absolute -top-1 -right-1 flex min-w-[1.125rem] h-[1.125rem] px-1 items-center justify-center rounded-full bg-[#ef4444] text-white text-[10px] font-bold shadow-sm animate-in zoom-in-75 notranslate">
                {wishlistCount}
              </span>
            )}
          </Link>

          {/* 5. Cart with unified RED badge (#EF4444 — exactly matches Wishlist badge) */}
          <Link
            to="/cart"
            aria-label="Giỏ hàng"
            title="Giỏ hàng"
            className="relative h-9 w-9 rounded-xl text-on-surface-variant hover:bg-surface-container hover:text-primary transition-colors flex items-center justify-center shrink-0 focus-visible:outline-2 focus-visible:outline-primary"
          >
            <span className="material-symbols-outlined text-xl">
              shopping_bag
            </span>
            {itemCount > 0 && (
              <span translate="no" className="absolute -top-1 -right-1 flex min-w-[1.125rem] h-[1.125rem] px-1 items-center justify-center rounded-full bg-[#ef4444] text-white text-[10px] font-bold shadow-sm animate-in zoom-in-75 notranslate">
                {cartBadgeCount}
              </span>
            )}
          </Link>

          {/* 6. User Account Menu or Login Button */}
          {user ? (
            <div className="relative" ref={userMenuRef}>
              <button
                type="button"
                onClick={() => setUserMenuOpen((v) => !v)}
                className={`h-9 flex items-center gap-1.5 rounded-xl px-2.5 transition-all border ${
                  userMenuOpen
                    ? 'bg-surface-container text-primary border-primary/30 ring-2 ring-primary/20'
                    : 'hover:bg-surface-container text-on-surface border-transparent'
                }`}
                aria-label="Tài khoản người dùng"
                aria-expanded={userMenuOpen}
              >
                <span className="material-symbols-outlined text-on-surface-variant text-xl">
                  account_circle
                </span>
                <span className="text-xs font-semibold text-on-surface max-w-[110px] truncate">
                  {user.name}
                </span>
                <span
                  className={`material-symbols-outlined text-outline text-sm transition-transform duration-200 ${
                    userMenuOpen ? 'rotate-180 text-primary' : ''
                  }`}
                >
                  expand_more
                </span>
              </button>

              {userMenuOpen && (
                <div className="absolute right-0 top-full mt-2 w-52 bg-surface-container-high/95 backdrop-blur-xl rounded-2xl p-1.5 shadow-2xl border border-outline-variant/30 z-50 animate-in fade-in zoom-in-95 duration-150">
                  <div className="px-3 py-2 border-b border-outline-variant/15 mb-1">
                    <p className="text-xs font-bold text-on-surface truncate">
                      {user.name}
                    </p>
                    <p translate="no" className="text-[11px] text-outline truncate notranslate">
                      {user.email}
                    </p>
                  </div>
                  <Link
                    to="/account/profile"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-xs font-medium"
                  >
                    <span className="material-symbols-outlined text-base">
                      manage_accounts
                    </span>
                    Tài khoản của tôi
                  </Link>
                  <Link
                    to="/account/addresses"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-xs font-medium"
                  >
                    <span className="material-symbols-outlined text-base">
                      pin_drop
                    </span>
                    Sổ địa chỉ nhận hàng
                  </Link>
                  <Link
                    to="/orders"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-xs font-medium"
                  >
                    <span className="material-symbols-outlined text-base">
                      receipt_long
                    </span>
                    Đơn hàng của tôi
                  </Link>
                  {(isAdmin() || canAccessAdmin?.()) && (
                    <Link
                      to="/admin"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-2 px-3 py-2 rounded-xl text-primary hover:bg-primary/10 transition-colors text-xs font-bold"
                    >
                      <span className="material-symbols-outlined text-base">
                        admin_panel_settings
                      </span>
                      Quản trị
                    </Link>
                  )}
                  <button
                    type="button"
                    onClick={handleLogout}
                    className="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-error hover:bg-error-container/20 transition-colors text-xs font-semibold mt-1"
                  >
                    <span className="material-symbols-outlined text-base">
                      logout
                    </span>
                    Đăng xuất
                  </button>
                </div>
              )}
            </div>
          ) : (
            <Link
              to="/login"
              className="h-9 px-3.5 rounded-xl bg-primary-container text-on-primary-container text-xs font-bold hover:bg-primary hover:text-on-primary transition-all flex items-center justify-center shadow-sm"
            >
              Đăng nhập
            </Link>
          )}
        </div>
      </div>

      {/* ── MOBILE HEADER (2-Row Layout) ── */}
      <div className="lg:hidden w-full px-3.5 pt-2.5 pb-2">
        {/* ROW 1: Logo MobileX (Left) + Quick Utilities & Menu Toggle (Right) */}
        <div className="flex items-center justify-between gap-2 h-11">
          {/* Logo MobileX */}
          <Link
            to="/"
            translate="no"
            className="flex items-center gap-1.5 shrink-0 py-1 notranslate"
            aria-label="MobileX — Trang chủ"
          >
            <span translate="no" className="text-xl font-extrabold tracking-tight text-on-surface notranslate">
              Mobile<span className="text-primary font-black">X</span>
            </span>
          </Link>

          {/* Quick Actions on Mobile */}
          <div className="flex items-center gap-1.5 shrink-0">
            {/* Language Selector */}
            <LanguageSelector />

            {/* Wishlist Icon */}
            <Link
              to="/wishlist"
              aria-label="Danh sách yêu thích"
              className="relative h-9 w-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container transition-colors"
            >
              <span className="material-symbols-outlined text-xl">
                favorite_border
              </span>
              {wishlistCount > 0 && (
                <span translate="no" className="absolute -top-0.5 -right-0.5 flex min-w-[1rem] h-[1rem] px-0.5 items-center justify-center rounded-full bg-[#ef4444] text-white text-[9px] font-bold shadow-sm notranslate">
                  {wishlistCount}
                </span>
              )}
            </Link>

            {/* Cart Icon */}
            <Link
              to="/cart"
              aria-label="Giỏ hàng"
              className="relative h-9 w-9 rounded-xl flex items-center justify-center text-on-surface-variant hover:bg-surface-container transition-colors"
            >
              <span className="material-symbols-outlined text-xl">
                shopping_bag
              </span>
              {itemCount > 0 && (
                <span translate="no" className="absolute -top-0.5 -right-0.5 flex min-w-[1rem] h-[1rem] px-0.5 items-center justify-center rounded-full bg-[#ef4444] text-white text-[9px] font-bold shadow-sm notranslate">
                  {cartBadgeCount}
                </span>
              )}
            </Link>

            {/* Mobile Menu Hamburger / Close */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen((v) => !v)}
              className="h-9 w-9 rounded-xl flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors"
              aria-label="Mở menu điều hướng"
              aria-expanded={mobileMenuOpen}
            >
              <span className="material-symbols-outlined text-xl">
                {mobileMenuOpen ? 'close' : 'menu'}
              </span>
            </button>
          </div>
        </div>

        {/* ROW 2: Full-Width Search Bar */}
        <div ref={mobileSearchRef} className="relative mt-2">
          <form onSubmit={handleSearch}>
            <div className="relative flex items-center w-full bg-surface-container-low border border-outline-variant/30 rounded-xl px-3 py-1.5 focus-within:ring-2 focus-within:ring-primary/40 focus-within:border-primary/50">
              <span className="material-symbols-outlined text-outline text-base mr-2 select-none">
                search
              </span>
              <input
                ref={mobileSearchInputRef}
                type="text"
                value={searchQuery}
                onChange={(e) => {
                  setSearchQuery(e.target.value)
                  setSelectedIndex(-1)
                  if (!showSearchDropdown) setShowSearchDropdown(true)
                }}
                onFocus={() => {
                  setShowSearchDropdown(true)
                  setSelectedIndex(-1)
                }}
                onKeyDown={handleSearchKeyDown}
                className="w-full bg-transparent text-xs text-on-surface placeholder:text-outline focus:outline-none"
                placeholder={lang === 'zh-CN' ? '搜索商品...' : lang === 'en' ? 'Search for products...' : 'Tìm kiếm sản phẩm...'}
                aria-label={lang === 'zh-CN' ? '搜索商品' : lang === 'en' ? 'Search products' : 'Tìm kiếm sản phẩm'}
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => {
                    setSearchQuery('')
                    setSearchSuggestions({ products: [], brands: [], categories: [], total: 0 })
                    setSelectedIndex(-1)
                  }}
                  className="text-outline hover:text-on-surface text-sm ml-1 p-0.5"
                  aria-label="Xóa từ khóa"
                >
                  <span className="material-symbols-outlined text-base">
                    close
                  </span>
                </button>
              )}
              <button
                type="button"
                onClick={handleVoiceSearch}
                aria-label="Tìm kiếm bằng giọng nói"
                className={`p-1 ml-1 rounded-lg transition-colors flex items-center justify-center ${
                  isListening
                    ? 'text-error animate-pulse bg-error-container/20'
                    : 'text-outline hover:text-primary'
                }`}
              >
                <span className="material-symbols-outlined text-base">
                  {isListening ? 'mic' : 'mic_none'}
                </span>
              </button>
            </div>
          </form>

          {/* Mobile Autocomplete / History Dropdown */}
          {showSearchDropdown && (
            <SearchDropdownPanel
              searchQuery={searchQuery}
              suggestions={searchSuggestions}
              loadingSuggestions={loadingSuggestions}
              recentSearches={recentSearches}
              selectedIndex={selectedIndex}
              onSelectSearch={executeSearch}
              onSelectBrand={(brand) => {
                addRecentSearch(brand.name)
                setRecentSearches(getRecentSearches())
                setShowSearchDropdown(false)
                navigate(`/products?brand_id=${brand.id}`)
              }}
              onSelectCategory={(cat) => {
                addRecentSearch(cat.name)
                setRecentSearches(getRecentSearches())
                setShowSearchDropdown(false)
                navigate(`/products?category_id=${cat.id}`)
              }}
              onRemoveHistory={(term) => {
                setRecentSearches(removeRecentSearch(term))
              }}
              onClearHistory={() => {
                setRecentSearches(clearRecentSearches())
              }}
              onClose={() => {
                setShowSearchDropdown(false)
                setSelectedIndex(-1)
              }}
              lang={lang}
              isMobile={true}
            />
          )}
        </div>
      </div>

      {/* ── MOBILE COLLAPSIBLE DRAWER MENU ── */}
      {mobileMenuOpen && (
        <div className="lg:hidden bg-surface-container-low/95 backdrop-blur-xl border-t border-outline-variant/20 px-4 py-3 max-h-[calc(100vh-120px)] overflow-y-auto animate-in slide-in-from-top-2 duration-200">
          {/* User Account Section */}
          {user ? (
            <div className="bg-surface-container rounded-2xl p-3 mb-3 border border-outline-variant/20">
              <div className="flex items-center gap-2.5 mb-2 pb-2 border-b border-outline-variant/20">
                <span className="material-symbols-outlined text-2xl text-primary">
                  account_circle
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-xs font-bold text-on-surface truncate">
                    {user.name}
                  </p>
                  <p className="text-[11px] text-outline truncate">
                    {user.email}
                  </p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-1.5 text-xs">
                <Link
                  to="/account/profile"
                  onClick={() => setMobileMenuOpen(false)}
                  className="p-2 rounded-xl bg-surface-container-low hover:bg-surface-bright text-on-surface text-center font-medium"
                >
                  Tài khoản
                </Link>
                <Link
                  to="/orders"
                  onClick={() => setMobileMenuOpen(false)}
                  className="p-2 rounded-xl bg-surface-container-low hover:bg-surface-bright text-on-surface text-center font-medium"
                >
                  Đơn hàng
                </Link>
                {(isAdmin() || canAccessAdmin?.()) && (
                  <Link
                    to="/admin"
                    onClick={() => setMobileMenuOpen(false)}
                    className="col-span-2 p-2 rounded-xl bg-primary/10 text-primary text-center font-bold"
                  >
                    Trang quản trị (Admin)
                  </Link>
                )}
                <button
                  type="button"
                  onClick={handleLogout}
                  className="col-span-2 p-2 rounded-xl bg-error-container/20 text-error text-center font-semibold"
                >
                  Đăng xuất
                </button>
              </div>
            </div>
          ) : (
            <div className="mb-3">
              <Link
                to="/login"
                onClick={() => setMobileMenuOpen(false)}
                className="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-bold text-xs shadow-sm"
              >
                <span className="material-symbols-outlined text-base">
                  login
                </span>
                Đăng nhập tài khoản
              </Link>
            </div>
          )}

          {/* Navigation Links */}
          <nav className="flex flex-col gap-1 text-xs">
            {/* Category Collapsible */}
            <div className="border-b border-outline-variant/15 pb-1 mb-1">
              <button
                type="button"
                onClick={() => setMobileCatOpen((v) => !v)}
                className="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-bold text-on-surface hover:bg-surface-container transition-colors"
              >
                <span className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-base text-primary">
                    grid_view
                  </span>
                  {ui.categoryTitle}
                </span>
                <span
                  className={`material-symbols-outlined text-sm transition-transform ${
                    mobileCatOpen ? 'rotate-180 text-primary' : 'text-outline'
                  }`}
                >
                  expand_more
                </span>
              </button>

              {mobileCatOpen && (
                <div className="pl-3 pr-2 py-1 space-y-2.5 max-h-60 overflow-y-auto">
                  <Link
                    to="/products"
                    onClick={() => {
                      setMobileCatOpen(false)
                      setMobileMenuOpen(false)
                    }}
                    className="block text-xs font-bold text-primary py-1 border-b border-outline-variant/15"
                  >
                    {ui.allProducts} →
                  </Link>
                  {categoryTree.map((cat) => (
                    <div key={cat.id} className="space-y-1">
                      <Link
                        to={`/products?category_id=${cat.id}`}
                        onClick={() => {
                          setMobileCatOpen(false)
                          setMobileMenuOpen(false)
                        }}
                        className="flex items-center gap-2 text-xs font-bold text-on-surface hover:text-primary py-0.5"
                      >
                        <span className="material-symbols-outlined text-sm text-primary">
                          {getCategoryIcon(cat.slug, cat.name)}
                        </span>
                        <span>{getLocalizedCategoryName(cat, lang)}</span>
                      </Link>
                      {cat.children && cat.children.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 pl-6">
                          {cat.children.map((child) => (
                            <Link
                              key={child.id}
                              to={`/products?category_id=${child.id}`}
                              onClick={() => {
                                setMobileCatOpen(false)
                                setMobileMenuOpen(false)
                              }}
                              className="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-surface-container hover:bg-primary-container text-on-surface-variant hover:text-on-primary-container transition-all"
                            >
                              {getLocalizedCategoryName(child, lang)}
                            </Link>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <Link
              to="/products"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center gap-2 px-3 py-2.5 rounded-xl font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
            >
              <span className="material-symbols-outlined text-base">
                smartphone
              </span>
              {ui.allProducts}
            </Link>

            <Link
              to="/tracking"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center gap-2 px-3 py-2.5 rounded-xl font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
            >
              <span className="material-symbols-outlined text-base">
                local_shipping
              </span>
              {lang === 'zh-CN' ? '查询订单' : lang === 'en' ? 'Track Order' : 'Tra cứu đơn hàng'}
            </Link>

            <Link
              to="/compare"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
            >
              <span className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base">
                  compare_arrows
                </span>
                So sánh sản phẩm
              </span>
              {compareCount > 0 && (
                <span className="px-1.5 py-0.5 rounded-full bg-primary text-on-primary text-[10px] font-bold">
                  {compareCount}
                </span>
              )}
            </Link>

            {/* Dark/Light mode toggle in mobile drawer */}
            <button
              type="button"
              onClick={toggleTheme}
              className="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors text-left"
            >
              <span className="flex items-center gap-2">
                <span className="material-symbols-outlined text-base">
                  {isDark ? 'light_mode' : 'dark_mode'}
                </span>
                {isDark ? (lang === 'zh-CN' ? '浅色模式' : lang === 'en' ? 'Light Mode' : 'Giao diện sáng') : (lang === 'zh-CN' ? '深色模式' : lang === 'en' ? 'Dark Mode' : 'Giao diện tối')}
              </span>
              <span className="text-[11px] text-outline uppercase font-bold">
                {isDark ? 'Dark' : 'Light'}
              </span>
            </button>
          </nav>
        </div>
      )}
    </header>
  )
}
