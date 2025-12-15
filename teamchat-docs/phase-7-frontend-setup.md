# Phase 7: Frontend Setup (Woche 14-16)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Electron App mit Vue.js 3 und TypeScript
- Pinia Store für State Management
- Vue Router für Navigation
- Laravel Echo für WebSocket-Verbindung
- Axios für API-Kommunikation
- TailwindCSS für Styling
- Basis-Projektstruktur

---

## 7.1 Projekt-Initialisierung [FE]

### 7.1.1 Electron + Vue Projekt erstellen
- [ ] **Erledigt**

→ *Abhängig von Phase 6 abgeschlossen*

**Durchführung:**
```bash
cd teamchat

# Vue Projekt erstellen
npm create vue@latest frontend

# Optionen wählen:
# ✔ Add TypeScript? Yes
# ✔ Add JSX Support? No
# ✔ Add Vue Router? Yes
# ✔ Add Pinia? Yes
# ✔ Add Vitest? Yes
# ✔ Add ESLint? Yes
# ✔ Add Prettier? Yes

cd frontend
npm install

# Electron hinzufügen
npm install electron electron-builder --save-dev
npm install @electron-toolkit/preload @electron-toolkit/utils
```

**Akzeptanzkriterien:**
- [ ] `npm run dev` startet Vue Dev Server
- [ ] TypeScript ist konfiguriert
- [ ] Vue Router und Pinia sind installiert

---

### 7.1.2 Zusätzliche Dependencies installieren
- [ ] **Erledigt**

**Durchführung:**
```bash
cd frontend

# API & WebSocket
npm install axios laravel-echo pusher-js

# UI
npm install -D tailwindcss postcss autoprefixer
npm install @headlessui/vue @heroicons/vue
npm install vue-toastification@next

# Utilities
npm install date-fns
npm install lodash-es
npm install @vueuse/core

# TypeScript Types
npm install -D @types/lodash-es
```

---

### 7.1.3 TailwindCSS konfigurieren
- [ ] **Erledigt**

**Durchführung:**
```bash
cd frontend
npx tailwindcss init -p
```

**Datei:** `frontend/tailwind.config.js`
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
        sidebar: {
          DEFAULT: '#1e1e2e',
          light: '#2a2a3e',
          dark: '#181825',
        },
        chat: {
          DEFAULT: '#313244',
          hover: '#45475a',
          active: '#585b70',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
    },
  },
  plugins: [],
}
```

**Datei:** `frontend/src/assets/main.css`
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    --color-bg-primary: #1e1e2e;
    --color-bg-secondary: #313244;
    --color-text-primary: #cdd6f4;
    --color-text-secondary: #a6adc8;
    --color-accent: #89b4fa;
  }

  body {
    @apply bg-sidebar text-gray-100 antialiased;
    font-family: 'Inter', system-ui, sans-serif;
  }

  /* Scrollbar Styling */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  ::-webkit-scrollbar-track {
    @apply bg-sidebar-dark;
  }

  ::-webkit-scrollbar-thumb {
    @apply bg-chat-hover rounded-full;
  }

  ::-webkit-scrollbar-thumb:hover {
    @apply bg-chat-active;
  }
}

@layer components {
  .btn {
    @apply px-4 py-2 rounded-lg font-medium transition-colors duration-200;
  }

  .btn-primary {
    @apply bg-primary-600 text-white hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-sidebar;
  }

  .btn-secondary {
    @apply bg-chat text-gray-100 hover:bg-chat-hover;
  }

  .btn-danger {
    @apply bg-red-600 text-white hover:bg-red-700;
  }

  .btn-ghost {
    @apply text-gray-400 hover:text-gray-100 hover:bg-chat-hover;
  }

  .input {
    @apply w-full px-4 py-2 bg-chat border border-chat-hover rounded-lg text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  }

  .card {
    @apply bg-chat rounded-xl p-4;
  }
}
```

---

### 7.1.4 Electron Hauptprozess einrichten
- [ ] **Erledigt**

**Datei:** `frontend/electron/main.ts`
```typescript
import { app, BrowserWindow, ipcMain, shell } from 'electron'
import { join } from 'path'
import { electronApp, optimizer, is } from '@electron-toolkit/utils'

let mainWindow: BrowserWindow | null = null

function createWindow(): void {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 800,
    minHeight: 600,
    show: false,
    frame: false, // Frameless für custom titlebar
    titleBarStyle: 'hidden',
    trafficLightPosition: { x: 15, y: 15 },
    webPreferences: {
      preload: join(__dirname, '../preload/index.js'),
      sandbox: false,
      contextIsolation: true,
      nodeIntegration: false,
    },
    icon: join(__dirname, '../../resources/icon.png'),
  })

  mainWindow.on('ready-to-show', () => {
    mainWindow?.show()
  })

  mainWindow.webContents.setWindowOpenHandler((details) => {
    shell.openExternal(details.url)
    return { action: 'deny' }
  })

  // HMR für Entwicklung
  if (is.dev && process.env['ELECTRON_RENDERER_URL']) {
    mainWindow.loadURL(process.env['ELECTRON_RENDERER_URL'])
  } else {
    mainWindow.loadFile(join(__dirname, '../renderer/index.html'))
  }
}

// Window Controls IPC Handler
ipcMain.on('window-minimize', () => mainWindow?.minimize())
ipcMain.on('window-maximize', () => {
  if (mainWindow?.isMaximized()) {
    mainWindow.unmaximize()
  } else {
    mainWindow?.maximize()
  }
})
ipcMain.on('window-close', () => mainWindow?.close())
ipcMain.handle('window-is-maximized', () => mainWindow?.isMaximized())

// App Events
app.whenReady().then(() => {
  electronApp.setAppUserModelId('com.teamchat.app')

  app.on('browser-window-created', (_, window) => {
    optimizer.watchWindowShortcuts(window)
  })

  createWindow()

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow()
    }
  })
})

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
  }
})
```

