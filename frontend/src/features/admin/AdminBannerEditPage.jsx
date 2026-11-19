// src/features/admin/AdminBannerEditPage.jsx
// Dedicated page for editing an existing promotional banner in MobieX Admin.
// URL: /admin/banners/:id/edit

import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import BannerForm from './banner/BannerForm'
import Spinner from '../../components/common/Spinner'

export default function AdminBannerEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [banner, setBanner] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  useEffect(() => {
    setLoading(true)
    axiosClient
      .get(EP.ADMIN_BANNER_DETAIL(id))
      .then(({ data }) => {
        setBanner(data.data ?? data)
      })
      .catch((err) => {
        showToast(err.message || 'Không thể tải thông tin banner', 'error')
        navigate('/admin/banners')
      })
      .finally(() => setLoading(false))
  }, [id, navigate, showToast])

  const handleSubmit = async (payload) => {
    setSubmitting(true)
    setErrors({})

    try {
      await axiosClient.put(EP.ADMIN_BANNER_DETAIL(id), payload)
      showToast('Cập nhật banner quảng cáo thành công!', 'success')
      navigate('/admin/banners')
    } catch (err) {
      if (err.validationErrors) {
        setErrors(err.validationErrors)
      }
      showToast(err.message || 'Không thể cập nhật banner', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = () => {
    navigate('/admin/banners')
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center py-20 gap-3">
        <Spinner size="lg" />
        <span className="text-sm text-on-surface-variant font-medium">Đang tải thông tin banner...</span>
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
        <Link to="/admin/banners" className="hover:text-primary transition-colors">
          Quản lý Banner
        </Link>
        <span className="text-outline">/</span>
        <span className="text-on-surface font-semibold truncate max-w-xs">
          {banner?.title || `Banner #${id}`}
        </span>
      </nav>

      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-on-surface">Chỉnh sửa banner</h1>
        <p className="text-sm text-on-surface-variant">
          Cập nhật hình ảnh, tiêu đề, nút kêu gọi hành động (CTA) và thời gian hiển thị.
        </p>
      </div>

      {/* Main Form */}
      <BannerForm
        initialData={banner}
        isEdit={true}
        submitting={submitting}
        errors={errors}
        onSubmit={handleSubmit}
        onCancel={handleCancel}
      />
    </div>
  )
}
