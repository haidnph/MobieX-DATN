// src/utils/urlUtils.js
// Safe redirect utility to prevent open redirects and ensure proper routing
// between Client Storefront and Admin Management.

const AUTH_PATHS = [
  '/login',
  '/admin/login',
  '/register',
  '/forgot-password',
  '/reset-password',
]

/**
 * Validates and sanitizes an intended URL to ensure it is a safe, internal relative path.
 * Protects against open redirect attacks, protocol injection, and cross-portal mismatches.
 *
 * @param {string | { pathname?: string, search?: string, hash?: string } | null | undefined} from
 * @param {string} defaultUrl - Fallback URL if invalid (default: '/')
 * @param {object} options
 * @param {boolean} [options.isAdminPortal=false] - Whether this redirect is originating from Admin Login
 * @param {boolean} [options.canAccessAdmin=false] - Whether the authenticated user has admin privileges
 * @returns {string} Sanitized safe relative URL
 */
export function getSafeIntendedUrl(
  from,
  defaultUrl = '/',
  { isAdminPortal = false, canAccessAdmin = false } = {},
) {
  let target = ''

  if (typeof from === 'string') {
    target = from.trim()
  } else if (from && typeof from === 'object' && typeof from.pathname === 'string') {
    const search = from.search || ''
    const hash = from.hash || ''
    target = `${from.pathname}${search}${hash}`.trim()
  }

  // Fallback if empty or not starting with single slash
  if (!target || !target.startsWith('/')) {
    return defaultUrl
  }

  // Reject protocol-relative URLs (e.g. "//evil.com") and Windows-style paths (e.g. "/\\evil.com")
  if (target.startsWith('//') || target.startsWith('/\\') || target.includes('\\')) {
    return defaultUrl
  }

  // Reject URLs containing protocol schemes (e.g. "http:", "https:", "javascript:", "data:")
  if (/^[a-zA-Z][a-zA-Z\d+.-]*:/.test(target.slice(1)) || target.includes('://')) {
    return defaultUrl
  }

  // Extract path without query or hash for auth routes check
  const pathOnly = target.split('?')[0].split('#')[0]

  // Reject authentication pages to avoid redirect loops
  if (AUTH_PATHS.some((authPath) => pathOnly === authPath)) {
    return defaultUrl
  }

  // If in Admin Portal login:
  if (isAdminPortal) {
    // Only accept paths within /admin
    if (!pathOnly.startsWith('/admin')) {
      return '/admin'
    }
    return target
  }

  // If in Client Storefront login:
  // If the target is an admin path, only allow it if the user actually has admin access.
  // Otherwise, fallback to defaultUrl to prevent Customer users from looping into 403 Forbidden.
  if (pathOnly.startsWith('/admin')) {
    if (!canAccessAdmin) {
      return defaultUrl === '/admin' ? '/' : defaultUrl
    }
  }

  return target
}
