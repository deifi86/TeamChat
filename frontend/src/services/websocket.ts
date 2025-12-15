import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from '@/stores/auth'

// Pusher global machen für Laravel Echo
declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<any>
  }
}

window.Pusher = Pusher

let echoInstance: Echo<any> | null = null

export function initializeEcho(): Echo<any> {
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

export function getEcho(): Echo<any> | null {
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
