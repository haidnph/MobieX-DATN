// src/utils/formatDate.js
// Safe Date/time formatting helpers.

/**
 * Format an ISO date string to Vietnamese locale short date.
 * @param {string} isoString
 * @returns {string}  e.g. "08/11/2026"
 */
export function formatDate(isoString) {
  if (!isoString) return '—'
  const d = new Date(isoString)
  if (isNaN(d.getTime())) return '—'
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d)
}

/**
 * Format an ISO date string to Vietnamese locale with time.
 * @param {string} isoString
 * @returns {string}  e.g. "08/11/2026, 22:00"
 */
export function formatDateTime(isoString) {
  if (!isoString) return '—'
  const d = new Date(isoString)
  if (isNaN(d.getTime())) return '—'
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d)
}

/**
 * Format a date as a relative time string (e.g., "2 ngày trước").
 * @param {string} isoString
 * @returns {string}
 */
export function formatRelativeTime(isoString) {
  if (!isoString) return '—'
  const d = new Date(isoString)
  if (isNaN(d.getTime())) return '—'
  const diff = Date.now() - d.getTime()
  const rtf = new Intl.RelativeTimeFormat('vi', { numeric: 'auto' })
  const minutes = Math.round(diff / 60_000)
  if (Math.abs(minutes) < 1) return 'vừa xong'
  if (Math.abs(minutes) < 60) return rtf.format(-minutes, 'minute')
  const hours = Math.round(diff / 3_600_000)
  if (Math.abs(hours) < 24) return rtf.format(-hours, 'hour')
  const days = Math.round(diff / 86_400_000)
  return rtf.format(-days, 'day')
}
