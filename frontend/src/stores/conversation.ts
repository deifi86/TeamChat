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

      if (loadMore && messages.value.length > 0 && messages.value[0]) {
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
