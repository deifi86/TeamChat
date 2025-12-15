<template>
  <div class="conversation-view">
    <!-- Header -->
    <div class="conversation-header">
      <div class="header-info">
        <Avatar
          :src="conversationStore.currentConversation?.other_user.avatar_url"
          :alt="conversationStore.currentConversation?.other_user.username"
          :status="conversationStore.currentConversation?.other_user.status"
          show-status
          size="sm"
        />
        <div class="user-info">
          <span class="username">{{ conversationStore.currentConversation?.other_user.username }}</span>
          <span class="status-text">{{ statusText }}</span>
        </div>
      </div>
    </div>

    <!-- Pending Request Notice -->
    <div
      v-if="conversationStore.currentConversation?.is_pending_my_acceptance"
      class="pending-notice"
    >
      <p class="notice-text">
        <strong>{{ conversationStore.currentConversation?.other_user.username }}</strong>
        möchte dir eine Nachricht senden.
      </p>
      <div class="notice-actions">
        <Button variant="primary" @click="acceptConversation">
          Akzeptieren
        </Button>
        <Button variant="ghost" @click="rejectConversation">
          Ablehnen
        </Button>
      </div>
    </div>

    <!-- Messages -->
    <template v-else>
      <div ref="messagesContainer" class="messages-container">
        <div v-if="conversationStore.hasMore" class="load-more">
          <button class="load-more-btn" @click="loadMore">
            Ältere Nachrichten laden
          </button>
        </div>

        <MessageList :messages="conversationStore.sortedMessages" />
      </div>

      <MessageInput
        :channel-id="conversationId"
        placeholder="Nachricht schreiben..."
        @send="sendMessage"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConversationStore } from '@/stores/conversation'
import Avatar from '@/components/common/Avatar.vue'
import Button from '@/components/common/Button.vue'
import MessageList from '@/components/chat/MessageList.vue'
import MessageInput from '@/components/chat/MessageInput.vue'

const route = useRoute()
const router = useRouter()
const conversationStore = useConversationStore()

const messagesContainer = ref<HTMLElement | null>(null)

const conversationId = computed(() => Number(route.params.conversationId))

const statusText = computed(() => {
  const status = conversationStore.currentConversation?.other_user.status
  const map = {
    available: 'Online',
    busy: 'Beschäftigt',
    away: 'Abwesend',
    offline: 'Offline',
  }
  return status ? map[status] : ''
})

watch(conversationId, async (newId) => {
  if (newId) {
    const conv = conversationStore.conversations.find(c => c.id === newId)
    if (conv) {
      await conversationStore.selectConversation(conv)
      nextTick(() => scrollToBottom())
    }
  }
}, { immediate: true })

async function loadMore() {
  await conversationStore.loadMessages(conversationId.value, true)
}

async function sendMessage(content: string) {
  await conversationStore.sendMessage(conversationId.value, content)
  nextTick(() => scrollToBottom())
}

async function acceptConversation() {
  await conversationStore.acceptConversation(conversationId.value)
}

async function rejectConversation() {
  await conversationStore.rejectConversation(conversationId.value)
  router.push('/app/dm')
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}
</script>

<style scoped>
.conversation-view {
  @apply flex flex-col h-full;
}

.conversation-header {
  @apply h-12 px-4 flex items-center border-b border-chat-hover bg-chat;
}

.header-info {
  @apply flex items-center gap-3;
}

.user-info {
  @apply flex flex-col;
}

.username {
  @apply font-semibold text-gray-100;
}

.status-text {
  @apply text-xs text-gray-400;
}

.pending-notice {
  @apply flex-1 flex flex-col items-center justify-center p-8 text-center;
}

.notice-text {
  @apply text-gray-300 mb-4;
}

.notice-actions {
  @apply flex gap-3;
}

.messages-container {
  @apply flex-1 overflow-y-auto px-4 py-4;
}

.load-more {
  @apply flex justify-center py-4;
}

.load-more-btn {
  @apply text-sm text-gray-400 hover:text-gray-200;
}
</style>