**Datei:** `frontend/electron/preload.ts`
```typescript
import { contextBridge, ipcRenderer } from 'electron'

// Expose protected methods to renderer
contextBridge.exposeInMainWorld('electronAPI', {
  // Window controls
  minimizeWindow: () => ipcRenderer.send('window-minimize'),
  maximizeWindow: () => ipcRenderer.send('window-maximize'),
  closeWindow: () => ipcRenderer.send('window-close'),
  isMaximized: () => ipcRenderer.invoke('window-is-maximized'),

  // Notifications
  showNotification: (title: string, body: string) => {
    new Notification(title, { body })
  },

  // Platform info
  platform: process.platform,
})

// Types für TypeScript
declare global {
  interface Window {
    electronAPI: {
      minimizeWindow: () => void
      maximizeWindow: () => void
      closeWindow: () => void
      isMaximized: () => Promise<boolean>
      showNotification: (title: string, body: string) => void
      platform: NodeJS.Platform
    }
  }
}
```

---

### 7.1.5 Package.json für Electron anpassen
- [ ] **Erledigt**

**Datei:** `frontend/package.json` (relevante Teile):
```json
{
  "name": "teamchat",
  "version": "1.0.0",
  "description": "TeamChat - Microsoft Teams Alternative",
  "main": "dist-electron/main.js",
  "scripts": {
    "dev": "vite",
    "dev:electron": "electron-vite dev",
    "build": "vue-tsc && vite build",
    "build:electron": "electron-vite build",
    "preview": "vite preview",
    "test:unit": "vitest",
    "lint": "eslint . --ext .vue,.js,.jsx,.cjs,.mjs,.ts,.tsx,.cts,.mts --fix",
    "format": "prettier --write src/",
    "electron:dev": "electron-vite dev",
    "electron:build": "electron-vite build && electron-builder",
    "electron:build:win": "electron-vite build && electron-builder --win",
    "electron:build:mac": "electron-vite build && electron-builder --mac",
    "electron:build:linux": "electron-vite build && electron-builder --linux"
  },
  "build": {
    "appId": "com.teamchat.app",
    "productName": "TeamChat",
    "directories": {
      "output": "release"
    },
    "files": [
      "dist-electron",
      "dist"
    ],
    "win": {
      "target": ["nsis"],
      "icon": "resources/icon.ico"
    },
    "mac": {
      "target": ["dmg"],
      "icon": "resources/icon.icns"
    },
    "linux": {
      "target": ["AppImage", "deb"],
      "icon": "resources/icon.png"
    }
  }
}
```

---

### 7.1.6 Vite Config für Electron
- [ ] **Erledigt**

**Datei:** `frontend/electron.vite.config.ts`
```typescript
import { resolve } from 'path'
import { defineConfig, externalizeDepsPlugin } from 'electron-vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  main: {
    plugins: [externalizeDepsPlugin()],
    build: {
      rollupOptions: {
        input: {
          index: resolve(__dirname, 'electron/main.ts')
        }
      }
    }
  },
  preload: {
    plugins: [externalizeDepsPlugin()],
    build: {
      rollupOptions: {
        input: {
          index: resolve(__dirname, 'electron/preload.ts')
        }
      }
    }
  },
  renderer: {
    root: '.',
    build: {
      rollupOptions: {
        input: {
          index: resolve(__dirname, 'index.html')
        }
      }
    },
    plugins: [vue()],
    resolve: {
      alias: {
        '@': resolve(__dirname, 'src')
      }
    }
  }
})
```

---

## 7.2 API & WebSocket Setup [FE]

### 7.2.1 API Client erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/services/api.ts`
```typescript
import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'
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
```

---

