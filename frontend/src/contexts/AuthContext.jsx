// src/contexts/AuthContext.jsx
// Global authentication state: user profile, token, roles, permissions.
// Persists token in localStorage via storage utility.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import axiosClient from '../api/axiosClient'
import EP from '../api/endpoints'
import storage from '../utils/storage'

/** @typedef {{ id: number, name: string, email: string, roles: string[], permissions: string[] }} AuthUser */

const AuthContext = createContext(null)

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
        setUser(data.data ?? data)
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
    const userObj = payload.user ?? payload
    storage.set('token', newToken)
    setToken(newToken)
    setUser(userObj)
    return userObj
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
  const hasPermission = useCallback(
    (slug) => !!(user?.permissions ?? []).includes(slug),
    [user],
  )

  const hasRole = useCallback(
    (role) => !!(user?.roles ?? []).includes(role),
    [user],
  )

  const isAdmin = useCallback(
    () => hasRole('admin') || hasRole('super-admin'),
    [hasRole],
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
