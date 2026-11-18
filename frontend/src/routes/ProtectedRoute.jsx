// src/routes/ProtectedRoute.jsx
// Protects routes from unauthenticated and unauthorized access.
// Directs unauthenticated users to /login (Client) or /admin/login (Admin).
// Shows a spinner while AuthContext is still hydrating.

import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import Spinner from '../components/common/Spinner'

/**
 * @param {{
 *   children: React.ReactNode,
 *   adminOnly?: boolean,
 * }} props
 */
export default function ProtectedRoute({ children, adminOnly = false }) {
  const { user, loading, canAccessAdmin } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-background">
        <Spinner size="lg" />
      </div>
    )
  }

  const isAdminArea = adminOnly || location.pathname.startsWith('/admin')

  // 1. Not authenticated
  if (!user) {
    const loginPath = isAdminArea ? '/admin/login' : '/login'
    return <Navigate to={loginPath} state={{ from: location }} replace />
  }

  // 2. Authenticated, but attempting to access Admin portal without admin/staff roles
  if (isAdminArea && !canAccessAdmin()) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[70vh] gap-4 text-center px-4 bg-background">
        <div className="w-16 h-16 rounded-2xl bg-error/10 text-error flex items-center justify-center mb-2">
          <span className="material-symbols-outlined text-4xl">gpp_bad</span>
        </div>
        <h1 className="text-2xl font-bold text-on-surface">403 — Khu vực Quản trị Giới hạn</h1>
        <p className="text-on-surface-variant text-sm max-w-md">
          Tài khoản của bạn ({user.email}) không có quyền truy cập khu vực Quản trị MobieX.
          Vui lòng quay lại Cửa hàng hoặc đăng nhập bằng tài khoản Quản trị.
        </p>
        <div className="flex items-center gap-3 mt-4">
          <a
            href="/"
            className="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-sm font-semibold hover:bg-primary-dark transition-colors"
          >
            Quay lại Cửa hàng
          </a>
          <a
            href="/admin/login"
            className="px-5 py-2.5 rounded-xl border border-outline-variant/30 text-on-surface text-sm font-semibold hover:bg-surface-container transition-colors"
          >
            Đăng nhập Quản trị
          </a>
        </div>
      </div>
    )
  }

  return children
}
