// src/utils/formatCurrencyVND.js
// Format a number as Vietnamese Dong currency.

/**
 * @param {number} amount
 * @returns {string}  e.g. "29.990.000 ₫"
 */
export function formatCurrencyVND(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return '—'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}
