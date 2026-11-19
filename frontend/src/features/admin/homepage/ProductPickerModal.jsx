// src/features/admin/homepage/ProductPickerModal.jsx
// Interactive modal for searching and selecting products for featured homepage sections.

import { useEffect, useState } from 'react'
import axiosClient from '../../../api/axiosClient'
import EP from '../../../api/endpoints'
import Modal from '../../../components/common/Modal'
import Button from '../../../components/common/Button'
import Spinner from '../../../components/common/Spinner'
import { formatCurrencyVND } from '../../../utils/formatCurrencyVND'
import { resolveImageUrl } from '../../../utils/resolveImageUrl'

export default function ProductPickerModal({
  open = false,
  onClose,
  selectedIds = [],
  onConfirm,
}) {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState('')
  const [chosenIds, setChosenIds] = useState([])

  useEffect(() => {
    if (open) {
      setChosenIds([...selectedIds])
      fetchProducts('')
    }
  }, [open, selectedIds])

  const fetchProducts = (q) => {
    setLoading(true)
    const params = { per_page: 30 }
    if (q) params.keyword = q

    axiosClient
      .get(EP.PRODUCTS, { params })
      .then(({ data }) => {
        const list = data?.data?.data ?? data?.data ?? (Array.isArray(data) ? data : [])
        setProducts(list)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  const handleSearchSubmit = (e) => {
    e.preventDefault()
    fetchProducts(search.trim())
  }

  const toggleSelect = (id) => {
    setChosenIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    )
  }

  const handleApply = () => {
    onConfirm(chosenIds)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Chọn sản phẩm đưa lên Trang chủ"
      size="lg"
    >
      <div className="space-y-4">
        {/* Search Bar */}
        <form onSubmit={handleSearchSubmit} className="flex gap-2">
          <div className="relative flex-1">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-base">
              search
            </span>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Tìm theo tên sản phẩm..."
              className="w-full bg-surface-container-low text-on-surface rounded-xl pl-9 pr-3 py-2 text-xs border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <Button type="submit" variant="primary" size="sm">
            Tìm kiếm
          </Button>
        </form>

        {/* Selected count chip */}
        <div className="flex items-center justify-between text-xs text-on-surface-variant">
          <span>
            Đã chọn: <strong className="text-primary font-bold">{chosenIds.length}</strong> sản phẩm
          </span>
          {chosenIds.length > 0 && (
            <button
              type="button"
              onClick={() => setChosenIds([])}
              className="text-error hover:underline cursor-pointer"
            >
              Bỏ chọn tất cả
            </button>
          )}
        </div>

        {/* Products Grid */}
        <div className="max-h-96 overflow-y-auto space-y-2 pr-1 divide-y divide-outline-variant/10">
          {loading ? (
            <div className="flex flex-col items-center justify-center py-16 gap-2">
              <Spinner size="md" />
              <span className="text-xs text-on-surface-variant">Đang tải danh sách sản phẩm...</span>
            </div>
          ) : products.length === 0 ? (
            <div className="text-center py-12 text-on-surface-variant text-xs">
              Không tìm thấy sản phẩm nào
            </div>
          ) : (
            products.map((p) => {
              const isSelected = chosenIds.includes(p.id)
              const thumb = resolveImageUrl(
                p.images?.find((img) => img.is_primary)?.image_url ||
                p.thumbnail ||
                p.images?.[0]?.image_url
              )
              const price = p.min_price || p.base_price || 0

              return (
                <div
                  key={p.id}
                  onClick={() => toggleSelect(p.id)}
                  className={[
                    'flex items-center justify-between p-2.5 rounded-xl cursor-pointer transition-all',
                    isSelected
                      ? 'bg-primary/10 border border-primary/40'
                      : 'hover:bg-surface-container-high border border-transparent',
                  ].join(' ')}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={() => {}} // handled by parent onClick
                      className="rounded text-primary focus:ring-primary"
                    />
                    <div className="w-12 h-12 rounded-lg bg-surface-container-lowest border border-outline-variant/30 overflow-hidden flex items-center justify-center shrink-0">
                      {thumb ? (
                        <img src={thumb} alt={p.name} className="w-full h-full object-contain" />
                      ) : (
                        <span className="material-symbols-outlined text-outline text-base">smartphone</span>
                      )}
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs font-semibold text-on-surface truncate">{p.name}</p>
                      <div className="flex items-center gap-2 text-[11px] text-on-surface-variant mt-0.5">
                        <span className="text-primary font-bold">{formatCurrencyVND(Number(price))}</span>
                        {p.brand?.name && <span>· {p.brand.name}</span>}
                      </div>
                    </div>
                  </div>

                  <span
                    className={`material-symbols-outlined text-xl ${
                      isSelected ? 'text-primary' : 'text-outline-variant'
                    }`}
                  >
                    {isSelected ? 'check_circle' : 'radio_button_unchecked'}
                  </span>
                </div>
              )
            })
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex justify-end gap-2 pt-3 border-t border-outline-variant/10">
          <Button type="button" variant="ghost" onClick={onClose}>
            Hủy
          </Button>
          <Button type="button" variant="primary" onClick={handleApply}>
            Áp dụng ({chosenIds.length})
          </Button>
        </div>
      </div>
    </Modal>
  )
}
