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
import { useDebounce } from '../../hooks/useDebounce'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import LanguageSelector from './LanguageSelector'
import CategoryMegaMenu, {
  buildCategoryTree,
  getCategoryIcon,
} from './CategoryMegaMenu'

export default function Header() {
  const { user, logout, isAdmin } = useAuth()
  const { itemCount, cartBadgeCount } = useCart()
  const { compareCount } = useCompare()
  const { wishlistCount } = useWishlist()
  const { isDark, toggleTheme } = useTheme()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [categories, setCategories] = useState([])
  const categoryTree = useMemo(() => buildCategoryTree(categories), [categories])
  const [searchQuery, setSearchQuery] = useState('')
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [categoryMenuOpen, setCategoryMenuOpen] = useState(false)
  const [mobileCatOpen, setMobileCatOpen] = useState(false)

  // Autocomplete search states
  const [searchSuggestions, setSearchSuggestions] = useState([])
  const [loadingSuggestions, setLoadingSuggestions] = useState(false)
  const [showSearchDropdown, setShowSearchDropdown] = useState(false)

  // Voice Search states
  const [isListening, setIsListening] = useState(false)
  const recognitionRef = useRef(null)

  const userMenuRef = useRef(null)
  const categoryMenuRef = useRef(null)
  const searchRef = useRef(null)
  const mobileSearchRef = useRef(null)

  const debouncedSearch = useDebounce(searchQuery, 300)

  // Fetch categories for navigation dropdown
  useEffect(() => {
    axiosClient
      .get(EP.CATEGORIES)
      .then(({ data }) => setCategories(data.data ?? data))
      .catch(() => {})
  }, [])

  // Live autocomplete search query
  useEffect(() => {
    const query = debouncedSearch.trim()
    if (query.length < 2) {
      setSearchSuggestions([])
      setShowSearchDropdown(false)
      return
    }

    setLoadingSuggestions(true)
    setShowSearchDropdown(true)
    axiosClient
      .get(EP.PRODUCTS, { params: { keyword: query, per_page: 5 } })
      .then(({ data }) => {
        const payload = data.data ?? data
        const items = Array.isArray(payload) ? payload : payload.data ?? []
        setSearchSuggestions(items)
      })
      .catch(() => {
        setSearchSuggestions([])
      })
      .finally(() => {
        setLoadingSuggestions(false)
      })
  }, [debouncedSearch])

  // Handle search submission
  const handleSearch = (e) => {
    e.preventDefault()
    setShowSearchDropdown(false)
    if (searchQuery.trim()) {
      navigate(`/products?q=${encodeURIComponent(searchQuery.trim())}`)
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
        mobileSearchRef.current &&
        !mobileSearchRef.current.contains(e.target)
      ) {
        setShowSearchDropdown(false)
      }
    }

    const keyHandler = (e) => {
      if (e.key === 'Escape') {
        setShowSearchDropdown(false)
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
    navigate('/login')
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

          {/* 2. Flexible Search Bar with Autocomplete & Voice Search */}
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
                  type="text"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value)
                    if (
                      !showSearchDropdown &&
                      e.target.value.trim().length >= 2
                    ) {
                      setShowSearchDropdown(true)
                    }
                  }}
                  onFocus={() => {
                    if (
                      searchSuggestions.length > 0 ||
                      searchQuery.trim().length >= 2
                    ) {
                      setShowSearchDropdown(true)
                    }
                  }}
                  className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline/80 focus:outline-none"
                  placeholder="Tìm kiếm sản phẩm..."
                  aria-label="Tìm kiếm sản phẩm"
                />
                {searchQuery && (
                  <button
                    type="button"
                    onClick={() => {
                      setSearchQuery('')
                      setSearchSuggestions([])
                      setShowSearchDropdown(false)
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

            {/* Live Autocomplete Dropdown */}
            {showSearchDropdown && searchQuery.trim().length >= 2 && (
              <div className="absolute top-full left-0 right-0 mt-2 bg-surface-container-high/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50">
                <div className="px-4 py-2.5 border-b border-outline-variant/20 flex items-center justify-between text-xs text-on-surface-variant font-medium">
                  <span>Gợi ý tìm kiếm ({searchSuggestions.length})</span>
                  {loadingSuggestions && (
                    <span className="flex items-center gap-1.5 text-primary">
                      <span className="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                      Đang tìm...
                    </span>
                  )}
                </div>

                {loadingSuggestions && searchSuggestions.length === 0 ? (
                  <div className="py-6 text-center text-xs text-on-surface-variant">
                    Đang tìm sản phẩm phù hợp...
                  </div>
                ) : searchSuggestions.length === 0 ? (
                  <div className="py-6 px-4 text-center text-xs text-on-surface-variant">
                    Không tìm thấy sản phẩm nào khớp với &ldquo;
                    {searchQuery}&rdquo;
                  </div>
                ) : (
                  <div className="divide-y divide-outline-variant/10 max-h-80 overflow-y-auto">
                    {searchSuggestions.map((item) => {
                      const img =
                        item.images?.find((i) => i.is_primary)?.image_path ||
                        item.thumbnail ||
                        item.images?.[0]?.image_path
                      const price =
                        item.min_price ||
                        item.price ||
                        item.base_price ||
                        item.variants?.[0]?.price
                      return (
                        <Link
                          key={item.id}
                          to={`/products/${item.slug || item.id}`}
                          onClick={() => {
                            setShowSearchDropdown(false)
                            setSearchQuery('')
                          }}
                          className="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-bright transition-colors group"
                        >
                          <div className="w-10 h-10 rounded-xl bg-surface-container-low shrink-0 overflow-hidden border border-outline-variant/30 flex items-center justify-center p-1">
                            {img ? (
                              <img
                                src={img}
                                alt={item.name}
                                className="w-full h-full object-contain"
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
                            {price && (
                              <p className="text-[11px] font-bold text-primary">
                                {formatCurrencyVND(price)}
                              </p>
                            )}
                          </div>
                          <span className="material-symbols-outlined text-outline-variant group-hover:text-primary text-base">
                            chevron_right
                          </span>
                        </Link>
                      )
                    })}
                  </div>
                )}

                {searchQuery.trim() && (
                  <button
                    type="button"
                    onClick={() => {
                      setShowSearchDropdown(false)
                      navigate(
                        `/products?q=${encodeURIComponent(searchQuery.trim())}`
                      )
                    }}
                    className="w-full px-4 py-2.5 bg-surface-container-low hover:bg-surface-container text-xs text-primary font-semibold text-center border-t border-outline-variant/20 transition-colors flex items-center justify-center gap-1"
                  >
                    <span>
                      Xem tất cả kết quả cho &ldquo;{searchQuery}&rdquo;
                    </span>
                    <span className="material-symbols-outlined text-sm">
                      arrow_forward
                    </span>
                  </button>
                )}
              </div>
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
            Sản phẩm
          </Link>

          {/* 5. Order Tracking Icon Button (Refactored from text to clean icon with tooltip) */}
          <Link
            to="/tracking"
            aria-label="Tra cứu đơn hàng"
            title="Tra cứu đơn hàng"
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
                  {isAdmin() && (
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
                type="text"
                value={searchQuery}
                onChange={(e) => {
                  setSearchQuery(e.target.value)
                  if (!showSearchDropdown && e.target.value.trim().length >= 2) {
                    setShowSearchDropdown(true)
                  }
                }}
                onFocus={() => {
                  if (
                    searchSuggestions.length > 0 ||
                    searchQuery.trim().length >= 2
                  ) {
                    setShowSearchDropdown(true)
                  }
                }}
                className="w-full bg-transparent text-xs text-on-surface placeholder:text-outline focus:outline-none"
                placeholder="Tìm kiếm sản phẩm..."
                aria-label="Tìm kiếm sản phẩm"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => {
                    setSearchQuery('')
                    setSearchSuggestions([])
                    setShowSearchDropdown(false)
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

          {/* Mobile Autocomplete Dropdown */}
          {showSearchDropdown && searchQuery.trim().length >= 2 && (
            <div className="absolute top-full left-0 right-0 mt-1.5 bg-surface-container-high rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50">
              <div className="px-3.5 py-2 border-b border-outline-variant/20 flex items-center justify-between text-[11px] text-on-surface-variant font-medium">
                <span>Gợi ý ({searchSuggestions.length})</span>
                {loadingSuggestions && (
                  <span className="text-primary text-[10px]">Đang tìm...</span>
                )}
              </div>
              {searchSuggestions.slice(0, 4).map((item) => (
                <Link
                  key={item.id}
                  to={`/products/${item.slug || item.id}`}
                  onClick={() => {
                    setShowSearchDropdown(false)
                    setSearchQuery('')
                  }}
                  className="flex items-center gap-2.5 px-3 py-2 hover:bg-surface-bright text-xs text-on-surface border-b border-outline-variant/10"
                >
                  <span className="truncate flex-1 font-semibold">
                    {item.name}
                  </span>
                  <span className="material-symbols-outlined text-xs text-outline">
                    arrow_forward
                  </span>
                </Link>
              ))}
              <button
                type="button"
                onClick={() => {
                  setShowSearchDropdown(false)
                  navigate(
                    `/products?q=${encodeURIComponent(searchQuery.trim())}`
                  )
                }}
                className="w-full px-3 py-2 text-center text-xs text-primary font-bold bg-surface-container-low hover:bg-surface-container"
              >
                Xem tất cả kết quả →
              </button>
            </div>
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
                {isAdmin() && (
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
                  Danh mục sản phẩm
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
                    Xem tất cả sản phẩm →
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
                        <span>{cat.name}</span>
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
                              {child.name}
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
              Tất cả sản phẩm
            </Link>

            <Link
              to="/tracking"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center gap-2 px-3 py-2.5 rounded-xl font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
            >
              <span className="material-symbols-outlined text-base">
                local_shipping
              </span>
              Tra cứu đơn hàng
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
                {isDark ? 'Giao diện sáng' : 'Giao diện tối'}
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
