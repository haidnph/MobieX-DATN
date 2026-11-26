
import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import AdminOmnisearchModal from '../components/admin/AdminOmnisearchModal'

const NAV_SECTIONS = [
  { label: 'Tổng quan', items: [
    { to: '/admin', icon: 'dashboard', label: 'Tổng quan', end: true, permission: 'dashboard.view' },
  ] },
  { label: 'Vận hành', items: [
    { to: '/admin/orders', icon: 'receipt_long', label: 'Đơn hàng', permission: 'orders.view' },
    { to: '/admin/returns', icon: 'assignment_return', label: 'Đổi trả & Hoàn tiền', permission: 'orders.view' },
  ] },
  { label: 'Danh mục', items: [
    { to: '/admin/products', icon: 'inventory_2', label: 'Sản phẩm', permission: 'products.view' },
    { to: '/admin/categories', icon: 'category', label: 'Danh mục', permission: 'categories.view' },
    { to: '/admin/brands', icon: 'store', label: 'Thương hiệu', permission: 'brands.view' },
    { to: '/admin/users', icon: 'group', label: 'Người dùng', permission: 'users.view' },
  ] },
  { label: 'Khuyến mãi', items: [
    { to: '/admin/coupons', icon: 'local_offer', label: 'Mã giảm giá', permission: 'coupons.view' },
    { to: '/admin/flash-sales', icon: 'bolt', label: 'Flash Sale', permission: 'flash_sales.view' },
  ] },
  { label: 'Nội dung', items: [
    { to: '/admin/reviews', icon: 'rate_review', label: 'Đánh giá', permission: 'reviews.view' },
    { to: '/admin/banners', icon: 'image', label: 'Banner', permission: 'banners.view' },
    { to: '/admin/homepage', icon: 'view_quilt', label: 'Quản lý Trang chủ', permission: 'banners.view' },
    { to: '/admin/trash', icon: 'delete_outline', label: 'Thùng rác', permission: 'products.view' },
  ] },
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

  const visibleNavItems = NAV_SECTIONS.flatMap((section) => section.items).filter(
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
    <div className="min-h-screen flex bg-slate-50 text-slate-900 dark:bg-background dark:text-on-background">
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
          'fixed top-0 left-0 h-full z-50 flex flex-col bg-white dark:bg-surface-container-low border-r border-slate-200 dark:border-outline-variant/20 transition-all duration-300',
          mobileNavOpen ? 'translate-x-0 w-72 shadow-2xl' : '-translate-x-full lg:translate-x-0',
          collapsed ? 'lg:w-[76px]' : 'lg:w-[272px]',
        ].join(' ')}
      >
        {/* Logo */}
        <div className="h-[76px] flex items-center justify-between px-5 border-b border-slate-100 dark:border-outline-variant/20 shrink-0">
          <Link to="/admin" className="flex items-center gap-2 overflow-hidden">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-600 text-sm font-extrabold tracking-tight text-white shadow-sm">MX</span>
            {(!collapsed || mobileNavOpen) && (
              <span className="min-w-0 whitespace-nowrap">
                <span className="block text-[15px] font-bold text-slate-900 dark:text-on-surface">MobileX</span>
                <span className="block text-[11px] font-medium tracking-wide text-slate-500 dark:text-on-surface-variant">QUẢN TRỊ CỬA HÀNG</span>
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
        <nav className="flex-1 overflow-y-auto px-3 py-5">
          {NAV_SECTIONS.map((section) => {
            const items = section.items.filter((item) => visibleNavItems.includes(item))
            if (!items.length) return null
            return (
              <div key={section.label} className={collapsed && !mobileNavOpen ? 'mb-2 last:mb-0' : 'mb-5 last:mb-0'}>
                {(!collapsed || mobileNavOpen) && (
                  <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-outline">{section.label}</p>
                )}
                <div className="space-y-1">
          {items.map(({ to, icon, label, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              onClick={() => setMobileNavOpen(false)}
              className={({ isActive }) =>
                [
                  'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium cursor-pointer transition-colors select-none',
                  isActive
                    ? 'bg-blue-50 text-blue-700 dark:bg-primary-container/15 dark:text-primary font-semibold'
                    : 'text-slate-600 dark:text-on-surface-variant hover:bg-slate-50 dark:hover:bg-surface-container-high hover:text-slate-950 dark:hover:text-on-surface',
                ].join(' ')
              }
              title={collapsed ? label : undefined}
            >
              {({ isActive }) => (
                <>
                  {collapsed && !mobileNavOpen ? (
                    <span className="material-symbols-outlined mx-auto text-[20px]">{icon}</span>
                  ) : (
                    <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${isActive ? 'bg-blue-600' : 'bg-slate-300 dark:bg-outline-variant'}`} />
                  )}

                  {(!collapsed || mobileNavOpen) && (
                    <div className="flex min-w-0 flex-1 items-center justify-between">
                      <span className="truncate">{label}</span>

                      {to === '/admin/trash' && trashCount > 0 && (
                        <span className="rounded-md bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-error-container dark:text-on-error-container">
                          {trashCount}
                        </span>
                      )}
                    </div>
                  )}
                </>
              )}
            </NavLink>
          ))}
                </div>
              </div>
            )
          })}
        </nav>

        {/* Collapse toggle (Desktop only) */}
        <div className="hidden lg:block border-t border-slate-100 p-3 dark:border-outline-variant/20">
          <button
            type="button"
            onClick={() => setCollapsed((v) => !v)}
            className="w-full flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-slate-500 dark:text-on-surface-variant hover:bg-slate-50 dark:hover:bg-surface-container-high cursor-pointer transition-colors text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-primary"
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
      <div className={['flex-1 flex flex-col transition-all duration-300 min-w-0 ml-0', collapsed ? 'lg:ml-[76px]' : 'lg:ml-[272px]'].join(' ')}>
        {/* Top bar */}
        <header className="sticky top-0 z-30 flex h-[76px] items-center justify-between gap-2 border-b border-slate-200 bg-white/90 px-3 backdrop-blur-sm dark:border-outline-variant/20 dark:bg-surface-container-low/90 sm:gap-4 sm:px-6">
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

            <Link to="/" target="_blank" className="flex shrink-0 items-center gap-2 rounded-lg px-2 py-2 text-xs text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900 dark:text-on-surface-variant dark:hover:bg-surface-container-high dark:hover:text-on-surface sm:text-sm">
              <span className="material-symbols-outlined text-base">open_in_new</span>
              <span className="hidden sm:inline">Xem cửa hàng</span>
            </Link>
          </div>

          {/* Omnisearch Button (Ctrl + K) */}
          <button
            type="button"
            onClick={() => setOmnisearchOpen(true)}
            className="flex max-w-[200px] flex-1 items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-100/80 px-2.5 py-2 text-xs text-slate-500 transition-colors hover:border-blue-300 hover:bg-slate-100 dark:border-outline-variant/30 dark:bg-surface-container-high/60 dark:text-on-surface-variant dark:hover:bg-surface-container-high sm:max-w-xs sm:px-3 md:max-w-md"
          >
            <div className="flex items-center gap-2 truncate">
              <span className="material-symbols-outlined text-base text-blue-600 dark:text-primary">search</span>
              <span className="truncate">Tìm kiếm...</span>
            </div>
            <kbd className="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/40 text-on-surface-variant shrink-0">
              Ctrl K
            </kbd>
          </button>

          <div className="flex items-center gap-2 sm:gap-3 shrink-0">
            <div className="hidden text-right leading-tight md:block">
              <span className="block text-sm font-semibold text-slate-800 dark:text-on-surface">{user?.name}</span>
              <span className="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-primary">
                {user?.roles?.[0] ?? 'Admin'}
              </span>
            </div>

            <button
              type="button"
              onClick={handleLogout}
              className="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50 dark:text-error dark:hover:bg-error-container/20 sm:px-3 sm:text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-error"
              title="Đăng xuất khỏi Admin"
            >
              <span className="material-symbols-outlined text-base">logout</span>
              <span className="hidden md:inline">Đăng xuất</span>
            </button>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
          <div className="mx-auto w-full max-w-[1440px]">
            <Outlet />
          </div>
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
