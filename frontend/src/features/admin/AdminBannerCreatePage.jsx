// src/features/admin/AdminBannerCreatePage.jsx
// Dedicated page for creating a new promotional banner in MobieX Admin.
// URL: /admin/banners/create

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import BannerForm from './banner/BannerForm'

export default function AdminBannerCreatePage() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  const handleSubmit = async (payload) => {
    setSubmitting(true)
    setErrors({})

    try {
      await axiosClient.post(EP.ADMIN_BANNERS, payload)
      showToast('Tạo banner quảng cáo mới thành công!', 'success')
      navigate('/admin/banners')
    } catch (err) {
      if (err.validationErrors) {
        setErrors(err.validationErrors)
      }
      showToast(err.message || 'Không thể tạo banner', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/banners')
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
        <Link to="/admin/banners" className="hover:text-primary transition-colors">
          Quản lý Banner
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold">Thêm banner mới</span>
      </nav>

      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-on-surface">Thêm banner quảng cáo mới</h1>
        <p className="text-sm text-on-surface-variant">
          Tạo banner quảng bá chiến dịch, sản phẩm mới với hỗ trợ tải ảnh Desktop, Mobile và xem trước thời gian thực.
        </p>
      </div>

      {/* Main Form */}
      <BannerForm
        isEdit={false}
        submitting={submitting}
        errors={errors}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  )
}
