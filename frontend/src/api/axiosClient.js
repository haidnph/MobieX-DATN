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
      // Clear session and redirect to login
      storage.clear()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
      return Promise.reject(new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'))
    }

    if (status === 422) {
      // Flatten Laravel validation error bag
      const errors = data?.errors ?? {}
      const messages = Object.values(errors).flat()
      const message = messages.length > 0
        ? messages.join(' ')
        : (data?.message ?? 'Dữ liệu không hợp lệ.')
      const flatError = new Error(message)
      flatError.validationErrors = errors
      flatError.status = 422
      return Promise.reject(flatError)
    }

    if (status === 403) {
      return Promise.reject(new Error(data?.message ?? 'Bạn không có quyền thực hiện hành động này.'))
    }

    if (status === 404) {
      return Promise.reject(new Error(data?.message ?? 'Không tìm thấy tài nguyên yêu cầu.'))
    }

    // Generic server error
    const message = data?.message ?? `Đã xảy ra lỗi (${status}). Vui lòng thử lại.`
    return Promise.reject(new Error(message))
  },
)

export default axiosClient
