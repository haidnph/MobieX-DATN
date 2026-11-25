import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND as money } from '../../utils/formatCurrencyVND'

export default function VoucherWallet({ home = false, onSelect }) {
  const { user } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [open, setOpen] = useState(home)
  const [busy, setBusy] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const load = async () => {
    setLoading(true); setError('')
    try { const { data } = await axiosClient.get(home ? '/vouchers/public' : '/vouchers/wallet'); setRows(data.data ?? []) }
    catch(e) { setError(e.message) } finally { setLoading(false) }
  }
  useEffect(() => { if (open && (home || user)) load(); else setRows([]) }, [open, home, user?.id])
  const collect = async (v) => {
    if (!user) { navigate('/login', {state:{from:{pathname:'/'}}}); return }
    setBusy(v.id)
    try { await axiosClient.post(`/vouchers/${v.id}/collect`); setRows(old=>old.map(x=>x.id===v.id?{...x,saved:true}:x)); showToast('Đã lưu Voucher','success') }
    catch(e) { showToast(e.message,'error') } finally { setBusy(null) }
  }
  if (!home && !user) return null
  return <section className="space-y-3">
    {home ? <h2 className="text-xl font-bold text-on-surface">Săn Voucher ưu đãi</h2> : <button type="button" onClick={()=>setOpen(!open)} className="text-primary font-semibold text-sm flex items-center gap-2"><span className="material-symbols-outlined">confirmation_number</span>{open?'Đóng ví Voucher':'Chọn Voucher đã lưu'}</button>}
    {open && <div className={home?'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3':'space-y-2 max-h-80 overflow-y-auto'}>
      {loading && <p className="text-sm">Đang tải Voucher...</p>}
      {error && <p role="alert" className="text-error text-sm">{error} <button type="button" onClick={load}>Thử lại</button></p>}
      {!loading && !error && rows.length===0 && <p className="text-sm text-on-surface-variant">Chưa có Voucher.</p>}
      {rows.map(v=><article key={v.id} className="rounded-xl bg-surface-container border border-outline-variant/30 p-3 space-y-2">
        <p className="font-bold text-primary">{v.discount_type==='percent'?`${Number(v.discount_value)}%`:money(v.discount_value)} <span className="text-xs text-on-surface-variant">{v.code}</span></p>
        <p className="text-xs">Đơn từ {money(v.min_order_amount)}{v.max_discount!=null?` · Tối đa ${money(v.max_discount)}`:''}</p>
        <p className="text-xs">HSD: {v.end_at?new Date(v.end_at).toLocaleDateString('vi-VN'):'Không giới hạn'} · {v.remaining==null?'Không giới hạn lượt':`Còn ${v.remaining} lượt`}</p>
        <details className="text-xs"><summary className="cursor-pointer text-primary">Điều kiện sử dụng</summary><p>{v.products?.length?v.products.map(p=>p.name).join(', '):'Toàn bộ sản phẩm phù hợp'}. Không kết hợp Flash Sale. Tối đa {v.max_uses_per_customer??1} lượt/khách.</p></details>
        {v.reason && <p className="text-xs text-error">{v.reason}</p>}
        {home?<button type="button" disabled={v.saved||!!v.reason||busy!==null} onClick={()=>collect(v)} className="rounded-lg bg-primary text-on-primary px-3 py-2 text-xs font-bold disabled:opacity-50">{v.saved?'Đã lưu':busy===v.id?'Đang lưu':'Lưu mã'}</button>:<button type="button" disabled={!!v.reason} onClick={()=>{onSelect(v.code);setOpen(false)}} className="rounded-lg bg-primary text-on-primary px-3 py-2 text-xs disabled:opacity-40">Chọn mã</button>}
      </article>)}
    </div>}
  </section>
}
