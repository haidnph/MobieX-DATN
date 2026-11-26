import { useEffect, useMemo, useState } from 'react'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import Button from '../../components/common/Button'
import Input from '../../components/common/Input'
import Spinner from '../../components/common/Spinner'

const EMPTY_FORM = {
  name: '',
  slug: '',
  description: '',
  start_at: '',
  end_at: '',
  status: 'draft',
  items: [],
}

const STATUS_LABELS = {
  draft: 'Bản nháp',
  scheduled: 'Sắp diễn ra',
  active: 'Đang diễn ra',
  ended: 'Đã kết thúc',
  cancelled: 'Đã hủy',
}

function formatCampaignDate(value) {
  if (!value) return 'Chưa thiết lập thời gian'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function toDateTimeLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return offsetDate.toISOString().slice(0, 16)
}

function toIsoDate(value) {
  return value ? new Date(value).toISOString() : undefined
}

function slugify(value) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

export default function AdminFlashSalesPage() {
  const { hasPermission, isAdmin } = useAuth()
  const canManage = isAdmin() || hasPermission('flash_sales.manage')
  const { showToast } = useToast()
  const [sales, setSales] = useState([])
  const [variants, setVariants] = useState([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState(EMPTY_FORM)
  const [editingId, setEditingId] = useState(null)
  const [originalItems, setOriginalItems] = useState('')
  const [variantSearch, setVariantSearch] = useState('')

  const loadSales = () => axiosClient.get(EP.ADMIN_FLASH_SALES)
    .then(({ data }) => setSales(data?.data?.data ?? data?.data ?? []))
    .catch((error) => showToast(error.message || 'Không tải được chiến dịch Flash Sale.', 'error'))
    .finally(() => setLoading(false))

  useEffect(() => {
    loadSales()
    if (canManage) {
      axiosClient.get(EP.ADMIN_FLASH_SALE_VARIANTS, { params: { per_page: 100 } })
        .then(({ data }) => setVariants(data?.data?.data ?? []))
        .catch((error) => showToast(error.message || 'Không tải được danh sách phiên bản sản phẩm.', 'error'))
    }
  }, [])

  const filteredVariants = useMemo(() => {
    const needle = variantSearch.trim().toLocaleLowerCase()
    if (!needle) return variants
    return variants.filter((variant) =>
      `${variant.product?.name ?? ''} ${variant.sku ?? ''} ${variant.option_signature ?? ''}`.toLocaleLowerCase().includes(needle),
    )
  }, [variantSearch, variants])

  const activeCount = sales.filter((sale) => sale.status === 'active').length
  const upcomingCount = sales.filter((sale) => sale.status === 'scheduled').length

  const startCreate = () => {
    setEditingId(null)
    setOriginalItems('')
    setForm({ ...EMPTY_FORM, items: [{ product_variant_id: '', sale_price: '', quantity_limit: '', max_per_customer: '1' }] })
  }

  const startEdit = (sale) => {
    const items = (sale.items ?? []).map((item) => ({
      product_variant_id: String(item.product_variant_id),
      sale_price: String(item.sale_price),
      quantity_limit: item.quantity_limit == null ? '' : String(item.quantity_limit),
      max_per_customer: item.max_per_customer == null ? '' : String(item.max_per_customer),
    }))
    setEditingId(sale.id)
    setOriginalItems(JSON.stringify(items))
    setForm({
      name: sale.name,
      slug: sale.slug,
      description: sale.description ?? '',
      start_at: toDateTimeLocal(sale.start_at),
      end_at: toDateTimeLocal(sale.end_at),
      status: sale.status,
      items,
    })
  }

  const updateItem = (index, field, value) => {
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, [field]: value } : item),
    }))
  }

  const save = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const normalizedItems = form.items.map((item) => ({
        product_variant_id: Number(item.product_variant_id),
        sale_price: Number(item.sale_price),
        quantity_limit: item.quantity_limit === '' ? null : Number(item.quantity_limit),
        max_per_customer: item.max_per_customer === '' ? null : Number(item.max_per_customer),
      }))
      const payload = {
        name: form.name,
        slug: form.slug || slugify(form.name),
        description: form.description || null,
        start_at: toIsoDate(form.start_at),
        end_at: toIsoDate(form.end_at),
        status: form.status,
      }
      if (!editingId || JSON.stringify(form.items) !== originalItems) payload.items = normalizedItems

      if (editingId) {
        await axiosClient.patch(EP.ADMIN_FLASH_SALE_DETAIL(editingId), payload)
        showToast('Đã cập nhật chiến dịch Flash Sale.', 'success')
      } else {
        await axiosClient.post(EP.ADMIN_FLASH_SALES, payload)
        showToast('Đã tạo chiến dịch Flash Sale.', 'success')
      }
      setForm(EMPTY_FORM)
      setEditingId(null)
      await loadSales()
    } catch (error) {
      showToast(error.message || 'Không thể lưu chiến dịch Flash Sale.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const removeSale = async (sale) => {
    if (!window.confirm(`Xóa hoặc hủy chiến dịch “${sale.name}”?`)) return
    try {
      await axiosClient.delete(EP.ADMIN_FLASH_SALE_DETAIL(sale.id))
      showToast('Đã xử lý chiến dịch Flash Sale.', 'success')
      await loadSales()
    } catch (error) {
      showToast(error.message || 'Không thể xóa chiến dịch.', 'error')
    }
  }

  return (
    <div className="space-y-7 pb-8">
      <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div className="space-y-2">
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-blue-700 dark:text-primary">Khuyến mãi / Quản lý</p>
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-slate-950 dark:text-on-surface">Flash Sale</h1>
            <p className="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500 dark:text-on-surface-variant">Lên lịch chương trình, chọn phiên bản sản phẩm và theo dõi số lượng bán.</p>
          </div>
        </div>
        {canManage && <Button onClick={startCreate} className="rounded-xl bg-blue-700 px-5 py-3 text-white shadow-sm hover:bg-blue-800 dark:bg-primary-container dark:text-on-primary-container dark:hover:bg-primary">Tạo chiến dịch</Button>}
      </div>

      <section className="grid gap-3 sm:grid-cols-3" aria-label="Tóm tắt chiến dịch">
        {[
          { label: 'Tổng chiến dịch', value: sales.length, note: 'Tất cả trạng thái', tone: 'text-slate-900 dark:text-on-surface' },
          { label: 'Đang diễn ra', value: activeCount, note: 'Đang hiển thị cho khách', tone: 'text-emerald-700 dark:text-secondary' },
          { label: 'Sắp diễn ra', value: upcomingCount, note: 'Đã lên lịch bắt đầu', tone: 'text-blue-700 dark:text-primary' },
        ].map((stat) => (
          <article key={stat.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-outline-variant/20 dark:bg-surface-container">
            <p className="text-xs font-semibold text-slate-500 dark:text-on-surface-variant">{stat.label}</p>
            <div className="mt-3 flex items-end justify-between gap-3">
              <span className={`text-3xl font-bold tracking-tight ${stat.tone}`}>{loading ? '—' : stat.value}</span>
              <span className="pb-1 text-right text-[11px] text-slate-400 dark:text-outline">{stat.note}</span>
            </div>
          </article>
        ))}
      </section>

      {canManage && form.items.length > 0 && (
        <form onSubmit={save} className="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-outline-variant/25 dark:bg-surface-container sm:p-6">
          <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-outline-variant/20">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-700 dark:text-primary">Thiết lập</p>
              <h2 className="mt-1 text-lg font-bold text-slate-900 dark:text-on-surface">{editingId ? 'Chỉnh sửa chiến dịch' : 'Chiến dịch mới'}</h2>
            </div>
            <button type="button" className="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-on-surface-variant dark:hover:bg-surface-container-high" onClick={() => { setForm(EMPTY_FORM); setEditingId(null) }}>Đóng</button>
          </div>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Input label="Tên chiến dịch" required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value, slug: current.slug || slugify(event.target.value) }))} />
            <Input label="Slug" required value={form.slug} onChange={(event) => setForm((current) => ({ ...current, slug: slugify(event.target.value) }))} />
            <Input label="Bắt đầu" type="datetime-local" required value={form.start_at} onChange={(event) => setForm((current) => ({ ...current, start_at: event.target.value }))} />
            <Input label="Kết thúc" type="datetime-local" required value={form.end_at} onChange={(event) => setForm((current) => ({ ...current, end_at: event.target.value }))} />
            <label className="flex flex-col gap-1 text-sm">Trạng thái
              <select className="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2" value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}>
                {['draft', 'scheduled', 'active', 'ended', 'cancelled'].map((status) => <option key={status} value={status}>{STATUS_LABELS[status]}</option>)}
              </select>
            </label>
            <Input label="Mô tả" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} />
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <div><h3 className="font-semibold text-slate-900 dark:text-on-surface">Sản phẩm tham gia</h3><p className="mt-1 text-xs text-slate-500 dark:text-on-surface-variant">Chọn phiên bản và đặt giá, tồn lượng cho chương trình.</p></div>
              <Button type="button" variant="secondary" className="rounded-lg" onClick={() => setForm((current) => ({ ...current, items: [...current.items, { product_variant_id: '', sale_price: '', quantity_limit: '', max_per_customer: '1' }] }))}>Thêm phiên bản</Button>
            </div>
            <Input label="Tìm phiên bản" value={variantSearch} onChange={(event) => setVariantSearch(event.target.value)} placeholder="Tên sản phẩm hoặc SKU" />
            {form.items.map((item, index) => (
              <div key={index} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-outline-variant/20 dark:bg-surface-container-low md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                <select required className="min-w-0 rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2" value={item.product_variant_id} onChange={(event) => updateItem(index, 'product_variant_id', event.target.value)}>
                  <option value="">Chọn phiên bản</option>
                  {filteredVariants.map((variant) => <option key={variant.id} value={variant.id}>{variant.product?.name} · {variant.option_signature || variant.sku} · {formatCurrencyVND(variant.price)}</option>)}
                </select>
                <Input label="Giá sale" type="number" min="1" required value={item.sale_price} onChange={(event) => updateItem(index, 'sale_price', event.target.value)} />
                <Input label="Giới hạn (trống = tồn kho)" type="number" min="1" value={item.quantity_limit} onChange={(event) => updateItem(index, 'quantity_limit', event.target.value)} />
                <Input label="Tối đa mỗi khách (trống = không giới hạn)" type="number" min="1" value={item.max_per_customer} onChange={(event) => updateItem(index, 'max_per_customer', event.target.value)} />
                <button type="button" className="self-end rounded-lg px-3 py-2 text-error hover:bg-error/10" aria-label="Xóa phiên bản" onClick={() => setForm((current) => ({ ...current, items: current.items.filter((_, itemIndex) => itemIndex !== index) }))}>Xóa</button>
              </div>
            ))}
          </div>
          <div className="flex justify-end border-t border-slate-100 pt-4 dark:border-outline-variant/20"><Button type="submit" loading={saving} className="rounded-xl bg-blue-700 px-5 text-white shadow-sm hover:bg-blue-800 dark:bg-primary-container dark:text-on-primary-container">{editingId ? 'Lưu thay đổi' : 'Tạo chiến dịch'}</Button></div>
        </form>
      )}

      <section className="space-y-4">
        <div className="flex items-end justify-between gap-3">
          <div>
            <h2 className="text-lg font-bold text-slate-900 dark:text-on-surface">Danh sách chiến dịch</h2>
            <p className="mt-1 text-xs text-slate-500 dark:text-on-surface-variant">Theo dõi lịch chạy và hiệu quả từng chương trình.</p>
          </div>
          {!loading && <span className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-surface-container-high dark:text-on-surface-variant">{sales.length} chiến dịch</span>}
        </div>
        {loading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center dark:border-outline-variant/20 dark:bg-surface-container"><Spinner /></div>
        ) : sales.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm dark:border-outline-variant/30 dark:bg-surface-container">
            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-blue-700 dark:text-primary">Chưa có dữ liệu</p>
            <h3 className="mt-3 text-xl font-bold text-slate-900 dark:text-on-surface">Chưa có chiến dịch Flash Sale</h3>
            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-on-surface-variant">Tạo chương trình đầu tiên để thiết lập thời gian, sản phẩm và giá ưu đãi.</p>
            {canManage && <Button onClick={startCreate} className="mt-5 rounded-xl bg-blue-700 text-white shadow-sm hover:bg-blue-800 dark:bg-primary-container dark:text-on-primary-container">Tạo chiến dịch đầu tiên</Button>}
          </div>
        ) : (
          <div className="grid gap-3 xl:grid-cols-2">
            {sales.map((sale) => (
              <article key={sale.id} className="flex min-w-0 flex-col justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-outline-variant/20 dark:bg-surface-container">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h3 className="truncate text-base font-bold text-slate-900 dark:text-on-surface">{sale.name}</h3>
                      <p className="mt-1 text-xs text-slate-500 dark:text-on-surface-variant">{sale.slug}</p>
                    </div>
                    <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${sale.status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-secondary-container/15 dark:text-secondary' : sale.status === 'scheduled' ? 'bg-blue-50 text-blue-700 dark:bg-primary-container/15 dark:text-primary' : 'bg-slate-100 text-slate-600 dark:bg-surface-container-high dark:text-on-surface-variant'}`}>
                      {STATUS_LABELS[sale.status] ?? sale.status}
                    </span>
                  </div>
                  <div className="mt-4 grid gap-2 rounded-xl bg-slate-50 px-3.5 py-3 text-xs text-slate-600 dark:bg-surface-container-low dark:text-on-surface-variant sm:grid-cols-2">
                    <p><span className="font-semibold text-slate-800 dark:text-on-surface">Bắt đầu:</span> {formatCampaignDate(sale.start_at)}</p>
                    <p><span className="font-semibold text-slate-800 dark:text-on-surface">Kết thúc:</span> {formatCampaignDate(sale.end_at)}</p>
                  </div>
                  <p className="mt-3 text-xs font-medium text-slate-500 dark:text-on-surface-variant">{sale.items_count ?? sale.items?.length ?? 0} phiên bản tham gia</p>
                  {(sale.items ?? []).length > 0 && (
                    <div className="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-outline-variant/20">
                      {(sale.items ?? []).slice(0, 3).map((item) => (
                        <div key={item.id ?? item.product_variant_id} className="flex items-center justify-between gap-3 text-xs">
                          <span className="min-w-0 truncate text-slate-700 dark:text-on-surface">{item.variant?.product?.name ?? item.variant?.sku ?? 'Phiên bản sản phẩm'}</span>
                          <span className="shrink-0 font-semibold text-slate-900 dark:text-on-surface">{formatCurrencyVND(item.sale_price)}</span>
                        </div>
                      ))}
                      {(sale.items ?? []).length > 3 && <p className="text-[11px] text-slate-400">và {(sale.items ?? []).length - 3} phiên bản khác</p>}
                    </div>
                  )}
                </div>
                {canManage && <div className="flex shrink-0 gap-2 border-t border-slate-100 pt-4 dark:border-outline-variant/20">
                  <Button variant="secondary" className="rounded-lg" onClick={() => startEdit(sale)}>Chỉnh sửa</Button>
                  <Button variant="danger" className="rounded-lg" onClick={() => removeSale(sale)}>Xóa / hủy</Button>
                </div>}
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
