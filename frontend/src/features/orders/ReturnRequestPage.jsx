import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const REASONS = ['Sản phẩm bị lỗi / không hoạt động', 'Sản phẩm bị hư hỏng khi nhận', 'Giao sai sản phẩm / phiên bản', 'Thiếu phụ kiện', 'Sản phẩm không đúng mô tả', 'Lý do khác']

export default function ReturnRequestPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()
  const [order, setOrder] = useState(null)
  const [selected, setSelected] = useState({})
  const [reason, setReason] = useState(REASONS[0])
  const [description, setDescription] = useState('')
  const [photos, setPhotos] = useState([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    axiosClient.get(EP.ORDER_DETAIL(id)).then(({ data }) => {
      const current = data.data ?? data
      setOrder(current)
      const initial = {}
      ;(current.items || []).forEach((item) => { initial[item.id] = 0 })
      setSelected(initial)
    }).catch((error) => showToast(error.response?.data?.message || 'Không tải được đơn hàng.', 'error'))
      .finally(() => setLoading(false))
  }, [id])

  const submit = async (event) => {
    event.preventDefault()
    const lines = Object.entries(selected).filter(([, quantity]) => Number(quantity) > 0)
    if (!lines.length) return showToast('Vui lòng chọn ít nhất một sản phẩm cần trả.', 'warning')
    const form = new FormData()
    form.append('order_id', order.id)
    form.append('reason', reason)
    form.append('description', description)
    lines.forEach(([orderItemId, quantity], index) => {
      form.append(`items[${index}][order_item_id]`, orderItemId)
      form.append(`items[${index}][quantity]`, quantity)
    })
    photos.forEach((photo) => form.append('photos[]', photo))
    setSubmitting(true)
    try {
      const { data } = await axiosClient.post(EP.RETURNS, form)
      showToast(data.message || 'Đã gửi yêu cầu trả hàng.', 'success')
      navigate('/returns')
    } catch (error) {
      const message = error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Không thể gửi yêu cầu trả hàng.'
      showToast(message, 'error')
    } finally { setSubmitting(false) }
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><Spinner size="lg" /></div>
  if (!order) return <div className="max-w-3xl mx-auto p-8">Không tìm thấy đơn hàng.</div>
  const eligible = ['delivered', 'received'].includes(order.status)

  return <main className="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <div className="flex items-center justify-between gap-3"><div><Link to={`/orders/${id}`} className="text-sm text-primary">← Quay lại đơn hàng</Link><h1 className="text-2xl font-bold mt-2">Yêu cầu trả hàng</h1><p className="text-sm text-on-surface-variant">Đơn #{order.order_code || order.id}</p></div><Link to="/returns" className="text-sm text-primary">Lịch sử trả hàng</Link></div>
    {!eligible ? <div className="rounded-xl border border-outline-variant/30 bg-surface-container p-5"><p className="font-semibold">Đơn hàng chưa đủ điều kiện trả hàng</p><p className="text-sm text-on-surface-variant mt-1">Chỉ đơn đã giao thành công hoặc đã nhận hàng mới có thể gửi yêu cầu trả hàng.</p></div> :
      <form onSubmit={submit} className="bg-surface-container rounded-2xl border border-outline-variant/20 p-5 md:p-7 space-y-6">
        <section className="space-y-3"><h2 className="font-semibold">1. Chọn sản phẩm và số lượng cần trả</h2>
          {(order.items || []).map((item) => <div key={item.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-outline-variant/20 rounded-xl p-3">
            <div className="min-w-0"><p className="font-medium">{item.product_name}</p><p className="text-xs text-on-surface-variant">{item.variant_name || item.sku} · Đã mua: {item.quantity}</p></div>
            <div className="flex items-center gap-2"><label className="text-xs text-on-surface-variant" htmlFor={`qty-${item.id}`}>Số lượng trả</label><input id={`qty-${item.id}`} type="number" min="0" max={item.quantity} value={selected[item.id] ?? 0} onChange={(e) => setSelected((old) => ({ ...old, [item.id]: Math.min(item.quantity, Math.max(0, Number(e.target.value))) }))} className="w-20 rounded-lg border border-outline-variant bg-surface px-3 py-2" /></div>
          </div>)}
        </section>
        <section className="space-y-3"><h2 className="font-semibold">2. Lý do trả hàng</h2><select value={reason} onChange={(e) => setReason(e.target.value)} className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-3">{REASONS.map((value) => <option key={value}>{value}</option>)}</select><textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={4} maxLength={3000} placeholder="Mô tả tình trạng sản phẩm và lý do trả hàng..." className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-3" /></section>
        <section className="space-y-2"><h2 className="font-semibold">3. Hình ảnh minh chứng (tối đa 5 ảnh, 5MB/ảnh)</h2><input type="file" accept="image/*" multiple onChange={(e) => setPhotos(Array.from(e.target.files || []).slice(0, 5))} className="block w-full text-sm" />{photos.length > 0 && <p className="text-xs text-on-surface-variant">Đã chọn {photos.length} ảnh.</p>}</section>
        <div className="rounded-xl bg-surface p-4 text-sm text-on-surface-variant">Sau khi gửi, cửa hàng sẽ kiểm tra và phản hồi yêu cầu. Đây là quy trình trả sản phẩm, <strong>không phát sinh hoàn tiền</strong>.</div>
        <button disabled={submitting} className="w-full sm:w-auto rounded-xl bg-primary text-on-primary px-6 py-3 font-semibold disabled:opacity-50">{submitting ? 'Đang gửi...' : 'Gửi yêu cầu trả hàng'}</button>
      </form>}
  </main>
}
