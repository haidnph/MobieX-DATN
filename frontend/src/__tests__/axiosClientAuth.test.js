import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import axiosClient from '../api/axiosClient'
import storage from '../utils/storage'

describe('Axios authentication error handling', () => {
  let originalAdapter

  beforeEach(() => {
    originalAdapter = axiosClient.defaults.adapter
    storage.set('token', 'existing-session-token')
  })

  afterEach(() => {
    axiosClient.defaults.adapter = originalAdapter
    storage.clear()
  })

  it('keeps the existing session and reports invalid credentials on login 401', async () => {
    axiosClient.defaults.adapter = async (config) => {
      const response = {
        config,
        data: { message: 'The provided credentials are incorrect.' },
        headers: {},
        status: 401,
        statusText: 'Unauthorized',
      }
      throw { config, response }
    }

    await expect(axiosClient.post('/auth/login', {
      email: 'customer@example.test',
      password: 'incorrect',
    })).rejects.toMatchObject({
      message: 'Email hoặc mật khẩu không chính xác.',
      status: 401,
    })

    expect(storage.get('token')).toBe('existing-session-token')
  })

  it('clears the session when a protected API request returns 401', async () => {
    axiosClient.defaults.adapter = async (config) => {
      const response = {
        config,
        data: { message: 'Unauthenticated.' },
        headers: {},
        status: 401,
        statusText: 'Unauthorized',
      }
      throw { config, response }
    }

    await expect(axiosClient.get('/me')).rejects.toMatchObject({
      message: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    })

    expect(storage.get('token')).toBeNull()
  })
})
