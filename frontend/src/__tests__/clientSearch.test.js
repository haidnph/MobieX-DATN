import { describe, it, expect, beforeEach } from 'vitest'
import {
  getRecentSearches,
  addRecentSearch,
  removeRecentSearch,
  clearRecentSearches,
  POPULAR_SEARCHES,
} from '../utils/searchHistory'

describe('Client Search Functionality & Search History', () => {
  beforeEach(() => {
    clearRecentSearches()
  })

  it('starts with an empty search history', () => {
    expect(getRecentSearches()).toEqual([])
  })

  it('adds items to search history up to MAX_HISTORY_ITEMS (8) with deduplication', () => {
    addRecentSearch('iPhone 16')
    addRecentSearch('Samsung S24')
    addRecentSearch('iPhone 16') // Duplicate should move to top

    let history = getRecentSearches()
    expect(history).toHaveLength(2)
    expect(history[0]).toBe('iPhone 16')
    expect(history[1]).toBe('Samsung S24')

    // Add up to 10 unique searches
    for (let i = 1; i <= 10; i++) {
      addRecentSearch(`Device #${i}`)
    }

    history = getRecentSearches()
    expect(history).toHaveLength(8)
    expect(history[0]).toBe('Device #10')
  })

  it('removes a specific item from search history', () => {
    addRecentSearch('Tai nghe Sony')
    addRecentSearch('Củ sạc Anker')

    removeRecentSearch('Tai nghe Sony')
    const history = getRecentSearches()
    expect(history).toHaveLength(1)
    expect(history[0]).toBe('Củ sạc Anker')
  })

  it('clears all search history on clearRecentSearches', () => {
    addRecentSearch('iPhone 15')
    addRecentSearch('Galaxy Z Fold')
    clearRecentSearches()
    expect(getRecentSearches()).toEqual([])
  })

  it('contains popular search keywords for client discovery', () => {
    expect(POPULAR_SEARCHES.length).toBeGreaterThanOrEqual(5)
    expect(POPULAR_SEARCHES).toContain('iPhone 16 Pro Max')
    expect(POPULAR_SEARCHES).toContain('Samsung Galaxy S24 Ultra')
  })
})
