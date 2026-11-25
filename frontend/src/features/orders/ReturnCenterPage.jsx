import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const STATUS = {
  pending: ['Chờ duyệt', 'bg-amber-100 text-amber-800'], approved: ['Đã duyệt', 'bg-blue-100 text-blue-800'], rejected: ['Từ chối', 'bg-red-100 text-red-800'],
  shipping_back: ['Đang gửi hàng về', 'bg-indigo-100 text-indigo-800'], received: ['Đã nhận hàng', 'bg-cyan-100 text-cyan-800'], inspecting: ['Đang kiểm tra', 'bg-violet-100 text-violet-800'], completed: ['Đã hoàn tất', 'bg-emerald-100 text-emerald-800'], cancelled: ['Đã hủy', 'bg-gray-100 text-gray-700'],
}

export default function ReturnCenterPage() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [shippingDrafts, setShippingDrafts] = useState({})
  const { showToast } = useToast()
  const load = () => axiosClient.get(EP.RETURNS).then(({ data }) => setRows(data.data || [])).catch(() => showToast('Không tải được lịch sử trả hàng.', 'error')).finally(() => setLoading(false))
  useEffect(() => { load() }, [])
  const updateDraft = (id, field, value) => setShippingDrafts((old) => ({ ...old, [id]: { ...(old[id] || {}), [field]: value } }))
  const submitShipping = async (id) => {
    const draft = shippingDrafts[id] || {}
    if (!draft.shipping_carrier?.trim() || !draft.return_tracking_code?.trim()) return showToast('Vui lòng nhập đơn vị vận chuyển và mã vận đơn.', 'warning')
    try { await axiosClient.patch(EP.RETURN_SHIPPING(id), draft); showToast('Đã cập nhật thông tin vận chuyển.', 'success'); load() }
    catch (error) { showToast(error.response?.data?.message || 'Không thể cập nhật vận đơn.', 'error') }
  }
  const cancel = async (id) => {
    if (!window.confirm('Bạn chắc chắn muốn hủy yêu cầu trả hàng này?')) return
    try { await axiosClient.patch(EP.RETURN_CANCEL(id)); showToast('Đã hủy yêu cầu.', 'success'); load() }
    catch (error) { showToast(error.response?.data?.message || 'Không thể hủy yêu cầu.', 'error') }
  }
  if (loading) return <div className="min-h-[50vh] grid place-items-center"><Spinner size="lg" /></div>
  return <main className="max-w-5xl mx-auto px-4 py-8 space-y-5"><div className="flex items-center justify-between"><div><h1 className="text-2xl font-bold">Lịch sử trả hàng</h1><p className="text-sm text-on-surface-variant">Theo dõi tiến trình các yêu cầu trả sản phẩm.</p></div><Link to="/orders" className="text-sm text-primary">Đơn hàng của tôi</Link></div>
    {rows.length === 0 ? <div className="rounded-2xl bg-surface-container p-8 text-center text-on-surface-variant">Bạn chưa có yêu cầu trả hàng nào.</div> : rows.map((row) => <article key={row.id} className="rounded-2xl border border-outline-variant/20 bg-surface-container p-5 space-y-3"><div className="flex flex-wrap items-center justify-between gap-2"><div><p className="font-semibold">{row.return_code}</p><p className="text-xs text-on-surface-variant">Đơn hàng #{row.order?.order_code || row.order_id} · {new Date(row.created_at).toLocaleString('vi-VN')}</p></div><span className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS[row.status]?.[1] || ''}`}>{STATUS[row.status]?.[0] || row.status}</span></div><p className="text-sm">Lý do: {row.reason}</p><p className="text-xs text-on-surface-variant">{(row.items || []).map((line) => `${line.order_item?.product_name || 'Sản phẩm'} × ${line.quantity}`).join(' · ')}</p>{row.admin_note && <p className="text-sm rounded-lg bg-surface p-3">Phản hồi cửa hàng: {row.admin_note}</p>}{row.status === 'approved' && <div className="grid sm:grid-cols-2 gap-2"><input value={shippingDrafts[row.id]?.shipping_carrier || ''} onChange={(e) => updateDraft(row.id, 'shipping_carrier', e.target.value)} placeholder="Đơn vị vận chuyển" className="rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm"/><input value={shippingDrafts[row.id]?.return_tracking_code || ''} onChange={(e) => updateDraft(row.id, 'return_tracking_code', e.target.value)} placeholder="Mã vận đơn" className="rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm"/><button onClick={() => submitShipping(row.id)} className="sm:col-span-2 justify-self-start rounded-lg bg-primary text-on-primary px-4 py-2 text-sm">Cập nhật vận đơn gửi hàng về</button></div>}{row.status === 'pending' && <button onClick={() => cancel(row.id)} className="text-sm font-semibold text-error">Hủy yêu cầu</button>}</article>)}
  </main>
}
