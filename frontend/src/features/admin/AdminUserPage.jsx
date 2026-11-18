// src/features/admin/AdminUserPage.jsx
// Full-featured Admin User Management: Search, Filter, Sort, Pagination, User CRUD,
// RBAC Roles Assignment, Status Toggle, Password Reset, Detail Drawer/Modal, and Interactive RBAC Matrix.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatDate } from '../../utils/formatDate'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'
import Pagination from '../../components/common/Pagination'

export const ROLE_CONFIG = {
  'super-admin': {
    label: 'Super Admin',
    sublabel: 'Quản trị cấp cao',
    variant: 'primary',
    icon: 'verified_user',
    badgeClass: 'text-purple-700 dark:text-purple-300 bg-purple-100/80 dark:bg-purple-950/50 border border-purple-300 dark:border-purple-800/60',
    description: 'Toàn quyền tối cao và quản trị hệ thống, quản lý phân quyền vai trò (RBAC).',
  },
  super_admin: {
    label: 'Super Admin',
    sublabel: 'Quản trị cấp cao',
    variant: 'primary',
    icon: 'verified_user',
    badgeClass: 'text-purple-700 dark:text-purple-300 bg-purple-100/80 dark:bg-purple-950/50 border border-purple-300 dark:border-purple-800/60',
    description: 'Toàn quyền tối cao và quản trị hệ thống, quản lý phân quyền vai trò (RBAC).',
  },
  admin: {
    label: 'Quản trị viên',
    sublabel: 'Admin vận hành',
    variant: 'primary',
    icon: 'shield_person',
    badgeClass: 'text-primary bg-primary/10 border border-primary/20',
    description: 'Quản trị vận hành kinh doanh, sản phẩm, đơn hàng, khách hàng, nội dung.',
  },
  accountant: {
    label: 'Kế toán',
    sublabel: 'Tài chính & Thanh toán',
    variant: 'info',
    icon: 'calculate',
    badgeClass: 'text-sky-700 dark:text-sky-300 bg-sky-100/80 dark:bg-sky-950/50 border border-sky-300 dark:border-sky-800/60',
    description: 'Theo dõi thanh toán, doanh thu và báo cáo tài chính.',
  },
  warehouse: {
    label: 'Thủ kho',
    sublabel: 'Kho & Vận chuyển',
    variant: 'warning',
    icon: 'warehouse',
    badgeClass: 'text-amber-700 dark:text-amber-300 bg-amber-100/80 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-800/60',
    description: 'Quản lý tồn kho, nhập/xuất kho và cập nhật vận chuyển.',
  },
  sales: {
    label: 'Bán hàng',
    sublabel: 'Kinh doanh & Khuyến mãi',
    variant: 'success',
    icon: 'storefront',
    badgeClass: 'text-emerald-700 dark:text-emerald-300 bg-emerald-100/80 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800/60',
    description: 'Quản lý đơn hàng, khách hàng, chiến dịch flash sale và mã giảm giá.',
  },
  customer: {
    label: 'Khách hàng',
    sublabel: 'Người mua hàng',
    variant: 'neutral',
    icon: 'person',
    badgeClass: 'text-outline bg-surface-container-high border border-outline-variant/30',
    description: 'Tài khoản người mua hàng, quản lý đơn hàng và đánh giá cá nhân.',
  },
}

const ORDER_STATUS_LABELS = {
  pending:          { label: 'Chờ duyệt',        variant: 'warning' },
  confirmed:        { label: 'Đã xác nhận',      variant: 'info' },
  processing:       { label: 'Đang xử lý',       variant: 'info' },
  ready_for_pickup: { label: 'Chờ giao hàng',    variant: 'info' },
  shipping:         { label: 'Đang giao',        variant: 'info' },
  delivered:        { label: 'Đã giao',          variant: 'success' },
  completed:        { label: 'Hoàn tất',         variant: 'success' },
  cancelled:        { label: 'Đã hủy',           variant: 'error' },
  returned:         { label: 'Đã hoàn trả',      variant: 'neutral' },
}

const PERMISSION_MODULES = [
  { prefix: 'dashboard',        label: 'Tổng quan & Dashboard',      icon: 'dashboard' },
  { prefix: 'users',            label: 'Quản lý Người dùng',         icon: 'group' },
  { prefix: 'roles',            label: 'Phân quyền & Vai trò',       icon: 'security' },
  { prefix: 'categories',       label: 'Danh mục Sản phẩm',          icon: 'category' },
  { prefix: 'brands',           label: 'Thương hiệu',                icon: 'branding_watermark' },
  { prefix: 'attributes',       label: 'Thuộc tính Kỹ thuật',        icon: 'tune' },
  { prefix: 'products',         label: 'Sản phẩm & Biến thể',        icon: 'inventory_2' },
  { prefix: 'inventory',        label: 'Kho & Tồn kho',              icon: 'warehouse' },
  { prefix: 'orders',           label: 'Đơn hàng & Xử lý đơn',       icon: 'shopping_cart' },
  { prefix: 'payments',         label: 'Thanh toán & Giao dịch',     icon: 'payments' },
  { prefix: 'coupons',          label: 'Mã giảm giá (Coupon)',       icon: 'confirmation_number' },
  { prefix: 'flash_sales',      label: 'Flash Sale & Khuyến mãi',    icon: 'bolt' },
  { prefix: 'banners',          label: 'Banner & Quảng cáo',         icon: 'ad_units' },
  { prefix: 'shipping_methods', label: 'Phương thức Vận chuyển',     icon: 'local_shipping' },
  { prefix: 'reviews',          label: 'Đánh giá & Kiểm duyệt',      icon: 'rate_review' },
  { prefix: 'reports',          label: 'Báo cáo & Thống kê',         icon: 'analytics' },
  { prefix: 'profile',          label: 'Hồ sơ Cá nhân',              icon: 'badge' },
  { prefix: 'addresses',        label: 'Địa chỉ Khách hàng',         icon: 'location_on' },
]

