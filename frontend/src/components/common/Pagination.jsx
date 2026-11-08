// src/components/common/Pagination.jsx
// Simple page-number based pagination control.

/**
 * @param {{
 *   currentPage: number,
 *   lastPage: number,
 *   onPageChange: (page: number) => void,
 * }} props
 */
export default function Pagination({ currentPage, lastPage, totalPages, onPageChange }) {
  const total = lastPage || totalPages || 1
  if (total <= 1) return null

  const pages = Array.from({ length: total }, (_, i) => i + 1)
  // Show at most 7 page buttons with ellipsis
  const visible = pages.filter(
    (p) => p === 1 || p === total || Math.abs(p - currentPage) <= 2,
  )

  const btnBase =
    'h-9 w-9 flex items-center justify-center rounded-lg text-sm font-medium transition-colors'

  return (
    <nav className="flex items-center gap-1" aria-label="Phân trang">
      <button
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        className={`${btnBase} bg-surface-container-high text-on-surface-variant hover:bg-surface-bright disabled:opacity-30`}
        aria-label="Trang trước"
      >
        <span className="material-symbols-outlined text-base">chevron_left</span>
      </button>

      {visible.map((p, idx) => {
        const prev = visible[idx - 1]
        const showEllipsis = prev !== undefined && p - prev > 1
        return (
          <span key={p} className="flex items-center gap-1">
            {showEllipsis && (
              <span className="h-9 w-6 flex items-center justify-center text-on-surface-variant text-sm">
                …
              </span>
            )}
            <button
              onClick={() => onPageChange(p)}
              className={[
                btnBase,
                p === currentPage
                  ? 'bg-primary-container text-on-primary-container shadow'
                  : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-bright',
              ].join(' ')}
              aria-current={p === currentPage ? 'page' : undefined}
            >
              {p}
            </button>
          </span>
        )
      })}

      <button
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === total}
        className={`${btnBase} bg-surface-container-high text-on-surface-variant hover:bg-surface-bright disabled:opacity-30`}
        aria-label="Trang sau"
      >
        <span className="material-symbols-outlined text-base">chevron_right</span>
      </button>
    </nav>
  )
}
