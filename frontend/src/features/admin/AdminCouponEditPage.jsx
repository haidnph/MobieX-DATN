// src/features/admin/AdminCouponEditPage.jsx
// Dedicated page for editing an existing coupon in Admin MobieX.
// URL: /admin/coupons/:id/edit

import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import CouponForm from './coupon/CouponForm'
import Spinner from '../../components/common/Spinner'
import Button from '../../components/common/Button'
import Badge from '../../components/common/Badge'

export default function AdminCouponEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [coupon, setCoupon] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})
  const [notFound, setNotFound] = useState(false)

  // Fetch coupon detail on mount
  useEffect(() => {
    if (!id) return
    setLoading(true)
    setNotFound(false)

    axiosClient
      .get(EP.ADMIN_COUPON_DETAIL(id))
      .then(({ data }) => {
        const payload = data.data ?? data
        if (payload && payload.id) {
          setCoupon(payload)
        } else {
          setNotFound(true)
        }
      })
      .catch((err) => {
        setNotFound(true)
        showToast(err.message || 'Không tìm thấy mã giảm giá yêu cầu', 'error')
      })
      .finally(() => setLoading(false))
  }, [id, showToast])

  const handleSubmit = async (payload) => {
    setSubmitting(true)
    setErrors({})

    try {
      await axiosClient.put(EP.ADMIN_COUPON_DETAIL(id), payload)
      showToast('Cập nhật mã giảm giá thành công!', 'success')
      navigate('/admin/coupons')
    } catch (err) {
      if (err.validationErrors) {
        setErrors(err.validationErrors)
      }
      showToast(err.message || 'Không thể cập nhật mã giảm giá', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/coupons')
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] gap-3">
        <Spinner size="lg" />
        <p className="text-sm text-on-surface-variant">Đang tải thông tin mã giảm giá #{id}...</p>
      </div>
    )
  }

  if (notFound || !coupon) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center max-w-md mx-auto">
        <span className="material-symbols-outlined text-5xl text-outline">search_off</span>
        <h2 className="text-xl font-bold text-on-surface">Không tìm thấy mã giảm giá</h2>
        <p className="text-sm text-on-surface-variant">
          Mã giảm giá với ID #{id} không tồn tại hoặc đã bị xóa khỏi hệ thống.
        </p>
        <Button variant="primary" onClick={() => navigate('/admin/coupons')}>
          Quay lại danh sách mã giảm giá
        </Button>
      </div>
    )
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
        <span className="text-on-surface font-semibold font-mono">
          Chỉnh sửa: {coupon.code}
        </span>
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
            <h1 className="text-2xl font-bold text-on-surface flex items-center gap-2.5">
              <span>Chỉnh sửa mã giảm giá:</span>
              <span className="font-mono text-primary">{coupon.code}</span>
              <Badge variant={coupon.is_active ? 'success' : 'neutral'}>
                {coupon.is_active ? 'Hoạt động' : 'Tạm dừng'}
              </Badge>
            </h1>
          </div>
          <p className="text-sm text-on-surface-variant mt-1 pl-9">
            Cập nhật hình thức chiết khấu, hạn mức, thời gian hiệu lực và trạng thái phát hành.
          </p>
        </div>
      </div>

      {/* Coupon Form */}
      <CouponForm
        initialData={coupon}
        isEdit={true}
        submitting={submitting}
        errors={errors}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  )
}
