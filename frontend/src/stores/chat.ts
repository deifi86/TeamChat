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

      if (loadMore && messages.value.length > 0 && messages.value[0]) {
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

      if (!userId) return

      if (response.data.action === 'added') {
        const reaction = message.reactions.find(r => r.emoji === emoji)
        if (reaction) {
          reaction.count++
          reaction.user_ids.push(userId)
        } else {
          message.reactions.push({
            emoji,
            count: 1,
            user_ids: [userId],
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
          if (existingIndex !== -1 && typingUsers.value[existingIndex]) {
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
