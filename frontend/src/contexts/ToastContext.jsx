// src/contexts/ToastContext.jsx
// Global toast notification system.
// Usage: const { showToast } = useToast()
//        showToast('Đã thêm vào giỏ hàng', 'success')

import { createContext, useCallback, useContext, useState } from 'react'

const ToastContext = createContext(null)

let _id = 0

/**
 * @typedef {'success'|'error'|'warning'|'info'} ToastType
 * @typedef {{ id: number, message: string, type: ToastType }} Toast
 */

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState(/** @type {Toast[]} */ ([]))

  const showToast = useCallback((message, type = 'info', duration = 3500) => {
    const id = ++_id
    setToasts((prev) => [...prev, { id, message, type }])
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id))
    }, duration)
  }, [])

  const dismiss = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      <ToastContainer toasts={toasts} onDismiss={dismiss} />
    </ToastContext.Provider>
  )
}

/** @returns {{ showToast: (message: string, type?: ToastType, duration?: number) => void }} */
export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider>')
  return ctx
}

// ── Toast Container UI ─────────────────────────────────────────────────────

const typeStyles = {
  success: 'bg-secondary-container text-on-secondary-container border-secondary/30',
  error:   'bg-error-container text-on-error-container border-error/30',
  warning: 'bg-tertiary-container/40 text-on-surface border-tertiary/30',
  info:    'bg-primary-container/30 text-on-surface border-primary/20',
}

const typeIcons = {
  success: 'check_circle',
  error:   'error',
  warning: 'warning',
  info:    'info',
}

function ToastContainer({ toasts, onDismiss }) {
  if (toasts.length === 0) return null

  return (
    <div className="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 max-w-sm w-full pointer-events-none">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={[
            'flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg',
            'pointer-events-auto backdrop-blur-sm animate-in slide-in-from-right-5',
            typeStyles[toast.type] ?? typeStyles.info,
          ].join(' ')}
        >
          <span className="material-symbols-outlined text-lg shrink-0 mt-0.5">
            {typeIcons[toast.type] ?? 'info'}
          </span>
          <p className="flex-1 text-sm leading-snug">{toast.message}</p>
          <button
            onClick={() => onDismiss(toast.id)}
            className="shrink-0 opacity-60 hover:opacity-100 transition-opacity"
            aria-label="Đóng thông báo"
          >
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      ))}
    </div>
  )
}
