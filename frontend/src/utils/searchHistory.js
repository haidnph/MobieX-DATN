// src/utils/searchHistory.js
// Search history and trending popular searches management for MobieX client storefront.

import storage from './storage'

const SEARCH_HISTORY_KEY = 'recent_searches'
const MAX_HISTORY_ITEMS = 8

export const POPULAR_SEARCHES = [
  'iPhone 16 Pro Max',
  'Samsung Galaxy S24 Ultra',
  'Tai nghe AirPods',
  'Củ sạc Anker 65W',
  'Sony WH-1000XM5',
  'Xiaomi 14 Pro',
  'Ốp lưng MagSafe',
  'Kính cường lực 9D',
]

/**
 * Retrieve the list of recent search queries from storage.
 * @returns {string[]}
 */
export function getRecentSearches() {
  const list = storage.get(SEARCH_HISTORY_KEY, [])
  return Array.isArray(list) ? list : []
}

/**
 * Add a new query to the search history (deduplicated, limited to MAX_HISTORY_ITEMS).
 * @param {string} query
 * @returns {string[]} Updated history list
 */
export function addRecentSearch(query) {
  const clean = typeof query === 'string' ? query.trim() : ''
  if (!clean || clean.length < 2) return getRecentSearches()

  const current = getRecentSearches()
  // Remove duplicate (case-insensitive)
  const filtered = current.filter(
    (item) => item.toLowerCase() !== clean.toLowerCase()
  )
  const updated = [clean, ...filtered].slice(0, MAX_HISTORY_ITEMS)
  storage.set(SEARCH_HISTORY_KEY, updated)
  return updated
}

/**
 * Remove a specific search term from history.
 * @param {string} query
 * @returns {string[]} Updated history list
 */
export function removeRecentSearch(query) {
  const clean = typeof query === 'string' ? query.trim().toLowerCase() : ''
  const current = getRecentSearches()
  const updated = current.filter((item) => item.toLowerCase() !== clean)
  storage.set(SEARCH_HISTORY_KEY, updated)
  return updated
}

/**
 * Clear all search history.
 */
export function clearRecentSearches() {
  storage.remove(SEARCH_HISTORY_KEY)
  return []
}
