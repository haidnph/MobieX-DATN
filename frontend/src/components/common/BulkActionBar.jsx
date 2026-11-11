// src/components/common/BulkActionBar.jsx
// Floating bottom bar for batch actions in admin tables (products, orders, users).

import Button from './Button'

/**
 * @param {Object} props
 * @param {number} props.selectedCount   - Number of items currently checked
 * @param {function} props.onClear        - Called when user clicks Deselect All
 * @param {function} [props.onActivate]   - Called to bulk activate
 * @param {function} [props.onDeactivate] - Called to bulk deactivate / hide
 * @param {function} [props.onDelete]     - Called to bulk delete
 * @param {boolean} [props.loading]       - Global loading state during mutation
 */
export default function BulkActionBar({
  selectedCount,
  onClear,
  onActivate,
  onDeactivate,
  onDelete,
  loading = false,
}) {
  if (!selectedCount || selectedCount <= 0) return null

  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[92%] max-w-2xl bg-surface-container-high/95 backdrop-blur-xl border border-primary/40 rounded-2xl p-4 shadow-[0_20px_50px_rgba(0,0,0,0.5)] transition-all animate-in fade-in slide-in-from-bottom-5 duration-300">
      <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex h-7 px-2.5 items-center justify-center rounded-lg bg-primary text-on-primary font-bold text-xs">
            {selectedCount}
          </span>
          <span className="text-sm font-semibold text-on-surface">
            Đã chọn {selectedCount} mục
          </span>
          <button
            type="button"
            onClick={onClear}
            className="text-xs text-outline hover:text-primary transition-colors underline"
          >
            Bỏ chọn
          </button>
        </div>

        <div className="flex items-center gap-2 flex-wrap justify-end w-full sm:w-auto">
          {onActivate && (
            <Button
              type="button"
              size="sm"
              variant="secondary"
              loading={loading}
              onClick={onActivate}
              className="flex items-center gap-1 text-xs"
            >
              <span className="material-symbols-outlined text-sm">visibility</span>
              Kích hoạt
            </Button>
          )}

          {onDeactivate && (
            <Button
              type="button"
              size="sm"
              variant="ghost"
              loading={loading}
              onClick={onDeactivate}
              className="flex items-center gap-1 text-xs border border-outline-variant/30"
            >
              <span className="material-symbols-outlined text-sm">visibility_off</span>
              Tạm ẩn
            </Button>
          )}

          {onDelete && (
            <Button
              type="button"
              size="sm"
              variant="danger"
              loading={loading}
              onClick={onDelete}
              className="flex items-center gap-1 text-xs"
            >
              <span className="material-symbols-outlined text-sm">delete</span>
              Xóa đã chọn
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
