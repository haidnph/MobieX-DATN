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
import { useDebounce } from '../../hooks/useDebounce'

export default function Header() {
  const { user, logout, isAdmin } = useAuth()
  const { itemCount, cartBadgeCount } = useCart()
  const { compareCount } = useCompare()
  const { wishlistCount } = useWishlist()
  const { isDark, toggleTheme } = useTheme()
  const navigate = useNavigate()

  const [categories, setCategories] = useState([])
  const [searchQuery, setSearchQuery] = useState('')
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const userMenuRef = useRef(null)

  const debouncedSearch = useDebounce(searchQuery, 300)

  // Fetch categories for navigation
  useEffect(() => {
    axiosClient
      .get(EP.CATEGORIES)
      .then(({ data }) => setCategories(data.data ?? data))
      .catch(() => {})
  }, [])

  // Navigate on search submit
  const handleSearch = (e) => {
    e.preventDefault()
    if (debouncedSearch.trim()) {
      navigate(`/products?q=${encodeURIComponent(debouncedSearch.trim())}`)
    }
  }

  // Close user menu on outside click
  useEffect(() => {
    const handler = (e) => {
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setUserMenuOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
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

        {/* Search bar */}
        <form
          onSubmit={handleSearch}
          className="relative flex-1 max-w-xl mx-2 hidden md:block"
        >
          <div className="relative flex items-center w-full bg-surface-container-low rounded-xl px-4 py-2 transition-all duration-300 focus-within:ring-2 focus-within:ring-primary focus-within:bg-surface-container">
            <span className="material-symbols-outlined text-outline mr-2 text-lg select-none">search</span>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline focus:outline-none"
              placeholder="Tìm kiếm điện thoại, phụ kiện..."
            />
          </div>
        </form>

        {/* Desktop navigation */}
        <nav className="hidden lg:flex items-center gap-4">
          <Link to="/" className="text-sm text-on-surface-variant hover:text-on-surface transition-colors">
            Trang chủ
          </Link>
          <Link to="/products?category=phones" className="text-sm text-on-surface-variant hover:text-on-surface transition-colors">
            Điện thoại
          </Link>
          <Link to="/products?category=accessories" className="text-sm text-on-surface-variant hover:text-on-surface transition-colors">
            Phụ kiện
          </Link>
          {categories.slice(0, 3).map((cat) => (
            <Link
              key={cat.id}
              to={`/products?category_id=${cat.id}`}
              className="text-sm text-on-surface-variant hover:text-on-surface transition-colors"
            >
              {cat.name}
            </Link>
          ))}
        </nav>

        {/* Right actions */}
        <div className="flex items-center gap-2 shrink-0">
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
          <form onSubmit={handleSearch} className="mb-4">
            <div className="flex items-center bg-surface-container rounded-xl px-4 py-2">
              <span className="material-symbols-outlined text-outline mr-2 text-lg">search</span>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full bg-transparent text-sm text-on-surface placeholder:text-outline focus:outline-none"
                placeholder="Tìm kiếm..."
              />
            </div>
          </form>
          <nav className="flex flex-col gap-1">
            {[
              { to: '/', label: 'Trang chủ' },
              { to: '/products', label: 'Sản phẩm' },
              { to: '/cart', label: 'Giỏ hàng' },
              { to: '/orders', label: 'Đơn hàng' },
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
