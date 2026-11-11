// src/features/auth/ForgotPasswordPage.jsx
// Request password reset email via POST /auth/forgot-password.

import { useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'

export default function ForgotPasswordPage() {
  const { showToast } = useToast()

  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState({})
  const [submitted, setSubmitted] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setErrors({})
    try {
      await axiosClient.post(EP.AUTH_FORGOT_PASSWORD, { email })
      setSubmitted(true)
      showToast('Đã gửi yêu cầu! Vui lòng kiểm tra hộp thư email của bạn.', 'success')
    } catch (err) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else {
        showToast(err.response?.data?.message || err.message || 'Không thể gửi email đặt lại mật khẩu', 'error')
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
          <div className="text-center mb-8">
            <Link to="/" className="inline-block mb-3">
              <span className="text-2xl font-bold text-on-surface">
                Mobile<span className="text-primary">X</span>
              </span>
            </Link>
            <h1 className="text-xl font-bold text-on-surface">
              Quên mật khẩu?
            </h1>
            <p className="text-sm text-on-surface-variant mt-2 leading-relaxed">
              {submitted
                ? 'Liên kết khôi phục đã được gửi tới hòm thư của bạn.'
                : 'Nhập địa chỉ email liên kết với tài khoản MobieX để nhận liên kết đặt lại mật khẩu.'}
            </p>
          </div>

          {submitted ? (
            <div className="flex flex-col items-center text-center space-y-4">
              <div className="w-16 h-16 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shadow-sm">
                <span className="material-symbols-outlined text-3xl">mark_email_read</span>
              </div>

              <div className="bg-surface-container-high/60 rounded-xl p-4 w-full text-left border border-outline-variant/20">
                <p className="text-xs text-on-surface-variant font-medium">Email người nhận:</p>
                <p className="text-sm font-semibold text-on-surface break-all">{email}</p>
                <p className="text-xs text-outline mt-2 leading-normal">
                  Vui lòng kiểm tra hộp thư đến (và thư mục Spam/Rác) rồi nhấn vào liên kết bên trong để tiến hành đổi mật khẩu mới.
                </p>
              </div>

              <div className="flex flex-col gap-2 w-full pt-2">
                <button
                  type="button"
                  onClick={() => setSubmitted(false)}
                  className="text-xs text-primary hover:underline font-medium py-1"
                >
                  Gửi lại email hoặc đổi địa chỉ khác
                </button>
                <Link
                  to="/login"
                  className="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-surface-bright text-on-surface text-sm font-semibold transition-colors"
                >
                  Quay lại Đăng nhập
                </Link>
              </div>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              <Input
                id="email"
                name="email"
                type="email"
                label="Địa chỉ Email"
                icon="email"
                value={email}
                onChange={(e) => {
                  setEmail(e.target.value)
                  if (errors.email) setErrors((prev) => ({ ...prev, email: null }))
                }}
                placeholder="email@example.com"
                error={errors.email?.[0]}
                required
                autoComplete="email"
              />

              <Button type="submit" loading={loading} className="w-full mt-2">
                Gửi liên kết đặt lại
              </Button>

              <div className="flex items-center justify-center gap-1 text-sm text-on-surface-variant mt-4">
                <span>Nhớ mật khẩu rồi?</span>
                <Link to="/login" className="text-primary hover:underline font-medium">
                  Đăng nhập ngay
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
