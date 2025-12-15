import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth'

// Mock API
vi.mock('@/services/api', () => ({
  authAPI: {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
  },
  userAPI: {
    updateProfile: vi.fn(),
    updateStatus: vi.fn(),
  },
}))

vi.mock('@/services/websocket', () => ({
  initializeEcho: vi.fn(),
  disconnectEcho: vi.fn(),
}))

vi.mock('@/router', () => ({
  default: {
    push: vi.fn(),
  },
}))

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('starts with no user', () => {
    const store = useAuthStore()
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })

  it('stores token in localStorage', async () => {
    const store = useAuthStore()
    const { authAPI } = await import('@/services/api')

    vi.mocked(authAPI.login).mockResolvedValue({
      data: {
        token: 'test-token',
        user: { id: 1, email: 'test@test.de', username: 'Test' },
      },
    } as any)

    await store.login('test@test.de', 'password')

    expect(localStorage.getItem('auth_token')).toBe('test-token')
    expect(store.token).toBe('test-token')
  })

  it('clears token on logout', async () => {
    const store = useAuthStore()
    store.token = 'test-token'
    localStorage.setItem('auth_token', 'test-token')

    await store.logout()

    expect(store.token).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })
})
