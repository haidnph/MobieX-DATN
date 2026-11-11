// src/features/auth/LoginPage.jsx
// User login form — POST /auth/login → store token → redirect to home or previous location.

import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'

export default function LoginPage() {
  const { login } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const location = useLocation()
  const from = location.state?.from?.pathname ?? '/'

  const [form, setForm] = useState({ email: '', password: '' })
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState({})

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setErrors((prev) => ({ ...prev, [e.target.name]: '' }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setErrors({})
    try {
      const user = await login(form)
      showToast(`Chào mừng trở lại, ${user.name}!`, 'success')
      // Admin users → admin dashboard; others → previous page or home
      if (user.roles?.includes('admin') || user.roles?.includes('super-admin')) {
        navigate('/admin', { replace: true })
      } else {
        navigate(from, { replace: true })
      }
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message, 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        {/* Card */}
        <div className="bg-surface-container rounded-2xl border border-outline-variant/20 shadow-2xl p-8">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-on-surface">
              Đăng nhập <span className="text-primary">MobieX</span>
            </h1>
            <p className="text-sm text-on-surface-variant mt-2">
              Chào mừng bạn quay trở lại!
            </p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Input
              id="email"
              name="email"
              type="email"
              label="Email"
              icon="email"
              value={form.email}
              onChange={handleChange}
              placeholder="email@example.com"
              error={errors.email?.[0]}
              required
              autoComplete="email"
            />

            <Input
              id="password"
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

            <div className="flex justify-end">
              <Link
                to="/forgot-password"
                className="text-xs text-primary hover:text-primary-fixed-dim transition-colors"
              >
                Quên mật khẩu?
              </Link>
            </div>

            <Button type="submit" loading={loading} className="w-full mt-2">
              Đăng nhập
            </Button>
          </form>

          <p className="text-center text-sm text-on-surface-variant mt-6">
            Chưa có tài khoản?{' '}
            <Link to="/register" className="text-primary hover:underline font-medium">
              Đăng ký ngay
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
