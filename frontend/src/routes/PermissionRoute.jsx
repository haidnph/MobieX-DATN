// src/routes/PermissionRoute.jsx
// Guard that shows a 403 Forbidden page when the user lacks a required permission.
// Always wraps inside <ProtectedRoute> so user is guaranteed to be authenticated.

import { useAuth } from '../contexts/AuthContext'

/**
 * @param {{ permission: string, children: React.ReactNode }} props
 */
export default function PermissionRoute({ permission, children }) {
  const { hasPermission, isAdmin } = useAuth()

  // Super-admin bypasses all permission checks
  if (isAdmin() || hasPermission(permission)) {
    return children
  }

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
      <span className="material-symbols-outlined text-6xl text-error">block</span>
      <h1 className="text-2xl font-bold text-on-surface">403 — Không có quyền truy cập</h1>
      <p className="text-on-surface-variant text-sm max-w-sm">
        Bạn không có quyền thực hiện hành động này. Vui lòng liên hệ quản trị viên nếu bạn cho rằng
        đây là sự nhầm lẫn.
      </p>
    </div>
  )
}
