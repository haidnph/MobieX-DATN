
import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import AdminOmnisearchModal from '../components/admin/AdminOmnisearchModal'

const NAV_ITEMS = [
  { to: '/admin',             icon: 'dashboard',          label: 'Tổng quan',          end: true, permission: 'dashboard.view' },
  { to: '/admin/orders',      icon: 'receipt_long',       label: 'Đơn hàng',                     permission: 'orders.view' },

  // Quản lý hoàn hàng
  { to: '/admin/returns',     icon: 'assignment_return',  label: 'Hoàn hàng',                    permission: 'orders.view' },

  { to: '/admin/products',    icon: 'inventory_2',        label: 'Sản phẩm',                     permission: 'products.view' },
  { to: '/admin/categories',  icon: 'category',           label: 'Danh mục',                     permission: 'categories.view' },
  { to: '/admin/brands',      icon: 'store',              label: 'Thương hiệu',                  permission: 'brands.view' },
  { to: '/admin/users',       icon: 'group',              label: 'Người dùng',                   permission: 'users.view' },
  { to: '/admin/coupons',     icon: 'local_offer',        label: 'Mã giảm giá',                  permission: 'coupons.view' },
  { to: '/admin/flash-sales', icon: 'bolt',               label: 'Flash Sale',                  permission: 'flash_sales.view' },
  { to: '/admin/reviews',     icon: 'rate_review',        label: 'Đánh giá',                     permission: 'reviews.view' },
  { to: '/admin/banners',     icon: 'image',              label: 'Banner',                       permission: 'banners.view' },
  { to: '/admin/homepage',    icon: 'view_quilt',         label: 'Quản lý Trang chủ',            permission: 'banners.view' },
  { to: '/admin/trash',       icon: 'auto_delete',        label: 'Thùng rác',                    permission: 'products.view' },
]

