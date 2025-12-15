<template>
  <div class="channel-view">
    <!-- Channel Header -->
    <div class="channel-header">
      <div class="header-info">
        <HashtagIcon class="w-5 h-5 text-gray-400 mr-2" />
        <h1 class="channel-title">{{ companyStore.currentChannel?.name }}</h1>
        <span v-if="companyStore.currentChannel?.description" class="channel-description">
          {{ companyStore.currentChannel.description }}
        </span>
      </div>
      <div class="header-actions">
        <button class="header-btn" title="Mitglieder">
          <UsersIcon class="w-5 h-5" />
          <span>{{ companyStore.currentChannel?.members_count }}</span>
        </button>
        <button class="header-btn" title="Suchen">
          <MagnifyingGlassIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Messages Area -->
    <div
      ref="messagesContainer"
      class="messages-container"
      @scroll="handleScroll"
    >
      <!-- Load More -->
      <div v-if="chatStore.hasMore" class="load-more">
        <button
          class="load-more-btn"
          :disabled="chatStore.loading"
          @click="loadMore"
        >
          <LoadingSpinner v-if="chatStore.loading" size="sm" />
          <span v-else>Ältere Nachrichten laden</span>
        </button>
      </div>

      <!-- Messages -->
      <MessageList :messages="chatStore.sortedMessages" />

      <!-- Typing Indicator -->
      <TypingIndicator
        v-if="chatStore.typingUsernames.length > 0"
        :usernames="chatStore.typingUsernames"
      />
    </div>

    <!-- Message Input -->
    <MessageInput
      :channel-id="channelId"
      @send="sendMessage"
      @typing="handleTyping"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useCompanyStore } from '@/stores/company'
import { useChatStore } from '@/stores/chat'
import { HashtagIcon, UsersIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import MessageList from '@/components/chat/MessageList.vue'
import MessageInput from '@/components/chat/MessageInput.vue'
import TypingIndicator from '@/components/chat/TypingIndicator.vue'

const route = useRoute()
const companyStore = useCompanyStore()
const chatStore = useChatStore()

const messagesContainer = ref<HTMLElement | null>(null)

const channelId = computed(() => Number(route.params.channelId))

// Channel wechseln
watch(channelId, async (newId, oldId) => {
  if (newId && newId !== oldId) {
    await loadChannel(newId)
  }
}, { immediate: true })

async function loadChannel(id: number) {
  // Channel in Store setzen
  const channel = companyStore.channels.find(c => c.id === id)
  if (channel) {
    await companyStore.selectChannel(channel)
  }

  // Nachrichten laden
  chatStore.clearMessages()
  await chatStore.loadMessages(id)

  // WebSocket subscriben
  chatStore.subscribeToChannelMessages(id)

  // Zum Ende scrollen
  await nextTick()
  scrollToBottom()
}

async function loadMore() {
  await chatStore.loadMessages(channelId.value, true)
}

function sendMessage(content: string) {
  chatStore.sendMessage(channelId.value, content)
  nextTick(() => scrollToBottom())
}

function handleTyping() {
  chatStore.sendTyping(channelId.value)
}

function handleScroll(event: Event) {
  const target = event.target as HTMLElement
  // Auto-Load wenn oben angekommen
  if (target.scrollTop < 100 && chatStore.hasMore && !chatStore.loading) {
    loadMore()
  }
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

onMounted(() => {
  // Scroll bei neuen Nachrichten (wenn bereits unten)
  watch(() => chatStore.messages.length, () => {
    if (messagesContainer.value) {
      const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value
      const isNearBottom = scrollHeight - scrollTop - clientHeight < 100
      if (isNearBottom) {
        nextTick(() => scrollToBottom())
      }
    }
  })
})

onUnmounted(() => {
  chatStore.clearMessages()
})
</script>

<style scoped>
.channel-view {
  @apply flex flex-col h-full;
}

.channel-header {
  @apply h-12 px-4 flex items-center justify-between border-b border-chat-hover bg-chat flex-shrink-0;
}

.header-info {
  @apply flex items-center;
}

.channel-title {
  @apply font-semibold text-gray-100;
}

.channel-description {
  @apply ml-3 text-sm text-gray-400 truncate max-w-md;
}

.header-actions {
  @apply flex items-center gap-2;
}

.header-btn {
  @apply flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.messages-container {
  @apply flex-1 overflow-y-auto px-4 py-4;
}

.load-more {
  @apply flex justify-center py-4;
}

.load-more-btn {
  @apply px-4 py-2 text-sm text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors disabled:opacity-50;
}
</style>
