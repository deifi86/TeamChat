import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authAPI, userAPI } from '@/services/api'
import { initializeEcho, disconnectEcho } from '@/services/websocket'
import router from '@/router'

export interface User {
  id: number
  email: string
  username: string
  avatar_url: string
  status: 'available' | 'busy' | 'away' | 'offline'
  status_text: string | null
  created_at: string
}

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const currentUserId = computed(() => user.value?.id)

  // Actions
  async function login(email: string, password: string) {
    loading.value = true
    error.value = null

    try {
      const response = await authAPI.login({ email, password })
      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)

      // WebSocket verbinden
      initializeEcho()

      router.push('/app')
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Login fehlgeschlagen'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function register(data: {
    email: string
    password: string
    password_confirmation: string
    username: string
  }) {
    loading.value = true
    error.value = null

    try {
      const response = await authAPI.register(data)
      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)

      initializeEcho()
      router.push('/app')
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Registrierung fehlgeschlagen'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authAPI.logout()
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      disconnectEcho()
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      router.push('/login')
    }
  }

  async function fetchUser() {
    if (!token.value) return

    loading.value = true
    try {
      const response = await authAPI.me()
      user.value = response.data.user
      initializeEcho()
    } catch (err) {
      logout()
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(data: { username?: string; status_text?: string }) {
    const response = await userAPI.updateProfile(data)
    user.value = response.data.user
  }

  async function updateStatus(status: string, statusText?: string) {
    const response = await userAPI.updateStatus({ status, status_text: statusText })
    if (user.value) {
      user.value.status = response.data.status
      user.value.status_text = response.data.status_text
    }
  }

  async function uploadAvatar(file: File) {
    const response = await userAPI.uploadAvatar(file)
    if (user.value) {
      user.value.avatar_url = response.data.avatar_url
    }
  }

  async function deleteAvatar() {
    const response = await userAPI.deleteAvatar()
    if (user.value) {
      user.value.avatar_url = response.data.avatar_url
    }
  }

  return {
    // State
    user,
    token,
    loading,
    error,
    // Getters
    isAuthenticated,
    currentUserId,
    // Actions
    login,
    register,
    logout,
    fetchUser,
    updateProfile,
    updateStatus,
    uploadAvatar,
    deleteAvatar,
  }
})
