import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const REASONS = [
  'Sản phẩm bị lỗi kỹ thuật / không hoạt động',
  'Sản phẩm bị hư hỏng / bể vỡ trong quá trình vận chuyển',
  'Giao sai sản phẩm / sai phiên bản / sai màu sắc',
  'Thiếu phụ kiện / quà tặng kèm theo',
  'Sản phẩm không đúng với thông tin mô tả trên website',
  'Khác (vui lòng nêu rõ trong phần mô tả)',
]

const POPULAR_BANKS = [
  'Vietcombank',
  'Techcombank',
  'MBBank',
  'VietinBank',
  'BIDV',
  'ACB',
  'VPBank',
  'TPBank',
  'Sacombank',
  'HDBank',
  'Khác',
]

export default function ReturnRequestPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [order, setOrder] = useState(null)
  const [selected, setSelected] = useState({})
  const [returnType, setReturnType] = useState('refund')
  const [reason, setReason] = useState(REASONS[0])
  const [description, setDescription] = useState('')
  const [customerNote, setCustomerNote] = useState('')
  const [bankName, setBankName] = useState('Vietcombank')
  const [customBankName, setCustomBankName] = useState('')
  const [bankAccountNumber, setBankAccountNumber] = useState('')
  const [bankAccountName, setBankAccountName] = useState('')
  const [photos, setPhotos] = useState([])
  const [photoPreviews, setPhotoPreviews] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    axiosClient
      .get(EP.ORDER_DETAIL(id))
      .then(({ data }) => {
        const current = data.data ?? data
        setOrder(current)
        const initial = {}
        ;(current.items || []).forEach((item) => {
          initial[item.id] = 0
        })
        setSelected(initial)
      })
      .catch((error) => {
        showToast(error.response?.data?.message || 'Không tải được thông tin đơn hàng.', 'error')
      })
      .finally(() => setLoading(false))
  }, [id, showToast])

  const handlePhotoChange = (e) => {
    const files = Array.from(e.target.files || []).slice(0, 5)
    setPhotos(files)
    const previews = files.map((file) => URL.createObjectURL(file))
    setPhotoPreviews(previews)
  }

  // Calculate estimated refund total
  const estimatedRefundTotal = Object.entries(selected).reduce((acc, [itemId, qty]) => {
    if (qty <= 0) return acc
    const item = (order?.items || []).find((i) => i.id === Number(itemId))
    return acc + (item ? Number(item.price || 0) * qty : 0)
  }, 0)

  const isCODOrBankTransfer = order?.payment_method === 'cod' || order?.payment_method === 'bank_transfer'

  const submit = async (event) => {
    event.preventDefault()

    const lines = Object.entries(selected).filter(([, quantity]) => Number(quantity) > 0)
    if (!lines.length) {
      return showToast('Vui lòng chọn ít nhất một sản phẩm và số lượng cần đổi trả.', 'warning')
    }

    if (!description.trim()) {
      return showToast('Vui lòng mô tả chi tiết tình trạng sản phẩm gặp lỗi hoặc hư hỏng.', 'warning')
    }

    if (returnType === 'refund' && isCODOrBankTransfer) {
      const effectiveBank = bankName === 'Khác' ? customBankName.trim() : bankName
      if (!effectiveBank || !bankAccountNumber.trim() || !bankAccountName.trim()) {
        return showToast('Vui lòng nhập đầy đủ thông tin tài khoản ngân hàng để nhận tiền hoàn.', 'warning')
      }
    }

    const form = new FormData()
    form.append('order_id', order.id)
    form.append('return_type', returnType)
    form.append('reason', reason)
    form.append('description', description.trim())
    if (customerNote.trim()) form.append('customer_note', customerNote.trim())

    if (returnType === 'refund' && isCODOrBankTransfer) {
      form.append('bank_name', bankName === 'Khác' ? customBankName.trim() : bankName)
      form.append('bank_account_number', bankAccountNumber.trim())
      form.append('bank_account_name', bankAccountName.trim().toUpperCase())
    }

    lines.forEach(([orderItemId, quantity], index) => {
      form.append(`items[${index}][order_item_id]`, orderItemId)
      form.append(`items[${index}][quantity]`, quantity)
    })

    photos.forEach((photo) => form.append('photos[]', photo))

    setSubmitting(true)
    try {
      const { data } = await axiosClient.post(EP.RETURNS, form)
      showToast(data.message || 'Đã gửi yêu cầu đổi/trả hàng thành công.', 'success')
      navigate('/returns')
    } catch (error) {
      const message =
        error.response?.data?.message ||
        Object.values(error.response?.data?.errors || {}).flat()[0] ||
        'Không thể gửi yêu cầu đổi/trả hàng lúc này.'
      showToast(message, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-[50vh] grid place-items-center">
        <Spinner size="lg" />
      </div>
    )
  }

  if (!order) {
    return <div className="max-w-3xl mx-auto p-8 text-center text-on-surface-variant">Không tìm thấy đơn hàng.</div>
  }

  const eligible = ['delivered', 'received'].includes(order.status)

  return (
    <main className="max-w-4xl mx-auto px-4 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to={`/orders/${id}`} className="text-xs text-primary font-semibold hover:underline">
            ← Quay lại chi tiết đơn hàng
          </Link>
          <h1 className="text-2xl font-bold mt-1 text-on-surface">Yêu cầu Đổi trả & Hoàn tiền</h1>
          <p className="text-xs text-on-surface-variant">
            Đơn hàng #{order.order_code || order.id} · Phương thức thanh toán:{' '}
            <span className="font-semibold uppercase">{order.payment_method || 'COD'}</span>
          </p>
        </div>
        <Link
          to="/returns"
          className="text-xs px-3 py-1.5 rounded-lg border border-outline-variant hover:bg-surface-container font-semibold transition-colors"
        >
          Lịch sử đổi trả
        </Link>
      </div>

      {!eligible ? (
        <div className="rounded-2xl border border-outline-variant/30 bg-surface-container p-6 space-y-2">
          <div className="flex items-center gap-2 text-warning font-semibold">
            <span className="material-symbols-outlined">warning</span>
            Đơn hàng chưa đủ điều kiện gửi yêu cầu đổi trả
          </div>
          <p className="text-sm text-on-surface-variant">
            Chính sách MobieX chỉ hỗ trợ gửi yêu cầu đổi trả đối với những đơn hàng đã giao thành công hoặc khách hàng đã
            xác nhận nhận hàng.
          </p>
          <div className="pt-2">
            <Link to="/orders" className="text-primary text-sm font-semibold hover:underline">
              Quay lại danh sách đơn hàng
            </Link>
          </div>
        </div>
      ) : (
        <form onSubmit={submit} className="bg-surface-container rounded-2xl border border-outline-variant/20 p-5 md:p-8 space-y-8">
          {/* Section 1: Choose Resolution Method */}
          <section className="space-y-3">
            <div className="flex items-center gap-2">
              <span className="w-1.5 h-4 bg-primary rounded-full" />
              <h2 className="font-bold text-on-surface text-base">1. Chọn phương án xử lý mong muốn</h2>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label
                className={`relative flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all ${
                  returnType === 'refund'
                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                    : 'border-outline-variant/30 bg-surface hover:bg-surface-container-high'
                }`}
              >
                <input
                  type="radio"
                  name="return_type"
                  value="refund"
                  checked={returnType === 'refund'}
                  onChange={() => setReturnType('refund')}
                  className="mt-1 text-primary focus:ring-primary"
                />
                <div>
                  <p className="font-semibold text-sm text-on-surface">Trả hàng & Hoàn tiền</p>
                  <p className="text-xs text-on-surface-variant mt-0.5">
                    Gửi trả sản phẩm về kho và nhận lại tiền hoàn theo đúng giá trị sản phẩm.
                  </p>
                </div>
              </label>

              <label
                className={`relative flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all ${
                  returnType === 'replacement'
                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                    : 'border-outline-variant/30 bg-surface hover:bg-surface-container-high'
                }`}
              >
                <input
                  type="radio"
                  name="return_type"
                  value="replacement"
                  checked={returnType === 'replacement'}
                  onChange={() => setReturnType('replacement')}
                  className="mt-1 text-primary focus:ring-primary"
                />
                <div>
                  <p className="font-semibold text-sm text-on-surface">Đổi sản phẩm mới (Đổi hàng)</p>
                  <p className="text-xs text-on-surface-variant mt-0.5">
                    Đổi sang sản phẩm mới cùng loại khi sản phẩm nhận được bị lỗi, hư hỏng hoặc sai phiên bản.
                  </p>
                </div>
              </label>
            </div>
          </section>

          {/* Section 2: Choose Products & Quantities */}
          <section className="space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="w-1.5 h-4 bg-primary rounded-full" />
                <h2 className="font-bold text-on-surface text-base">2. Chọn sản phẩm và số lượng cần đổi trả</h2>
              </div>
              {returnType === 'refund' && estimatedRefundTotal > 0 && (
                <div className="text-xs font-semibold text-primary">
                  Tạm tính hoàn lại: {estimatedRefundTotal.toLocaleString('vi-VN')} đ
                </div>
              )}
            </div>

            <div className="divide-y divide-outline-variant/15 border border-outline-variant/20 rounded-xl overflow-hidden bg-surface">
              {(order.items || []).map((item) => {
                const qty = selected[item.id] ?? 0
                return (
                  <div key={item.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3 min-w-0">
                      {item.image_path ? (
                        <img
                          src={item.image_path.startsWith('http') ? item.image_path : `/storage/${item.image_path}`}
                          alt={item.product_name}
                          className="w-12 h-12 object-cover rounded-lg border border-outline-variant/30 shrink-0"
                        />
                      ) : (
                        <div className="w-12 h-12 bg-surface-container grid place-items-center rounded-lg text-outline shrink-0">
                          <span className="material-symbols-outlined text-lg">devices</span>
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-on-surface truncate">{item.product_name}</p>
                        <p className="text-xs text-on-surface-variant">
                          {item.variant_name || item.sku || 'Mặc định'} · Đơn giá: {Number(item.price || 0).toLocaleString('vi-VN')} đ · Đã mua: {item.quantity}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-3 self-end sm:self-center">
                      <label htmlFor={`qty-${item.id}`} className="text-xs text-on-surface-variant font-medium">
                        Số lượng trả:
                      </label>
                      <input
                        id={`qty-${item.id}`}
                        type="number"
                        min="0"
                        max={item.quantity}
                        value={qty}
                        onChange={(e) =>
                          setSelected((old) => ({
                            ...old,
                            [item.id]: Math.min(item.quantity, Math.max(0, Number(e.target.value))),
                          }))
                        }
                        className="w-20 rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm font-semibold text-center focus:ring-1 focus:ring-primary"
                      />
                    </div>
                  </div>
                )
              })}
            </div>
          </section>

          {/* Section 3: Reason & Condition Description */}
          <section className="space-y-3">
            <div className="flex items-center gap-2">
              <span className="w-1.5 h-4 bg-primary rounded-full" />
              <h2 className="font-bold text-on-surface text-base">3. Lý do và mô tả tình trạng sản phẩm</h2>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1.5">Lý do chính *</label>
                <select
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary"
                >
                  {REASONS.map((value) => (
                    <option key={value} value={value}>
                      {value}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1.5">
                  Mô tả chi tiết tình trạng sản phẩm gặp phải *
                </label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={4}
                  maxLength={3000}
                  placeholder="Mô tả cụ thể lỗi kỹ thuật, tình trạng tem mác, hộp đựng hoặc thiếu phụ kiện gì..."
                  className="w-full rounded-xl border border-outline-variant bg-surface p-3.5 text-sm text-on-surface focus:ring-1 focus:ring-primary"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1.5">
                  Ghi chú thêm cho cửa hàng (tùy chọn)
                </label>
                <input
                  type="text"
                  value={customerNote}
                  onChange={(e) => setCustomerNote(e.target.value)}
                  maxLength={1000}
                  placeholder="Ví dụ: Giờ có thể nghe máy shipper, ghi chú địa chỉ gửi lại..."
                  className="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary"
                />
              </div>
            </div>
          </section>

          {/* Section 4: Bank Account Details (If Refund & COD/Bank Transfer) */}
          {returnType === 'refund' && (
            <section className="space-y-3 p-4 rounded-xl border border-outline-variant/30 bg-surface">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-lg">account_balance</span>
                <h3 className="font-bold text-sm text-on-surface">Thông tin nhận tiền hoàn</h3>
              </div>

              {isCODOrBankTransfer ? (
                <div className="space-y-3 pt-1">
                  <p className="text-xs text-on-surface-variant">
                    Đơn hàng thanh toán khi nhận hàng (COD) hoặc Chuyển khoản. Vui lòng cung cấp tài khoản ngân hàng chính chủ để cửa hàng chuyển khoản hoàn tiền sau khi kiểm tra sản phẩm.
                  </p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-medium text-on-surface mb-1">Ngân hàng *</label>
                      <select
                        value={bankName}
                        onChange={(e) => setBankName(e.target.value)}
                        className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs"
                      >
                        {POPULAR_BANKS.map((b) => (
                          <option key={b} value={b}>
                            {b}
                          </option>
                        ))}
                      </select>
                      {bankName === 'Khác' && (
                        <input
                          type="text"
                          placeholder="Nhập tên ngân hàng"
                          value={customBankName}
                          onChange={(e) => setCustomBankName(e.target.value)}
                          className="mt-2 w-full rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-xs"
                          required
                        />
                      )}
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-on-surface mb-1">Số tài khoản *</label>
                      <input
                        type="text"
                        placeholder="Ví dụ: 0987654321"
                        value={bankAccountNumber}
                        onChange={(e) => setBankAccountNumber(e.target.value)}
                        className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-mono"
                        required
                      />
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-xs font-medium text-on-surface mb-1">Tên chủ tài khoản (viết hoa không dấu) *</label>
                      <input
                        type="text"
                        placeholder="Ví dụ: NGUYEN VAN A"
                        value={bankAccountName}
                        onChange={(e) => setBankAccountName(e.target.value.toUpperCase())}
                        className="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-xs font-mono uppercase"
                        required
                      />
                    </div>
                  </div>
                </div>
              ) : (
                <div className="text-xs text-on-surface-variant p-3 rounded-lg bg-surface-container">
                  Đơn hàng được thanh toán trực tuyến qua <strong className="uppercase">{order.payment_method}</strong>. Tiền hoàn sẽ được hoàn trực tiếp về tài khoản / thẻ ban đầu của bạn hoặc theo thỏa thuận hoàn tiền của cổng.
                </div>
              )}
            </section>
          )}

          {/* Section 5: Evidence Photos Upload */}
          <section className="space-y-3">
            <div className="flex items-center gap-2">
              <span className="w-1.5 h-4 bg-primary rounded-full" />
              <h2 className="font-bold text-on-surface text-base">4. Hình ảnh minh chứng (tối đa 5 ảnh, 5MB/ảnh)</h2>
            </div>
            <p className="text-xs text-on-surface-variant">
              Khuyến khích chụp rõ tem mác sản phẩm, phiếu bảo hành và vị trí hư hỏng để yêu cầu được phê duyệt nhanh chóng.
            </p>
            <input
              type="file"
              accept="image/*"
              multiple
              onChange={handlePhotoChange}
              className="block w-full text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary hover:file:opacity-90 cursor-pointer"
            />
            {photoPreviews.length > 0 && (
              <div className="flex flex-wrap gap-3 pt-2">
                {photoPreviews.map((src, index) => (
                  <img
                    key={index}
                    src={src}
                    alt={`Minh chứng ${index + 1}`}
                    className="w-20 h-20 object-cover rounded-xl border border-outline-variant/30"
                  />
                ))}
              </div>
            )}
          </section>

          {/* Policy Note & Action */}
          <div className="rounded-xl bg-surface p-4 text-xs text-on-surface-variant border border-outline-variant/20 space-y-1">
            <p className="font-semibold text-on-surface">Lưu ý khi gửi yêu cầu đổi trả:</p>
            <ul className="list-disc list-inside space-y-0.5">
              <li>Cửa hàng sẽ phản hồi trong vòng 24 - 48 giờ làm việc.</li>
              <li>Vui lòng giữ nguyên hộp, tem niêm phong và toàn bộ phụ kiện đi kèm khi gửi trả.</li>
              <li>Sau khi gửi, bạn có thể theo dõi tiến trình trực tiếp tại trang Quản lý đổi trả.</li>
            </ul>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-4 pt-2">
            <Link to={`/orders/${id}`} className="text-sm font-semibold text-on-surface-variant hover:text-on-surface">
              Hủy bỏ
            </Link>
            <button
              type="submit"
              disabled={submitting}
              className="rounded-xl bg-primary text-on-primary px-8 py-3 text-sm font-bold shadow hover:opacity-95 disabled:opacity-50 transition-all"
            >
              {submitting ? 'Đang gửi yêu cầu...' : 'Gửi yêu cầu đổi/trả ngay'}
            </button>
          </div>
        </form>
      )}
    </main>
  )
}