### 7.2.2 WebSocket Client erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/services/websocket.ts`
```typescript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from '@/stores/auth'

// Pusher global machen für Laravel Echo
declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo
  }
}

window.Pusher = Pusher

let echoInstance: Echo | null = null

export function initializeEcho(): Echo {
  if (echoInstance) {
    return echoInstance
  }

  const authStore = useAuthStore()

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'teamchat-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: 'application/json',
      },
    },
  })

  window.Echo = echoInstance

  return echoInstance
}

export function getEcho(): Echo | null {
  return echoInstance
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

// Channel Subscriptions
export function subscribeToChannel(channelId: number, callbacks: {
  onMessage?: (data: any) => void
  onMessageEdited?: (data: any) => void
  onMessageDeleted?: (data: any) => void
  onTyping?: (data: any) => void
  onReactionAdded?: (data: any) => void
  onReactionRemoved?: (data: any) => void
}) {
  const echo = getEcho()
  if (!echo) return null

  const channel = echo.private(`channel.${channelId}`)

  if (callbacks.onMessage) {
    channel.listen('.message.new', callbacks.onMessage)
  }
  if (callbacks.onMessageEdited) {
    channel.listen('.message.edited', callbacks.onMessageEdited)
  }
  if (callbacks.onMessageDeleted) {
    channel.listen('.message.deleted', callbacks.onMessageDeleted)
  }
  if (callbacks.onTyping) {
    channel.listen('.user.typing', callbacks.onTyping)
  }
  if (callbacks.onReactionAdded) {
    channel.listen('.reaction.added', callbacks.onReactionAdded)
  }
  if (callbacks.onReactionRemoved) {
    channel.listen('.reaction.removed', callbacks.onReactionRemoved)
  }

  return channel
}

export function subscribeToConversation(conversationId: number, callbacks: {
  onMessage?: (data: any) => void
  onMessageEdited?: (data: any) => void
  onMessageDeleted?: (data: any) => void
  onTyping?: (data: any) => void
  onReactionAdded?: (data: any) => void
  onReactionRemoved?: (data: any) => void
}) {
  const echo = getEcho()
  if (!echo) return null

  const channel = echo.private(`conversation.${conversationId}`)

  if (callbacks.onMessage) {
    channel.listen('.message.new', callbacks.onMessage)
  }
  if (callbacks.onMessageEdited) {
    channel.listen('.message.edited', callbacks.onMessageEdited)
  }
  if (callbacks.onMessageDeleted) {
    channel.listen('.message.deleted', callbacks.onMessageDeleted)
  }
  if (callbacks.onTyping) {
    channel.listen('.user.typing', callbacks.onTyping)
  }
  if (callbacks.onReactionAdded) {
    channel.listen('.reaction.added', callbacks.onReactionAdded)
  }
  if (callbacks.onReactionRemoved) {
    channel.listen('.reaction.removed', callbacks.onReactionRemoved)
  }

  return channel
}

export function subscribeToUser(userId: number, callbacks: {
  onConversationRequest?: (data: any) => void
  onConversationAccepted?: (data: any) => void
}) {
  const echo = getEcho()
  if (!echo) return null

  const channel = echo.private(`user.${userId}`)

  if (callbacks.onConversationRequest) {
    channel.listen('.conversation.request', callbacks.onConversationRequest)
  }
  if (callbacks.onConversationAccepted) {
    channel.listen('.conversation.accepted', callbacks.onConversationAccepted)
  }

  return channel
}

export function subscribeToCompanyPresence(companyId: number, callbacks: {
  onHere?: (users: any[]) => void
  onJoining?: (user: any) => void
  onLeaving?: (user: any) => void
  onStatusChanged?: (data: any) => void
}) {
  const echo = getEcho()
  if (!echo) return null

  const channel = echo.join(`company.${companyId}`)

  if (callbacks.onHere) {
    channel.here(callbacks.onHere)
  }
  if (callbacks.onJoining) {
    channel.joining(callbacks.onJoining)
  }
  if (callbacks.onLeaving) {
    channel.leaving(callbacks.onLeaving)
  }
  if (callbacks.onStatusChanged) {
    channel.listen('.user.status_changed', callbacks.onStatusChanged)
  }

  return channel
}

export function leaveChannel(channelName: string): void {
  const echo = getEcho()
  if (echo) {
    echo.leave(channelName)
  }
}
```

---

### 7.2.3 Environment Variablen
- [ ] **Erledigt**

**Datei:** `frontend/.env`
```env
VITE_API_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=teamchat-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```

**Datei:** `frontend/.env.production`
```env
VITE_API_URL=https://api.teamchat.example.com/api
VITE_REVERB_APP_KEY=teamchat-key
VITE_REVERB_HOST=ws.teamchat.example.com
VITE_REVERB_PORT=443
```

---

## 7.3 Pinia Stores [FE]

### 7.3.1 Auth Store erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/stores/auth.ts`
```typescript
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
```

---

