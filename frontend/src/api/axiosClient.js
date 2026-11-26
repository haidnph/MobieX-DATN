// src/api/axiosClient.js
// Centralized Axios instance — the ONLY network layer in the app.
// All features import and use this instance; never use raw fetch().

import axios from 'axios'
import storage from '../utils/storage'

const axiosClient = axios.create({
  baseURL: '/api', // proxied by Vite to http://127.0.0.1:8000/api in dev
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15_000,
})

// ── Request Interceptor ────────────────────────────────────────────────────
// Attach the Bearer token from localStorage on every outgoing request.
axiosClient.interceptors.request.use(
  (config) => {
    const token = storage.get('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

const ERROR_TRANSLATIONS = {
  'The provided credentials are incorrect.': 'Email hoặc mật khẩu không chính xác.',
  'You cannot remove your own admin role.': 'Bạn không thể gỡ vai trò quản trị của chính mình.',
  'Cannot remove the admin role from the last active administrator.': 'Không thể gỡ vai trò của quản trị viên duy nhất đang hoạt động trong hệ thống.',
  'You cannot deactivate your own account.': 'Bạn không thể vô hiệu hóa tài khoản của chính mình.',
  'Cannot deactivate the last active administrator.': 'Không thể vô hiệu hóa quản trị viên duy nhất đang hoạt động. Hãy đảm bảo hệ thống có ít nhất một quản trị viên khác.',
  'Role not found.': 'Vai trò được chọn không tồn tại trong hệ thống.',
  'User not found.': 'Không tìm thấy thông tin tài khoản người dùng.',
  'The given data was invalid.': 'Dữ liệu cung cấp không hợp lệ. Vui lòng kiểm tra lại.',
  'Unauthenticated.': 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
  'This action is unauthorized.': 'Bạn không có quyền thực hiện hành động này.',
}

function translateMessage(rawMessage, fallback) {
  if (!rawMessage || typeof rawMessage !== 'string') return fallback
  return ERROR_TRANSLATIONS[rawMessage.trim()] || rawMessage
}

// ── Response Interceptor ───────────────────────────────────────────────────
// 401 → auto-logout and redirect to /login.
// 422 → flatten Laravel validation errors into a single readable message.
axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (!error.response) {
      // Network error / timeout
      return Promise.reject(new Error('Không thể kết nối tới máy chủ. Vui lòng kiểm tra kết nối mạng.'))
    }

    const { status, data } = error.response

    if (status === 401) {
      const requestPath = error.config?.url ?? ''
      const isLoginAttempt = /(?:^|\/)auth\/login\/?(?:[?#].*)?$/.test(requestPath)

      if (isLoginAttempt) {
        const loginError = new Error(
          translateMessage(data?.message, 'Email hoặc mật khẩu không chính xác.'),
        )
        loginError.response = error.response
        loginError.status = status

        return Promise.reject(loginError)
      }

      // Clear expired session
      storage.clear()

      const currentPath = typeof window !== 'undefined' ? window.location.pathname : ''

      // If user is inside Admin portal, direct to Admin Login
      if (currentPath.startsWith('/admin') && currentPath !== '/admin/login') {
        window.location.href = '/admin/login'
      } else {
        // Protected storefront routes that require immediate authentication
        const customerProtectedPrefixes = ['/checkout', '/payment', '/orders', '/account']
        const isCustomerProtected = customerProtectedPrefixes.some((prefix) =>
          currentPath.startsWith(prefix),
        )

        if (isCustomerProtected && currentPath !== '/login') {
          window.location.href = '/login'
        }
        // If on a public page (e.g. /, /products, /cart), do NOT force redirect;
        // let the app degrade to guest mode gracefully.
      }

      return Promise.reject(new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'))
    }

    if (status === 422) {
      // Flatten Laravel validation error bag
      const errors = data?.errors ?? {}
      const messages = Object.values(errors).flat()
      const message = messages.length > 0
        ? messages.join(' ')
        : translateMessage(data?.message, 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại các trường thông tin.')
      const flatError = new Error(message)
      flatError.validationErrors = errors
      flatError.status = 422
      flatError.response = error.response
      return Promise.reject(flatError)
    }

    if (status === 403) {
      return Promise.reject(new Error(translateMessage(data?.message, 'Bạn không có quyền thực hiện hành động này.')))
    }

    if (status === 404) {
      return Promise.reject(new Error(translateMessage(data?.message, 'Không tìm thấy tài nguyên yêu cầu.')))
    }

    if (status === 409) {
      return Promise.reject(new Error(translateMessage(data?.message, 'Thao tác xung đột với trạng thái hiện tại của hệ thống.')))
    }

    if (status >= 500) {
      const serverMessage = status === 503 && typeof data?.message === 'string'
        ? translateMessage(data.message, '')
        : ''
      return Promise.reject(new Error(serverMessage || 'Máy chủ gặp sự cố nội bộ. Vui lòng thử lại sau ít phút.'))
    }

    // Generic client error
    const message = translateMessage(data?.message, `Đã xảy ra lỗi (${status}). Vui lòng thử lại.`)
    const finalErr = new Error(message)
    finalErr.response = error.response
    finalErr.status = status
    return Promise.reject(finalErr)
  },
  
)

export default axiosClient
