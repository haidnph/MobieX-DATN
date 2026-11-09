// src/features/account/ProfilePage.jsx
// Customer profile management: personal info view/edit, password change, and account shortcuts.

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function ProfilePage() {
  const { user, setUser } = useAuth()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(false)

  // Profile Form
  const [profileForm, setProfileForm] = useState({
    name: '',
    phone: '',
  })
  const [profileErrors, setProfileErrors] = useState({})
  const [updatingProfile, setUpdatingProfile] = useState(false)

  // Password Form
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [passwordErrors, setPasswordErrors] = useState({})
  const [updatingPassword, setUpdatingPassword] = useState(false)

  // ── Fetch Profile ──────────────────────────────────────────────────────────
  useEffect(() => {
    if (user) {
      setProfileForm({
        name: user.name ?? '',
        phone: user.phone ?? '',
      })
    }
  }, [user])

  // ── Update Profile Info ───────────────────────────────────────────────────
  const handleUpdateProfile = async (e) => {
    e.preventDefault()
    setUpdatingProfile(true)
    setProfileErrors({})

    try {
      const { data } = await axiosClient.patch(EP.PROFILE, {
        name: profileForm.name.trim(),
        phone: profileForm.phone.trim() || null,
      })
      const updatedUser = data.data?.user ?? data.data ?? data
      if (setUser) setUser(updatedUser)
      showToast('Cập nhật thông tin cá nhân thành công!')
    } catch (err) {
      if (err.response?.status === 422) {
        setProfileErrors(err.response.data?.errors ?? {})
      } else {
        const msg = err.response?.data?.message || 'Không thể cập nhật hồ sơ.'
        showToast(msg, 'error')
      }
    } finally {
      setUpdatingProfile(false)
    }
  }

  // ── Change Password ───────────────────────────────────────────────────────
  const handleChangePassword = async (e) => {
    e.preventDefault()
    setUpdatingPassword(true)
    setPasswordErrors({})

    try {
      await axiosClient.patch(EP.PROFILE_PASSWORD, {
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      })
      showToast('Đổi mật khẩu thành công! Hãy ghi nhớ mật khẩu mới của bạn.')
      setPasswordForm({
        current_password: '',
        password: '',
        password_confirmation: '',
      })
    } catch (err) {
      if (err.response?.status === 422) {
        setPasswordErrors(err.response.data?.errors ?? {})
      } else {
        const msg = err.response?.data?.message || 'Đổi mật khẩu thất bại. Vui lòng kiểm tra lại mật khẩu cũ.'
        showToast(msg, 'error')
      }
    } finally {
      setUpdatingPassword(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 space-y-8">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-outline">
        <Link to="/" className="hover:text-primary transition-colors">Trang chủ</Link>
        <span>/</span>
        <span className="text-on-surface font-medium">Tài khoản của tôi</span>
      </div>

      {/* Profile Overview Card */}
      <div className="bg-surface-container rounded-3xl p-6 sm:p-8 border border-outline-variant/20 shadow-sm flex flex-col sm:flex-row items-center gap-6 justify-between">
        <div className="flex items-center gap-5">
          <div className="w-16 h-16 rounded-2xl bg-primary text-on-primary text-2xl font-black flex items-center justify-center shadow-lg">
            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
          </div>
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-on-surface">{user?.name}</h1>
            <p className="text-sm text-outline font-mono mt-0.5">{user?.email}</p>
            <div className="flex items-center gap-2 mt-2">
              <Badge variant="success" className="text-xs">Tài khoản đã xác thực</Badge>
              {user?.roles?.map((r) => (
                <Badge key={r.slug} variant="primary" className="text-xs">
                  {r.name || r.slug}
                </Badge>
              ))}
            </div>
          </div>
        </div>

        {/* Shortcuts */}
        <div className="flex flex-wrap sm:flex-col gap-2 w-full sm:w-auto">
          <Link
            to="/account/addresses"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-primary hover:text-on-primary transition-colors text-xs font-semibold text-on-surface"
          >
            <span className="material-symbols-outlined text-base">pin_drop</span>
            Sổ địa chỉ giao hàng
          </Link>
          <Link
            to="/orders"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-primary hover:text-on-primary transition-colors text-xs font-semibold text-on-surface"
          >
            <span className="material-symbols-outlined text-base">receipt_long</span>
            Lịch sử đơn hàng
          </Link>
        </div>
      </div>

      {/* Two Column Grid for Forms */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Update Profile Form */}
        <div className="bg-surface-container rounded-2xl p-6 border border-outline-variant/20 space-y-4">
          <div className="flex items-center gap-2 border-b border-outline-variant/20 pb-3">
            <span className="material-symbols-outlined text-primary">person</span>
            <h2 className="text-base font-bold text-on-surface">Thông tin cá nhân</h2>
          </div>

          <form onSubmit={handleUpdateProfile} className="space-y-4">
            <Input
              label="Họ và tên *"
              value={profileForm.name}
              onChange={(e) => setProfileForm({ ...profileForm, name: e.target.value })}
              error={profileErrors.name?.[0]}
              required
            />

            <div>
              <label className="text-sm font-medium text-on-surface-variant block mb-1">Email đăng nhập</label>
              <input
                type="text"
                value={user?.email || ''}
                disabled
                className="w-full bg-surface-container-high/50 text-outline rounded-xl px-3 py-2 text-sm border border-outline-variant/20 cursor-not-allowed"
              />
              <span className="text-[11px] text-outline mt-1 block">Email là định danh tài khoản, không thể thay đổi</span>
            </div>

            <Input
              label="Số điện thoại liên hệ"
              value={profileForm.phone}
              onChange={(e) => setProfileForm({ ...profileForm, phone: e.target.value })}
              placeholder="0912345678"
              error={profileErrors.phone?.[0]}
            />

            <div className="pt-2">
              <Button type="submit" variant="primary" loading={updatingProfile} className="w-full">
                Lưu thay đổi
              </Button>
            </div>
          </form>
        </div>

        {/* Change Password Form */}
        <div className="bg-surface-container rounded-2xl p-6 border border-outline-variant/20 space-y-4">
          <div className="flex items-center gap-2 border-b border-outline-variant/20 pb-3">
            <span className="material-symbols-outlined text-primary">lock_reset</span>
            <h2 className="text-base font-bold text-on-surface">Đổi mật khẩu</h2>
          </div>

          <form onSubmit={handleChangePassword} className="space-y-4">
            <Input
              label="Mật khẩu hiện tại *"
              type="password"
              value={passwordForm.current_password}
              onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
              placeholder="••••••••"
              error={passwordErrors.current_password?.[0]}
              required
            />

            <Input
              label="Mật khẩu mới *"
              type="password"
              value={passwordForm.password}
              onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
              placeholder="Tối thiểu 8 ký tự"
              error={passwordErrors.password?.[0]}
              required
            />

            <Input
              label="Xác nhận mật khẩu mới *"
              type="password"
              value={passwordForm.password_confirmation}
              onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
              placeholder="Nhập lại mật khẩu mới"
              error={passwordErrors.password_confirmation?.[0]}
              required
            />

            <div className="pt-2">
              <Button type="submit" variant="ghost" loading={updatingPassword} className="w-full border border-outline-variant/30 hover:border-primary">
                Cập nhật mật khẩu
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
