<template>
  <aside class="dm-sidebar">
    <div class="sidebar-header">
      <h2 class="sidebar-title">Direktnachrichten</h2>
      <button
        class="new-dm-btn"
        title="Neue Nachricht"
        @click="showNewDMModal = true"
      >
        <PlusIcon class="w-5 h-5" />
      </button>
    </div>

    <div class="sidebar-content">
      <!-- Pending Requests -->
      <div v-if="conversationStore.pendingConversations.length > 0" class="section">
        <h3 class="section-title">Anfragen</h3>
        <div class="conversation-list">
          <button
            v-for="conv in conversationStore.pendingConversations"
            :key="conv.id"
            class="conversation-item pending"
            @click="selectConversation(conv)"
          >
            <Avatar
              :src="conv.other_user.avatar_url"
              :alt="conv.other_user.username"
              :status="conv.other_user.status"
              show-status
              size="sm"
            />
            <div class="conversation-info">
              <span class="conversation-name">{{ conv.other_user.username }}</span>
              <span class="pending-badge">Neue Anfrage</span>
            </div>
          </button>
        </div>
      </div>

      <!-- Active Conversations -->
      <div class="section">
        <div class="conversation-list">
          <RouterLink
            v-for="conv in conversationStore.acceptedConversations"
            :key="conv.id"
            :to="`/app/dm/${conv.id}`"
            class="conversation-item"
            :class="{ active: conversationStore.currentConversation?.id === conv.id }"
          >
            <Avatar
              :src="conv.other_user.avatar_url"
              :alt="conv.other_user.username"
              :status="conv.other_user.status"
              show-status
              size="sm"
            />
            <div class="conversation-info">
              <span class="conversation-name">{{ conv.other_user.username }}</span>
              <span v-if="conv.last_message" class="last-message">
                {{ conv.last_message.is_mine ? 'Du: ' : '' }}{{ truncate(conv.last_message.content, 30) }}
              </span>
            </div>
            <span
              v-if="conv.unread_count > 0"
              class="unread-badge"
            >
              {{ conv.unread_count }}
            </span>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- New DM Modal -->
    <NewDMModal
      :is-open="showNewDMModal"
      @close="showNewDMModal = false"
    />
  </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useConversationStore, type Conversation } from '@/stores/conversation'
import { PlusIcon } from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import NewDMModal from '@/components/modals/NewDMModal.vue'

const router = useRouter()
const conversationStore = useConversationStore()

const showNewDMModal = ref(false)

function selectConversation(conversation: Conversation) {
  conversationStore.selectConversation(conversation)
  router.push(`/app/dm/${conversation.id}`)
}

function truncate(text: string, length: number): string {
  if (text.length <= length) return text
  return text.substring(0, length) + '...'
}
</script>

<style scoped>
.dm-sidebar {
  @apply w-60 bg-sidebar-light flex flex-col border-r border-chat-hover;
}

.sidebar-header {
  @apply h-12 px-4 flex items-center justify-between border-b border-chat-hover;
}

.sidebar-title {
  @apply font-semibold text-gray-100;
}

.new-dm-btn {
  @apply p-1.5 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.sidebar-content {
  @apply flex-1 overflow-y-auto py-2;
}

.section {
  @apply mb-4;
}

.section-title {
  @apply px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider;
}

.conversation-list {
  @apply space-y-0.5;
}

.conversation-item {
  @apply flex items-center gap-3 px-4 py-2 mx-2 rounded text-gray-300 hover:bg-chat-hover transition-colors;
}

.conversation-item.active {
  @apply bg-chat-hover text-white;
}

.conversation-item.pending {
  @apply bg-yellow-500/10 border border-yellow-500/30;
}

.conversation-info {
  @apply flex-1 min-w-0;
}

.conversation-name {
  @apply block font-medium truncate;
}

.last-message {
  @apply block text-xs text-gray-500 truncate;
}

.pending-badge {
  @apply block text-xs text-yellow-400;
}

.unread-badge {
  @apply min-w-[20px] h-5 px-1.5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center;
}
</style>
