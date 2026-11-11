// src/layouts/AdminLayout.jsx
// Wraps admin portal pages: collapsible sidebar + top navbar + main content area.

import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import AdminOmnisearchModal from '../components/admin/AdminOmnisearchModal'

const NAV_ITEMS = [
  { to: '/admin',            icon: 'dashboard',           label: 'Tổng quan',       end: true },
  { to: '/admin/orders',     icon: 'receipt_long',        label: 'Đơn hàng' },
  { to: '/admin/products',   icon: 'inventory_2',         label: 'Sản phẩm' },
  { to: '/admin/categories', icon: 'category',            label: 'Danh mục' },
  { to: '/admin/brands',     icon: 'store',               label: 'Thương hiệu' },
  { to: '/admin/specs',      icon: 'tune',                label: 'Thông số kỹ thuật' },
  { to: '/admin/inventories',icon: 'warehouse',           label: 'Kho hàng' },
  { to: '/admin/users',      icon: 'group',               label: 'Người dùng' },
  { to: '/admin/coupons',    icon: 'local_offer',         label: 'Mã giảm giá' },
  { to: '/admin/reviews',    icon: 'rate_review',         label: 'Đánh giá' },
  { to: '/admin/banners',    icon: 'image',               label: 'Banner' },
  { to: '/admin/trash',      icon: 'auto_delete',         label: 'Thùng rác' },
]

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [collapsed, setCollapsed] = useState(false)
  const [trashCount, setTrashCount] = useState(0)
  const [omnisearchOpen, setOmnisearchOpen] = useState(false)

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
    navigate('/login')
  }

  return (
    <div className="min-h-screen flex bg-background">
      {/* Sidebar */}
      <aside
        className={[
          'fixed top-0 left-0 h-full z-40 flex flex-col bg-surface-container-low border-r border-outline-variant/20 transition-all duration-300',
          collapsed ? 'w-16' : 'w-64',
        ].join(' ')}
      >
        {/* Logo */}
        <div className="h-16 flex items-center px-4 border-b border-outline-variant/20 shrink-0">
          <Link to="/admin" className="flex items-center gap-2 overflow-hidden">
            <span className="material-symbols-outlined text-primary text-2xl shrink-0">admin_panel_settings</span>
            {!collapsed && (
              <span className="text-base font-bold text-on-surface whitespace-nowrap">
                Mobile<span className="text-primary">X</span> Admin
              </span>
            )}
          </Link>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-1">
          {NAV_ITEMS.map(({ to, icon, label, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                [
                  'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-primary-container/20 text-primary'
                    : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface',
                ].join(' ')
              }
            >
              <span className="material-symbols-outlined text-xl shrink-0">{icon}</span>
              {!collapsed && (
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

        {/* Collapse toggle */}
        <div className="p-2 border-t border-outline-variant/20">
          <button
            onClick={() => setCollapsed((v) => !v)}
            className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-container-high transition-colors text-sm"
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
      <div className={['flex-1 flex flex-col transition-all duration-300', collapsed ? 'ml-16' : 'ml-64'].join(' ')}>
        {/* Top bar */}
        <header className="sticky top-0 z-30 h-16 flex items-center justify-between px-6 bg-surface-container-low/90 backdrop-blur-sm border-b border-outline-variant/20 gap-4">
          <div className="flex items-center gap-3">
            <Link to="/" target="_blank" className="flex items-center gap-2 text-sm text-on-surface-variant hover:text-on-surface transition-colors shrink-0">
              <span className="material-symbols-outlined text-base">open_in_new</span>
              <span className="hidden sm:inline">Xem cửa hàng</span>
            </Link>
          </div>

          {/* Omnisearch Button (Ctrl + K) */}
          <button
            type="button"
            onClick={() => setOmnisearchOpen(true)}
            className="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-surface-container-high/60 hover:bg-surface-container-high text-on-surface-variant text-xs border border-outline-variant/30 transition-all hover:border-primary/40 shadow-xs w-full max-w-xs md:max-w-md justify-between"
          >
            <div className="flex items-center gap-2 truncate">
              <span className="material-symbols-outlined text-base text-primary">search</span>
              <span className="truncate">Tìm kiếm đơn hàng, sản phẩm, khách hàng...</span>
            </div>
            <kbd className="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/40 text-on-surface-variant shrink-0">
              Ctrl K
            </kbd>
          </button>

          <div className="flex items-center gap-3 shrink-0">
            <span className="text-sm text-on-surface-variant hidden md:block">{user?.name}</span>
            <button
              onClick={handleLogout}
              className="flex items-center gap-2 px-3 py-1.5 rounded-lg text-error hover:bg-error-container/20 transition-colors text-sm font-medium"
            >
              <span className="material-symbols-outlined text-base">logout</span>
              <span className="hidden md:inline">Đăng xuất</span>
            </button>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-auto p-6">
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