### 7.3.2 Company Store erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/stores/company.ts`
```typescript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { companyAPI, channelAPI } from '@/services/api'
import { subscribeToCompanyPresence, leaveChannel } from '@/services/websocket'

export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  owner: {
    id: number
    username: string
    avatar_url: string
  }
  members_count: number
  my_role: 'admin' | 'user'
  is_owner: boolean
  created_at: string
}

export interface Channel {
  id: number
  name: string
  description: string | null
  is_private: boolean
  is_member: boolean
  members_count: number
  has_pending_request?: boolean
}

export interface OnlineUser {
  id: number
  username: string
  avatar_url: string
  status: string
}

export const useCompanyStore = defineStore('company', () => {
  // State
  const companies = ref<Company[]>([])
  const currentCompany = ref<Company | null>(null)
  const channels = ref<Channel[]>([])
  const currentChannel = ref<Channel | null>(null)
  const onlineUsers = ref<OnlineUser[]>([])
  const loading = ref(false)

  // Getters
  const myChannels = computed(() => channels.value.filter(c => c.is_member))
  const otherChannels = computed(() => channels.value.filter(c => !c.is_member))
  const isAdmin = computed(() => currentCompany.value?.my_role === 'admin')
  const isOwner = computed(() => currentCompany.value?.is_owner)

  // Actions
  async function fetchCompanies() {
    loading.value = true
    try {
      const response = await companyAPI.myCompanies()
      companies.value = response.data.companies
    } finally {
      loading.value = false
    }
  }

  async function selectCompany(company: Company) {
    // Alte Company-Subscription verlassen
    if (currentCompany.value) {
      leaveChannel(`presence-company.${currentCompany.value.id}`)
    }

    currentCompany.value = company
    currentChannel.value = null

    // Channels laden
    await fetchChannels()

    // Presence Channel subscriben
    subscribeToCompanyPresence(company.id, {
      onHere: (users) => {
        onlineUsers.value = users
      },
      onJoining: (user) => {
        if (!onlineUsers.value.find(u => u.id === user.id)) {
          onlineUsers.value.push(user)
        }
      },
      onLeaving: (user) => {
        onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id)
      },
      onStatusChanged: (data) => {
        const user = onlineUsers.value.find(u => u.id === data.user_id)
        if (user) {
          user.status = data.status
        }
      },
    })
  }

  async function fetchChannels() {
    if (!currentCompany.value) return

    const response = await channelAPI.list(currentCompany.value.id)
    channels.value = response.data.channels
  }

  async function selectChannel(channel: Channel) {
    currentChannel.value = channel
  }

  async function createCompany(data: { name: string; join_password: string }) {
    const response = await companyAPI.create(data)
    companies.value.push(response.data.company)
    return response.data.company
  }

  async function joinCompany(companyId: number, password: string) {
    const response = await companyAPI.join(companyId, password)
    companies.value.push(response.data.company)
    return response.data.company
  }

  async function leaveCompany(companyId: number) {
    await companyAPI.leave(companyId)
    companies.value = companies.value.filter(c => c.id !== companyId)
    if (currentCompany.value?.id === companyId) {
      currentCompany.value = null
      channels.value = []
    }
  }

  async function createChannel(data: { name: string; description?: string; is_private?: boolean }) {
    if (!currentCompany.value) return

    const response = await channelAPI.create(currentCompany.value.id, data)
    channels.value.push({
      ...response.data.channel,
      is_member: true,
      has_pending_request: false,
    })
    return response.data.channel
  }

  async function requestJoinChannel(channelId: number, message?: string) {
    await channelAPI.requestJoin(channelId, message)
    const channel = channels.value.find(c => c.id === channelId)
    if (channel) {
      channel.has_pending_request = true
    }
  }

  function isUserOnline(userId: number): boolean {
    return onlineUsers.value.some(u => u.id === userId)
  }

  function getUserStatus(userId: number): string {
    const user = onlineUsers.value.find(u => u.id === userId)
    return user?.status || 'offline'
  }

  return {
    // State
    companies,
    currentCompany,
    channels,
    currentChannel,
    onlineUsers,
    loading,
    // Getters
    myChannels,
    otherChannels,
    isAdmin,
    isOwner,
    // Actions
    fetchCompanies,
    selectCompany,
    fetchChannels,
    selectChannel,
    createCompany,
    joinCompany,
    leaveCompany,
    createChannel,
    requestJoinChannel,
    isUserOnline,
    getUserStatus,
  }
})
```

---

