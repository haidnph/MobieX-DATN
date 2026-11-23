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

  const startCreate = () => {
    setEditingId(null)
    setOriginalItems('')
    setForm({ ...EMPTY_FORM, items: [{ product_variant_id: '', sale_price: '', quantity_limit: '' }] })
  }

  const startEdit = (sale) => {
    const items = (sale.items ?? []).map((item) => ({
      product_variant_id: String(item.product_variant_id),
      sale_price: String(item.sale_price),
      quantity_limit: item.quantity_limit == null ? '' : String(item.quantity_limit),
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
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-on-surface">Flash Sale</h1>
          <p className="text-sm text-on-surface-variant">Quản lý thời gian, phiên bản tham gia, giá và hạn mức bán.</p>
        </div>
        {canManage && <Button onClick={startCreate}>Tạo chiến dịch</Button>}
      </div>

      {canManage && form.items.length > 0 && (
        <form onSubmit={save} className="space-y-5 rounded-2xl border border-outline-variant/30 bg-surface-container p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-bold">{editingId ? 'Sửa chiến dịch' : 'Chiến dịch mới'}</h2>
            <button type="button" className="text-sm text-outline hover:text-error" onClick={() => { setForm(EMPTY_FORM); setEditingId(null) }}>Đóng</button>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <Input label="Tên chiến dịch" required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value, slug: current.slug || slugify(event.target.value) }))} />
            <Input label="Slug" required value={form.slug} onChange={(event) => setForm((current) => ({ ...current, slug: slugify(event.target.value) }))} />
            <Input label="Bắt đầu" type="datetime-local" required value={form.start_at} onChange={(event) => setForm((current) => ({ ...current, start_at: event.target.value }))} />
            <Input label="Kết thúc" type="datetime-local" required value={form.end_at} onChange={(event) => setForm((current) => ({ ...current, end_at: event.target.value }))} />
            <label className="flex flex-col gap-1 text-sm">Trạng thái
              <select className="rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2" value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}>
                {['draft', 'scheduled', 'active', 'ended', 'cancelled'].map((status) => <option key={status} value={status}>{status}</option>)}
              </select>
            </label>
            <Input label="Mô tả" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} />
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h3 className="font-semibold">Sản phẩm tham gia</h3>
              <Button type="button" variant="secondary" onClick={() => setForm((current) => ({ ...current, items: [...current.items, { product_variant_id: '', sale_price: '', quantity_limit: '' }] }))}>Thêm phiên bản</Button>
            </div>
            <Input label="Tìm phiên bản" value={variantSearch} onChange={(event) => setVariantSearch(event.target.value)} placeholder="Tên sản phẩm hoặc SKU" />
            {form.items.map((item, index) => (
              <div key={index} className="grid gap-3 rounded-xl border border-outline-variant/20 p-3 md:grid-cols-[2fr_1fr_1fr_auto]">
                <select required className="min-w-0 rounded-xl border border-outline-variant/40 bg-surface-container-low px-3 py-2" value={item.product_variant_id} onChange={(event) => updateItem(index, 'product_variant_id', event.target.value)}>
                  <option value="">Chọn phiên bản</option>
                  {filteredVariants.map((variant) => <option key={variant.id} value={variant.id}>{variant.product?.name} · {variant.option_signature || variant.sku} · {formatCurrencyVND(variant.price)}</option>)}
                </select>
                <Input label="Giá sale" type="number" min="1" required value={item.sale_price} onChange={(event) => updateItem(index, 'sale_price', event.target.value)} />
                <Input label="Giới hạn (trống = tồn kho)" type="number" min="1" value={item.quantity_limit} onChange={(event) => updateItem(index, 'quantity_limit', event.target.value)} />
                <button type="button" className="self-end rounded-lg px-3 py-2 text-error hover:bg-error/10" aria-label="Xóa phiên bản" onClick={() => setForm((current) => ({ ...current, items: current.items.filter((_, itemIndex) => itemIndex !== index) }))}>Xóa</button>
              </div>
            ))}
          </div>
          <div className="flex justify-end"><Button type="submit" loading={saving}>{editingId ? 'Lưu thay đổi' : 'Tạo chiến dịch'}</Button></div>
        </form>
      )}

      <div className="overflow-hidden rounded-2xl border border-outline-variant/20 bg-surface-container">
        {loading ? <div className="p-10 text-center"><Spinner /></div> : sales.length === 0 ? (
          <div className="p-10 text-center text-on-surface-variant">Chưa có chiến dịch Flash Sale.</div>
        ) : (
          <div className="divide-y divide-outline-variant/20">
            {sales.map((sale) => (
              <article key={sale.id} className="flex flex-col justify-between gap-4 p-4 md:flex-row md:items-center">
                <div className="min-w-0">
                  <h3 className="font-bold">{sale.name}</h3>
                  <p className="text-xs text-on-surface-variant">{sale.start_at} → {sale.end_at} · {sale.items_count ?? sale.items?.length ?? 0} phiên bản · {sale.status}</p>
                  <p className="mt-1 text-xs text-on-surface-variant">{(sale.items ?? []).map((item) => `${item.variant?.product?.name ?? item.variant?.sku}: ${formatCurrencyVND(item.sale_price)} (${item.quantity_sold} bán / ${item.quantity_reserved} giữ)`).join(' · ')}</p>
                </div>
                {canManage && <div className="flex shrink-0 gap-2">
                  <Button variant="secondary" onClick={() => startEdit(sale)}>Sửa</Button>
                  <Button variant="danger" onClick={() => removeSale(sale)}>Xóa / hủy</Button>
                </div>}
              </article>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
