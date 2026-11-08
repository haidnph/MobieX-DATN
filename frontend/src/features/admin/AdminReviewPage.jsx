// src/features/admin/AdminReviewPage.jsx
// Admin review moderation interface: approve, hide, and inspect customer product feedback.

import { useEffect, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { formatDate } from '../../utils/formatDate'
import { useToast } from '../../contexts/ToastContext'
import Badge from '../../components/common/Badge'
import Spinner from '../../components/common/Spinner'

const STATUS_CONFIG = {
  pending: { label: 'Chờ duyệt', variant: 'pending' },
  approved: { label: 'Đã duyệt (Hiện)', variant: 'success' },
  hidden: { label: 'Đã ẩn', variant: 'error' },
}

export default function AdminReviewPage() {
  const { showToast } = useToast()

  const [reviews, setReviews] = useState([])
  const [filterStatus, setFilterStatus] = useState('pending')
  const [loading, setLoading] = useState(true)
  const [actionId, setActionId] = useState(null)

  const fetchReviews = () => {
    setLoading(true)
    const params = {}
    if (filterStatus) params.status = filterStatus

    axiosClient
      .get(EP.ADMIN_REVIEWS, { params })
      .then(({ data }) => {
        const payload = data.data ?? data
        setReviews(Array.isArray(payload) ? payload : (payload.data ?? []))
      })
      .catch((err) => showToast(err.message || 'Không thể tải danh sách đánh giá', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchReviews()
  }, [filterStatus])

  const handleUpdateStatus = async (reviewId, newStatus) => {
    setActionId(reviewId)
    try {
      await axiosClient.patch(EP.ADMIN_REVIEW_STATUS(reviewId), { status: newStatus })
      showToast(newStatus === 'approved' ? 'Đã duyệt đánh giá thành công!' : 'Đã ẩn đánh giá.', 'success')
      fetchReviews()
    } catch (err) {
      showToast(err.message || 'Không thể cập nhật trạng thái đánh giá', 'error')
    } finally {
      setActionId(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* Title */}
      <div>
        <h1 className="text-2xl font-bold text-on-surface">Kiểm duyệt đánh giá (Reviews)</h1>
        <p className="text-sm text-on-surface-variant">
          Kiểm tra và phê duyệt các phản hồi sản phẩm từ khách hàng đã mua hàng.
        </p>
      </div>

      {/* Filter Tabs */}
      <div className="flex gap-2 border-b border-outline-variant/20 pb-2">
        {[
          { key: 'pending', label: 'Chờ duyệt' },
          { key: 'approved', label: 'Đã phê duyệt' },
          { key: 'hidden', label: 'Đã ẩn' },
          { key: '', label: 'Tất cả' },
        ].map((tab) => (
          <button
            key={tab.key}
            onClick={() => setFilterStatus(tab.key)}
            className={[
              'px-4 py-2 rounded-xl text-sm font-medium transition-all',
              filterStatus === tab.key
                ? 'bg-primary-container text-on-primary-container font-semibold shadow'
                : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Spinner size="lg" />
          </div>
        ) : reviews.length === 0 ? (
          <div className="p-12 text-center text-on-surface-variant space-y-2">
            <span className="material-symbols-outlined text-5xl text-outline">rate_review</span>
            <p className="text-sm font-medium">Không có đánh giá nào trong danh mục này.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-container-high text-xs uppercase text-on-surface-variant border-b border-outline-variant/20">
                <tr>
                  <th className="px-5 py-3.5">Sản phẩm</th>
                  <th className="px-5 py-3.5">Người đánh giá</th>
                  <th className="px-5 py-3.5">Số sao & Nội dung</th>
                  <th className="px-5 py-3.5">Ngày gửi</th>
                  <th className="px-5 py-3.5 text-center">Trạng thái</th>
                  <th className="px-5 py-3.5 text-right">Kiểm duyệt</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/10">
                {reviews.map((rev) => {
                  const cfg = STATUS_CONFIG[rev.status] || { label: rev.status, variant: 'neutral' }
                  const product = rev.product || {}
                  const user = rev.user || {}
                  const isBusy = actionId === rev.id

                  return (
                    <tr key={rev.id} className="hover:bg-surface-container-high/40 transition-colors">
                      <td className="px-5 py-4 max-w-[200px]">
                        <p className="font-semibold text-on-surface truncate">{product.name || 'Sản phẩm'}</p>
                        <p className="text-xs text-outline font-mono">ID: #{product.id || rev.product_id}</p>
                      </td>
                      <td className="px-5 py-4 whitespace-nowrap">
                        <p className="font-medium text-on-surface">{user.name || 'Khách ẩn danh'}</p>
                        <p className="text-xs text-outline">{user.email || '—'}</p>
                      </td>
                      <td className="px-5 py-4 max-w-sm">
                        <div className="flex items-center gap-1 text-secondary text-xs mb-1">
                          {Array.from({ length: 5 }).map((_, i) => (
                            <span key={i} className="material-symbols-outlined text-sm">
                              {i < rev.rating ? 'star' : 'star_outline'}
                            </span>
                          ))}
                          <span className="ml-1 font-bold">({rev.rating}/5)</span>
                        </div>
                        {rev.title && <p className="text-xs font-bold text-on-surface mb-0.5">{rev.title}</p>}
                        <p className="text-xs text-on-surface-variant line-clamp-2 leading-relaxed">
                          {rev.content}
                        </p>
                      </td>
                      <td className="px-5 py-4 text-xs text-on-surface-variant whitespace-nowrap">
                        {formatDate(rev.created_at)}
                      </td>
                      <td className="px-5 py-4 text-center">
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                      </td>
                      <td className="px-5 py-4 text-right space-x-2 whitespace-nowrap">
                        {rev.status !== 'approved' && (
                          <button
                            onClick={() => handleUpdateStatus(rev.id, 'approved')}
                            disabled={isBusy}
                            className="px-2.5 py-1 bg-secondary-container/20 hover:bg-secondary-container/40 rounded-lg text-xs font-semibold text-secondary transition-colors disabled:opacity-50"
                          >
                            Duyệt
                          </button>
                        )}
                        {rev.status !== 'hidden' && (
                          <button
                            onClick={() => handleUpdateStatus(rev.id, 'hidden')}
                            disabled={isBusy}
                            className="px-2.5 py-1 bg-error-container/20 hover:bg-error-container/40 rounded-lg text-xs font-semibold text-error transition-colors disabled:opacity-50"
                          >
                            Ẩn
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
