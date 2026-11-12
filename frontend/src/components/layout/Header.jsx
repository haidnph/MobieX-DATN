// src/components/layout/Header.jsx
// Main storefront header: logo, search bar with debounce, navigation, cart badge, user menu.
// Categories and brands are fetched from the backend API.

import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useAuth } from '../../contexts/AuthContext'
import { useCart } from '../../contexts/CartContext'
import { useCompare } from '../../contexts/CompareContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { useTheme } from '../../contexts/ThemeContext'
import { useLanguage } from '../../contexts/LanguageContext'
import { useToast } from '../../contexts/ToastContext'
import { useDebounce } from '../../hooks/useDebounce'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

export default function Header() {
  const { user, logout, isAdmin } = useAuth()
  const { itemCount, cartBadgeCount } = useCart()
  const { compareCount } = useCompare()
  const { wishlistCount } = useWishlist()
  const { isDark, toggleTheme } = useTheme()
  const { lang, toggleLang, t } = useLanguage()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [categories, setCategories] = useState([])
  const [searchQuery, setSearchQuery] = useState('')
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [categoryMenuOpen, setCategoryMenuOpen] = useState(false)
  const [mobileCatOpen, setMobileCatOpen] = useState(false)

  // Live autocomplete search states
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

  // Fetch categories for navigation
  useEffect(() => {
    axiosClient
      .get(EP.CATEGORIES)
      .then(({ data }) => setCategories(data.data ?? data))
      .catch(() => {})
  }, [])

  // Live autocomplete search fetch
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
        const items = Array.isArray(payload) ? payload : (payload.data ?? [])
        setSearchSuggestions(items)
      })
      .catch(() => {
        setSearchSuggestions([])
      })
      .finally(() => {
        setLoadingSuggestions(false)
      })
  }, [debouncedSearch])

  // Navigate on search submit
  const handleSearch = (e) => {
    e.preventDefault()
    setShowSearchDropdown(false)
    if (searchQuery.trim()) {
      navigate(`/products?q=${encodeURIComponent(searchQuery.trim())}`)
    }
  }

  // Voice Search via Web Speech API
  const handleVoiceSearch = () => {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SpeechRecognition) {
      showToast('Trình duyệt không hỗ trợ nhận diện giọng nói. Vui lòng dùng Chrome hoặc Edge.', 'warning')
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
        showToast('Đang lắng nghe... Hãy nói tên thiết bị (VD: iPhone 16 Pro)', 'info')
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
          showToast('Không nhận diện được âm thanh. Vui lòng thử lại.', 'warning')
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
      if (categoryMenuRef.current && !categoryMenuRef.current.contains(e.target)) {
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
    <header className="fixed top-0 left-0 right-0 z-50 bg-surface-container-lowest/80 backdrop-blur-xl shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
      <div className="h-20 w-full max-w-7xl mx-auto px-4 md:px-8 lg:px-16 flex items-center justify-between gap-4">

        {/* Logo */}
        <Link to="/" className="flex items-center gap-2 shrink-0 group">
          <span className="text-xl font-bold text-on-surface tracking-tight group-hover:text-primary transition-colors">
            Mobile<span className="text-primary">X</span>
          </span>
        </Link>

        {/* Search bar with live autocomplete dropdown */}
        <div ref={searchRef} className="relative flex-1 max-w-xl mx-2 hidden md:block">
          <form onSubmit={handleSearch}>
            <div className="relative flex items-center w-full bg-surface-container-low rounded-xl px-4 py-2 transition-all duration-300 focus-within:ring-2 focus-within:ring-primary focus-within:bg-surface-container">
              <span className="material-symbols-outlined text-outline mr-2 text-lg select-none">search</span>
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
                  if (searchSuggestions.length > 0 || searchQuery.trim().length >= 2) {
                    setShowSearchDropdown(true)
                  }
                }}
                className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline focus:outline-none"
                placeholder="Tìm kiếm điện thoại, phụ kiện..."
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => {
                    setSearchQuery('')
                    setSearchSuggestions([])
                    setShowSearchDropdown(false)
                  }}
                  className="text-outline hover:text-on-surface text-sm ml-1"
                >
                  <span className="material-symbols-outlined text-base">close</span>
                </button>
              )}
              {/* Voice search button */}
              <button
                type="button"
                onClick={handleVoiceSearch}
                aria-label="Tìm kiếm bằng giọng nói"
                title={isListening ? 'Đang lắng nghe... Bấm để dừng' : 'Tìm kiếm bằng giọng nói'}
                className={`p-1 ml-1 rounded-lg transition-colors flex items-center justify-center ${
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

          {/* Autocomplete Dropdown */}
          {showSearchDropdown && searchQuery.trim().length >= 2 && (
            <div className="absolute top-full left-0 right-0 mt-2 bg-surface-container-high rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50">
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
                  Không tìm thấy sản phẩm nào khớp với &ldquo;{searchQuery}&rdquo;
                </div>
              ) : (
                <div className="divide-y divide-outline-variant/10 max-h-80 overflow-y-auto">
                  {searchSuggestions.map((item) => {
                    const img =
                      item.images?.find((i) => i.is_primary)?.image_path ||
                      item.thumbnail ||
                      item.images?.[0]?.image_path
                    const price =
                      item.min_price || item.price || item.base_price || item.variants?.[0]?.price
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
                        <div className="w-10 h-10 rounded-lg bg-surface-container-low shrink-0 overflow-hidden border border-outline-variant/30 flex items-center justify-center p-1">
                          {img ? (
                            <img src={img} alt={item.name} className="w-full h-full object-contain" />
                          ) : (
                            <span className="material-symbols-outlined text-outline text-lg">smartphone</span>
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
                    navigate(`/products?q=${encodeURIComponent(searchQuery.trim())}`)
                  }}
                  className="w-full px-4 py-2.5 bg-surface-container-low hover:bg-surface-container text-xs text-primary font-semibold text-center border-t border-outline-variant/20 transition-colors flex items-center justify-center gap-1"
                >
                  <span>Xem tất cả kết quả cho &ldquo;{searchQuery}&rdquo;</span>
                  <span className="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
              )}
            </div>
          )}
        </div>

        {/* Desktop navigation */}
        <nav className="hidden lg:flex items-center gap-3">
          <Link
            to="/"
            className="px-2.5 py-1.5 rounded-xl text-sm font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high/60 transition-colors"
          >
            {t('home')}
          </Link>

          {/* Compact Category Dropdown */}
          <div ref={categoryMenuRef} className="relative">
            <button
              type="button"
              onClick={() => setCategoryMenuOpen((v) => !v)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-medium transition-all ${
                categoryMenuOpen
                  ? 'bg-primary/10 text-primary ring-1 ring-primary/30'
                  : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high/60'
              }`}
            >
              <span className="material-symbols-outlined text-lg">grid_view</span>
              <span>Danh mục</span>
              <span
                className={`material-symbols-outlined text-base transition-transform duration-200 ${
                  categoryMenuOpen ? 'rotate-180 text-primary' : 'text-outline'
                }`}
              >
                expand_more
              </span>
            </button>

            {categoryMenuOpen && (
              <div className="absolute top-full left-0 mt-2 w-[440px] bg-surface-container-high/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-outline-variant/30 p-3 z-50 animate-in fade-in zoom-in-95 duration-150">
                <div className="flex items-center justify-between pb-2 mb-2 border-b border-outline-variant/20 px-2">
                  <span className="text-xs font-bold text-on-surface-variant uppercase tracking-wider flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-sm text-primary">category</span>
                    Danh mục sản phẩm
                  </span>
                  <Link
                    to="/products"
                    onClick={() => setCategoryMenuOpen(false)}
                    className="text-xs font-semibold text-primary hover:underline flex items-center gap-0.5"
                  >
                    Tất cả sản phẩm
                    <span className="material-symbols-outlined text-sm">chevron_right</span>
                  </Link>
                </div>

                <div className="max-h-[360px] overflow-y-auto space-y-2.5 pr-1">
                  {categories.map((cat) => (
                    <div key={cat.id} className="bg-surface-container/60 hover:bg-surface-container rounded-xl p-2.5 border border-outline-variant/10 transition-colors">
                      <Link
                        to={`/products?category_id=${cat.id}`}
                        onClick={() => setCategoryMenuOpen(false)}
                        className="flex items-center justify-between text-sm font-bold text-on-surface hover:text-primary transition-colors group mb-1"
                      >
                        <span className="flex items-center gap-2">
                          <span className="w-1.5 h-1.5 rounded-full bg-primary" />
                          {cat.name}
                        </span>
                        <span className="material-symbols-outlined text-xs text-outline group-hover:text-primary transition-colors">
                          arrow_forward
                        </span>
                      </Link>

                      {cat.children && cat.children.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 pl-3.5 pt-1">
                          {cat.children.map((child) => (
                            <Link
                              key={child.id}
                              to={`/products?category_id=${child.id}`}
                              onClick={() => setCategoryMenuOpen(false)}
                              className="px-2 py-0.5 rounded-lg bg-surface-container-low hover:bg-primary-container text-xs text-on-surface-variant hover:text-on-primary-container transition-all"
                            >
                              {child.name}
                            </Link>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <Link
            to="/products"
            className="px-2.5 py-1.5 rounded-xl text-sm font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high/60 transition-colors"
          >
            Sản phẩm
          </Link>

          <Link
            to="/tracking"
            className="px-2.5 py-1.5 rounded-xl text-sm font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high/60 transition-colors"
          >
            Tra cứu đơn
          </Link>
        </nav>

        {/* Right actions */}
        <div className="flex items-center gap-2 shrink-0">
          {/* Language Switcher */}
          <button
            type="button"
            onClick={toggleLang}
            title={lang === 'vi' ? 'Switch to English' : 'Chuyển sang Tiếng Việt'}
            className="px-2 py-1 rounded-xl text-xs font-bold bg-surface-container-low hover:bg-surface-container text-on-surface border border-outline-variant/30 transition-colors flex items-center gap-1 shrink-0"
          >
            <span>{lang === 'vi' ? '🇻🇳' : '🇬🇧'}</span>
            <span className="uppercase text-[10px]">{lang}</span>
          </button>

          {/* Theme Toggle Button */}
          <button
            type="button"
            onClick={toggleTheme}
            aria-label={isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'}
            title={isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'}
            className="p-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors flex items-center justify-center"
          >
            <span className="material-symbols-outlined text-xl">
              {isDark ? 'light_mode' : 'dark_mode'}
            </span>
          </button>

          {/* Compare */}
          <Link
            to="/compare"
            aria-label="So sánh sản phẩm"
            title="So sánh sản phẩm"
            className="relative p-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors flex items-center justify-center"
          >
            <span className="material-symbols-outlined text-xl">compare_arrows</span>
            {compareCount > 0 && (
              <span className="absolute top-1.5 right-1.5 flex h-4 min-w-[1rem] px-1 items-center justify-center rounded-full bg-primary text-on-primary text-[10px] font-bold shadow-[0_0_8px_rgba(77,142,255,0.6)]">
                {compareCount}
              </span>
            )}
          </Link>

          {/* Wishlist */}
          <Link
            to="/wishlist"
            aria-label="Sản phẩm yêu thích"
            title="Sản phẩm yêu thích"
            className="relative p-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors flex items-center justify-center"
          >
            <span className="material-symbols-outlined text-xl">favorite_border</span>
            {wishlistCount > 0 && (
              <span className="absolute top-1.5 right-1.5 flex h-4 min-w-[1rem] px-1 items-center justify-center rounded-full bg-error text-white text-[10px] font-bold shadow-[0_0_8px_rgba(239,68,68,0.6)]">
                {wishlistCount}
              </span>
            )}
          </Link>

          {/* Cart */}
          <Link
            to="/cart"
            aria-label="Giỏ hàng"
            className="relative p-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors"
          >
            <span className="material-symbols-outlined text-xl">shopping_bag</span>
            {itemCount > 0 && (
              <span className="absolute top-1.5 right-1.5 flex h-4 min-w-[1rem] px-1 items-center justify-center rounded-full bg-secondary-container text-on-secondary-container text-[10px] font-bold shadow-[0_0_10px_rgba(74,225,118,0.5)]">
                {cartBadgeCount}
              </span>
            )}
          </Link>

          {/* User menu */}
          {user ? (
            <div className="relative" ref={userMenuRef}>
              <button
                onClick={() => setUserMenuOpen((v) => !v)}
                className="flex items-center gap-2 rounded-xl px-3 py-2 hover:bg-surface-container-high transition-colors"
                aria-label="Tài khoản"
              >
                <span className="material-symbols-outlined text-on-surface-variant text-xl">account_circle</span>
                <span className="hidden md:block text-sm text-on-surface max-w-[120px] truncate">
                  {user.name}
                </span>
                <span className="material-symbols-outlined text-on-surface-variant text-base">
                  {userMenuOpen ? 'expand_less' : 'expand_more'}
                </span>
              </button>

              {userMenuOpen && (
                <div className="absolute right-0 top-full mt-2 w-52 bg-surface-container-high rounded-xl p-2 shadow-[0_20px_40px_-15px_rgba(6,14,32,0.9)] border border-outline-variant/20 z-50">
                  <div className="px-3 py-2 border-b border-outline-variant/20 mb-1">
                    <p className="text-xs font-bold text-on-surface truncate">{user.name}</p>
                    <p className="text-xs text-outline truncate">{user.email}</p>
                  </div>
                  <Link
                    to="/account/profile"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-sm"
                  >
                    <span className="material-symbols-outlined text-base">manage_accounts</span>
                    Tài khoản của tôi
                  </Link>
                  <Link
                    to="/account/addresses"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-sm"
                  >
                    <span className="material-symbols-outlined text-base">pin_drop</span>
                    Sổ địa chỉ nhận hàng
                  </Link>
                  <Link
                    to="/orders"
                    onClick={() => setUserMenuOpen(false)}
                    className="flex items-center gap-2 px-3 py-2 rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-sm"
                  >
                    <span className="material-symbols-outlined text-base">receipt_long</span>
                    Đơn hàng của tôi
                  </Link>
                  {isAdmin() && (
                    <Link
                      to="/admin"
                      onClick={() => setUserMenuOpen(false)}
                      className="flex items-center gap-2 px-3 py-2 rounded-lg text-on-surface-variant hover:bg-surface-bright hover:text-on-surface transition-colors text-sm"
                    >
                      <span className="material-symbols-outlined text-base">admin_panel_settings</span>
                      Quản trị
                    </Link>
                  )}
                  <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-error hover:bg-error-container/20 transition-colors text-sm mt-1"
                  >
                    <span className="material-symbols-outlined text-base">logout</span>
                    Đăng xuất
                  </button>
                </div>
              )}
            </div>
          ) : (
            <Link
              to="/login"
              className="px-4 py-2 rounded-xl bg-primary-container text-on-primary-container text-sm font-semibold hover:bg-primary hover:text-on-primary transition-colors"
            >
              Đăng nhập
            </Link>
          )}

          {/* Mobile menu toggle */}
          <button
            onClick={() => setMobileMenuOpen((v) => !v)}
            className="lg:hidden p-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high transition-colors"
            aria-label="Menu"
          >
            <span className="material-symbols-outlined text-xl">
              {mobileMenuOpen ? 'close' : 'menu'}
            </span>
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      {mobileMenuOpen && (
        <div className="lg:hidden bg-surface-container-low border-t border-outline-variant/20 px-4 py-4">
          <div ref={mobileSearchRef} className="relative mb-4">
            <form onSubmit={handleSearch}>
              <div className="flex items-center bg-surface-container rounded-xl px-4 py-2">
                <span className="material-symbols-outlined text-outline mr-2 text-lg">search</span>
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
                    if (searchSuggestions.length > 0 || searchQuery.trim().length >= 2) {
                      setShowSearchDropdown(true)
                    }
                  }}
                  className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline focus:outline-none"
                  placeholder="Tìm kiếm sản phẩm..."
                />
                <button
                  type="button"
                  onClick={handleVoiceSearch}
                  className={`p-1 ml-1 rounded-lg transition-colors flex items-center justify-center ${
                    isListening
                      ? 'text-error animate-pulse bg-error-container/20'
                      : 'text-outline hover:text-primary'
                  }`}
                >
                  <span className="material-symbols-outlined text-lg">
                    {isListening ? 'mic' : 'mic_none'}
                  </span>
                </button>
              </div>
            </form>

            {/* Mobile Autocomplete Dropdown */}
            {showSearchDropdown && searchQuery.trim().length >= 2 && (
              <div className="absolute top-full left-0 right-0 mt-2 bg-surface-container-high rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50">
                <div className="px-4 py-2 border-b border-outline-variant/20 flex items-center justify-between text-xs text-on-surface-variant font-medium">
                  <span>Gợi ý ({searchSuggestions.length})</span>
                  {loadingSuggestions && (
                    <span className="text-primary text-[11px]">Đang tìm...</span>
                  )}
                </div>
                {searchSuggestions.slice(0, 4).map((item) => (
                  <Link
                    key={item.id}
                    to={`/products/${item.slug || item.id}`}
                    onClick={() => {
                      setShowSearchDropdown(false)
                      setMobileMenuOpen(false)
                      setSearchQuery('')
                    }}
                    className="flex items-center gap-2 px-3 py-2 hover:bg-surface-bright text-xs text-on-surface border-b border-outline-variant/10"
                  >
                    <span className="truncate flex-1 font-semibold">{item.name}</span>
                    <span className="material-symbols-outlined text-xs text-outline">arrow_forward</span>
                  </Link>
                ))}
              </div>
            )}
          </div>
          <nav className="flex flex-col gap-1">
            <Link
              to="/"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2 rounded-lg text-sm text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors"
            >
              Trang chủ
            </Link>

            {/* Mobile Category Collapsible */}
            <div className="border-y border-outline-variant/15 py-1 my-1">
              <button
                type="button"
                onClick={() => setMobileCatOpen((v) => !v)}
                className="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface-container-high transition-colors"
              >
                <span className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-base text-primary">grid_view</span>
                  Danh mục sản phẩm
                </span>
                <span className={`material-symbols-outlined text-sm transition-transform ${mobileCatOpen ? 'rotate-180 text-primary' : 'text-outline'}`}>
                  expand_more
                </span>
              </button>

              {mobileCatOpen && (
                <div className="pl-4 pr-2 py-1 space-y-2 max-h-60 overflow-y-auto">
                  <Link
                    to="/products"
                    onClick={() => {
                      setMobileCatOpen(false)
                      setMobileMenuOpen(false)
                    }}
                    className="block text-xs font-semibold text-primary py-1"
                  >
                    Xem tất cả sản phẩm →
                  </Link>
                  {categories.map((cat) => (
                    <div key={cat.id} className="space-y-1">
                      <Link
                        to={`/products?category_id=${cat.id}`}
                        onClick={() => {
                          setMobileCatOpen(false)
                          setMobileMenuOpen(false)
                        }}
                        className="block text-xs font-bold text-on-surface hover:text-primary py-0.5"
                      >
                        {cat.name}
                      </Link>
                      {cat.children && cat.children.length > 0 && (
                        <div className="flex flex-wrap gap-1 pl-2">
                          {cat.children.map((child) => (
                            <Link
                              key={child.id}
                              to={`/products?category_id=${child.id}`}
                              onClick={() => {
                                setMobileCatOpen(false)
                                setMobileMenuOpen(false)
                              }}
                              className="px-2 py-0.5 rounded text-[11px] bg-surface-container text-on-surface-variant hover:text-primary"
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

            {[
              { to: '/products', label: 'Tất cả sản phẩm' },
              { to: '/cart', label: 'Giỏ hàng' },
              { to: '/tracking', label: 'Tra cứu đơn hàng' },
              { to: '/orders', label: 'Đơn hàng của tôi' },
            ].map(({ to, label }) => (
              <Link
                key={to}
                to={to}
                onClick={() => setMobileMenuOpen(false)}
                className="px-3 py-2 rounded-lg text-sm text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors"
              >
                {label}
              </Link>
            ))}
          </nav>
        </div>
      )}
    </header>
  )
}
