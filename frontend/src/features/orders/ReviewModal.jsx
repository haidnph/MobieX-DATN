// src/features/orders/ReviewModal.jsx
// Modal for submitting a verified purchase product review.

import { useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Modal from '../../components/common/Modal'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'

export default function ReviewModal({ open, onClose, product, orderItemId, onSuccess }) {
  const { showToast } = useToast()

  const [rating, setRating] = useState(5)
  const [hoverRating, setHoverRating] = useState(0)
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState({})

  if (!open) return null

  const productId = product?.id

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!productId) return

    setSubmitting(true)
    setErrors({})

    try {
      await axiosClient.post(EP.PRODUCT_REVIEWS(productId), {
        order_item_id: orderItemId,
        rating,
        title: title.trim() || undefined,
        content: content.trim(),
      })

      showToast('Đánh giá của bạn đã được gửi và đang chờ quản trị viên phê duyệt!', 'success')
      if (onSuccess) onSuccess()
      onClose()
    } catch (err) {
      if (err.validationErrors) setErrors(err.validationErrors)
      showToast(err.message || 'Không thể gửi đánh giá, vui lòng thử lại', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Đánh giá sản phẩm" size="md">
      <form onSubmit={handleSubmit} className="space-y-4">
        {product && (
          <div className="flex items-center gap-3 p-3 bg-surface-container-low rounded-xl">
            {product.thumbnail && (
              <img
                src={product.thumbnail}
                alt={product.name}
                className="w-12 h-12 object-contain rounded-lg"
              />
            )}
            <div className="min-w-0">
              <p className="text-sm font-semibold text-on-surface truncate">{product.name}</p>
              <p className="text-xs text-on-surface-variant">Trải nghiệm của bạn thế nào?</p>
            </div>
          </div>
        )}

        {/* Star Rating Selector */}
        <div className="text-center py-2 space-y-1">
          <label className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block">
            Chọn số sao
          </label>
          <div className="flex justify-center gap-2">
            {[1, 2, 3, 4, 5].map((star) => {
              const active = star <= (hoverRating || rating)
              return (
                <button
                  key={star}
                  type="button"
                  onClick={() => setRating(star)}
                  onMouseEnter={() => setHoverRating(star)}
                  onMouseLeave={() => setHoverRating(0)}
                  className="p-1 text-2xl transition-transform hover:scale-110 focus:outline-none"
                  aria-label={`${star} sao`}
                >
                  <span
                    className={`material-symbols-outlined text-3xl ${
                      active ? 'text-secondary fill-1' : 'text-outline'
                    }`}
                  >
                    star
                  </span>
                </button>
              )
            })}
          </div>
          <p className="text-xs text-secondary font-medium">
            {['Rất tệ', 'Tệ', 'Bình thường', 'Hài lòng', 'Tuyệt vời'][rating - 1]}
          </p>
        </div>

        <Input
          label="Tiêu đề đánh giá (tùy chọn)"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="Tóm tắt cảm nhận của bạn..."
          error={errors.title?.[0]}
        />

        <div className="flex flex-col gap-1">
          <label className="text-sm font-medium text-on-surface-variant">Nội dung chi tiết</label>
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={4}
            placeholder="Hãy chia sẻ cảm nhận thực tế về chất lượng sản phẩm, đóng gói và thời gian giao hàng..."
            className="w-full bg-surface-container-low text-on-surface placeholder:text-outline rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all resize-none"
            required
          />
          {errors.content && <p className="text-xs text-error mt-0.5">{errors.content[0]}</p>}
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <Button type="button" variant="ghost" onClick={onClose} disabled={submitting}>
            Hủy
          </Button>
          <Button type="submit" variant="primary" loading={submitting}>
            Gửi đánh giá
          </Button>
        </div>
      </form>
    </Modal>
  )
}