export default function AdminUserPage() {
  const { user: currentAuthUser, hasPermission } = useAuth()
  const { showToast } = useToast()

  // Tabs: 'users' | 'matrix'
  const [activeTab, setActiveTab] = useState('users')

  // Users data & pagination
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [totalCount, setTotalCount] = useState(0)
  const [stats, setStats] = useState({ total: 0, active: 0, blocked: 0 })

  // Roles & Permissions from API
  const [roles, setRoles] = useState([])
  const [permissions, setPermissions] = useState([])
  const [loadingRoles, setLoadingRoles] = useState(false)
  const [rolesError, setRolesError] = useState(null)

  // Filters & Sorting
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [fromDate, setFromDate] = useState('')
  const [toDate, setToDate] = useState('')
  const [sortOption, setSortOption] = useState('id-desc')

  // RBAC Matrix Editing state
  const [isEditingMatrix, setIsEditingMatrix] = useState(false)
  const [matrixDraft, setMatrixDraft] = useState({}) // { [roleSlug]: Set<string> }
  const [savingMatrix, setSavingMatrix] = useState(false)
  const [showSaveMatrixConfirm, setShowSaveMatrixConfirm] = useState(false)

  // Modals state
  const [statusTarget, setStatusTarget] = useState(null)
  const [togglingStatus, setTogglingStatus] = useState(false)

  const [roleModalUser, setRoleModalUser] = useState(null)
  const [selectedRole, setSelectedRole] = useState('')
  const [updatingRole, setUpdatingRole] = useState(false)

  const [showCreateModal, setShowCreateModal] = useState(false)
  const [createForm, setCreateForm] = useState({ name: '', email: '', phone: '', password: '', role: '' })
  const [createErrors, setCreateErrors] = useState({})
  const [creating, setCreating] = useState(false)

  const [editModalUser, setEditModalUser] = useState(null)
  const [editForm, setEditForm] = useState({ name: '', email: '', phone: '' })
  const [editErrors, setEditErrors] = useState({})
  const [updatingUser, setUpdatingUser] = useState(false)

  const [detailUser, setDetailUser] = useState(null)
  const [detailData, setDetailData] = useState(null)
  const [loadingDetail, setLoadingDetail] = useState(false)

  const [passwordTargetUser, setPasswordTargetUser] = useState(null)
  const [newPassword, setNewPassword] = useState('')
  const [resettingPassword, setResettingPassword] = useState(false)

  // Can manage RBAC roles
  const canManageRoles = hasPermission('roles.manage')

  // Debounced search
  const debounceRef = useRef(null)
  const handleSearchChange = (val) => {
    setSearchInput(val)
    clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setSearch(val)
      setPage(1)
    }, 350)
  }

  // Load Roles & Permissions
  const loadRolesAndPermissions = useCallback(async () => {
    setLoadingRoles(true)
    setRolesError(null)
    try {
      const [roleRes, permRes] = await Promise.all([
        axiosClient.get(EP.ADMIN_ROLES),
        axiosClient.get(EP.ADMIN_PERMISSIONS),
      ])

      const rawRoles = roleRes.data?.data?.roles ?? roleRes.data?.data ?? []
      const rawPerms = permRes.data?.data?.permissions ?? permRes.data?.data ?? []

      // Normalize roles order: super-admin first, then admin, accountant, warehouse, sales, customer
      const roleOrder = ['super-admin', 'admin', 'accountant', 'warehouse', 'sales', 'customer']
      const sortedRoles = [...rawRoles].sort((a, b) => {
        const idxA = roleOrder.indexOf(a.slug)
        const idxB = roleOrder.indexOf(b.slug)
        if (idxA !== -1 && idxB !== -1) return idxA - idxB
        if (idxA !== -1) return -1
        if (idxB !== -1) return 1
        return a.slug.localeCompare(b.slug)
      })

      setRoles(sortedRoles)
      setPermissions(rawPerms)

      // Initialize matrix draft
      const draft = {}
      sortedRoles.forEach((r) => {
        const perms = (r.permissions || []).map((p) => (typeof p === 'string' ? p : p.slug))
        draft[r.slug] = new Set(perms)
      })
      setMatrixDraft(draft)

      const firstNonCust = sortedRoles.find((r) => r.slug !== 'customer')
      if (firstNonCust) setSelectedRole(firstNonCust.slug)
    } catch (err) {
      setRolesError(err.message || 'Không thể tải danh sách vai trò và quyền hạn.')
    } finally {
      setLoadingRoles(false)
    }
  }, [])

  useEffect(() => {
    loadRolesAndPermissions()
  }, [loadRolesAndPermissions])

  // Parse sort
  const [sortField, sortOrder] = sortOption.split('-')

  // Fetch Users (Server-side)
  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const params = {
        page,
        per_page: 15,
        sort: sortField || 'id',
        order: sortOrder || 'desc',
      }
      if (search.trim()) params.search = search.trim()
      if (roleFilter) params.role = roleFilter
      if (statusFilter) params.status = statusFilter
      if (fromDate) params.from_date = fromDate
      if (toDate) params.to_date = toDate

      const { data } = await axiosClient.get(EP.ADMIN_USERS, { params })
      const userList = Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : [])
      const total = data.meta?.total ?? data.stats?.total ?? userList.length
      const totalPagesCount = data.meta?.last_page ?? 1

      setUsers(userList)
      setTotalPages(totalPagesCount)
      setTotalCount(total)

      if (data.stats) {
        setStats(data.stats)
      } else {
        setStats({
          total,
          active: userList.filter((u) => u.is_active).length,
          blocked: userList.filter((u) => !u.is_active).length,
        })
      }
    } catch {
      showToast('Không thể tải danh sách tài khoản người dùng.', 'error')
    } finally {
      setLoading(false)
    }
  }, [page, search, roleFilter, statusFilter, fromDate, toDate, sortField, sortOrder, showToast])

  useEffect(() => {
    fetchUsers()
  }, [fetchUsers])

  // Group permissions by module
  const groupedPermissions = useMemo(() => {
    const groups = {}
    PERMISSION_MODULES.forEach((mod) => {
      groups[mod.prefix] = {
        module: mod,
        permissions: [],
      }
    })

    const otherGroup = {
      module: { prefix: 'other', label: 'Quyền hạn khác', icon: 'extension' },
      permissions: [],
    }

    permissions.forEach((perm) => {
      const prefix = perm.slug.split('.')[0]
      if (groups[prefix]) {
        groups[prefix].permissions.push(perm)
      } else {
        otherGroup.permissions.push(perm)
      }
    })

    const result = Object.values(groups).filter((g) => g.permissions.length > 0)
    if (otherGroup.permissions.length > 0) {
      result.push(otherGroup)
    }
    return result
  }, [permissions])

  // Count changed permissions in Matrix Draft
  const changedRolesCount = useMemo(() => {
    let changed = 0
    roles.forEach((r) => {
      const origPerms = new Set((r.permissions || []).map((p) => (typeof p === 'string' ? p : p.slug)))
      const draftPerms = matrixDraft[r.slug] || new Set()
      if (origPerms.size !== draftPerms.size) {
        changed++
        return
      }
      for (const p of origPerms) {
        if (!draftPerms.has(p)) {
          changed++
          return
        }
      }
    })
    return changed
  }, [roles, matrixDraft])

  // Toggle permission in Matrix Draft
  const handleToggleMatrixPerm = (roleSlug, permSlug) => {
    if (!isEditingMatrix || !canManageRoles) return

    // Customer role is fixed
    if (roleSlug === 'customer') {
      showToast('Vai trò Khách hàng có phân quyền cố định, không thể chỉnh sửa.', 'warning')
      return
    }

    // Protect Super Admin critical core permissions
    if (roleSlug === 'super-admin' && ['roles.manage', 'users.manage', 'dashboard.view'].includes(permSlug)) {
      showToast('Không thể tắt các quyền quản trị tối cao của Super Admin.', 'warning')
      return
    }

    setMatrixDraft((prev) => {
      const currentSet = new Set(prev[roleSlug] || [])
      if (currentSet.has(permSlug)) {
        currentSet.delete(permSlug)
      } else {
        currentSet.add(permSlug)
      }
      return {
        ...prev,
        [roleSlug]: currentSet,
      }
    })
  }

  // Cancel Matrix Draft
  const handleCancelMatrixEdit = () => {
    const draft = {}
    roles.forEach((r) => {
      const perms = (r.permissions || []).map((p) => (typeof p === 'string' ? p : p.slug))
      draft[r.slug] = new Set(perms)
    })
    setMatrixDraft(draft)
    setIsEditingMatrix(false)
  }

  // Save Matrix Permissions
  const handleSaveMatrixConfirm = async () => {
    setSavingMatrix(true)
    try {
      const updatePromises = []

      roles.forEach((r) => {
        const origPerms = new Set((r.permissions || []).map((p) => (typeof p === 'string' ? p : p.slug)))
        const draftPerms = matrixDraft[r.slug] || new Set()

        let hasChange = origPerms.size !== draftPerms.size
        if (!hasChange) {
          for (const p of origPerms) {
            if (!draftPerms.has(p)) {
              hasChange = true
              break
            }
          }
        }

        if (hasChange) {
          updatePromises.push(
            axiosClient.put(EP.ADMIN_ROLE_PERMISSIONS_SYNC(r.slug), {
              permissions: Array.from(draftPerms),
            })
          )
        }
      })

      if (updatePromises.length === 0) {
        showToast('Không có thay đổi nào cần lưu.', 'info')
        setIsEditingMatrix(false)
        setShowSaveMatrixConfirm(false)
        return
      }

      await Promise.all(updatePromises)
      showToast(`Đã cập nhật ma trận phân quyền thành công (${updatePromises.length} vai trò)!`)
      setShowSaveMatrixConfirm(false)
      setIsEditingMatrix(false)
      await loadRolesAndPermissions()
    } catch (err) {
      showToast(err.message || 'Không thể lưu thay đổi ma trận phân quyền.', 'error')
    } finally {
      setSavingMatrix(false)
    }
  }

  // Load User Details
  const handleOpenDetail = async (user) => {
    setDetailUser(user)
    setDetailData(null)
    setLoadingDetail(true)
    try {
      const { data } = await axiosClient.get(EP.ADMIN_USER_DETAIL(user.id))
      setDetailData(data.data ?? data)
    } catch {
      showToast('Không thể tải thông tin chi tiết tài khoản.', 'error')
    } finally {
      setLoadingDetail(false)
    }
  }

  // Toggle Account Active Status
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
      if (detailUser?.id === statusTarget.id) {
        handleOpenDetail(statusTarget)
      }
    } catch (err) {
      showToast(err.message || 'Không thể thay đổi trạng thái tài khoản.', 'error')
    } finally {
      setTogglingStatus(false)
    }
  }

  // Assign Role
  const handleAssignRole = async (e) => {
    e.preventDefault()
    if (!roleModalUser || !selectedRole) return
    setUpdatingRole(true)

    try {
      await axiosClient.post(EP.ADMIN_USER_ROLES(roleModalUser.id), {
        role: selectedRole,
      })
      const cfg = ROLE_CONFIG[selectedRole]
      showToast(`Đã gán vai trò "${cfg?.label || selectedRole}" thành công!`)
      setRoleModalUser(null)
      fetchUsers()
      if (detailUser?.id === roleModalUser.id) {
        handleOpenDetail(roleModalUser)
      }
    } catch (err) {
      showToast(err.message || 'Không thể gán vai trò này.', 'error')
    } finally {
      setUpdatingRole(false)
    }
  }

  // Revoke Role
  const handleRevokeRole = async (user, roleSlug) => {
    try {
      await axiosClient.delete(EP.ADMIN_USER_ROLE_DELETE(user.id, roleSlug))
      const cfg = ROLE_CONFIG[roleSlug]
      showToast(`Đã gỡ vai trò "${cfg?.label || roleSlug}" thành công!`)
      fetchUsers()
      if (roleModalUser?.id === user.id) {
        setRoleModalUser(null)
      }
      if (detailUser?.id === user.id) {
        handleOpenDetail(user)
      }
    } catch (err) {
      showToast(err.message || 'Không thể gỡ vai trò này.', 'error')
    }
  }

  // Create User
  const handleCreateUser = async (e) => {
    e.preventDefault()
    setCreating(true)
    setCreateErrors({})

    try {
      await axiosClient.post(EP.ADMIN_USER_CREATE, createForm)
      showToast('Tài khoản mới đã được tạo thành công!')
      setShowCreateModal(false)
      setCreateForm({ name: '', email: '', phone: '', password: '', role: '' })
      fetchUsers()
    } catch (err) {
      if (err.validationErrors) {
        setCreateErrors(err.validationErrors)
      } else {
        showToast(err.message || 'Không thể tạo tài khoản mới.', 'error')
      }
    } finally {
      setCreating(false)
    }
  }

  // Open Edit Modal
  const handleOpenEdit = (u) => {
    setEditModalUser(u)
    setEditForm({
      name: u.name || '',
      email: u.email || '',
      phone: u.phone || '',
    })
    setEditErrors({})
  }

  // Update User
  const handleUpdateUser = async (e) => {
    e.preventDefault()
    if (!editModalUser) return
    setUpdatingUser(true)
    setEditErrors({})

    try {
      await axiosClient.patch(EP.ADMIN_USER_UPDATE(editModalUser.id), editForm)
      showToast('Đã cập nhật thông tin tài khoản thành công!')
      setEditModalUser(null)
      fetchUsers()
      if (detailUser?.id === editModalUser.id) {
        handleOpenDetail(editModalUser)
      }
    } catch (err) {
      if (err.validationErrors) {
        setEditErrors(err.validationErrors)
      } else {
        showToast(err.message || 'Không thể cập nhật tài khoản.', 'error')
      }
    } finally {
      setUpdatingUser(false)
    }
  }

  // Reset Password
  const handleResetPassword = async (e) => {
    e.preventDefault()
    if (!passwordTargetUser || !newPassword) return
    setResettingPassword(true)

    try {
      await axiosClient.patch(EP.ADMIN_USER_PASSWORD_RESET(passwordTargetUser.id), {
        password: newPassword,
      })
      showToast(`Đã đổi mật khẩu cho "${passwordTargetUser.name}" thành công!`)
      setPasswordTargetUser(null)
      setNewPassword('')
    } catch (err) {
      showToast(err.message || 'Không thể đặt lại mật khẩu.', 'error')
    } finally {
      setResettingPassword(false)
    }
  }

  const generateRandomPassword = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*'
    let pwd = 'MbX'
    for (let i = 0; i < 9; i++) {
      pwd += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    setNewPassword(pwd)
  }

  const adminRoles = roles.filter((r) => r.slug !== 'customer')

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-2xl">manage_accounts</span>
            Quản lý Tài khoản & Phân quyền (RBAC)
          </h1>
          <p className="text-sm text-outline mt-1">
            Kiểm soát người dùng, cấu hình ma trận phân quyền chi tiết, bảo vệ chốt chặn bảo mật hệ thống
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <Button
            variant="primary"
            onClick={() => setShowCreateModal(true)}
            className="cursor-pointer active:scale-95 transition-transform"
          >
            <span className="material-symbols-outlined text-base">person_add</span>
            Tạo tài khoản
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-outline-variant/30">
        <button
          type="button"
          onClick={() => setActiveTab('users')}
          className={[
            'pb-3 px-4 text-sm font-semibold transition-all border-b-2 cursor-pointer flex items-center gap-2',
            activeTab === 'users'
              ? 'border-primary text-primary'
              : 'border-transparent text-outline hover:text-on-surface hover:border-outline-variant/50',
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-lg">group</span>
          Danh sách người dùng
          <span className="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-mono font-bold">
            {totalCount}
          </span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('matrix')}
          className={[
            'pb-3 px-4 text-sm font-semibold transition-all border-b-2 cursor-pointer flex items-center gap-2',
            activeTab === 'matrix'
              ? 'border-primary text-primary'
              : 'border-transparent text-outline hover:text-on-surface hover:border-outline-variant/50',
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-lg">shield</span>
          Ma trận phân quyền (RBAC Matrix)
          <span className="text-xs px-2 py-0.5 rounded-full bg-surface-container-high font-mono">
            {roles.length} vai trò / {permissions.length} quyền
          </span>
        </button>
      </div>

      {activeTab === 'matrix' ? (
        /* RBAC Matrix Tab */
        <div className="bg-surface-container rounded-2xl border border-outline-variant/20 p-6 shadow-sm space-y-6">
          {/* Header & Controls */}
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
              <div className="flex items-center gap-2.5">
                <h2 className="text-lg font-bold text-on-surface">Ma trận Phân quyền Vai trò Hệ thống</h2>
                {isEditingMatrix && (
                  <span className="bg-amber-500/15 text-amber-600 dark:text-amber-400 text-xs font-bold px-2 py-0.5 rounded-full border border-amber-500/30 animate-pulse">
                    Đang ở chế độ chỉnh sửa
                  </span>
                )}
              </div>
              <p className="text-xs text-outline mt-1">
                Quy định quyền truy cập chuẩn hóa cho từng vai trò Canonical. Mọi thao tác đều được kiểm tra thực tế tại Backend.
              </p>
            </div>

            {/* Actions & Legend */}
            <div className="flex flex-wrap items-center gap-3">
              {/* Legend */}
              <div className="flex items-center gap-3 text-xs bg-surface-container-low px-3 py-2 rounded-xl border border-outline-variant/20">
                <span className="flex items-center gap-1.5 font-medium text-on-surface">
                  <span className="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-base">check_circle</span>
                  Được phép
                </span>
                <span className="flex items-center gap-1.5 font-medium text-outline">
                  <span className="material-symbols-outlined text-rose-500/50 dark:text-rose-400/50 text-base">cancel</span>
                  Không có quyền
                </span>
                {isEditingMatrix && (
                  <span className="flex items-center gap-1 text-primary font-bold ml-1 pl-2 border-l border-outline-variant/20">
                    <span className="material-symbols-outlined text-sm">touch_app</span> Bấm ô để đổi
                  </span>
                )}
              </div>

              {/* Edit Mode Buttons */}
              {canManageRoles && (
                <div className="flex items-center gap-2">
                  {!isEditingMatrix ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setIsEditingMatrix(true)}
                      className="cursor-pointer"
                    >
                      <span className="material-symbols-outlined text-sm">edit</span>
                      Chỉnh sửa ma trận
                    </Button>
                  ) : (
                    <>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleCancelMatrixEdit}
                        disabled={savingMatrix}
                        className="cursor-pointer"
                      >
                        Hủy bỏ
                      </Button>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => setShowSaveMatrixConfirm(true)}
                        loading={savingMatrix}
                        className="cursor-pointer"
                      >
                        <span className="material-symbols-outlined text-sm">save</span>
                        Lưu thay đổi {changedRolesCount > 0 && `(${changedRolesCount} vai trò)`}
                      </Button>
                    </>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Error Banner */}
          {rolesError && (
            <div className="p-4 rounded-xl bg-error/10 border border-error/30 text-error flex items-center justify-between gap-3 text-sm">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-xl">error</span>
                <span>{rolesError}</span>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={loadRolesAndPermissions}
                className="cursor-pointer text-xs"
              >
                Thử tải lại
              </Button>
            </div>
          )}

          {/* Matrix Table */}
          {loadingRoles ? (
            <div className="flex flex-col items-center justify-center py-20 text-outline gap-3">
              <Spinner size="lg" />
              <p className="text-sm">Đang tải cấu hình vai trò và ma trận phân quyền...</p>
            </div>
          ) : roles.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 text-outline gap-2">
              <span className="material-symbols-outlined text-4xl">shield_question</span>
              <p className="text-sm">Chưa có vai trò nào được đăng ký trong hệ thống.</p>
            </div>
          ) : (
            <div className="overflow-x-auto rounded-xl border border-outline-variant/20 shadow-inner">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="bg-surface-container-high border-b border-outline-variant/30 text-on-surface">
                    <th className="py-4 px-4 font-bold text-sm min-w-[280px]">
                      Phân hệ & Quyền hạn (Permissions)
                    </th>
                    {roles.map((r) => {
                      const cfg = ROLE_CONFIG[r.slug] || {
                        label: r.name,
                        sublabel: r.slug,
                        icon: 'shield',
                        badgeClass: 'text-on-surface bg-surface-container-low border border-outline-variant/30',
                        description: r.description,
                      }
                      return (
                        <th
                          key={r.slug}
                          className="py-4 px-3 text-center min-w-[130px] border-l border-outline-variant/15"
                        >
                          <div className="inline-flex flex-col items-center gap-1 group relative">
                            <span className="material-symbols-outlined text-xl text-primary">
                              {cfg.icon}
                            </span>
                            <span className="font-bold text-xs text-on-surface whitespace-nowrap">
                              {cfg.label}
                            </span>
                            <span className="text-[10px] font-mono text-outline">
                              ({r.slug})
                            </span>
                            {/* Role Tooltip */}
                            <div className="text-[10px] text-outline line-clamp-1 max-w-[120px] font-normal" title={cfg.description || r.description}>
                              {cfg.description || r.description || cfg.sublabel}
                            </div>
                          </div>
                        </th>
                      )
                    })}
                  </tr>
                </thead>

                <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                  {groupedPermissions.map((group) => (
                    <tr key={group.module.prefix} className="contents">
                      {/* Module Header Row */}
                      <tr className="bg-surface-container-high/40 border-t-2 border-b border-outline-variant/25">
                        <td
                          colSpan={roles.length + 1}
                          className="py-2.5 px-4 font-bold text-xs text-primary uppercase tracking-wider flex items-center gap-2"
                        >
                          <span className="material-symbols-outlined text-base">
                            {group.module.icon}
                          </span>
                          {group.module.label}
                          <span className="text-[10px] font-mono font-normal text-outline lowercase ml-1">
                            ({group.permissions.length} quyền)
                          </span>
                        </td>
                      </tr>

                      {/* Permissions Rows in this Module */}
                      {group.permissions.map((p) => (
                        <tr
                          key={p.slug}
                          className="hover:bg-surface-container-high/25 transition-colors"
                        >
                          <td className="py-2.5 px-4">
                            <div className="font-semibold text-on-surface text-xs">{p.name || p.slug}</div>
                            <div className="text-[11px] text-outline font-mono mt-0.5 flex items-center gap-1.5">
                              <span>{p.slug}</span>
                              {p.description && (
                                <span className="text-[10px] text-outline/80 italic font-sans">
                                  — {p.description}
                                </span>
                              )}
                            </div>
                          </td>

                          {roles.map((r) => {
                            // Check permission in draft or original
                            const draftSet = matrixDraft[r.slug]
                            const origSet = new Set((r.permissions || []).map((x) => (typeof x === 'string' ? x : x.slug)))

                            const hasPerm = isEditingMatrix
                              ? (draftSet ? draftSet.has(p.slug) : false)
                              : origSet.has(p.slug)

                            const isModified = isEditingMatrix && (
                              (draftSet?.has(p.slug) && !origSet.has(p.slug)) ||
                              (!draftSet?.has(p.slug) && origSet.has(p.slug))
                            )

                            const isLocked = isEditingMatrix && (
                              r.slug === 'customer' ||
                              (r.slug === 'super-admin' && ['roles.manage', 'users.manage', 'dashboard.view'].includes(p.slug))
                            )

                            return (
                              <td
                                key={r.slug}
                                className={[
                                  'py-2 px-3 text-center border-l border-outline-variant/10 transition-colors',
                                  isEditingMatrix && !isLocked ? 'cursor-pointer hover:bg-surface-container-high/60 active:scale-95' : '',
                                  isModified ? 'bg-amber-500/10 dark:bg-amber-500/15' : '',
                                ].join(' ')}
                                onClick={() => handleToggleMatrixPerm(r.slug, p.slug)}
                                title={
                                  isEditingMatrix
                                    ? isLocked
                                      ? 'Quyền hạn bảo vệ cố định'
                                      : `Nhấn để ${hasPerm ? 'tắt' : 'bật'} quyền "${p.name}" cho ${r.name}`
                                    : `${r.name}: ${hasPerm ? 'Được phép' : 'Không có quyền'}`
                                }
                              >
                                <div className="inline-flex items-center justify-center">
                                  {hasPerm ? (
                                    <span className="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-lg transition-transform font-bold">
                                      check_circle
                                    </span>
                                  ) : (
                                    <span className="material-symbols-outlined text-rose-500/50 dark:text-rose-400/50 text-lg transition-transform">
                                      cancel
                                    </span>
                                  )}
                                  {isModified && (
                                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500 ml-1 shrink-0" title="Đã chỉnh sửa" />
                                  )}
                                </div>
                              </td>
                            )
                          })}
                        </tr>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ) : (
        /* Users List Tab */
        <div className="space-y-6">
          {/* Top Quick Stats */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 shadow-sm flex items-center gap-3.5">
              <div className="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <span className="material-symbols-outlined text-2xl">group</span>
              </div>
              <div>
                <p className="text-xs text-outline font-medium">Tổng người dùng</p>
                <p className="text-xl font-bold text-on-surface mt-0.5">{stats.total || totalCount}</p>
              </div>
            </div>

            <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 shadow-sm flex items-center gap-3.5">
              <div className="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <span className="material-symbols-outlined text-2xl">verified_user</span>
              </div>
              <div>
                <p className="text-xs text-outline font-medium">Đang hoạt động</p>
                <p className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{stats.active || 0}</p>
              </div>
            </div>

            <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 shadow-sm flex items-center gap-3.5">
              <div className="w-11 h-11 rounded-xl bg-error/10 text-error flex items-center justify-center shrink-0">
                <span className="material-symbols-outlined text-2xl">lock</span>
              </div>
              <div>
                <p className="text-xs text-outline font-medium">Đã bị khóa</p>
                <p className="text-xl font-bold text-error mt-0.5">{stats.blocked || 0}</p>
              </div>
            </div>

            <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 shadow-sm flex items-center gap-3.5">
              <div className="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                <span className="material-symbols-outlined text-2xl">shield</span>
              </div>
              <div>
                <p className="text-xs text-outline font-medium">Vai trò quản trị</p>
                <p className="text-xl font-bold text-purple-600 dark:text-purple-400 mt-0.5">{adminRoles.length}</p>
              </div>
            </div>
          </div>

          {/* Search, Filter & Sort Bar */}
          <div className="bg-surface-container rounded-2xl p-4 border border-outline-variant/20 space-y-3 shadow-sm">
            <div className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
              {/* Search */}
              <div className="relative flex-1 max-w-md">
                <span className="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-lg">
                  search
                </span>
                <input
                  type="text"
                  value={searchInput}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  placeholder="Tìm theo tên, email, số điện thoại..."
                  className="w-full pl-10 pr-4 py-2.5 bg-surface-container-low text-on-surface text-sm rounded-xl border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary cursor-text transition-all"
                />
                {searchInput && (
                  <button
                    type="button"
                    onClick={() => { setSearchInput(''); setSearch(''); setPage(1) }}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface cursor-pointer"
                    aria-label="Xóa tìm kiếm"
                  >
                    <span className="material-symbols-outlined text-sm">close</span>
                  </button>
                )}
              </div>

              {/* Filters */}
              <div className="flex flex-wrap items-center gap-2.5">
                <select
                  value={roleFilter}
                  onChange={(e) => { setRoleFilter(e.target.value); setPage(1) }}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
                >
                  <option value="">Tất cả vai trò</option>
                  {roles.map((r) => (
                    <option key={r.slug} value={r.slug}>
                      {ROLE_CONFIG[r.slug]?.label || r.name}
                    </option>
                  ))}
                </select>

                <select
                  value={statusFilter}
                  onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
                >
                  <option value="">Tất cả trạng thái</option>
                  <option value="active">Đang hoạt động</option>
                  <option value="blocked">Đã bị khóa</option>
                </select>

                <select
                  value={sortOption}
                  onChange={(e) => { setSortOption(e.target.value); setPage(1) }}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-xs border border-outline-variant/30 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
                >
                  <option value="id-desc">Mới tạo trước</option>
                  <option value="id-asc">Cũ nhất trước</option>
                  <option value="name-asc">Tên (A → Z)</option>
                  <option value="name-desc">Tên (Z → A)</option>
                  <option value="created_at-desc">Ngày đăng ký mới nhất</option>
                </select>
              </div>
            </div>

            {/* Date Range Sub-filter */}
            <div className="flex flex-wrap items-center gap-3 pt-2 border-t border-outline-variant/10 text-xs text-outline">
              <span className="font-medium text-on-surface-variant flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">calendar_month</span> Lọc theo ngày:
              </span>
              <div className="flex items-center gap-1.5">
                <span>Từ:</span>
                <input
                  type="date"
                  value={fromDate}
                  onChange={(e) => { setFromDate(e.target.value); setPage(1) }}
                  className="bg-surface-container-low text-on-surface rounded-lg px-2 py-1 text-xs border border-outline-variant/30 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                />
              </div>
              <div className="flex items-center gap-1.5">
                <span>Đến:</span>
                <input
                  type="date"
                  value={toDate}
                  onChange={(e) => { setToDate(e.target.value); setPage(1) }}
                  className="bg-surface-container-low text-on-surface rounded-lg px-2 py-1 text-xs border border-outline-variant/30 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                />
              </div>
              {(fromDate || toDate || roleFilter || statusFilter || search) && (
                <button
                  type="button"
                  onClick={() => {
                    setSearchInput('')
                    setSearch('')
                    setRoleFilter('')
                    setStatusFilter('')
                    setFromDate('')
                    setToDate('')
                    setSortOption('id-desc')
                    setPage(1)
                  }}
                  className="text-primary hover:underline cursor-pointer ml-auto flex items-center gap-0.5 font-medium"
                >
                  <span className="material-symbols-outlined text-xs">restart_alt</span> Đặt lại bộ lọc
                </button>
              )}
            </div>
          </div>

          {/* Table Card */}
          <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
            {loading ? (
              <div className="flex items-center justify-center py-20">
                <Spinner size="lg" />
              </div>
            ) : users.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-20 text-outline gap-3">
                <div className="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center">
                  <span className="material-symbols-outlined text-3xl">person_off</span>
                </div>
                <p className="text-base font-medium text-on-surface">Không tìm thấy tài khoản người dùng</p>
                <p className="text-xs text-outline max-w-sm text-center">
                  Thử thay đổi từ khóa tìm kiếm hoặc bỏ bớt các bộ lọc để hiển thị kết quả.
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-outline-variant/20 bg-surface-container-high/50 text-xs font-semibold text-outline uppercase tracking-wider">
                      <th className="py-3.5 px-4">Tài khoản</th>
                      <th className="py-3.5 px-4">Thông tin liên hệ</th>
                      <th className="py-3.5 px-4">Vai trò (Roles)</th>
                      <th className="py-3.5 px-4 text-center">Trạng thái</th>
                      <th className="py-3.5 px-4">Ngày tham gia</th>
                      <th className="py-3.5 px-4 text-right">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                    {users.map((u) => {
                      const isActive = Boolean(u.is_active)
                      const isCurrentAuth = Number(u.id) === Number(currentAuthUser?.id)
                      const userRoles = u.roles || []

                      return (
                        <tr
                          key={u.id}
                          className="hover:bg-surface-container-high/30 transition-colors group"
                        >
                          {/* User info */}
                          <td className="py-3.5 px-4">
                            <div className="flex items-center gap-3">
                              <div className="w-10 h-10 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center shrink-0 text-sm shadow-inner">
                                {u.name?.charAt(0)?.toUpperCase() || 'U'}
                              </div>
                              <div>
                                <div className="flex items-center gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleOpenDetail(u)}
                                    className="font-semibold text-on-surface hover:text-primary transition-colors text-left cursor-pointer"
                                  >
                                    {u.name}
                                  </button>
                                  {isCurrentAuth && (
                                    <span className="bg-primary/15 text-primary text-[10px] font-bold px-1.5 py-0.5 rounded">
                                      Bạn
                                    </span>
                                  )}
                                </div>
                                <span className="text-xs text-outline font-mono">ID: #{u.id}</span>
                              </div>
                            </div>
                          </td>

                          {/* Contact */}
                          <td className="py-3.5 px-4 text-xs">
                            <p className="text-on-surface font-medium">{u.email}</p>
                            <p className="text-outline mt-0.5">{u.phone || 'Chưa cập nhật SĐT'}</p>
                          </td>

                          {/* Roles */}
                          <td className="py-3.5 px-4">
                            <div className="flex flex-wrap gap-1.5 items-center">
                              {userRoles.length === 0 ? (
                                <Badge variant="neutral" className="text-[11px]">
                                  Khách hàng
                                </Badge>
                              ) : (
                                userRoles.map((r) => {
                                  const cfg = ROLE_CONFIG[r.slug] || {
                                    label: r.name || r.slug,
                                    variant: 'neutral',
                                    icon: 'badge',
                                  }
                                  return (
                                    <Badge
                                      key={r.slug}
                                      variant={cfg.variant}
                                      className="text-[11px] inline-flex items-center gap-1"
                                    >
                                      {cfg.icon && (
                                        <span className="material-symbols-outlined text-xs">
                                          {cfg.icon}
                                        </span>
                                      )}
                                      {cfg.label}
                                    </Badge>
                                  )
                                })
                              )}
                            </div>
                          </td>

                          {/* Status */}
                          <td className="py-3.5 px-4 text-center">
                            <Badge variant={isActive ? 'success' : 'error'}>
                              {isActive ? 'Hoạt động' : 'Bị khóa'}
                            </Badge>
                          </td>

                          {/* Joined date */}
                          <td className="py-3.5 px-4 text-xs text-outline font-mono">
                            {u.created_at ? formatDate(u.created_at) : '—'}
                          </td>

                          {/* Actions */}
                          <td className="py-3.5 px-4 text-right">
                            <div className="flex items-center justify-end gap-1">
                              {/* Detail button */}
                              <button
                                type="button"
                                onClick={() => handleOpenDetail(u)}
                                title="Xem chi tiết tài khoản"
                                aria-label={`Xem chi tiết tài khoản ${u.name}`}
                                className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all cursor-pointer active:scale-90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                              >
                                <span className="material-symbols-outlined text-lg">visibility</span>
                              </button>

                              {/* Edit info button */}
                              <button
                                type="button"
                                onClick={() => handleOpenEdit(u)}
                                title="Chỉnh sửa thông tin"
                                aria-label={`Chỉnh sửa thông tin ${u.name}`}
                                className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all cursor-pointer active:scale-90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                              >
                                <span className="material-symbols-outlined text-lg">edit</span>
                              </button>

                              {/* Role assignment button */}
                              <button
                                type="button"
                                onClick={() => setRoleModalUser(u)}
                                title="Phân quyền vai trò"
                                aria-label={`Phân quyền vai trò cho ${u.name}`}
                                className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-primary transition-all cursor-pointer active:scale-90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                              >
                                <span className="material-symbols-outlined text-lg">admin_panel_settings</span>
                              </button>

                              {/* Reset password button */}
                              <button
                                type="button"
                                onClick={() => { setPasswordTargetUser(u); setNewPassword('') }}
                                title="Đặt lại mật khẩu"
                                aria-label={`Đặt lại mật khẩu cho ${u.name}`}
                                className="p-1.5 rounded-lg hover:bg-surface-container-high text-on-surface-variant hover:text-amber-500 transition-all cursor-pointer active:scale-90 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:outline-none"
                              >
                                <span className="material-symbols-outlined text-lg">key</span>
                              </button>

                              {/* Lock / Unlock button */}
                              <button
                                type="button"
                                onClick={() => setStatusTarget(u)}
                                disabled={isCurrentAuth}
                                title={
                                  isCurrentAuth
                                    ? 'Không thể khóa tài khoản của chính bạn'
                                    : isActive
                                      ? 'Khóa tài khoản'
                                      : 'Mở khóa tài khoản'
                                }
                                aria-label={
                                  isCurrentAuth
                                    ? 'Không thể khóa tài khoản của chính bạn'
                                    : isActive
                                      ? `Khóa tài khoản ${u.name}`
                                      : `Mở khóa tài khoản ${u.name}`
                                }
                                className={[
                                  'p-1.5 rounded-lg transition-all focus-visible:outline-none',
                                  isCurrentAuth
                                    ? 'opacity-30 cursor-not-allowed text-outline'
                                    : isActive
                                      ? 'cursor-pointer hover:bg-error/10 text-on-surface-variant hover:text-error active:scale-90 focus-visible:ring-2 focus-visible:ring-error'
                                      : 'cursor-pointer hover:bg-emerald-500/10 text-on-surface-variant hover:text-emerald-500 active:scale-90 focus-visible:ring-2 focus-visible:ring-emerald-500',
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
            <div className="flex justify-center mt-6">
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </div>
          )}
        </div>
      )}

      {/* ── Save Matrix Confirmation Modal ────────────────────────────────────── */}
      <ConfirmDialog
        open={showSaveMatrixConfirm}
        onClose={() => setShowSaveMatrixConfirm(false)}
        onConfirm={handleSaveMatrixConfirm}
        title="Xác nhận lưu thay đổi Ma trận Phân quyền"
        message={`Bạn có chắc chắn muốn lưu lại các phân quyền vừa chỉnh sửa cho ${changedRolesCount} vai trò? Mọi tài khoản đang hoạt động thuộc các vai trò này sẽ chịu ảnh hưởng của các quyền mới ngay lập tức.`}
        confirmText="Xác nhận lưu ma trận"
        variant="primary"
        loading={savingMatrix}
      />

      {/* ── Detail Modal ──────────────────────────────────────────────────────── */}
      {detailUser && (
        <Modal
          open={Boolean(detailUser)}
          onClose={() => { setDetailUser(null); setDetailData(null) }}
          title={`Hồ sơ chi tiết: ${detailUser.name}`}
          size="lg"
        >
          {loadingDetail ? (
            <div className="flex justify-center py-12"><Spinner size="lg" /></div>
          ) : (
            <div className="space-y-6">
              {/* Profile Card */}
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-2xl bg-surface-container-high/40 border border-outline-variant/20">
                <div className="flex items-center gap-4">
                  <div className="w-14 h-14 rounded-2xl bg-primary/10 text-primary text-xl font-bold flex items-center justify-center shrink-0">
                    {detailUser.name?.charAt(0)?.toUpperCase()}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h3 className="text-base font-bold text-on-surface">{detailUser.name}</h3>
                      <Badge variant={detailUser.is_active ? 'success' : 'error'}>
                        {detailUser.is_active ? 'Đang hoạt động' : 'Bị khóa'}
                      </Badge>
                    </div>
                    <p className="text-xs text-outline mt-0.5">{detailUser.email}</p>
                    <p className="text-xs text-outline">{detailUser.phone || 'Chưa có SĐT'}</p>
                  </div>
                </div>

                <div className="text-xs text-outline sm:text-right">
                  <p>ID tài khoản: <strong className="text-on-surface font-mono">#{detailUser.id}</strong></p>
                  <p className="mt-1">
                    Ngày đăng ký: <strong className="text-on-surface font-mono">{detailUser.created_at ? formatDate(detailUser.created_at) : '—'}</strong>
                  </p>
                  {detailUser.email_verified_at ? (
                    <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                      <span className="material-symbols-outlined text-xs">verified</span> Đã xác thực email
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 text-amber-500 mt-1 font-medium">
                      <span className="material-symbols-outlined text-xs">info</span> Chưa xác thực email
                    </span>
                  )}
                </div>
              </div>

              {/* Roles & Permissions */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-sm font-bold text-on-surface flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-primary text-base">security</span>
                    Vai trò & Quyền hạn phân bổ
                  </h4>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => { setRoleModalUser(detailUser); setDetailUser(null) }}
                    className="cursor-pointer text-xs py-1"
                  >
                    <span className="material-symbols-outlined text-sm">edit</span> Thay đổi vai trò
                  </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                  {(detailData?.user?.roles ?? detailUser.roles ?? []).length === 0 ? (
                    <Badge variant="neutral">Khách hàng thông thường</Badge>
                  ) : (
                    (detailData?.user?.roles ?? detailUser.roles ?? []).map((r) => {
                      const cfg = ROLE_CONFIG[r.slug] || { label: r.name, variant: 'neutral' }
                      return (
                        <Badge key={r.slug} variant={cfg.variant} className="text-xs py-1 px-2.5 inline-flex items-center gap-1">
                          <span className="material-symbols-outlined text-xs">{cfg.icon || 'shield'}</span>
                          {cfg.label}
                        </Badge>
                      )
                    })
                  )}
                </div>

                {detailData?.permissions && detailData.permissions.length > 0 && (
                  <div className="p-3 rounded-xl bg-surface-container-low border border-outline-variant/15 space-y-1.5">
                    <p className="text-xs font-semibold text-outline">Quyền hạn thực tế có hiệu lực ({detailData.permissions.length}):</p>
                    <div className="flex flex-wrap gap-1 max-h-32 overflow-y-auto pr-1">
                      {detailData.permissions.map((p) => (
                        <span
                          key={p.slug}
                          title={p.description}
                          className="text-[10px] font-mono bg-surface-container-high px-1.5 py-0.5 rounded text-on-surface-variant border border-outline-variant/20"
                        >
                          {p.slug}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Recent Orders */}
              {detailData?.recent_orders && detailData.recent_orders.length > 0 && (
                <div className="space-y-2.5">
                  <h4 className="text-sm font-bold text-on-surface flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-primary text-base">shopping_cart</span>
                    Đơn hàng gần đây
                  </h4>
                  <div className="overflow-x-auto rounded-xl border border-outline-variant/20">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-surface-container-high/50 border-b border-outline-variant/20 text-outline">
                        <tr>
                          <th className="py-2 px-3">Mã đơn</th>
                          <th className="py-2 px-3">Tổng tiền</th>
                          <th className="py-2 px-3">Trạng thái</th>
                          <th className="py-2 px-3 text-right">Ngày đặt</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-outline-variant/10 text-on-surface-variant">
                        {detailData.recent_orders.map((o) => {
                          const st = ORDER_STATUS_LABELS[o.status] || { label: o.status, variant: 'neutral' }
                          return (
                            <tr key={o.id}>
                              <td className="py-2 px-3 font-mono font-semibold text-primary">{o.order_code}</td>
                              <td className="py-2 px-3 font-semibold text-on-surface">{formatCurrencyVND(o.total_amount)}</td>
                              <td className="py-2 px-3"><Badge variant={st.variant}>{st.label}</Badge></td>
                              <td className="py-2 px-3 text-right text-outline">{o.created_at ? formatDate(o.created_at) : '—'}</td>
                            </tr>
                          )
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              <div className="flex justify-end pt-3 border-t border-outline-variant/20">
                <Button variant="ghost" onClick={() => { setDetailUser(null); setDetailData(null) }}>
                  Đóng
                </Button>
              </div>
            </div>
          )}
        </Modal>
      )}

      {/* ── Create User Modal ─────────────────────────────────────────────────── */}
      {showCreateModal && (
        <Modal
          open={showCreateModal}
          onClose={() => { setShowCreateModal(false); setCreateErrors({}) }}
          title="Tạo tài khoản quản trị / người dùng mới"
          size="md"
        >
          <form onSubmit={handleCreateUser} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">
                  Họ và tên <span className="text-error">*</span>
                </label>
                <input
                  type="text"
                  value={createForm.name}
                  onChange={(e) => setCreateForm((f) => ({ ...f, name: e.target.value }))}
                  placeholder="Nguyễn Văn A"
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                  required
                />
                {createErrors.name && <p className="text-xs text-error mt-0.5">{createErrors.name[0]}</p>}
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">
                  Email đăng nhập <span className="text-error">*</span>
                </label>
                <input
                  type="email"
                  value={createForm.email}
                  onChange={(e) => setCreateForm((f) => ({ ...f, email: e.target.value }))}
                  placeholder="admin@mobiex.vn"
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                  required
                />
                {createErrors.email && <p className="text-xs text-error mt-0.5">{createErrors.email[0]}</p>}
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">Số điện thoại</label>
                <input
                  type="text"
                  value={createForm.phone}
                  onChange={(e) => setCreateForm((f) => ({ ...f, phone: e.target.value }))}
                  placeholder="0912345678"
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                />
                {createErrors.phone && <p className="text-xs text-error mt-0.5">{createErrors.phone[0]}</p>}
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">
                  Mật khẩu khởi tạo <span className="text-error">*</span>
                </label>
                <input
                  type="password"
                  value={createForm.password}
                  onChange={(e) => setCreateForm((f) => ({ ...f, password: e.target.value }))}
                  placeholder="Ít nhất 8 ký tự (chữ + số)"
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                  required
                />
                {createErrors.password && <p className="text-xs text-error mt-0.5">{createErrors.password[0]}</p>}
              </div>
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-xs font-semibold text-on-surface-variant">Phân quyền vai trò ban đầu:</label>
              <select
                value={createForm.role}
                onChange={(e) => setCreateForm((f) => ({ ...f, role: e.target.value }))}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
              >
                <option value="">Khách hàng thông thường (Customer)</option>
                {adminRoles.map((r) => (
                  <option key={r.slug} value={r.slug}>
                    {ROLE_CONFIG[r.slug]?.label || r.name} ({r.slug})
                  </option>
                ))}
              </select>
              {createErrors.role && <p className="text-xs text-error mt-0.5">{createErrors.role[0]}</p>}
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/15">
              <Button
                type="button"
                variant="ghost"
                onClick={() => { setShowCreateModal(false); setCreateErrors({}) }}
                disabled={creating}
              >
                Hủy
              </Button>
              <Button type="submit" variant="primary" loading={creating}>
                Tạo tài khoản
              </Button>
            </div>
          </form>
        </Modal>
      )}

      {/* ── Edit User Modal ───────────────────────────────────────────────────── */}
      {editModalUser && (
        <Modal
          open={Boolean(editModalUser)}
          onClose={() => { setEditModalUser(null); setEditErrors({}) }}
          title={`Chỉnh sửa tài khoản: ${editModalUser.name}`}
          size="md"
        >
          <form onSubmit={handleUpdateUser} className="space-y-4">
            <div className="space-y-3">
              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">Họ và tên</label>
                <input
                  type="text"
                  value={editForm.name}
                  onChange={(e) => setEditForm((f) => ({ ...f, name: e.target.value }))}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                  required
                />
                {editErrors.name && <p className="text-xs text-error">{editErrors.name[0]}</p>}
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">Email</label>
                <input
                  type="email"
                  value={editForm.email}
                  onChange={(e) => setEditForm((f) => ({ ...f, email: e.target.value }))}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                  required
                />
                {editErrors.email && <p className="text-xs text-error">{editErrors.email[0]}</p>}
              </div>

              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">Số điện thoại</label>
                <input
                  type="text"
                  value={editForm.phone}
                  onChange={(e) => setEditForm((f) => ({ ...f, phone: e.target.value }))}
                  className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-text"
                />
                {editErrors.phone && <p className="text-xs text-error">{editErrors.phone[0]}</p>}
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/15">
              <Button
                type="button"
                variant="ghost"
                onClick={() => { setEditModalUser(null); setEditErrors({}) }}
                disabled={updatingUser}
              >
                Hủy
              </Button>
              <Button type="submit" variant="primary" loading={updatingUser}>
                Lưu thay đổi
              </Button>
            </div>
          </form>
        </Modal>
      )}

      {/* ── Role Assignment Modal ─────────────────────────────────────────────── */}
      {roleModalUser && (
        <Modal
          open={Boolean(roleModalUser)}
          onClose={() => setRoleModalUser(null)}
          title={`Phân quyền vai trò: ${roleModalUser.name}`}
          size="md"
        >
          <div className="space-y-4">
            <div className="p-3.5 rounded-xl bg-surface-container-high/40 text-xs text-on-surface-variant flex flex-col gap-1.5 border border-outline-variant/20">
              <p><strong className="text-on-surface">Email:</strong> {roleModalUser.email}</p>
              <div className="flex items-center gap-2 mt-1">
                <strong className="text-on-surface">Vai trò hiện có:</strong>
                <div className="flex flex-wrap gap-1.5">
                  {(roleModalUser.roles || []).length === 0 ? (
                    <span className="text-outline">Mặc định khách hàng</span>
                  ) : (
                    roleModalUser.roles.map((r) => {
                      const cfg = ROLE_CONFIG[r.slug] || { label: r.name || r.slug }
                      return (
                        <span
                          key={r.slug}
                          className="inline-flex items-center gap-1 bg-primary/10 text-primary text-xs px-2.5 py-0.5 rounded-lg border border-primary/20"
                        >
                          {cfg.label}
                          {r.slug !== 'customer' && (
                            <button
                              type="button"
                              onClick={() => handleRevokeRole(roleModalUser, r.slug)}
                              title={`Gỡ vai trò ${cfg.label}`}
                              aria-label={`Gỡ vai trò ${cfg.label}`}
                              className="hover:text-error ml-1 font-bold cursor-pointer active:scale-90"
                            >
                              ×
                            </button>
                          )}
                        </span>
                      )
                    })
                  )}
                </div>
              </div>
            </div>

            <form onSubmit={handleAssignRole} className="space-y-3.5">
              <div className="flex flex-col gap-1">
                <label className="text-xs font-semibold text-on-surface-variant">Chọn vai trò muốn gán thêm:</label>
                <select
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value)}
                  className="w-full bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
                >
                  {adminRoles.map((r) => (
                    <option key={r.slug} value={r.slug}>
                      {ROLE_CONFIG[r.slug]?.label || r.name} ({r.slug})
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/15">
                <Button type="button" variant="ghost" onClick={() => setRoleModalUser(null)} disabled={updatingRole}>
                  Đóng
                </Button>
                <Button type="submit" variant="primary" loading={updatingRole} disabled={!selectedRole}>
                  Gán vai trò
                </Button>
              </div>
            </form>
          </div>
        </Modal>
      )}

      {/* ── Reset Password Modal ──────────────────────────────────────────────── */}
      {passwordTargetUser && (
        <Modal
          open={Boolean(passwordTargetUser)}
          onClose={() => { setPasswordTargetUser(null); setNewPassword('') }}
          title={`Đặt lại mật khẩu: ${passwordTargetUser.name}`}
          size="sm"
        >
          <form onSubmit={handleResetPassword} className="space-y-4">
            <p className="text-xs text-outline leading-relaxed">
              Nhập mật khẩu mới cho tài khoản <strong className="text-on-surface">{passwordTargetUser.email}</strong>.
              Mật khẩu mới yêu cầu tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường và chữ số.
            </p>

            <div className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-on-surface-variant">Mật khẩu mới</label>
                <button
                  type="button"
                  onClick={generateRandomPassword}
                  className="text-xs text-primary hover:underline cursor-pointer flex items-center gap-0.5"
                >
                  <span className="material-symbols-outlined text-xs">auto_awesome</span> Tạo ngẫu nhiên
                </button>
              </div>
              <input
                type="text"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="Nhập mật khẩu an toàn..."
                className="bg-surface-container-low text-on-surface rounded-xl px-3 py-2 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary font-mono cursor-text"
                required
              />
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-outline-variant/15">
              <Button
                type="button"
                variant="ghost"
                onClick={() => { setPasswordTargetUser(null); setNewPassword('') }}
                disabled={resettingPassword}
              >
                Hủy
              </Button>
              <Button
                type="submit"
                variant="primary"
                loading={resettingPassword}
                disabled={!newPassword || newPassword.length < 8}
              >
                Xác nhận đổi mật khẩu
              </Button>
            </div>
          </form>
        </Modal>
      )}

      {/* ── Status Lock / Unlock Confirm Dialog ───────────────────────────────── */}
      <ConfirmDialog
        open={Boolean(statusTarget)}
        onClose={() => setStatusTarget(null)}
        onConfirm={handleConfirmStatusToggle}
        title={statusTarget?.is_active ? 'Xác nhận khóa tài khoản' : 'Mở khóa tài khoản'}
        message={
          statusTarget?.is_active
            ? `Bạn có chắc chắn muốn khóa tài khoản "${statusTarget?.name}" (${statusTarget?.email})? Người dùng này sẽ lập tức bị tước phiên đăng nhập và không thể thao tác trên hệ thống.`
            : `Mở khóa cho tài khoản "${statusTarget?.name}" (${statusTarget?.email}) để người dùng này tiếp tục đăng nhập và sử dụng dịch vụ?`
        }
        confirmText={statusTarget?.is_active ? 'Khóa tài khoản' : 'Mở khóa'}
        variant={statusTarget?.is_active ? 'error' : 'primary'}
        loading={togglingStatus}
      />
    </div>
  )
}
