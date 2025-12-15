import axios, { type AxiosInstance, AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request Interceptor - Token hinzufügen
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  }
)

// Response Interceptor - Fehlerbehandlung
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const authStore = useAuthStore()

    if (error.response?.status === 401) {
      // Token ungültig - Logout
      authStore.logout()
      router.push('/login')
    }

    if (error.response?.status === 403) {
      console.error('Forbidden:', error.response.data)
    }

    if (error.response?.status === 422) {
      // Validation Error - durchreichen
      return Promise.reject(error)
    }

    if (error.response?.status === 500) {
      console.error('Server Error:', error.response.data)
    }

    return Promise.reject(error)
  }
)

export default api

// Typed API Functions
export const authAPI = {
  register: (data: { email: string; password: string; password_confirmation: string; username: string }) =>
    api.post('/auth/register', data),
  login: (data: { email: string; password: string }) =>
    api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  refresh: () => api.post('/auth/refresh'),
}

export const userAPI = {
  updateProfile: (data: { username?: string; status_text?: string }) =>
    api.put('/user/profile', data),
  updateStatus: (data: { status: string; status_text?: string }) =>
    api.put('/user/status', data),
  uploadAvatar: (file: File) => {
    const formData = new FormData()
    formData.append('avatar', file)
    return api.post('/user/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  deleteAvatar: () => api.delete('/user/avatar'),
  search: (q: string) => api.get('/users/search', { params: { q } }),
}

export const companyAPI = {
  myCompanies: () => api.get('/my-companies'),
  search: (q: string) => api.get('/companies/search', { params: { q } }),
  create: (data: { name: string; join_password: string }) =>
    api.post('/companies', data),
  get: (id: number) => api.get(`/companies/${id}`),
  update: (id: number, data: { name?: string; join_password?: string }) =>
    api.put(`/companies/${id}`, data),
  join: (id: number, password: string) =>
    api.post(`/companies/${id}/join`, { password }),
  leave: (id: number) => api.post(`/companies/${id}/leave`),
  members: (id: number) => api.get(`/companies/${id}/members`),
  updateMember: (companyId: number, userId: number, role: string) =>
    api.put(`/companies/${companyId}/members/${userId}`, { role }),
  removeMember: (companyId: number, userId: number) =>
    api.delete(`/companies/${companyId}/members/${userId}`),
  uploadLogo: (id: number, file: File) => {
    const formData = new FormData()
    formData.append('logo', file)
    return api.post(`/companies/${id}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}

export const channelAPI = {
  list: (companyId: number) => api.get(`/companies/${companyId}/channels`),
  create: (companyId: number, data: { name: string; description?: string; is_private?: boolean }) =>
    api.post(`/companies/${companyId}/channels`, data),
  get: (id: number) => api.get(`/channels/${id}`),
  update: (id: number, data: { name?: string; description?: string; is_private?: boolean }) =>
    api.put(`/channels/${id}`, data),
  delete: (id: number) => api.delete(`/channels/${id}`),
  members: (id: number) => api.get(`/channels/${id}/members`),
  addMember: (id: number, userId: number) =>
    api.post(`/channels/${id}/members`, { user_id: userId }),
  removeMember: (id: number, userId: number) =>
    api.delete(`/channels/${id}/members/${userId}`),
  requestJoin: (id: number, message?: string) =>
    api.post(`/channels/${id}/join-request`, { message }),
  joinRequests: (id: number) => api.get(`/channels/${id}/join-requests`),
  handleJoinRequest: (channelId: number, requestId: number, action: 'approve' | 'reject') =>
    api.put(`/channels/${channelId}/join-requests/${requestId}`, { action }),
}

export const messageAPI = {
  channelMessages: (channelId: number, params?: { before?: number; limit?: number }) =>
    api.get(`/channels/${channelId}/messages`, { params }),
  sendToChannel: (channelId: number, data: { content: string; parent_id?: number }) =>
    api.post(`/channels/${channelId}/messages`, data),
  update: (id: number, content: string) =>
    api.put(`/messages/${id}`, { content }),
  delete: (id: number) => api.delete(`/messages/${id}`),
  typing: (channelId: number) => api.post(`/channels/${channelId}/typing`),
}

export const conversationAPI = {
  list: () => api.get('/conversations'),
  create: (userId: number) => api.post('/conversations', { user_id: userId }),
  get: (id: number) => api.get(`/conversations/${id}`),
  delete: (id: number) => api.delete(`/conversations/${id}`),
  accept: (id: number) => api.post(`/conversations/${id}/accept`),
  reject: (id: number) => api.post(`/conversations/${id}/reject`),
  messages: (id: number, params?: { before?: number; limit?: number }) =>
    api.get(`/conversations/${id}/messages`, { params }),
  sendMessage: (id: number, data: { content: string; parent_id?: number }) =>
    api.post(`/conversations/${id}/messages`, data),
  typing: (id: number) => api.post(`/conversations/${id}/typing`),
}

export const reactionAPI = {
  list: (messageId: number) => api.get(`/messages/${messageId}/reactions`),
  add: (messageId: number, emoji: string) =>
    api.post(`/messages/${messageId}/reactions`, { emoji }),
  toggle: (messageId: number, emoji: string) =>
    api.post(`/messages/${messageId}/reactions/toggle`, { emoji }),
  remove: (messageId: number, emoji: string) =>
    api.delete(`/messages/${messageId}/reactions/${encodeURIComponent(emoji)}`),
  emojis: () => api.get('/emojis'),
}

export const fileAPI = {
  uploadToChannel: (channelId: number, file: File, message?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (message) formData.append('message', message)
    return api.post(`/channels/${channelId}/files`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  uploadToConversation: (conversationId: number, file: File, message?: string) => {
    const formData = new FormData()
    formData.append('file', file)
    if (message) formData.append('message', message)
    return api.post(`/conversations/${conversationId}/files`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  channelFiles: (channelId: number, params?: { type?: string; search?: string; page?: number }) =>
    api.get(`/channels/${channelId}/files`, { params }),
  conversationFiles: (conversationId: number, params?: { type?: string; page?: number }) =>
    api.get(`/conversations/${conversationId}/files`, { params }),
  get: (id: number) => api.get(`/files/${id}`),
  download: (id: number) => api.get(`/files/${id}/download`, { responseType: 'blob' }),
  delete: (id: number) => api.delete(`/files/${id}`),
}
