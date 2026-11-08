// src/hooks/useDebounce.js
// Returns a debounced version of `value`, updated after `delay` ms of inactivity.
// Used for search inputs to avoid firing API calls on every keystroke.

import { useEffect, useState } from 'react'

/**
 * @template T
 * @param {T} value
 * @param {number} [delay=300]
 * @returns {T}
 */
export function useDebounce(value, delay = 300) {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}

export default useDebounce

