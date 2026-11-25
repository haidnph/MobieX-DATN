import { useEffect, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useToast } from '../../contexts/ToastContext'
import Spinner from '../../components/common/Spinner'

const STATUS = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', shipping_back: 'Đang gửi về', received: 'Đã nhận hàng', inspecting: 'Đang kiểm tra', completed: 'Hoàn tất', cancelled: 'Đã hủy' }

export default function AdminReturnPage() {
  const [rows, setRows] = useState([])
  const [selected, setSelected] = useState(null)
  const [status, setStatus] = useState('')
  const [keyword, setKeyword] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [note, setNote] = useState('')
  const [conditions, setConditions] = useState({})
  const { showToast } = useToast()

  const load = () => {
    setLoading(true)
    axiosClient.get(EP.ADMIN_RETURNS, { params: { status: status || undefined, q: keyword || undefined, per_page: 50 } })
      .then(({ data }) => { const list = data.data || []; setRows(list); if (selected) { const fresh = list.find((r) => r.id === selected.id); if (fresh) openDetail(fresh.id) } })
      .catch((e) => showToast(e.response?.data?.message || 'Không tải được yêu cầu trả hàng.', 'error'))
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [status])
  const openDetail = async (id) => {
    try { const { data } = await axiosClient.get(EP.ADMIN_RETURN_DETAIL(id)); const row = data.data; setSelected(row); setNote(row.admin_note || ''); const next = {}; (row.items || []).forEach((i) => { next[i.id] = i.inspection_condition || 'good' }); setConditions(next) }
    catch (e) { showToast(e.response?.data?.message || 'Không tải được chi tiết.', 'error') }
  }
  const act = async (url, payload = {}) => {
    if (!selected) return
    setBusy(true)
    try { const { data } = await axiosClient.patch(url, payload); setSelected(data.data); showToast(data.message || 'Đã cập nhật yêu cầu.', 'success'); load() }
    catch (e) { showToast(e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Không thể cập nhật.', 'error') }
    finally { setBusy(false) }
  }
  const reject = () => { const value = window.prompt('Nhập lý do từ chối (bắt buộc):', note); if (value?.trim()) act(EP.ADMIN_RETURN_REJECT(selected.id), { admin_note: value.trim() }) }
  const saveInspection = () => act(EP.ADMIN_RETURN_INSPECT(selected.id), { admin_note: note.trim(), items: selected.items.map((item) => ({ id: item.id, inspection_condition: conditions[item.id], inspection_note: '' })) })

  return <main className="p-4 md:p-7 space-y-5"><div><h1 className="text-2xl font-bold">Quản lý trả hàng</h1><p className="text-sm text-on-surface-variant">Tiếp nhận, duyệt, nhận và kiểm tra sản phẩm trả về. Không thực hiện hoàn tiền.</p></div>
    <div className="flex flex-col md:flex-row gap-3"><input value={keyword} onChange={(e) => setKeyword(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} placeholder="Tìm mã trả hàng, mã đơn, khách hàng..." className="flex-1 rounded-xl border border-outline-variant bg-surface px-3 py-2"/><button onClick={load} className="rounded-xl bg-primary text-on-primary px-4 py-2">Tìm kiếm</button><select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded-xl border border-outline-variant bg-surface px-3 py-2"><option value="">Tất cả trạng thái</option>{Object.entries(STATUS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></div>
    <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(360px,1.1fr)] gap-5 items-start"><section className="rounded-2xl border border-outline-variant/20 bg-surface-container overflow-hidden"><div className="p-4 border-b border-outline-variant/20 font-semibold">Danh sách yêu cầu ({rows.length})</div>{loading ? <div className="p-8 grid place-items-center"><Spinner /></div> : rows.length === 0 ? <p className="p-6 text-sm text-on-surface-variant">Không có yêu cầu phù hợp.</p> : <div className="divide-y divide-outline-variant/20">{rows.map((row) => <button key={row.id} onClick={() => openDetail(row.id)} className={`w-full text-left p-4 hover:bg-surface-container-high ${selected?.id === row.id ? 'bg-primary/10' : ''}`}><div className="flex justify-between gap-2"><strong className="text-sm">{row.return_code}</strong><span className="text-xs rounded-full bg-surface px-2 py-1">{STATUS[row.status]}</span></div><p className="text-sm mt-1">{row.user?.name || 'Khách hàng'} · Đơn #{row.order?.order_code || row.order_id}</p><p className="text-xs text-on-surface-variant mt-1">{row.reason}</p></button>)}</div>}</section>
    <section className="rounded-2xl border border-outline-variant/20 bg-surface-container p-5 space-y-4">{!selected ? <p className="text-sm text-on-surface-variant">Chọn một yêu cầu để xem chi tiết và xử lý.</p> : <>
      <div className="flex flex-wrap items-center justify-between gap-2"><div><h2 className="text-lg font-bold">{selected.return_code}</h2><p className="text-xs text-on-surface-variant">Đơn #{selected.order?.order_code || selected.order_id} · {selected.user?.name} · {selected.user?.email}</p></div><span className="rounded-full bg-surface px-3 py-1 text-xs font-semibold">{STATUS[selected.status]}</span></div>
      <div className="space-y-2"><h3 className="font-semibold text-sm">Sản phẩm yêu cầu trả</h3>{(selected.items || []).map((line) => <div key={line.id} className="rounded-xl bg-surface p-3 text-sm"><div className="flex justify-between gap-2"><span>{line.order_item?.product_name} {line.order_item?.variant_name || ''}</span><strong>× {line.quantity}</strong></div>{['received', 'inspecting'].includes(selected.status) && <div className="mt-2 flex items-center gap-2"><label className="text-xs">Tình trạng:</label><select value={conditions[line.id] || 'good'} onChange={(e) => setConditions((old) => ({ ...old, [line.id]: e.target.value }))} className="rounded-lg border border-outline-variant bg-surface-container px-2 py-1 text-xs"><option value="good">Tốt / nguyên vẹn</option><option value="opened">Đã mở hộp</option><option value="damaged">Hư hỏng</option><option value="missing_accessory">Thiếu phụ kiện</option><option value="other">Khác</option></select></div>}</div>)}</div>
      <div className="text-sm space-y-1"><p><strong>Lý do:</strong> {selected.reason}</p><p><strong>Mô tả:</strong> {selected.description || 'Không có'}</p><p><strong>Ghi chú khách:</strong> {selected.customer_note || 'Không có'}</p>{selected.shipping_carrier && <p><strong>Vận chuyển:</strong> {selected.shipping_carrier} · {selected.return_tracking_code}</p>}</div>
      {selected.attachments?.length > 0 && <div className="flex flex-wrap gap-2">{selected.attachments.map((path) => <a key={path} href={`/storage/${path}`} target="_blank" rel="noreferrer" className="text-primary text-xs underline">Xem ảnh minh chứng</a>)}</div>}
      {['received', 'inspecting'].includes(selected.status) && <div className="space-y-2"><label className="text-sm font-semibold">Kết quả kiểm tra / ghi chú admin</label><textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} className="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2" placeholder="Ghi nhận tình trạng sản phẩm..."/><button disabled={busy || !note.trim()} onClick={saveInspection} className="rounded-lg bg-primary text-on-primary px-4 py-2 text-sm disabled:opacity-50">Lưu kết quả kiểm tra</button></div>}
      <div className="flex flex-wrap gap-2 border-t border-outline-variant/20 pt-4">{selected.status === 'pending' && <><button disabled={busy} onClick={() => act(EP.ADMIN_RETURN_APPROVE(selected.id))} className="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm">Duyệt yêu cầu</button><button disabled={busy} onClick={reject} className="rounded-lg bg-red-600 text-white px-4 py-2 text-sm">Từ chối</button></>}{['approved', 'shipping_back'].includes(selected.status) && <button disabled={busy} onClick={() => act(EP.ADMIN_RETURN_RECEIVE(selected.id))} className="rounded-lg bg-primary text-on-primary px-4 py-2 text-sm">Xác nhận đã nhận hàng</button>}{selected.status === 'inspecting' && <button disabled={busy} onClick={() => act(EP.ADMIN_RETURN_COMPLETE(selected.id), { admin_note: note || 'Đã kiểm tra và hoàn tất trả hàng. Không hoàn tiền.' })} className="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm">Hoàn tất trả hàng</button>}</div>
      <div className="rounded-xl bg-surface p-3 text-xs text-on-surface-variant"><strong>Lịch sử xử lý</strong>{(selected.histories || []).map((history) => <p key={history.id} className="mt-2">{new Date(history.created_at).toLocaleString('vi-VN')} · {STATUS[history.status] || history.status} · {history.note}</p>)}</div>
    </>}</section></div>
  </main>
}
