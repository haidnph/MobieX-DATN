import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useCompare, FALLBACK_PHONE_SVG } from '../../contexts/CompareContext'
import { useLanguage } from '../../contexts/LanguageContext'

const DOCK_LABELS = {
  vi: {
    title: 'So sánh sản phẩm',
    comparing: 'Đang so sánh',
    emptySlot: 'Trống',
    addMore: 'Thêm máy',
    compareNow: 'So sánh ngay',
    clearAll: 'Xóa tất cả',
    collapse: 'Thu gọn',
    expand: 'Mở rộng',
  },
  en: {
    title: 'Compare Devices',
    comparing: 'Comparing',
    emptySlot: 'Empty',
    addMore: 'Add more',
    compareNow: 'Compare Now',
    clearAll: 'Clear All',
    collapse: 'Minimize',
    expand: 'Expand',
  },
  'zh-CN': {
    title: '商品对比',
    comparing: '已选对比',
    emptySlot: '空位',
    addMore: '添加机型',
    compareNow: '立即对比',
    clearAll: '清空全部',
    collapse: '收起',
    expand: '展开',
  },
}

export default function CompareDock() {
  const { compareItems, compareCount, removeFromCompare, clearCompare, maxItems } = useCompare()
  const { lang } = useLanguage()
  const location = useLocation()
  const [minimized, setMinimized] = useState(false)

  const t = DOCK_LABELS[lang] || DOCK_LABELS.vi

  // Hide dock if no items or if user is already on compare page
  if (compareCount === 0 || location.pathname === '/compare') {
    return null
  }

  if (minimized) {
    return (
      <aside aria-label="Khay so sánh sản phẩm" className="fixed bottom-6 right-6 z-40 animate-bounce-in">
        <button
          onClick={() => setMinimized(false)}
          className="flex items-center gap-2 px-4 py-2.5 rounded-full bg-primary text-on-primary font-bold shadow-2xl hover:bg-primary/90 hover:scale-105 active:scale-95 transition-all text-xs"
          title={t.expand}
        >
          <span className="material-symbols-outlined text-lg">compare_arrows</span>
          <span>
            {t.comparing} ({compareCount}/{maxItems})
          </span>
        </button>
      </aside>
    )
  }

  // Generate slots array of length maxItems (4)
  const slots = Array.from({ length: maxItems }, (_, index) => compareItems[index] || null)

  return (
    <aside aria-label="Khay so sánh sản phẩm" className="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[95%] max-w-2xl animate-slide-up">
      <div className="bg-surface-container/95 backdrop-blur-xl border border-outline-variant/30 rounded-2xl p-3 md:p-3.5 shadow-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {/* Left: Device Thumbnails / Slots */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          {slots.map((item, idx) => {
            if (item) {
              return (
                <div
                  key={item.id}
                  className="relative group shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl bg-surface-container-high border border-outline-variant/30 p-1 flex items-center justify-center transition-all hover:border-primary/50"
                >
                  <img
                    src={item.image_url || FALLBACK_PHONE_SVG}
                    alt={item.name}
                    loading="lazy"
                    onError={(e) => {
                      e.currentTarget.onerror = null
                      e.currentTarget.src = FALLBACK_PHONE_SVG
                    }}
                    className="max-h-full max-w-full object-contain"
                  />
                  <button
                    type="button"
                    onClick={() => removeFromCompare(item.id)}
                    className="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-error text-white text-[10px] flex items-center justify-center shadow-md hover:scale-110 transition-transform opacity-90 hover:opacity-100"
                    title={`Bỏ ${item.name}`}
                  >
                    <span className="material-symbols-outlined text-[12px]">close</span>
                  </button>
                </div>
              )
            }

            return (
              <div
                key={`empty-${idx}`}
                className="shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl border border-dashed border-outline-variant/40 bg-surface-container-low/40 flex flex-col items-center justify-center text-[10px] text-on-surface-variant/60"
              >
                <span className="material-symbols-outlined text-sm opacity-50">add</span>
                <span className="text-[9px] font-mono leading-none">{idx + 1}</span>
              </div>
            )
          })}
        </div>

        {/* Right: Actions */}
        <div className="flex items-center justify-between sm:justify-end gap-2 shrink-0">
          <button
            type="button"
            onClick={clearCompare}
            className="text-[11px] font-medium text-error hover:bg-error/10 px-2 py-1.5 rounded-lg transition-colors flex items-center gap-1"
          >
            <span className="material-symbols-outlined text-sm">delete_sweep</span>
            <span className="hidden md:inline">{t.clearAll}</span>
          </button>

          <button
            type="button"
            onClick={() => setMinimized(true)}
            className="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container-highest transition-colors"
            title={t.collapse}
          >
            <span className="material-symbols-outlined text-base">expand_more</span>
          </button>

          <Link
            to="/compare"
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-on-primary font-bold text-xs shadow-md hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98] transition-all"
          >
            <span className="material-symbols-outlined text-base">compare_arrows</span>
            <span>
              {t.compareNow} ({compareCount}/{maxItems})
            </span>
          </Link>
        </div>
      </div>
    </aside>
  )
}
