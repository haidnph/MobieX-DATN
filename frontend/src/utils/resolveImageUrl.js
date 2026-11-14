// src/utils/resolveImageUrl.js
// Standardizes image URLs across local backend storage, proxy, and external CDNs.

/**
 * Resolves any image source (object, relative path, or absolute URL)
 * into a safe, accessible image URL for browser rendering.
 *
 * @param {string|Object|null|undefined} input
 * @returns {string|null}
 */
export function resolveImageUrl(input) {
  if (!input) return null

  let raw = ''
  if (typeof input === 'string') {
    raw = input.trim()
  } else if (typeof input === 'object') {
    raw = input.image_url || input.image_path || input.url || input.path || ''
    raw = typeof raw === 'string' ? raw.trim() : ''
  }

  if (!raw) return null

  // Client draft previews (Object URLs / Base64 Data URLs)
  if (raw.startsWith('blob:') || raw.startsWith('data:')) {
    return raw
  }

  // Normalize local backend storage URLs to relative /storage/ paths
  // e.g. http://127.0.0.1:8000/storage/products/xxx.png -> /storage/products/xxx.png
  // This enables Vite dev proxy and eliminates cross-origin / host mismatch errors.
  const localMatch = raw.match(/^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?(\/storage\/.*)$/i)
  if (localMatch) {
    return localMatch[1]
  }

  // Already relative with leading slash
  if (raw.startsWith('/storage/')) {
    return raw
  }

  // Relative storage without leading slash
  if (raw.startsWith('storage/')) {
    return '/' + raw
  }

  // Direct storage file path (e.g. "products/uAgLtj3l1s.png")
  if (raw.startsWith('products/') || raw.startsWith('uploads/')) {
    return '/storage/' + raw
  }

  // External CDNs or other valid absolute URLs
  return raw
}

export default resolveImageUrl
