// src/features/admin/AdminCouponCreatePage.jsx
// Dedicated page for creating a new promotional coupon in Admin MobieX.
// URL: /admin/coupons/create

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import CouponForm from './coupon/CouponForm'

export default function AdminCouponCreatePage() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const handleSubmit = async (payload) => {
    setSubmitting(true)
    setErrors({})

    try {
      await axiosClient.post(EP.ADMIN_COUPONS, payload)
      showToast('Tạo mã giảm giá mới thành công!', 'success')
      navigate('/admin/coupons')
    } catch (err) {
      if (err.validationErrors) {
        setErrors(err.validationErrors)
      }
      showToast(err.message || 'Không thể tạo mã giảm giá', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/coupons')
  }

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Breadcrumb Navigation */}
      <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-on-surface-variant flex-wrap">
        <Link to="/admin" className="hover:text-primary transition-colors flex items-center gap-1">
          <span className="material-symbols-outlined text-sm">home</span>
          Tổng quan
        </Link>
        <span className="text-outline">/</span>
        <Link to="/admin/coupons" className="hover:text-primary transition-colors">
          Mã giảm giá
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold">Tạo mã mới</span>
      </nav>

      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-outline-variant/20">
        <div>
          <div className="flex items-center gap-2.5">
            <Link
              to="/admin/coupons"
              className="p-1.5 rounded-xl hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors"
              title="Quay lại danh sách"
            >
              <span className="material-symbols-outlined text-xl leading-none">arrow_back</span>
            </Link>
            <h1 className="text-2xl font-bold text-on-surface">Tạo mã giảm giá mới</h1>
          </div>
          <p className="text-sm text-on-surface-variant mt-1 pl-9">
            Thiết lập chiến dịch khuyến mãi, mức chiết khấu và điều kiện áp dụng cho khách hàng MobieX.
          </p>
        </div>
      </div>

      {/* Coupon Form */}
      <CouponForm
        isEdit={false}
        submitting={submitting}
        errors={errors}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  )
}
