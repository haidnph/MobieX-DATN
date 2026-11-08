// src/features/auth/RegisterPage.jsx
// User registration form — POST /auth/register → auto-login → redirect home.

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import storage from '../../utils/storage'

export default function RegisterPage() {
  const { login } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
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
      await axiosClient.post(EP.AUTH_REGISTER, form)
      // Auto-login after successful registration
      await login({ email: form.email, password: form.password })
      showToast('Đăng ký thành công! Chào mừng bạn đến với MobieX.', 'success')
      navigate('/', { replace: true })
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message, 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center px-4 py-8">
      <div className="w-full max-w-md">
        <div className="bg-surface-container rounded-2xl border border-outline-variant/20 shadow-2xl p-8">
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-on-surface">
              Tạo tài khoản <span className="text-primary">MobieX</span>
            </h1>
            <p className="text-sm text-on-surface-variant mt-2">Mua sắm thông minh, trải nghiệm tuyệt vời</p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Input
              id="name"
              name="name"
              type="text"
              label="Họ và tên"
              icon="person"
              value={form.name}
              onChange={handleChange}
              placeholder="Nguyễn Văn A"
              error={errors.name?.[0]}
              required
            />
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
              placeholder="Tối thiểu 8 ký tự"
              error={errors.password?.[0]}
              required
              autoComplete="new-password"
            />
            <Input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              label="Xác nhận mật khẩu"
              icon="lock_open"
              value={form.password_confirmation}
              onChange={handleChange}
              placeholder="Nhập lại mật khẩu"
              error={errors.password_confirmation?.[0]}
              required
              autoComplete="new-password"
            />

            <Button type="submit" loading={loading} className="w-full mt-2">
              Đăng ký
            </Button>
          </form>

          <p className="text-center text-sm text-on-surface-variant mt-6">
            Đã có tài khoản?{' '}
            <Link to="/login" className="text-primary hover:underline font-medium">
              Đăng nhập
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