### 7.3.3 Chat Store erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/stores/chat.ts`
```typescript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { messageAPI, reactionAPI } from '@/services/api'
import { subscribeToChannel, leaveChannel } from '@/services/websocket'
import { useAuthStore } from './auth'
import { throttle } from 'lodash-es'

export interface Message {
  id: number
  content: string
  content_type: 'text' | 'file' | 'image' | 'emoji'
  sender: {
    id: number
    username: string
    avatar_url: string
    status?: string
  }
  reactions: Reaction[]
  parent_id: number | null
  edited_at: string | null
  created_at: string
  file?: {
    id: number
    original_name: string
    url: string
    thumbnail_url: string | null
    is_image: boolean
    human_size: string
  }
}

export interface Reaction {
  emoji: string
  count: number
  user_ids: number[]
}

export interface TypingUser {
  id: number
  username: string
  timestamp: number
}

export const useChatStore = defineStore('chat', () => {
  // State
  const messages = ref<Message[]>([])
  const hasMore = ref(false)
  const loading = ref(false)
  const sending = ref(false)
  const typingUsers = ref<TypingUser[]>([])
  const currentChannelId = ref<number | null>(null)

  // Typing Timeout (5 Sekunden)
  const TYPING_TIMEOUT = 5000

  // Getters
  const sortedMessages = computed(() => {
    return [...messages.value].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    )
  })

  const typingUsernames = computed(() => {
    const now = Date.now()
    return typingUsers.value
      .filter(u => now - u.timestamp < TYPING_TIMEOUT)
      .map(u => u.username)
  })

  // Actions
  async function loadMessages(channelId: number, loadMore = false) {
    if (loading.value) return

    loading.value = true

    try {
      const params: { before?: number; limit?: number } = { limit: 50 }

      if (loadMore && messages.value.length > 0) {
        params.before = messages.value[0].id
      }

      const response = await messageAPI.channelMessages(channelId, params)

      if (loadMore) {
        messages.value = [...response.data.messages, ...messages.value]
      } else {
        messages.value = response.data.messages
      }

      hasMore.value = response.data.has_more
    } finally {
      loading.value = false
    }
  }

  async function sendMessage(channelId: number, content: string, parentId?: number) {
    sending.value = true

    try {
      const response = await messageAPI.sendToChannel(channelId, {
        content,
        parent_id: parentId,
      })

      // Nachricht lokal hinzufügen
      messages.value.push(response.data.data)

      return response.data.data
    } finally {
      sending.value = false
    }
  }

  async function editMessage(messageId: number, content: string) {
    const response = await messageAPI.update(messageId, content)

    // Lokales Update
    const index = messages.value.findIndex(m => m.id === messageId)
    if (index !== -1) {
      messages.value[index] = response.data.data
    }
  }

  async function deleteMessage(messageId: number) {
    await messageAPI.delete(messageId)

    // Lokal entfernen
    messages.value = messages.value.filter(m => m.id !== messageId)
  }

  async function toggleReaction(messageId: number, emoji: string) {
    const response = await reactionAPI.toggle(messageId, emoji)

    // Lokales Update
    const message = messages.value.find(m => m.id === messageId)
    if (message) {
      const authStore = useAuthStore()
      const userId = authStore.currentUserId

      if (response.data.action === 'added') {
        const reaction = message.reactions.find(r => r.emoji === emoji)
        if (reaction) {
          reaction.count++
          reaction.user_ids.push(userId!)
        } else {
          message.reactions.push({
            emoji,
            count: 1,
            user_ids: [userId!],
          })
        }
      } else {
        const reaction = message.reactions.find(r => r.emoji === emoji)
        if (reaction) {
          reaction.count--
          reaction.user_ids = reaction.user_ids.filter(id => id !== userId)
          if (reaction.count === 0) {
            message.reactions = message.reactions.filter(r => r.emoji !== emoji)
          }
        }
      }
    }
  }

  // Throttled Typing Indicator
  const sendTyping = throttle(async (channelId: number) => {
    await messageAPI.typing(channelId)
  }, 3000)

  function subscribeToChannelMessages(channelId: number) {
    // Alte Subscription verlassen
    if (currentChannelId.value) {
      leaveChannel(`private-channel.${currentChannelId.value}`)
    }

    currentChannelId.value = channelId

    subscribeToChannel(channelId, {
      onMessage: (data) => {
        const authStore = useAuthStore()
        // Nur hinzufügen wenn nicht von uns selbst
        if (data.sender.id !== authStore.currentUserId) {
          messages.value.push(data)
        }
      },
      onMessageEdited: (data) => {
        const message = messages.value.find(m => m.id === data.id)
        if (message) {
          message.content = data.content
          message.edited_at = data.edited_at
        }
      },
      onMessageDeleted: (data) => {
        messages.value = messages.value.filter(m => m.id !== data.id)
      },
      onTyping: (data) => {
        const authStore = useAuthStore()
        if (data.user.id !== authStore.currentUserId) {
          const existingIndex = typingUsers.value.findIndex(u => u.id === data.user.id)
          if (existingIndex !== -1) {
            typingUsers.value[existingIndex].timestamp = Date.now()
          } else {
            typingUsers.value.push({
              ...data.user,
              timestamp: Date.now(),
            })
          }

          // Auto-Remove nach Timeout
          setTimeout(() => {
            typingUsers.value = typingUsers.value.filter(
              u => Date.now() - u.timestamp < TYPING_TIMEOUT
            )
          }, TYPING_TIMEOUT)
        }
      },
      onReactionAdded: (data) => {
        const message = messages.value.find(m => m.id === data.message_id)
        if (message) {
          const reaction = message.reactions.find(r => r.emoji === data.reaction.emoji)
          if (reaction) {
            reaction.count++
            reaction.user_ids.push(data.reaction.user.id)
          } else {
            message.reactions.push({
              emoji: data.reaction.emoji,
              count: 1,
              user_ids: [data.reaction.user.id],
            })
          }
        }
      },
      onReactionRemoved: (data) => {
        const message = messages.value.find(m => m.id === data.message_id)
        if (message) {
          const reaction = message.reactions.find(r => r.emoji === data.emoji)
          if (reaction) {
            reaction.count--
            reaction.user_ids = reaction.user_ids.filter(id => id !== data.user_id)
            if (reaction.count === 0) {
              message.reactions = message.reactions.filter(r => r.emoji !== data.emoji)
            }
          }
        }
      },
    })
  }

  function clearMessages() {
    messages.value = []
    hasMore.value = false
    typingUsers.value = []
  }

  return {
    // State
    messages,
    hasMore,
    loading,
    sending,
    typingUsers,
    // Getters
    sortedMessages,
    typingUsernames,
    // Actions
    loadMessages,
    sendMessage,
    editMessage,
    deleteMessage,
    toggleReaction,
    sendTyping,
    subscribeToChannelMessages,
    clearMessages,
  }
})
```

