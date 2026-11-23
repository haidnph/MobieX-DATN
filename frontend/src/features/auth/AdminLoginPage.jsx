// src/features/auth/AdminLoginPage.jsx
// Dedicated Admin & Staff Login Portal.
// Strictly authenticates internal staff and prevents customer accounts from accessing admin area.

import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import { getSafeIntendedUrl } from '../../utils/urlUtils'

export default function AdminLoginPage() {
  const { user, login, logout, canAccessAdmin, loading: authLoading } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const location = useLocation()

  const [form, setForm] = useState({ email: '', password: '' })
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState({})

  // If already logged in with admin/staff privileges, redirect to /admin or intended admin path
  useEffect(() => {
    if (!authLoading && user) {
      if (canAccessAdmin()) {
        const dest = getSafeIntendedUrl(location.state?.from, '/admin', {
          isAdminPortal: true,
          canAccessAdmin: true,
        })
        navigate(dest, { replace: true })
      }
    }
  }, [user, authLoading, canAccessAdmin, location.state, navigate])

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setErrors((prev) => ({ ...prev, [e.target.name]: '' }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setErrors({})

    try {
      const loggedUser = await login(form)

      // Strict check: verify user has admin or staff roles/permissions
      const isAuthorized =
        loggedUser.roles?.some((r) =>
          ['admin', 'super-admin', 'sales', 'warehouse', 'accountant'].includes(r),
        ) ||
        loggedUser.permissions?.includes('*') ||
        loggedUser.permissions?.includes('dashboard.view')

      if (!isAuthorized) {
        // Customer account attempting to enter admin portal: revoke session immediately
        await logout()
        const deniedMsg =
          'Tài khoản của bạn không có quyền truy cập khu vực quản trị. Vui lòng đăng nhập tại Cửa hàng.'
        setErrors({ email: [deniedMsg] })
        showToast(deniedMsg, 'error')
        return
      }

      showToast(`Chào mừng quản trị viên ${loggedUser.name}!`, 'success')
      const target = getSafeIntendedUrl(location.state?.from, '/admin', {
        isAdminPortal: true,
        canAccessAdmin: true,
      })
      navigate(target, { replace: true })
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Đăng nhập không thành công', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-primary selection:text-white">
      {/* Background Decorative Glow */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-primary/10 rounded-full blur-3xl" />
        <div className="absolute -bottom-40 right-10 w-[500px] h-[400px] bg-blue-600/10 rounded-full blur-3xl" />
      </div>

      <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4">
        {/* Brand / Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl shadow-primary/5 text-primary mb-4">
            <span className="material-symbols-outlined text-3xl">admin_panel_settings</span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">
            Mobile<span className="text-primary">X</span> Management
          </h1>
          <p className="text-xs text-slate-400 mt-1 uppercase tracking-widest font-semibold">
            Cổng Điều Hành & Quản Trị Hệ Thống
          </p>
        </div>

        {/* Form Card */}
        <div className="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-black/40">
          <div className="mb-6 pb-4 border-b border-slate-800/80">
            <div className="flex items-center gap-2 text-xs text-amber-400 font-medium bg-amber-400/10 border border-amber-400/20 px-3 py-2 rounded-xl">
              <span className="material-symbols-outlined text-base shrink-0">lock</span>
              <span>Khu vực bảo mật nội bộ. Chỉ dành cho Quản trị viên và Nhân viên.</span>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              id="admin-email"
              name="email"
              type="email"
              label="Email quản trị"
              icon="mail"
              value={form.email}
              onChange={handleChange}
              placeholder="admin@example.com"
              error={errors.email?.[0]}
              required
              autoComplete="email"
            />

            <Input
              id="admin-password"
              name="password"
              type="password"
              label="Mật khẩu"
              icon="lock"
              value={form.password}
              onChange={handleChange}
              placeholder="••••••••"
              error={errors.password?.[0]}
              required
              autoComplete="current-password"
            />

            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={loading}
              className="w-full mt-2 font-semibold shadow-lg shadow-primary/25"
            >
              Đăng nhập Quản trị
            </Button>
          </form>

          {/* Back to store navigation */}
          <div className="mt-8 pt-6 border-t border-slate-800 text-center">
            <Link
              to="/"
              className="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-white transition-colors"
            >
              <span className="material-symbols-outlined text-sm">arrow_back</span>
              Quay lại Cửa hàng MobieX
            </Link>
          </div>
        </div>

        {/* Footer info */}
        <p className="mt-6 text-center text-xs text-slate-400">
          MobieX Internal Operation Portal &copy; {new Date().getFullYear()}
        </p>
      </div>
    </div>
  )
}
