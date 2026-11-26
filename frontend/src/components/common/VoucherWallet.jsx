import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND as money } from '../../utils/formatCurrencyVND'

export default function VoucherWallet({ home = false, onSelect, title, subtitle, couponIds = [], limit = 6 }) {
  const { user } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [open, setOpen] = useState(home)
  const [busy, setBusy] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [walletTab, setWalletTab] = useState('available')
  const load = async () => {
    setLoading(true); setError('')
    try { const { data } = await axiosClient.get(home ? '/vouchers/public' : '/vouchers/wallet', home ? { params: { section: 1, coupon_ids: couponIds } } : undefined); setRows((data.data ?? []).slice(0, limit)) }
    catch(e) { setError(e.message) } finally { setLoading(false) }
  }
  useEffect(() => { if (open && (home || user)) load(); else setRows([]) }, [open, home, user?.id, couponIds.join(','), limit])
  const visibleRows = home ? rows : rows.filter((row) => row.wallet_status === walletTab)
  const collect = async (v) => {
    if (!user) { navigate('/login', {state:{from:{pathname:'/'}}}); return }
    setBusy(v.id)
    try { await axiosClient.post(`/vouchers/${v.id}/collect`); setRows(old=>old.map(x=>x.id===v.id?{...x,saved:true}:x)); showToast('Đã lưu Voucher','success') }
    catch(e) { showToast(e.message,'error') } finally { setBusy(null) }
  }
  if (!home && !user) return null
  return <section className="space-y-3">
    {home ? <div><h2 className="text-xl font-bold text-on-surface">{title || 'Săn Voucher ưu đãi'}</h2>{subtitle && <p className="mt-1 text-sm text-on-surface-variant">{subtitle}</p>}</div> : <button type="button" onClick={()=>setOpen(!open)} className="text-primary font-semibold text-sm flex items-center gap-2"><span className="material-symbols-outlined">confirmation_number</span>{open?'Đóng ví Voucher':'Chọn Voucher đã lưu'}</button>}
    {!home && open && <div className="flex flex-wrap gap-2">{[['available','Còn hạn'],['used','Đã sử dụng'],['expired','Hết hạn']].map(([key,label])=><button key={key} type="button" onClick={()=>setWalletTab(key)} className={`rounded-full px-3 py-1.5 text-xs font-semibold ${walletTab===key?'bg-primary text-on-primary':'bg-surface-container text-on-surface-variant'}`}>{label}</button>)}</div>}
    {open && <div className={home?'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3':'space-y-2 max-h-80 overflow-y-auto'}>
      {loading && <p className="text-sm">Đang tải Voucher...</p>}
      {error && <p role="alert" className="text-error text-sm">{error} <button type="button" onClick={load}>Thử lại</button></p>}
      {!loading && !error && visibleRows.length===0 && <p className="text-sm text-on-surface-variant">{home?'Chưa có voucher khả dụng.':'Không có voucher trong mục này.'}</p>}
      {visibleRows.map(v=><article key={v.id} className="rounded-xl bg-surface-container border border-outline-variant/30 p-3 space-y-2">
        <p className="font-bold text-primary">{v.name || v.code} <span className="text-xs text-on-surface-variant">{v.code}</span></p>
        <p className="text-sm font-semibold">Giảm {v.discount_type==='percent'?`${Number(v.discount_value)}%`:money(v.discount_value)}{v.max_discount!=null?` · tối đa ${money(v.max_discount)}`:''}</p>
        {v.description && <p className="text-xs text-on-surface-variant">{v.description}</p>}
        <p className="text-xs">Đơn từ {money(v.min_order_amount)}{v.max_discount!=null?` · Tối đa ${money(v.max_discount)}`:''}</p>
        <p className="text-xs">HSD: {v.end_at?new Date(v.end_at).toLocaleDateString('vi-VN'):'Không giới hạn'} · {v.remaining==null?'Không giới hạn lượt':`Còn ${v.remaining} lượt`}</p>
        <details className="text-xs"><summary className="cursor-pointer text-primary">Điều kiện sử dụng</summary><p>{v.products?.length?`Sản phẩm: ${v.products.map(p=>p.name).join(', ')}. `:''}{v.categories?.length?`Danh mục: ${v.categories.map(p=>p.name).join(', ')}. `:''}{v.brands?.length?`Thương hiệu: ${v.brands.map(p=>p.name).join(', ')}. `:''}{v.products?.length||v.categories?.length||v.brands?.length?'':'Toàn bộ sản phẩm. '}{v.min_order_amount>0?`Đơn tối thiểu ${money(v.min_order_amount)}. `:''}Tối đa {v.max_uses_per_customer??1} lượt/khách.</p></details>
        {v.reason && <p className="text-xs text-error">{v.reason}</p>}
        {home?<button type="button" disabled={v.saved||!!v.reason||busy!==null} onClick={()=>collect(v)} className="rounded-lg bg-primary text-on-primary px-3 py-2 text-xs font-bold disabled:opacity-50">{v.saved?'Đã lưu':busy===v.id?'Đang lưu':'Lưu mã'}</button>:<button type="button" disabled={!!v.reason} onClick={()=>{onSelect(v.code);setOpen(false)}} className="rounded-lg bg-primary text-on-primary px-3 py-2 text-xs disabled:opacity-40">Chọn mã</button>}
      </article>)}
    </div>}
  </section>
}
