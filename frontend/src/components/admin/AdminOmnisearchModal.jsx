// src/components/admin/AdminOmnisearchModal.jsx
// Enterprise Command Palette (Ctrl+K) for Admin: instant search across orders, products, users, coupons, and quick actions.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Spinner from '../common/Spinner'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'

export default function AdminOmnisearchModal({ open, onClose }) {
  const navigate = useNavigate()
  const inputRef = useRef(null)
  const listRef = useRef(null)

  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState({ actions: [], products: [], orders: [], users: [], categories: [], coupons: [] })
  const [selectedIndex, setSelectedIndex] = useState(0)

  // ── Fetch results ──────────────────────────────────────────────────────────
  const fetchResults = useCallback(async (q) => {
    setLoading(true)
    try {
      const { data: res } = await axiosClient.get(EP.ADMIN_OMNISEARCH, { params: { q: q.trim() } })
      setData(res.results || { actions: [], products: [], orders: [], users: [], categories: [], coupons: [] })
      setSelectedIndex(0)
    } catch {
      // Fallback
    } finally {
      setLoading(false)
    }
  }, [])

  // Debounced search
  useEffect(() => {
    if (!open) return
    const timer = setTimeout(() => {
      fetchResults(query)
    }, 180)
    return () => clearTimeout(timer)
  }, [query, open, fetchResults])

  // Focus input when opened
  useEffect(() => {
    if (open) {
      setTimeout(() => inputRef.current?.focus(), 50)
      setQuery('')
      fetchResults('')
    }
  }, [open, fetchResults])

  // Flattened items list for seamless keyboard navigation
  const flatItems = useMemo(() => {
    const list = []
    if (data.actions?.length) {
      data.actions.forEach((item) => list.push({ ...item, groupName: 'Thao tác nhanh' }))
    }
    if (data.orders?.length) {
      data.orders.forEach((item) => list.push({ ...item, groupName: 'Đơn hàng' }))
    }
    if (data.products?.length) {
      data.products.forEach((item) => list.push({ ...item, groupName: 'Sản phẩm' }))
    }
    if (data.users?.length) {
      data.users.forEach((item) => list.push({ ...item, groupName: 'Khách hàng & Người dùng' }))
    }
    if (data.categories?.length) {
      data.categories.forEach((item) => list.push({ ...item, groupName: 'Danh mục' }))
    }
    if (data.coupons?.length) {
      data.coupons.forEach((item) => list.push({ ...item, groupName: 'Mã giảm giá' }))
    }
    return list
  }, [data])

  // Handle item selection / click
  const handleSelect = (item) => {
    if (!item?.url) return
    onClose()
    navigate(item.url)
  }

  // Keyboard navigation inside modal
  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      e.preventDefault()
      onClose()
    } else if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedIndex((prev) => (flatItems.length ? (prev + 1) % flatItems.length : 0))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedIndex((prev) => (flatItems.length ? (prev - 1 + flatItems.length) % flatItems.length : 0))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (flatItems[selectedIndex]) {
        handleSelect(flatItems[selectedIndex])
      }
    }
  }

  // Scroll active item into view
  useEffect(() => {
    if (!listRef.current) return
    const activeEl = listRef.current.querySelector('[data-active="true"]')
    if (activeEl) {
      activeEl.scrollIntoView({ block: 'nearest' })
    }
  }, [selectedIndex])

  if (!open) return null

  let runningIndex = 0

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-12 sm:pt-20 p-4 bg-scrim/60 backdrop-blur-xs animate-in fade-in duration-150"
      onClick={onClose}
    >
      <div
        className="w-full max-w-2xl bg-surface-container rounded-2xl shadow-2xl border border-outline-variant/30 flex flex-col max-h-[80vh] overflow-hidden animate-in zoom-in-95 duration-150"
        onClick={(e) => e.stopPropagation()}
        onKeyDown={handleKeyDown}
      >
        {/* Search Input Bar */}
        <div className="flex items-center gap-3 px-4 py-3.5 border-b border-outline-variant/20 bg-surface-container-high/40">
          <span className="material-symbols-outlined text-primary text-2xl shrink-0">
            {loading ? 'progress_activity' : 'search'}
          </span>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Tìm đơn hàng (mã/sđt), sản phẩm, khách hàng, thao tác nhanh..."
            className="flex-1 bg-transparent text-on-surface placeholder:text-outline text-base focus:outline-none"
          />
          {query && (
            <button
              onClick={() => setQuery('')}
              className="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-lg">close</span>
            </button>
          )}
          <kbd className="hidden sm:inline-flex items-center gap-0.5 px-2 py-1 rounded bg-surface-container-highest text-[10px] font-mono text-on-surface-variant border border-outline-variant/30">
            ESC
          </kbd>
        </div>

        {/* Results List */}
        <div ref={listRef} className="flex-1 overflow-y-auto p-2 divide-y divide-outline-variant/10">
          {flatItems.length === 0 ? (
            <div className="py-14 text-center">
              {loading ? (
                <div className="flex flex-col items-center justify-center gap-2">
                  <Spinner size="md" />
                  <span className="text-xs text-on-surface-variant">Đang tìm kiếm trên hệ thống...</span>
                </div>
              ) : (
                <div className="space-y-1 text-on-surface-variant">
                  <span className="material-symbols-outlined text-4xl opacity-40">manage_search</span>
                  <p className="text-sm font-medium">Không tìm thấy kết quả nào cho "{query}"</p>
                  <p className="text-xs text-outline">Thử tìm theo mã đơn, số điện thoại, tên sản phẩm hoặc email.</p>
                </div>
              )}
            </div>
          ) : (
            <>
              {/* Render by sections */}
              {[
                { title: 'Thao tác nhanh', icon: 'bolt', items: data.actions },
                { title: 'Đơn hàng', icon: 'receipt_long', items: data.orders },
                { title: 'Sản phẩm', icon: 'inventory_2', items: data.products },
                { title: 'Khách hàng & Người dùng', icon: 'group', items: data.users },
                { title: 'Danh mục', icon: 'category', items: data.categories },
                { title: 'Mã giảm giá', icon: 'local_offer', items: data.coupons },
              ]
                .filter((sec) => sec.items && sec.items.length > 0)
                .map((sec) => (
                  <div key={sec.title} className="py-2 first:pt-0 last:pb-0">
                    <div className="px-3 py-1.5 flex items-center gap-1.5 text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                      <span className="material-symbols-outlined text-sm text-primary">{sec.icon}</span>
                      <span>{sec.title}</span>
                      <span className="text-[10px] font-normal text-outline">({sec.items.length})</span>
                    </div>

                    <div className="space-y-0.5 mt-1">
                      {sec.items.map((item) => {
                        const itemIndex = runningIndex++
                        const isSelected = itemIndex === selectedIndex

                        return (
                          <div
                            key={item.id || item.title}
                            data-active={isSelected ? 'true' : 'false'}
                            onClick={() => handleSelect(item)}
                            className={[
                              'flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl cursor-pointer transition-colors',
                              isSelected
                                ? 'bg-primary-container/30 text-on-surface'
                                : 'hover:bg-surface-container-high/60 text-on-surface',
                            ].join(' ')}
                          >
                            <div className="flex items-center gap-3 min-w-0">
                              <div
                                className={[
                                  'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-base',
                                  isSelected ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant',
                                ].join(' ')}
                              >
                                <span className="material-symbols-outlined text-lg">{item.icon || 'arrow_forward'}</span>
                              </div>
                              <div className="min-w-0">
                                <div className="text-sm font-semibold truncate text-on-surface">
                                  {item.title}
                                </div>
                                {item.subtitle && (
                                  <div className="text-xs text-on-surface-variant truncate">
                                    {item.subtitle}
                                  </div>
                                )}
                              </div>
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                              {item.price !== undefined && (
                                <span className="text-xs font-semibold text-primary">
                                  {formatCurrencyVND(item.price)}
                                </span>
                              )}
                              {item.badge_label && (
                                <span
                                  className={[
                                    'px-2 py-0.5 rounded-md text-[11px] font-medium',
                                    item.badge === 'active' || item.badge === 'delivered'
                                      ? 'bg-success-container text-on-success-container'
                                      : item.badge === 'pending'
                                      ? 'bg-warning-container text-on-warning-container'
                                      : 'bg-surface-container-highest text-on-surface-variant',
                                  ].join(' ')}
                                >
                                  {item.badge_label}
                                </span>
                              )}
                              <span
                                className={[
                                  'material-symbols-outlined text-base transition-opacity',
                                  isSelected ? 'opacity-100 text-primary' : 'opacity-0',
                                ].join(' ')}
                              >
                                keyboard_return
                              </span>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                ))}
            </>
          )}
        </div>

        {/* Footer info & shortcut cues */}
        <div className="px-4 py-2.5 bg-surface-container-high/60 border-t border-outline-variant/20 flex items-center justify-between text-xs text-on-surface-variant">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <kbd className="px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/30">
                ↑
              </kbd>
              <kbd className="px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/30">
                ↓
              </kbd>
              <span>di chuyển</span>
            </span>
            <span className="flex items-center gap-1">
              <kbd className="px-1.5 py-0.5 rounded bg-surface-container-highest text-[10px] font-mono border border-outline-variant/30">
                ↵
              </kbd>
              <span>chọn</span>
            </span>
          </div>
          <div className="flex items-center gap-1 text-[11px] text-outline">
            <span className="material-symbols-outlined text-xs text-primary">magic_button</span>
            <span>MobileX Smart Search</span>
          </div>
        </div>
      </div>
    </div>
  )
}
