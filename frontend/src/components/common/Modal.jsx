// src/components/common/Modal.jsx
// Accessible modal dialog with backdrop dismiss, ESC key support, scroll-lock, and 90dvh responsive height.

import { useEffect, useRef } from 'react'

/**
 * @param {{
 *   open: boolean,
 *   onClose: () => void,
 *   title?: string,
 *   children: React.ReactNode,
 *   size?: 'sm'|'md'|'lg'|'xl'|'2xl',
 *   footer?: React.ReactNode,
 *   className?: string,
 *   bodyClassName?: string,
 * }} props
 */
export default function Modal({
  open,
  onClose,
  title,
  children,
  size = 'md',
  footer = null,
  className = '',
  bodyClassName = '',
}) {
  const contentRef = useRef(null)

  // Close on ESC key
  useEffect(() => {
    if (!open) return
    const handler = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [open, onClose])

  // Strictly lock body scroll and prevent background shifting
  useEffect(() => {
    if (open) {
      const originalOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      return () => {
        document.body.style.overflow = originalOverflow
      }
    }
  }, [open])

  // Always reset scroll to the top of modal content when opened
  useEffect(() => {
    if (open && contentRef.current) {
      contentRef.current.scrollTop = 0
    }
  }, [open])

  if (!open) return null

  const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-3xl',
    '2xl': 'max-w-5xl',
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6"
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Panel with 90dvh max height and flex-col for internal scrolling */}
      <div
        className={[
          'relative z-10 w-full bg-surface-container-high rounded-2xl shadow-2xl',
          'border border-outline-variant/30 flex flex-col',
          'max-h-[90dvh] overflow-hidden',
          widths[size] ?? widths.md,
          className,
        ].join(' ')}
      >
        {/* Header - Fixed at top */}
        {title && (
          <div className="flex items-center justify-between px-6 py-4 border-b border-outline-variant/20 shrink-0 bg-surface-container-high select-none">
            <h2 className="text-base font-semibold text-on-surface line-clamp-1">{title}</h2>
            <button
              onClick={onClose}
              type="button"
              className="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface transition-colors"
              aria-label="Đóng"
            >
              <span className="material-symbols-outlined text-xl">close</span>
            </button>
          </div>
        )}

        {/* Scrollable Body - overflow-y: auto */}
        <div
          ref={contentRef}
          className={[
            'p-6 overflow-y-auto flex-1 min-h-0 overscroll-contain',
            bodyClassName,
          ].join(' ')}
        >
          {children}
        </div>

        {/* Optional Footer - Fixed at bottom */}
        {footer && (
          <div className="px-6 py-3.5 border-t border-outline-variant/20 bg-surface-container-high shrink-0 flex items-center justify-end gap-3 z-10">
            {footer}
          </div>
        )}
      </div>
    </div>
  )
}
