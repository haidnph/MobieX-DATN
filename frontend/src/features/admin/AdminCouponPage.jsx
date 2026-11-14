// src/features/admin/AdminCouponPage.jsx
// Admin promotional coupon code management list interface.
// Links to Dedicated Pages for Create (/admin/coupons/create) and Edit (/admin/coupons/:id/edit).

import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import { formatDate } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Button from '../../components/common/Button'
import ConfirmDialog from '../../components/feedback/ConfirmDialog'
import Spinner from '../../components/common/Spinner'
import Badge from '../../components/common/Badge'

export default function AdminCouponPage() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [coupons, setCoupons] = useState([])
  const [loading, setLoading] = useState(true)

  // Delete dialog
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const fetchCoupons = () => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_COUPONS)
      .then(({ data }) => {
        const payload = data.data ?? data
        setCoupons(Array.isArray(payload) ? payload : (payload.data ?? []))
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách mã giảm giá', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchCoupons()
  }, [])

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await axiosClient.delete(EP.ADMIN_COUPON_DETAIL(deleteTarget.id))
      showToast('Đã xóa mã giảm giá!', 'info')
      setDeleteTarget(null)
      fetchCoupons()
    } catch (err) {
      showToast(err.message || 'Không thể xóa mã giảm giá', 'error')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Page Title & Add Button */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Mã giảm giá (Coupons)</h1>
          <p className="text-sm text-on-surface-variant">
            Quản lý chiến dịch khuyến mãi, voucher chiết khấu đơn hàng trên MobieX.
          </p>
        </div>
        <Link
          to="/admin/coupons/create"
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-on-primary rounded-xl font-semibold text-sm hover:opacity-90 shadow-sm transition-all"
        >
          <span className="material-symbols-outlined text-base">add</span>
          Tạo mã mới
        </Link>
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : coupons.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-3">
            <span className="material-symbols-outlined text-5xl text-outline">local_offer</span>
            <p className="text-sm font-medium">Chưa có mã giảm giá nào được tạo.</p>
            <Link
              to="/admin/coupons/create"
              className="inline-flex items-center gap-1 text-sm text-primary hover:underline font-semibold"
            >
              <span className="material-symbols-outlined text-sm">add</span>
              Tạo mã đầu tiên ngay
            </Link>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Mã Code</th>
                  <th className="px-5 py-3.5">Loại & Mức giảm</th>
                  <th className="px-5 py-3.5">Đơn tối thiểu</th>
                  <th className="px-5 py-3.5 text-center">Lượt dùng</th>
                  <th className="px-5 py-3.5">Hạn dùng</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {coupons.map((c) => {
                  const isPercent = (c.discount_type || c.type) === 'percent'
                  const discountVal = c.discount_value ?? c.value
                  const minOrder = c.min_order_amount ?? 0
                  const expiry = c.end_at || c.expires_at

                  return (
                    <tr key={c.id} className="hover:bg-surface-container-high/40 transition-colors">
                      <td className="px-5 py-4">
                        <span className="font-mono font-bold text-primary text-sm">{c.code}</span>
                      </td>
                      <td className="px-5 py-4">
                        {isPercent ? (
                          <div className="space-y-0.5">
                            <span className="text-secondary font-bold">{discountVal}%</span>
                            {c.max_discount && Number(c.max_discount) > 0 && (
                              <p className="text-[11px] text-outline">
                                Tối đa {formatCurrencyVND(c.max_discount)}
                              </p>
                            )}
                          </div>
                        ) : (
                          <span className="text-secondary font-bold">
                            {formatCurrencyVND(discountVal)}
                          </span>
                        )}
                      </td>
                      <td className="px-5 py-4 text-xs text-on-surface-variant">
                        {Number(minOrder) > 0 ? formatCurrencyVND(minOrder) : 'Không giới hạn'}
                      </td>
                      <td className="px-5 py-4 text-center font-mono text-xs">
                        <span className="font-semibold text-on-surface">{c.used_count ?? 0}</span>
                        <span className="text-outline"> / {c.usage_limit ?? '∞'}</span>
                      </td>
                      <td className="px-5 py-4 text-xs text-on-surface-variant">
                        {expiry ? formatDate(expiry) : 'Vĩnh viễn'}
                      </td>
                      <td className="px-5 py-4 text-center">
                        <Badge variant={c.is_active ? 'success' : 'neutral'}>
                          {c.is_active ? 'Hoạt động' : 'Tạm dừng'}
                        </Badge>
                      </td>
                      <td className="px-5 py-4 text-right space-x-2">
                        <Link
                          to={`/admin/coupons/${c.id}/edit`}
                          className="inline-flex items-center gap-1 px-2.5 py-1 bg-surface-container-high hover:bg-primary hover:text-on-primary rounded-lg text-xs font-semibold text-on-surface transition-colors"
                        >
                          <span className="material-symbols-outlined text-xs">edit</span>
                          Sửa
                        </Link>
                        <button
                          type="button"
                          onClick={() => setDeleteTarget(c)}
                          className="inline-flex items-center gap-1 px-2.5 py-1 bg-error-container/20 hover:bg-error-container/40 rounded-lg text-xs font-semibold text-error transition-colors"
                        >
                          <span className="material-symbols-outlined text-xs">delete</span>
                          Xóa
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Chuyển vào Thùng rác"
        message={`Bạn có chắc chắn muốn chuyển mã "${deleteTarget?.code}" vào Thùng rác? Bạn có thể khôi phục lại bất kỳ lúc nào.`}
        confirmLabel="Chuyển vào Thùng rác"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
        loading={deleting}
      />
    </div>
  )
}
