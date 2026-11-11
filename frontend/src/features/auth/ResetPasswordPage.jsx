// src/features/auth/ResetPasswordPage.jsx
// Reset password page handling token verification via POST /auth/reset-password.

import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const token = searchParams.get('token') || ''
  const emailFromQuery = searchParams.get('email') || ''

  const [form, setForm] = useState({
    token: token,
    email: emailFromQuery,
    password: '',
    password_confirmation: '',
  })
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState({})
  const [isSuccess, setIsSuccess] = useState(false)

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }))
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (form.password !== form.password_confirmation) {
      setErrors((prev) => ({
        ...prev,
        password_confirmation: ['Xác nhận mật khẩu không khớp.'],
      }))
      return
    }

    setLoading(true)
    setErrors({})

    try {
      await axiosClient.post(EP.AUTH_RESET_PASSWORD, form)
      setIsSuccess(true)
      showToast('Đặt lại mật khẩu thành công! Bạn có thể đăng nhập ngay.', 'success')
    } catch (err) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else {
        const msg = err.response?.data?.message || err.message || 'Mã xác thực không hợp lệ hoặc đã hết hạn.'
        showToast(msg, 'error')
        setErrors({ general: msg })
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center px-4 py-8">
      <div className="w-full max-w-md">
        <div className="bg-surface-container rounded-2xl border border-outline-variant/20 shadow-2xl p-8">
          {/* Header */}
          <div className="text-center mb-6">
            <Link to="/" className="inline-block mb-3">
              <span className="text-2xl font-bold text-on-surface">
                Mobile<span className="text-primary">X</span>
              </span>
            </Link>
            <h1 className="text-xl font-bold text-on-surface">
              Đặt lại mật khẩu mới
            </h1>
            <p className="text-sm text-on-surface-variant mt-1.5">
              Tạo mật khẩu an toàn cho tài khoản của bạn.
            </p>
          </div>

          {!token ? (
            <div className="space-y-4 text-center">
              <div className="p-4 rounded-xl bg-error-container/20 border border-error/30 text-error text-sm">
                <span className="material-symbols-outlined text-2xl block mb-1">warning</span>
                Liên kết đặt lại mật khẩu không hợp lệ hoặc thiếu mã token. Vui lòng yêu cầu cấp lại liên kết mới.
              </div>
              <Link
                to="/forgot-password"
                className="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-primary text-on-primary font-semibold text-sm hover:opacity-90 transition-opacity"
              >
                Gửi lại yêu cầu khôi phục
              </Link>
            </div>
          ) : isSuccess ? (
            <div className="text-center space-y-4">
              <div className="w-16 h-16 mx-auto rounded-full bg-secondary-container/30 border border-secondary/40 flex items-center justify-center text-secondary">
                <span className="material-symbols-outlined text-3xl">check_circle</span>
              </div>
              <div>
                <h3 className="text-lg font-bold text-on-surface">Thành công!</h3>
                <p className="text-xs text-on-surface-variant mt-1">
                  Mật khẩu tài khoản của bạn đã được cập nhật thành công. Hãy dùng mật khẩu mới để đăng nhập.
                </p>
              </div>
              <Button
                type="button"
                onClick={() => navigate('/login', { replace: true })}
                className="w-full mt-2"
              >
                Đăng nhập ngay
              </Button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              {errors.general && (
                <div className="p-3 rounded-xl bg-error-container/20 border border-error/30 text-error text-xs">
                  {errors.general}
                </div>
              )}

              <Input
                id="email"
                name="email"
                type="email"
                label="Địa chỉ Email"
                icon="email"
                value={form.email}
                onChange={handleChange}
                placeholder="email@example.com"
                error={errors.email?.[0]}
                required
                readOnly={Boolean(emailFromQuery)}
                className={emailFromQuery ? 'opacity-80' : ''}
              />

              <Input
                id="password"
                name="password"
                type="password"
                label="Mật khẩu mới"
                icon="lock"
                value={form.password}
                onChange={handleChange}
                placeholder="••••••••"
                error={errors.password?.[0]}
                required
                autoComplete="new-password"
              />

              <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                label="Xác nhận mật khẩu mới"
                icon="lock_reset"
                value={form.password_confirmation}
                onChange={handleChange}
                placeholder="••••••••"
                error={errors.password_confirmation?.[0]}
                required
                autoComplete="new-password"
              />

              <div className="p-3 rounded-xl bg-surface-container-high/60 border border-outline-variant/20 text-[11px] text-on-surface-variant space-y-1">
                <p className="font-semibold text-on-surface">Yêu cầu bảo mật mật khẩu:</p>
                <ul className="list-disc list-inside space-y-0.5 pl-1 text-outline">
                  <li>Tối thiểu 8 ký tự</li>
                  <li>Có ít nhất 1 chữ hoa, 1 chữ thường</li>
                  <li>Có ít nhất 1 chữ số và 1 ký tự đặc biệt</li>
                </ul>
              </div>

              <Button type="submit" loading={loading} className="w-full mt-2">
                Lưu mật khẩu mới
              </Button>

              <div className="text-center mt-2">
                <Link to="/login" className="text-xs text-on-surface-variant hover:text-on-surface transition-colors">
                  Quay lại Đăng nhập
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
