// src/contexts/AuthContext.jsx
// Global authentication state: user profile, token, roles, permissions.
// Persists token in localStorage via storage utility.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import storage from '../utils/storage'

/** @typedef {{ id: number, name: string, email: string, roles: string[], permissions: string[] }} AuthUser */

const AuthContext = createContext(null)

function normalizeUser(rawUser) {
  if (!rawUser) return null
  const rawRoles = rawUser.roles ?? []
  const roleSlugs = rawRoles.map((r) => (typeof r === 'string' ? r : r?.slug)).filter(Boolean)
  const permissions = rawUser.permissions ?? []
  const permissionSlugs = permissions.map((p) => (typeof p === 'string' ? p : p?.slug)).filter(Boolean)
  return {
    ...rawUser,
    roles: roleSlugs,
    rawRoles,
    permissions: permissionSlugs,
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(/** @type {AuthUser|null} */ (null))
  const [token, setToken] = useState(/** @type {string|null} */ (storage.get('token')))
  const [loading, setLoading] = useState(true)

  // ── Restore session on page load ──────────────────────────────────────
  useEffect(() => {
    const savedToken = storage.get('token')
    if (!savedToken) {
      setLoading(false)
      return
    }
    axiosClient
      .get(EP.AUTH_ME)
      .then(({ data }) => {
        const u = data.data?.user ?? data.data ?? data
        setUser(normalizeUser(u))
        setToken(savedToken)
      })
      .catch(() => {
        // Token expired or invalid — clear everything
        storage.clear()
        setToken(null)
        setUser(null)
      })
      .finally(() => setLoading(false))
  }, [])

  // ── Login ──────────────────────────────────────────────────────────────
  const login = useCallback(async (credentials) => {
    const { data } = await axiosClient.post(EP.AUTH_LOGIN, credentials)
    const payload = data.data ?? data
    const newToken = payload.token ?? payload.access_token
    storage.set('token', newToken)
    setToken(newToken)

    // Ensure we have the full user profile with roles
    let userObj = payload.user ?? payload
    if (!userObj?.roles || userObj.roles.length === 0) {
      try {
        const meRes = await axiosClient.get(EP.AUTH_ME, {
          headers: { Authorization: `Bearer ${newToken}` },
        })
        userObj = meRes.data.data?.user ?? meRes.data.data ?? meRes.data
      } catch {
        // fallback to payload userObj
      }
    }
    const normalized = normalizeUser(userObj)
    setUser(normalized)
    return normalized
  }, [])

  // ── Logout ─────────────────────────────────────────────────────────────
  const logout = useCallback(async () => {
    try {
      await axiosClient.post(EP.AUTH_LOGOUT)
    } catch {
      // Even if the request fails, clear local session
    } finally {
      storage.clear()
      setToken(null)
      setUser(null)
    }
  }, [])

  // ── Permission helpers ─────────────────────────────────────────────────
  const hasRole = useCallback(
    (role) => {
      if (!user?.roles) return false
      return user.roles.some((r) => {
        const slug = typeof r === 'string' ? r : r?.slug
        return slug === role
      })
    },
    [user],
  )

  const isAdmin = useCallback(
    () => hasRole('admin') || hasRole('super-admin'),
    [hasRole],
  )

  const hasPermission = useCallback(
    (slug) => {
      if (isAdmin()) return true
      return !!(user?.permissions ?? []).includes(slug)
    },
    [user, isAdmin],
  )

  return (
    <AuthContext.Provider
      value={{ user, token, loading, login, logout, hasPermission, hasRole, isAdmin }}
    >
      {children}
    </AuthContext.Provider>
  )
}

/**
 * @returns {{
 *   user: AuthUser|null,
 *   token: string|null,
 *   loading: boolean,
 *   login: (credentials: object) => Promise<AuthUser>,
 *   logout: () => Promise<void>,
 *   hasPermission: (slug: string) => boolean,
 *   hasRole: (role: string) => boolean,
 *   isAdmin: () => boolean,
 * }}
 */
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
