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
