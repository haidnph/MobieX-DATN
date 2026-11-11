// src/utils/storage.js
// Robust wrapper around localStorage with consistent namespace and safe fallback for non-browser/SSR/test environments.

const PREFIX = 'mobieX_'
const memStorage = new Map()

function getStorage() {
  if (typeof window !== 'undefined' && typeof window.localStorage !== 'undefined') {
    return window.localStorage
  }
  if (typeof localStorage !== 'undefined') {
    return localStorage
  }
  return {
    getItem: (key) => memStorage.get(key) ?? null,
    setItem: (key, val) => memStorage.set(key, String(val)),
    removeItem: (key) => memStorage.delete(key),
    keys: () => Array.from(memStorage.keys()),
  }
}

const storage = {
  /**
   * Store a value (auto-serializes objects/arrays).
   * @param {string} key
   * @param {*} value
   */
  set(key, value) {
    try {
      const store = getStorage()
      store.setItem(PREFIX + key, JSON.stringify(value))
    } catch {
      // Silently ignore storage errors (e.g. private browsing quota exceeded)
    }
  },

  /**
   * Retrieve a stored value (auto-deserializes JSON).
   * @param {string} key
   * @param {*} fallback - returned when key is absent
   * @returns {*}
   */
  get(key, fallback = null) {
    try {
      const store = getStorage()
      const raw = store.getItem(PREFIX + key)
      return raw !== null ? JSON.parse(raw) : fallback
    } catch {
      return fallback
    }
  },

  /**
   * Remove a single key.
   * @param {string} key
   */
  remove(key) {
    try {
      const store = getStorage()
      store.removeItem(PREFIX + key)
    } catch {
      // ignore
    }
  },

  /**
   * Clear ALL MobieX keys from localStorage.
   */
  clear() {
    try {
      const store = getStorage()
      if (typeof store.keys === 'function') {
        store.keys()
          .filter((k) => k.startsWith(PREFIX))
          .forEach((k) => store.removeItem(k))
      } else {
        Object.keys(store)
          .filter((k) => k.startsWith(PREFIX))
          .forEach((k) => store.removeItem(k))
      }
    } catch {
      // ignore
    }
  },
}

export default storage