---

### 7.3.4 Conversation Store erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/stores/conversation.ts`
```typescript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { conversationAPI } from '@/services/api'
import { subscribeToConversation, subscribeToUser, leaveChannel } from '@/services/websocket'
import { useAuthStore } from './auth'

export interface Conversation {
  id: number
  other_user: {
    id: number
    username: string
    avatar_url: string
    status: string
  }
  is_accepted: boolean
  is_pending_my_acceptance: boolean
  last_message: {
    content: string
    created_at: string
    is_mine: boolean
  } | null
  unread_count: number
  updated_at: string
}

export interface DMMessage {
  id: number
  content: string
  content_type: string
  sender: {
    id: number
    username: string
    avatar_url: string
  }
  reactions: any[]
  parent_id: number | null
  edited_at: string | null
  created_at: string
}

export const useConversationStore = defineStore('conversation', () => {
  // State
  const conversations = ref<Conversation[]>([])
  const currentConversation = ref<Conversation | null>(null)
  const messages = ref<DMMessage[]>([])
  const hasMore = ref(false)
  const loading = ref(false)
  const pendingRequests = ref<Conversation[]>([])

  // Getters
  const acceptedConversations = computed(() =>
    conversations.value.filter(c => c.is_accepted)
  )

  const pendingConversations = computed(() =>
    conversations.value.filter(c => c.is_pending_my_acceptance)
  )

  const sortedMessages = computed(() =>
    [...messages.value].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    )
  )

  // Actions
  async function fetchConversations() {
    loading.value = true
    try {
      const response = await conversationAPI.list()
      conversations.value = response.data.conversations
    } finally {
      loading.value = false
    }
  }

  async function selectConversation(conversation: Conversation) {
    // Alte Subscription verlassen
    if (currentConversation.value) {
      leaveChannel(`private-conversation.${currentConversation.value.id}`)
    }

    currentConversation.value = conversation
    messages.value = []

    if (conversation.is_accepted) {
      await loadMessages(conversation.id)
      subscribeToConversationMessages(conversation.id)
    }
  }

  async function loadMessages(conversationId: number, loadMore = false) {
    if (loading.value) return

    loading.value = true

    try {
      const params: { before?: number; limit?: number } = { limit: 50 }

      if (loadMore && messages.value.length > 0) {
        params.before = messages.value[0].id
      }

      const response = await conversationAPI.messages(conversationId, params)

      if (loadMore) {
        messages.value = [...response.data.messages, ...messages.value]
      } else {
        messages.value = response.data.messages
      }

      hasMore.value = response.data.has_more
    } finally {
      loading.value = false
    }
  }

  async function sendMessage(conversationId: number, content: string) {
    const response = await conversationAPI.sendMessage(conversationId, { content })
    messages.value.push(response.data.data)
    return response.data.data
  }

  async function startConversation(userId: number) {
    const response = await conversationAPI.create(userId)
    const conversation = response.data.conversation

    // Zur Liste hinzufügen wenn neu
    if (!conversations.value.find(c => c.id === conversation.id)) {
      conversations.value.unshift(conversation)
    }

    return conversation
  }

  async function acceptConversation(conversationId: number) {
    const response = await conversationAPI.accept(conversationId)

    // Lokales Update
    const conv = conversations.value.find(c => c.id === conversationId)
    if (conv) {
      conv.is_accepted = true
      conv.is_pending_my_acceptance = false
    }

    return response.data.conversation
  }

  async function rejectConversation(conversationId: number) {
    await conversationAPI.reject(conversationId)

    // Aus Liste entfernen
    conversations.value = conversations.value.filter(c => c.id !== conversationId)

    if (currentConversation.value?.id === conversationId) {
      currentConversation.value = null
    }
  }

  function subscribeToConversationMessages(conversationId: number) {
    subscribeToConversation(conversationId, {
      onMessage: (data) => {
        const authStore = useAuthStore()
        if (data.sender.id !== authStore.currentUserId) {
          messages.value.push(data)
        }

        // Conversation in Liste aktualisieren
        const conv = conversations.value.find(c => c.id === conversationId)
        if (conv) {
          conv.last_message = {
            content: data.content,
            created_at: data.created_at,
            is_mine: data.sender.id === authStore.currentUserId,
          }
        }
      },
      onMessageEdited: (data) => {
        const message = messages.value.find(m => m.id === data.id)
        if (message) {
          message.content = data.content
          message.edited_at = data.edited_at
        }
      },
      onMessageDeleted: (data) => {
        messages.value = messages.value.filter(m => m.id !== data.id)
      },
    })
  }

  function subscribeToUserNotifications(userId: number) {
    subscribeToUser(userId, {
      onConversationRequest: (data) => {
        // Neue Anfrage zur Liste hinzufügen
        fetchConversations()
      },
      onConversationAccepted: (data) => {
        const conv = conversations.value.find(c => c.id === data.conversation_id)
        if (conv) {
          conv.is_accepted = true
        }
      },
    })
  }

  function clearMessages() {
    messages.value = []
    hasMore.value = false
  }

  return {
    // State
    conversations,
    currentConversation,
    messages,
    hasMore,
    loading,
    pendingRequests,
    // Getters
    acceptedConversations,
    pendingConversations,
    sortedMessages,
    // Actions
    fetchConversations,
    selectConversation,
    loadMessages,
    sendMessage,
    startConversation,
    acceptConversation,
    rejectConversation,
    subscribeToConversationMessages,
    subscribeToUserNotifications,
    clearMessages,
  }
})
```

