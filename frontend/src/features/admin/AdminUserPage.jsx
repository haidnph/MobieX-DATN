// src/features/admin/AdminUserPage.jsx
// Admin user management: list, filter, toggle active/blocked status, and RBAC role assignment.

import { useCallback, useEffect, useMemo, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatDate } from '../../utils/formatDate'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'

const ROLE_CONFIG = {
  admin: { label: 'Quản trị viên', variant: 'primary', icon: 'shield_person' },
  super_admin: { label: 'Super Admin', variant: 'primary', icon: 'verified_user' },
  staff: { label: 'Nhân viên', variant: 'info', icon: 'badge' },
  customer: { label: 'Khách hàng', variant: 'neutral', icon: 'person' },
}

export default function AdminUserPage() {
  const { user: currentAuthUser } = useAuth()
  const { showToast } = useToast()

  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [totalCount, setTotalCount] = useState(0)

  // Filters
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  // Status toggle confirmation
  const [statusTarget, setStatusTarget] = useState(null)
  const [togglingStatus, setTogglingStatus] = useState(false)

  // Role Assignment Modal
  const [roleModalUser, setRoleModalUser] = useState(null)
  const [selectedRole, setSelectedRole] = useState('admin')
  const [updatingRole, setUpdatingRole] = useState(false)

  // ── Fetch Users ────────────────────────────────────────────────────────────
  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await axiosClient.get(EP.ADMIN_USERS, {
        params: { page },
      })
      const payload = data.data ?? data
      setUsers(Array.isArray(payload) ? payload : (payload.data ?? []))
      setTotalPages(payload.meta?.last_page ?? payload.last_page ?? 1)
      setTotalCount(payload.meta?.total ?? payload.total ?? (Array.isArray(payload) ? payload.length : 0))
    } catch {
      showToast('Không thể tải danh sách tài khoản người dùng.', 'error')
    } finally {
      setLoading(false)
    }
  }, [page, showToast])

  useEffect(() => {
    fetchUsers()
  }, [fetchUsers])

  // ── Status Toggle (Lock / Unlock) ──────────────────────────────────────────
  const handleConfirmStatusToggle = async () => {
    if (!statusTarget) return
    setTogglingStatus(true)
    const newStatus = !statusTarget.is_active

    try {
      await axiosClient.patch(EP.ADMIN_USER_STATUS(statusTarget.id), {
        is_active: newStatus,
      })
      showToast(newStatus ? 'Đã mở khóa tài khoản thành công!' : 'Đã khóa tài khoản thành công!')
      setStatusTarget(null)
      fetchUsers()
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể thay đổi trạng thái tài khoản này.'
      showToast(msg, 'error')
    } finally {
      setTogglingStatus(false)
    }
  }

  // ── Role Management (Assign / Revoke) ──────────────────────────────────────
  const handleAssignRole = async (e) => {
    e.preventDefault()
    if (!roleModalUser) return
    setUpdatingRole(true)

    try {
      await axiosClient.post(EP.ADMIN_USER_ROLES(roleModalUser.id), {
        role: selectedRole,
      })
      showToast(`Đã gán vai trò "${ROLE_CONFIG[selectedRole]?.label || selectedRole}" thành công!`)
      setRoleModalUser(null)
      fetchUsers()
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể gán vai trò này.'
      showToast(msg, 'error')
    } finally {
      setUpdatingRole(false)
    }
  }

  const handleRevokeRole = async (user, roleSlug) => {
    try {
      await axiosClient.delete(EP.ADMIN_USER_ROLE_DELETE(user.id, roleSlug))
      showToast(`Đã gỡ vai trò "${ROLE_CONFIG[roleSlug]?.label || roleSlug}"!`)
      fetchUsers()
      if (roleModalUser?.id === user.id) {
        setRoleModalUser(null)
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể gỡ vai trò này.'
      showToast(msg, 'error')
    }
  }

  // ── Filtered Users (Client-side search on current page) ────────────────────
  const filteredUsers = useMemo(() => {
    return users.filter((u) => {
      // Search text
      if (search.trim()) {
        const q = search.toLowerCase().trim()
        const matchName = u.name?.toLowerCase().includes(q)
        const matchEmail = u.email?.toLowerCase().includes(q)
        const matchPhone = u.phone?.toLowerCase().includes(q)
        if (!matchName && !matchEmail && !matchPhone) return false
      }

      // Role filter
      if (roleFilter) {
        const hasRole = u.roles?.some((r) => r.slug === roleFilter)
        if (!hasRole) return false
      }

      // Status filter
      if (statusFilter !== '') {
        const wantActive = statusFilter === 'active'
        if (Boolean(u.is_active) !== wantActive) return false
      }

      return true
    })
  }, [users, search, roleFilter, statusFilter])

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Quản lý Tài khoản & Phân quyền</h1>
          <p className="text-sm text-outline mt-1">
            Kiểm soát danh sách tài khoản, khóa tài khoản vi phạm và phân quyền vai trò quản trị (RBAC)
          </p>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div className="relative w-full md:w-80">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">
            search
          </span>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Tìm theo tên, email, SĐT..."
            className="w-full pl-9 pr-4 py-2 bg-surface-container-low text-on-surface text-sm rounded-xl border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        <div className="flex flex-wrap items-center gap-3 w-full md:w-auto justify-end">
          <select
            value={roleFilter}
            onChange={(e) => setRoleFilter(e.target.value)}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả vai trò</option>
            <option value="admin">Quản trị viên (Admin)</option>
            <option value="staff">Nhân viên (Staff)</option>
            <option value="customer">Khách hàng (Customer)</option>
          </select>

          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="blocked">Đã bị khóa</option>
          </select>

          <div className="text-xs text-outline pl-2 hidden sm:block">
            Tổng: <strong className="text-on-surface">{totalCount}</strong> người dùng
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner size="lg" />
          </div>
        ) : filteredUsers.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
            <span className="material-symbols-outlined text-4xl">person_off</span>
            <p className="text-sm">Không tìm thấy tài khoản người dùng nào phù hợp</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                  <th className="py-3 px-4">Người dùng</th>
                  <th className="py-3 px-4">Liên hệ</th>
                  <th className="py-3 px-4">Vai trò (Roles)</th>
                  <th className="py-3 px-4 text-center">Trạng thái</th>
                  <th className="py-3 px-4">Ngày tham gia</th>
                  <th className="py-3 px-4 text-right">Hành động</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                {filteredUsers.map((u) => {
                  const isActive = Boolean(u.is_active)
                  const isCurrentAuth = Number(u.id) === Number(currentAuthUser?.id)
                  const roles = u.roles || []

                  return (
                    <tr key={u.id} className="hover:bg-surface-container-high/30 transition-colors">
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center shrink-0 text-sm">
                            {u.name?.charAt(0)?.toUpperCase() || 'U'}
                          </div>
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-semibold text-on-surface">{u.name}</span>
                              {isCurrentAuth && (
                                <span className="bg-primary/15 text-primary text-[10px] font-bold px-1.5 py-0.2 rounded">
                                  Bạn
                                </span>
                              )}
                            </div>
                            <span className="text-xs text-outline font-mono">ID: #{u.id}</span>
                          </div>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 text-xs">
                        <p className="text-on-surface font-medium">{u.email}</p>
                        <p className="text-outline mt-0.5">{u.phone || 'Chưa cập nhật SĐT'}</p>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="flex flex-wrap gap-1.5 items-center">
                          {roles.length === 0 ? (
                            <Badge variant="neutral" className="text-[11px]">Khách hàng</Badge>
                          ) : (
                            roles.map((r) => {
                              const cfg = ROLE_CONFIG[r.slug] || { label: r.name || r.slug, variant: 'neutral' }
                              return (
                                <Badge key={r.slug} variant={cfg.variant} className="text-[11px] inline-flex items-center gap-1">
                                  {cfg.icon && <span className="material-symbols-outlined text-xs">{cfg.icon}</span>}
                                  {cfg.label}
                                </Badge>
                              )
                            })
                          )}
                        </div>
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <Badge variant={isActive ? 'success' : 'error'}>
                          {isActive ? 'Hoạt động' : 'Bị khóa'}
                        </Badge>
                      </td>
                      <td className="py-3.5 px-4 text-xs text-outline font-mono">
                        {u.created_at ? formatDate(u.created_at) : '—'}
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            onClick={() => setRoleModalUser(u)}
                            title="Phân quyền vai trò"
                            className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors"
                          >
                            <span className="material-symbols-outlined text-lg">admin_panel_settings</span>
                          </button>

                          <button
                            type="button"
                            onClick={() => setStatusTarget(u)}
                            disabled={isCurrentAuth}
                            title={isCurrentAuth ? 'Không thể khóa tài khoản của chính bạn' : isActive ? 'Khóa tài khoản' : 'Mở khóa tài khoản'}
                            className={[
                              'p-1.5 rounded-lg transition-colors',
                              isCurrentAuth
                                ? 'opacity-30 cursor-not-allowed text-outline'
                                : isActive
                                  ? 'hover:bg-error/10 text-on-surface-variant hover:text-error'
                                  : 'hover:bg-secondary/10 text-on-surface-variant hover:text-secondary',
                            ].join(' ')}
                          >
                            <span className="material-symbols-outlined text-lg">
                              {isActive ? 'lock' : 'lock_open'}
                            </span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center mt-4">
          <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
      )}

      {/* Lock / Unlock Status Confirm Modal */}
      <ConfirmDialog
        open={Boolean(statusTarget)}
        onClose={() => setStatusTarget(null)}
        onConfirm={handleConfirmStatusToggle}
        title={statusTarget?.is_active ? 'Xác nhận khóa tài khoản' : 'Mở khóa tài khoản'}
        message={
          statusTarget?.is_active
            ? `Bạn có chắc muốn khóa tài khoản "${statusTarget?.name}" (${statusTarget?.email})? Người dùng này sẽ không thể đăng nhập hoặc đặt đơn hàng mới.`
            : `Mở khóa cho tài khoản "${statusTarget?.name}" (${statusTarget?.email}) để tiếp tục sử dụng hệ thống?`
        }
        confirmText={statusTarget?.is_active ? 'Khóa tài khoản' : 'Mở khóa'}
        variant={statusTarget?.is_active ? 'error' : 'primary'}
        loading={togglingStatus}
      />

      {/* Role Assignment Modal */}
      {roleModalUser && (
        <Modal
          open={Boolean(roleModalUser)}
          onClose={() => setRoleModalUser(null)}
          title={`Phân quyền tài khoản: ${roleModalUser.name}`}
          size="md"
        >
          <div className="space-y-4">
            <div className="p-3 rounded-xl bg-surface-container-high/40 text-xs text-on-surface-variant flex flex-col gap-1 border border-outline-variant/20">
              <p><strong className="text-on-surface">Email:</strong> {roleModalUser.email}</p>
              <div className="flex items-center gap-2 mt-1">
                <strong className="text-on-surface">Vai trò hiện có:</strong>
                <div className="flex flex-wrap gap-1">
                  {roleModalUser.roles?.length === 0 ? (
                    <span className="text-outline">Chưa có vai trò (Mặc định khách hàng)</span>
                  ) : (
                    roleModalUser.roles?.map((r) => (
                      <span
                        key={r.slug}
                        className="inline-flex items-center gap-1 bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-md"
                      >
                        {ROLE_CONFIG[r.slug]?.label || r.name}
                        {r.slug !== 'customer' && (
                          <button
                            type="button"
                            onClick={() => handleRevokeRole(roleModalUser, r.slug)}
                            title="Gỡ quyền này"
                            className="hover:text-error ml-1 font-bold"
                          >
                            ×
                          </button>
                        )}
                      </span>
                    ))
                  )}
                </div>
              </div>
            </div>

            <form onSubmit={handleAssignRole} className="space-y-3">
              <div className="flex flex-col gap-1">
                <label className="text-sm font-medium text-on-surface-variant">Chọn vai trò muốn gán thêm:</label>
                <select
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value)}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="admin">Quản trị viên hệ thống (Admin)</option>
                  <option value="staff">Nhân viên quản lý kho / đơn hàng (Staff)</option>
                </select>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/10">
                <Button type="button" variant="ghost" onClick={() => setRoleModalUser(null)} disabled={updatingRole}>
                  Đóng
                </Button>
                <Button type="submit" variant="primary" loading={updatingRole}>
                  Gán vai trò
                </Button>
              </div>
            </form>
          </div>
        </Modal>
      )}
    </div>
  )
}
