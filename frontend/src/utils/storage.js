// src/utils/storage.js
// Thin wrapper around localStorage with a consistent key namespace.

const PREFIX = 'mobieX_'

const storage = {
  /**
   * Store a value (auto-serializes objects/arrays).
   * @param {string} key
   * @param {*} value
   */
  set(key, value) {
    try {
      localStorage.setItem(PREFIX + key, JSON.stringify(value))
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
      const raw = localStorage.getItem(PREFIX + key)
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
    localStorage.removeItem(PREFIX + key)
  },

  /**
   * Clear ALL MobieX keys from localStorage.
   */
  clear() {
    Object.keys(localStorage)
      .filter((k) => k.startsWith(PREFIX))
      .forEach((k) => localStorage.removeItem(k))
  },
}

export default storage