---

## 7.4 Vue Router [FE]

### 7.4.1 Router konfigurieren
- [ ] **Erledigt**

**Datei:** `frontend/src/router/index.ts`
```typescript
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/app',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/app',
      name: 'app',
      component: () => import('@/views/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/views/HomeView.vue'),
        },
        {
          path: 'company/:companyId',
          name: 'company',
          component: () => import('@/views/CompanyView.vue'),
          children: [
            {
              path: 'channel/:channelId',
              name: 'channel',
              component: () => import('@/views/ChannelView.vue'),
            },
          ],
        },
        {
          path: 'dm',
          name: 'direct-messages',
          component: () => import('@/views/DirectMessagesView.vue'),
          children: [
            {
              path: ':conversationId',
              name: 'conversation',
              component: () => import('@/views/ConversationView.vue'),
            },
          ],
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/SettingsView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

// Navigation Guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Token vorhanden aber User nicht geladen
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  // Route erfordert Auth
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Route ist nur für Gäste
  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: 'app' })
  }

  next()
})

export default router
```

---

### 7.4.2 Route Types definieren
- [ ] **Erledigt**

**Datei:** `frontend/src/router/types.ts`
```typescript
import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guest?: boolean
    title?: string
  }
}
```

---

## 7.5 TypeScript Types [FE]

### 7.5.1 Globale Types definieren
- [ ] **Erledigt**

**Datei:** `frontend/src/types/index.ts`
```typescript
// User Types
export interface User {
  id: number
  email: string
  username: string
  avatar_url: string
  status: UserStatus
  status_text: string | null
  created_at: string
}

export type UserStatus = 'available' | 'busy' | 'away' | 'offline'

// Company Types
export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  owner: UserBasic
  members_count: number
  my_role: 'admin' | 'user'
  is_owner: boolean
  created_at: string
}

export interface CompanyMember extends UserBasic {
  role: 'admin' | 'user'
  joined_at: string
  is_owner: boolean
}

// Channel Types
export interface Channel {
  id: number
  company_id: number
  name: string
  description: string | null
  is_private: boolean
  is_member: boolean
  members_count: number
  has_pending_request?: boolean
  created_at?: string
}

export interface ChannelMember extends UserBasic {
  joined_at: string
}

export interface ChannelJoinRequest {
  id: number
  user: UserBasic
  message: string | null
  created_at: string
}

// Message Types
export interface Message {
  id: number
  content: string
  content_type: MessageContentType
  sender: UserBasic
  reactions: Reaction[]
  parent_id: number | null
  parent?: Message
  edited_at: string | null
  created_at: string
  file?: FileInfo
}

export type MessageContentType = 'text' | 'file' | 'image' | 'emoji'

export interface Reaction {
  emoji: string
  count: number
  user_ids: number[]
}

// Conversation Types
export interface Conversation {
  id: number
  other_user: UserBasic & { status: UserStatus }
  is_accepted: boolean
  is_pending_my_acceptance: boolean
  last_message: LastMessage | null
  unread_count: number
  updated_at: string
}

export interface LastMessage {
  content: string
  created_at: string
  is_mine: boolean
}

// File Types
export interface FileInfo {
  id: number
  original_name: string
  mime_type: string
  file_size: number
  human_size: string
  url: string
  thumbnail_url: string | null
  is_image: boolean
  is_compressed: boolean
  uploader?: UserBasic
  created_at: string
}

// Common Types
export interface UserBasic {
  id: number
  username: string
  avatar_url: string
  email?: string
  status?: UserStatus
}

export interface PaginationInfo {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

// WebSocket Event Types
export interface NewMessageEvent {
  id: number
  content: string
  content_type: MessageContentType
  sender: UserBasic
  parent_id: number | null
  created_at: string
}

export interface MessageEditedEvent {
  id: number
  content: string
  edited_at: string
}

export interface MessageDeletedEvent {
  id: number
}

export interface UserTypingEvent {
  user: {
    id: number
    username: string
  }
  timestamp: string
}

export interface ReactionAddedEvent {
  message_id: number
  reaction: {
    id: number
    emoji: string
    user: UserBasic
  }
}

export interface ReactionRemovedEvent {
  message_id: number
  emoji: string
  user_id: number
}

export interface UserStatusChangedEvent {
  user_id: number
  status: UserStatus
  status_text: string | null
}
```

