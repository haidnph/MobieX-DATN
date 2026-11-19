// src/features/admin/homepage/SectionEditModal.jsx
// Modal for configuring individual section metadata and content settings.

import { useEffect, useState } from 'react'
import Modal from '../../../components/common/Modal'
import Input from '../../../components/common/Input'
import Button from '../../../components/common/Button'
import ProductPickerModal from './ProductPickerModal'

export default function SectionEditModal({
  open = false,
  onClose,
  section = null,
  allCategories = [],
  allBrands = [],
  onSave,
}) {
  const [form, setForm] = useState({
    title: '',
    subtitle: '',
    layout_type: 'grid',
    items_limit: 8,
    is_active: true,
    settings: {},
  })

  const [productPickerOpen, setProductPickerOpen] = useState(false)

  useEffect(() => {
    if (section) {
      setForm({
        title: section.title || '',
        subtitle: section.subtitle || '',
        layout_type: section.layout_type || 'grid',
        items_limit: section.items_limit ?? 8,
        is_active: Boolean(section.is_active),
        settings: section.settings ? { ...section.settings } : {},
      })
    }
  }, [section])

  if (!section) return null

  const handleSettingChange = (key, val) => {
    setForm((prev) => ({
      ...prev,
      settings: { ...prev.settings, [key]: val },
    }))
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    onSave(section.id, {
      title: form.title.trim(),
      subtitle: form.subtitle?.trim() || null,
      layout_type: form.layout_type,
      items_limit: Number(form.items_limit) || 8,
      is_active: form.is_active,
      settings: form.settings,
    })
    onClose()
  }

  return (
    <>
      <Modal
        open={open}
        onClose={onClose}
        title={`Cấu hình Section: ${section.title || section.section_key}`}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-5">
          <Input
            label="Tiêu đề hiển thị *"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            placeholder="Nhập tiêu đề section..."
            required
          />

          <div className="space-y-1">
            <label className="text-sm font-medium text-on-surface-variant">Mô tả phụ</label>
            <textarea
              rows={2}
              value={form.subtitle}
              onChange={(e) => setForm({ ...form, subtitle: e.target.value })}
              placeholder="Nhập slogan hoặc mô tả phụ..."
              className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-on-surface-variant">Bố cục hiển thị</label>
              <select
                value={form.layout_type}
                onChange={(e) => setForm({ ...form, layout_type: e.target.value })}
                className="w-full bg-surface-container-low text-on-surface rounded-xl px-4 py-2.5 text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option value="grid">Dạng lưới (Grid)</option>
                <option value="carousel">Thanh trượt (Carousel)</option>
                {section.section_type === 'banner_promo' && (
                  <option value="split">Bố cục phân tách (Split)</option>
                )}
                <option value="standard">Tiêu chuẩn (Standard)</option>
              </select>
            </div>

            <Input
              label="Số lượng hiển thị tối đa"
              type="number"
              min="1"
              max="50"
              value={form.items_limit}
              onChange={(e) => setForm({ ...form, items_limit: e.target.value })}
            />
          </div>

          {/* Section-specific Settings */}
          {section.section_type === 'products' && (
            <div className="p-4 rounded-xl bg-surface-container-high/50 border border-outline-variant/20 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold text-on-surface block">Danh sách sản phẩm</span>
                  <span className="text-[11px] text-on-surface-variant">
                    {form.settings?.product_ids?.length > 0
                      ? `Đang chọn ${form.settings.product_ids.length} sản phẩm chỉ định`
                      : 'Đang hiển thị tự động theo sản phẩm mới nhất'}
                  </span>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setProductPickerOpen(true)}
                >
                  <span className="material-symbols-outlined text-sm">tune</span>
                  Chọn sản phẩm
                </Button>
              </div>

              {form.settings?.product_ids?.length > 0 && (
                <button
                  type="button"
                  onClick={() => handleSettingChange('product_ids', [])}
                  className="text-xs text-error hover:underline cursor-pointer"
                >
                  Xóa danh sách chỉ định (hiển thị tự động)
                </button>
              )}
            </div>
          )}

          {section.section_type === 'categories' && (
            <div className="p-4 rounded-xl bg-surface-container-high/50 border border-outline-variant/20 space-y-3">
              <span className="text-xs font-bold text-on-surface block">Chọn danh mục đưa lên trang chủ</span>
              <div className="max-h-40 overflow-y-auto space-y-1.5 divide-y divide-outline-variant/10 text-xs">
                {allCategories.map((c) => {
                  const currentIds = form.settings?.category_ids || []
                  const isChecked = currentIds.includes(c.id)
                  return (
                    <label key={c.id} className="flex items-center gap-2 pt-1.5 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={isChecked}
                        onChange={(e) => {
                          const next = e.target.checked
                            ? [...currentIds, c.id]
                            : currentIds.filter((x) => x !== c.id)
                          handleSettingChange('category_ids', next)
                        }}
                        className="rounded text-primary focus:ring-primary"
                      />
                      <span>{c.name}</span>
                    </label>
                  )
                })}
              </div>
              <span className="text-[11px] text-on-surface-variant block">
                Nếu không chọn danh mục nào, hệ thống sẽ tự động hiển thị các danh mục nổi bật.
              </span>
            </div>
          )}

          {section.section_type === 'brands' && (
            <div className="p-4 rounded-xl bg-surface-container-high/50 border border-outline-variant/20 space-y-3">
              <span className="text-xs font-bold text-on-surface block">Chọn thương hiệu đối tác</span>
              <div className="max-h-40 overflow-y-auto space-y-1.5 divide-y divide-outline-variant/10 text-xs">
                {allBrands.map((b) => {
                  const currentIds = form.settings?.brand_ids || []
                  const isChecked = currentIds.includes(b.id)
                  return (
                    <label key={b.id} className="flex items-center gap-2 pt-1.5 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={isChecked}
                        onChange={(e) => {
                          const next = e.target.checked
                            ? [...currentIds, b.id]
                            : currentIds.filter((x) => x !== b.id)
                          handleSettingChange('brand_ids', next)
                        }}
                        className="rounded text-primary focus:ring-primary"
                      />
                      <span>{b.name}</span>
                    </label>
                  )
                })}
              </div>
            </div>
          )}

          {section.section_type === 'banner_promo' && (
            <div className="space-y-3 p-4 rounded-xl bg-surface-container-high/50 border border-outline-variant/20">
              <Input
                label="Huy hiệu / Badge"
                value={form.settings?.badge || ''}
                onChange={(e) => handleSettingChange('badge', e.target.value)}
                placeholder="Ví dụ: Ưu đãi đặc biệt"
              />
              <div className="grid grid-cols-2 gap-3">
                <Input
                  label="Nhãn nút CTA"
                  value={form.settings?.cta_label || ''}
                  onChange={(e) => handleSettingChange('cta_label', e.target.value)}
                  placeholder="Mua sắm ngay"
                />
                <Input
                  label="Liên kết điều hướng CTA"
                  value={form.settings?.cta_url || ''}
                  onChange={(e) => handleSettingChange('cta_url', e.target.value)}
                  placeholder="/products"
                />
              </div>
            </div>
          )}

          {/* Toggle Active */}
          <label className="flex items-center gap-2 text-sm text-on-surface cursor-pointer pt-2">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              className="rounded text-primary focus:ring-primary"
            />
            <span>Kích hoạt hiển thị section này trên trang chủ</span>
          </label>

          <div className="flex justify-end gap-2 pt-4 border-t border-outline-variant/10">
            <Button type="button" variant="ghost" onClick={onClose}>
              Hủy
            </Button>
            <Button type="submit" variant="primary">
              Lưu thay đổi
            </Button>
          </div>
        </form>
      </Modal>

      {/* Product Picker Modal */}
      <ProductPickerModal
        open={productPickerOpen}
        onClose={() => setProductPickerOpen(false)}
        selectedIds={form.settings?.product_ids || []}
        onConfirm={(ids) => handleSettingChange('product_ids', ids)}
      />
    </>
  )
}
