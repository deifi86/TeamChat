<template>
  <div
    class="message-item"
    :class="{ 'is-own': isOwn, 'has-avatar': showAvatar }"
    @mouseenter="showActions = true"
    @mouseleave="showActions = false"
  >
    <!-- Avatar -->
    <div class="message-avatar">
      <Avatar
        v-if="showAvatar"
        :src="message.sender.avatar_url"
        :alt="message.sender.username"
        size="sm"
      />
    </div>

    <!-- Content -->
    <div class="message-content">
      <!-- Header (nur wenn Avatar sichtbar) -->
      <div v-if="showAvatar" class="message-header">
        <span class="sender-name">{{ message.sender.username }}</span>
        <span class="message-time">{{ formatTime(message.created_at) }}</span>
      </div>

      <!-- Body -->
      <div class="message-body">
        <!-- Text Content -->
        <p v-if="message.content_type === 'text'" class="message-text">
          {{ message.content }}
          <span v-if="message.edited_at" class="edited-badge">(bearbeitet)</span>
        </p>

        <!-- File/Image Content -->
        <FilePreview
          v-else-if="message.content_type === 'file' || message.content_type === 'image'"
          :file="message.file"
        />

        <!-- Reactions -->
        <MessageReactions
          v-if="message.reactions.length > 0"
          :reactions="message.reactions"
          :message-id="message.id"
        />
      </div>
    </div>

    <!-- Actions -->
    <div v-show="showActions" class="message-actions">
      <button
        class="action-btn"
        title="Emoji hinzufügen"
        @click="showEmojiPicker = true"
      >
        <FaceSmileIcon class="w-4 h-4" />
      </button>
      <button
        v-if="isOwn"
        class="action-btn"
        title="Bearbeiten"
        @click="$emit('edit', message)"
      >
        <PencilIcon class="w-4 h-4" />
      </button>
      <button
        v-if="isOwn"
        class="action-btn"
        title="Löschen"
        @click="$emit('delete', message)"
      >
        <TrashIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Emoji Picker Popover -->
    <EmojiPicker
      v-if="showEmojiPicker"
      @select="handleEmojiSelect"
      @close="showEmojiPicker = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { format, parseISO } from 'date-fns'
import { de } from 'date-fns/locale'
import type { Message } from '@/stores/chat'
import { useChatStore } from '@/stores/chat'
import { FaceSmileIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import FilePreview from './FilePreview.vue'
import MessageReactions from './MessageReactions.vue'
import EmojiPicker from './EmojiPicker.vue'

const props = defineProps<{
  message: Message
  showAvatar: boolean
  isOwn: boolean
}>()

defineEmits(['edit', 'delete'])

const chatStore = useChatStore()
const showActions = ref(false)
const showEmojiPicker = ref(false)

function formatTime(dateString: string): string {
  return format(parseISO(dateString), 'HH:mm', { locale: de })
}

async function handleEmojiSelect(emoji: string) {
  await chatStore.toggleReaction(props.message.id, emoji)
  showEmojiPicker.value = false
}
</script>

<style scoped>
.message-item {
  @apply relative flex gap-3 py-0.5 px-2 -mx-2 rounded hover:bg-chat-hover/50 group;
}

.message-item.has-avatar {
  @apply mt-4;
}

.message-avatar {
  @apply w-10 flex-shrink-0;
}

.message-content {
  @apply flex-1 min-w-0;
}

.message-header {
  @apply flex items-baseline gap-2 mb-0.5;
}

.sender-name {
  @apply font-medium text-gray-100;
}

.message-time {
  @apply text-xs text-gray-500;
}

.message-body {
  @apply space-y-1;
}

.message-text {
  @apply text-gray-200 whitespace-pre-wrap break-words;
}

.edited-badge {
  @apply text-xs text-gray-500 ml-1;
}

.message-actions {
  @apply absolute right-2 -top-3 flex items-center gap-0.5 px-1 py-0.5 bg-sidebar-light rounded-lg shadow-lg border border-chat-hover;
}

.action-btn {
  @apply p-1.5 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}
</style>