export default function AdminLayout() {
  const { user, logout, hasPermission, isAdmin } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [collapsed, setCollapsed] = useState(false)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [trashCount, setTrashCount] = useState(0)
  const [omnisearchOpen, setOmnisearchOpen] = useState(false)

  // Auto-close mobile drawer on route navigation
  useEffect(() => {
    setMobileNavOpen(false)
  }, [location.pathname])

  const visibleNavItems = NAV_ITEMS.filter(
    (item) => !item.permission || isAdmin() || hasPermission(item.permission),
  )

  // Global Ctrl+K / Cmd+K listener
  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setOmnisearchOpen((prev) => !prev)
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [])

  useEffect(() => {
    let mounted = true

    axiosClient.get(EP.ADMIN_TRASH_SUMMARY)
      .then(({ data }) => {
        if (mounted && data?.data?.total) {
          setTrashCount(data.data.total)
        }
      })
      .catch(() => {})

    return () => { mounted = false }
  }, [])

  const handleLogout = async () => {
    await logout()
    navigate('/admin/login', { replace: true })
  }

  return (
    <div className="min-h-screen flex bg-background">
      {/* Mobile Backdrop */}
      {mobileNavOpen && (
        <div
          className="fixed inset-0 bg-black/60 backdrop-blur-xs z-40 lg:hidden transition-opacity"
          onClick={() => setMobileNavOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Sidebar */}
      <aside
        className={[
          'fixed top-0 left-0 h-full z-50 flex flex-col bg-surface-container-low border-r border-outline-variant/20 transition-all duration-300',
          mobileNavOpen ? 'translate-x-0 w-64 shadow-2xl' : '-translate-x-full lg:translate-x-0',
          collapsed ? 'lg:w-16' : 'lg:w-64',
        ].join(' ')}
      >
        {/* Logo */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-outline-variant/20 shrink-0">
          <Link to="/admin" className="flex items-center gap-2 overflow-hidden">
            <span className="material-symbols-outlined text-primary text-2xl shrink-0">admin_panel_settings</span>
            {(!collapsed || mobileNavOpen) && (
              <span className="text-base font-bold text-on-surface whitespace-nowrap">
                Mobile<span className="text-primary">X</span> Admin
              </span>
            )}
          </Link>

          {/* Close button for mobile drawer */}
          <button
            type="button"
            onClick={() => setMobileNavOpen(false)}
            className="lg:hidden p-1.5 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors"
            aria-label="Đóng thanh điều hướng"
          >
            <span className="material-symbols-outlined text-xl">close</span>
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-1">
          {visibleNavItems.map(({ to, icon, label, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              onClick={() => setMobileNavOpen(false)}
              className={({ isActive }) =>
                [
                  'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium cursor-pointer transition-all active:scale-[0.99] select-none',
                  isActive
                    ? 'bg-primary-container/20 text-primary font-semibold shadow-2xs'
                    : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface',
                ].join(' ')
              }
            >
              <span className="material-symbols-outlined text-xl shrink-0">{icon}</span>

              {(!collapsed || mobileNavOpen) && (
                <div className="flex-1 flex items-center justify-between min-w-0">
                  <span className="truncate">{label}</span>

                  {to === '/admin/trash' && trashCount > 0 && (
                    <span className="px-1.5 py-0.5 rounded-full text-xs font-semibold bg-error-container text-on-error-container shrink-0">
                      {trashCount}
                    </span>
                  )}
                </div>
              )}
            </NavLink>
          ))}
        </nav>

        {/* Collapse toggle (Desktop only) */}
        <div className="hidden lg:block p-2 border-t border-outline-variant/20">
          <button
            type="button"
            onClick={() => setCollapsed((v) => !v)}
            className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high active:scale-95 cursor-pointer transition-all text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-primary"
            aria-label={collapsed ? 'Mở rộng' : 'Thu gọn'}
          >
            <span className="material-symbols-outlined text-xl">
              {collapsed ? 'chevron_right' : 'chevron_left'}
            </span>
            {!collapsed && <span>Thu gọn</span>}
          </button>
        </div>
      </aside>

      {/* Main area */}
      <div className={['flex-1 flex flex-col transition-all duration-300 min-w-0 ml-0', collapsed ? 'lg:ml-16' : 'lg:ml-64'].join(' ')}>
        {/* Top bar */}
        <header className="sticky top-0 z-30 h-16 flex items-center justify-between px-3 sm:px-6 bg-surface-container-low/90 backdrop-blur-sm border-b border-outline-variant/20 gap-2 sm:gap-4">
          <div className="flex items-center gap-2 sm:gap-3">
            {/* Hamburger button for mobile & tablet */}
            <button
              type="button"
              onClick={() => setMobileNavOpen(true)}
              className="lg:hidden p-2 rounded-xl text-on-surface hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer flex items-center justify-center -ml-1"
              aria-label="Mở menu quản trị"
            >
              <span className="material-symbols-outlined text-2xl">menu</span>
            </button>

            <Link to="/" target="_blank" className="flex items-center gap-1.5 text-xs sm:text-sm text-on-surface-variant hover:text-on-surface active:scale-95 cursor-pointer transition-all shrink-0">
              <span className="material-symbols-outlined text-base">open_in_new</span>
              <span className="hidden sm:inline">Xem cửa hàng</span>
            </Link>
          </div>

          {/* Omnisearch Button (Ctrl + K) */}
          <button
            type="button"
            onClick={() => setOmnisearchOpen(true)}
            className="flex items-center gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-surface-container-high/60 hover:bg-surface-container-high active:scale-[0.99] cursor-pointer text-on-surface-variant text-xs border border-outline-variant/30 transition-all hover:border-primary/40 shadow-xs flex-1 max-w-[200px] sm:max-w-xs md:max-w-md justify-between"
          >
            <div className="flex items-center gap-2 truncate">
              <span className="material-symbols-outlined text-base text-primary">search</span>
              <span className="truncate">Tìm kiếm...</span>
            </div>
            <kbd className="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/40 text-on-surface-variant shrink-0">
              Ctrl K
            </kbd>
          </button>

          <div className="flex items-center gap-2 sm:gap-3 shrink-0">
            <div className="text-right hidden md:block leading-tight">
              <span className="text-sm font-semibold text-on-surface block">{user?.name}</span>
              <span className="text-[10px] text-primary uppercase font-bold tracking-wider">
                {user?.roles?.[0] ?? 'Admin'}
              </span>
            </div>

            <button
              type="button"
              onClick={handleLogout}
              className="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg text-error hover:bg-error-container/20 active:scale-95 cursor-pointer transition-all text-xs sm:text-sm font-medium focus:outline-none focus-visible:ring-1 focus-visible:ring-error"
              title="Đăng xuất khỏi Admin"
            >
              <span className="material-symbols-outlined text-base">logout</span>
              <span className="hidden md:inline">Đăng xuất</span>
            </button>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>

      {/* Omnisearch Modal */}
      <AdminOmnisearchModal
        open={omnisearchOpen}
        onClose={() => setOmnisearchOpen(false)}
      />
    </div>
  )
}
