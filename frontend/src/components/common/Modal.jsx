// src/components/common/Modal.jsx
// Accessible modal dialog with backdrop dismiss and ESC key support.

import { useEffect } from 'react'

/**
 * @param {{
 *   open: boolean,
 *   onClose: () => void,
 *   title?: string,
 *   children: React.ReactNode,
 *   size?: 'sm'|'md'|'lg'|'xl',
 * }} props
 */
export default function Modal({ open, onClose, title, children, size = 'md' }) {
  // Close on ESC key
  useEffect(() => {
    if (!open) return
    const handler = (e) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [open, onClose])

  // Lock body scroll
  useEffect(() => {
    if (open) document.body.style.overflow = 'hidden'
    else document.body.style.overflow = ''
    return () => { document.body.style.overflow = '' }
  }, [open])

  if (!open) return null

  const widths = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-2xl' }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-background/80 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Panel */}
      <div
        className={[
          'relative z-10 w-full bg-surface-container-high rounded-2xl shadow-2xl',
          'border border-outline-variant/30 overflow-hidden',
          widths[size] ?? widths.md,
        ].join(' ')}
      >
        {title && (
          <div className="flex items-center justify-between px-6 py-4 border-b border-outline-variant/20">
            <h2 className="text-base font-semibold text-on-surface">{title}</h2>
            <button
              onClick={onClose}
              className="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface transition-colors"
              aria-label="Đóng"
            >
              <span className="material-symbols-outlined text-xl">close</span>
            </button>
          </div>
        )}
        <div className="p-6">{children}</div>
      </div>
    </div>
  )
}