---

## 7.6 App Entry Point [FE]

### 7.6.1 Main.ts konfigurieren
- [ ] **Erledigt**

**Datei:** `frontend/src/main.ts`
```typescript
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast, { POSITION, type PluginOptions } from 'vue-toastification'
import 'vue-toastification/dist/index.css'

import App from './App.vue'
import router from './router'
import './assets/main.css'

const app = createApp(App)

// Pinia
app.use(createPinia())

// Router
app.use(router)

// Toast Notifications
const toastOptions: PluginOptions = {
  position: POSITION.TOP_RIGHT,
  timeout: 4000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: 'button',
  icon: true,
  rtl: false,
  transition: 'Vue-Toastification__fade',
  maxToasts: 5,
  newestOnTop: true,
}
app.use(Toast, toastOptions)

app.mount('#app')
```

---

### 7.6.2 App.vue erstellen
- [ ] **Erledigt**

**Datei:** `frontend/src/App.vue`
```vue
<template>
  <div class="app">
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

onMounted(async () => {
  // Bei App-Start: User laden wenn Token vorhanden
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }
})
</script>

<style>
.app {
  min-height: 100vh;
  background-color: var(--color-bg-primary);
}
</style>
```

---

## 7.7 Tests & Abschluss [FE]

### 7.7.1 Frontend Test Setup
- [ ] **Erledigt**

**Datei:** `frontend/vitest.config.ts`
```typescript
import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      exclude: [...configDefaults.exclude, 'e2e/*'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      globals: true,
    },
  })
)
```

**Datei:** `frontend/src/stores/__tests__/auth.spec.ts`
```typescript
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
    })

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
```

---

### 7.7.2 Development Server starten
- [ ] **Erledigt**

**Durchführung:**
```bash
cd frontend

# Vue Dev Server (für Web-Entwicklung)
npm run dev

# Electron Dev (mit Hot Reload)
npm run electron:dev
```

**Akzeptanzkriterien:**
- [ ] `npm run dev` startet auf http://localhost:5173
- [ ] `npm run electron:dev` öffnet Electron Fenster
- [ ] Hot Reload funktioniert

---

### 7.7.3 Git Commit & Tag
- [ ] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 7: Frontend Setup - Electron, Vue, Pinia, WebSocket"
git tag v0.7.0
```

---

## Phase 7 Zusammenfassung

### Erstellte Dateien
```
frontend/
├── electron/
│   ├── main.ts          # Electron Hauptprozess
│   └── preload.ts       # Preload Script
├── src/
│   ├── assets/
│   │   └── main.css     # TailwindCSS Styles
│   ├── router/
│   │   ├── index.ts     # Vue Router
│   │   └── types.ts     # Route Types
│   ├── services/
│   │   ├── api.ts       # Axios API Client
│   │   └── websocket.ts # Laravel Echo
│   ├── stores/
│   │   ├── auth.ts      # Auth Store
│   │   ├── company.ts   # Company Store
│   │   ├── chat.ts      # Chat Store
│   │   └── conversation.ts # DM Store
│   ├── types/
│   │   └── index.ts     # TypeScript Types
│   ├── App.vue
│   └── main.ts
├── .env
├── .env.production
├── tailwind.config.js
├── electron.vite.config.ts
└── package.json
```

### Technologie-Stack
| Bereich | Technologie |
|---------|-------------|
| Framework | Vue 3 + TypeScript |
| Desktop | Electron |
| State | Pinia |
| Router | Vue Router 4 |
| Styling | TailwindCSS |
| API | Axios |
| WebSocket | Laravel Echo + Pusher |
| Icons | Heroicons |
| UI Components | HeadlessUI |
| Tests | Vitest |

### Pinia Stores
| Store | Verantwortung |
|-------|---------------|
| auth | Login, Logout, User-Daten |
| company | Firmen, Channels, Online-Status |
| chat | Channel-Nachrichten, Typing |
| conversation | Direct Messages |

### Nächste Phase
→ Weiter mit `phase-8-frontend-ui.md`
