<template>
  <div class="reactions-container">
    <button
      v-for="reaction in reactions"
      :key="reaction.emoji"
      class="reaction-badge"
      :class="{ 'is-own': hasReacted(reaction) }"
      @click="toggleReaction(reaction.emoji)"
    >
      <span class="reaction-emoji">{{ reaction.emoji }}</span>
      <span class="reaction-count">{{ reaction.count }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useChatStore, type Reaction } from '@/stores/chat'

const props = defineProps<{
  reactions: Reaction[]
  messageId: number
}>()

const authStore = useAuthStore()
const chatStore = useChatStore()

function hasReacted(reaction: Reaction): boolean {
  return reaction.user_ids.includes(authStore.currentUserId!)
}

function toggleReaction(emoji: string) {
  chatStore.toggleReaction(props.messageId, emoji)
}
</script>

<style scoped>
.reactions-container {
  @apply flex flex-wrap gap-1 mt-1;
}

.reaction-badge {
  @apply inline-flex items-center gap-1 px-2 py-0.5 bg-chat-hover rounded-full text-sm;
  @apply hover:bg-chat-active transition-colors;
}

.reaction-badge.is-own {
  @apply bg-primary-600/20 border border-primary-500/50;
}

.reaction-emoji {
  @apply text-base;
}

.reaction-count {
  @apply text-gray-400 text-xs;
}

.reaction-badge.is-own .reaction-count {
  @apply text-primary-400;
}
</style>
