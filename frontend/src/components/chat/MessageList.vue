<template>
  <div class="message-list">
    <template v-for="(message, index) in messages" :key="message.id">
      <!-- Date Separator -->
      <DateSeparator
        v-if="shouldShowDateSeparator(message, messages[index - 1])"
        :date="message.created_at"
      />

      <!-- Message -->
      <MessageItem
        :message="message"
        :show-avatar="shouldShowAvatar(message, messages[index - 1])"
        :is-own="message.sender.id === authStore.currentUserId"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import type { Message } from '@/stores/chat'
import MessageItem from './MessageItem.vue'
import DateSeparator from './DateSeparator.vue'
import { isSameDay, parseISO } from 'date-fns'

defineProps<{
  messages: Message[]
}>()

const authStore = useAuthStore()

function shouldShowDateSeparator(current: Message, previous?: Message): boolean {
  if (!previous) return true
  return !isSameDay(parseISO(current.created_at), parseISO(previous.created_at))
}

function shouldShowAvatar(current: Message, previous?: Message): boolean {
  if (!previous) return true
  if (current.sender.id !== previous.sender.id) return true

  // Mehr als 5 Minuten Unterschied
  const currentTime = parseISO(current.created_at).getTime()
  const previousTime = parseISO(previous.created_at).getTime()
  return currentTime - previousTime > 5 * 60 * 1000
}
</script>

<style scoped>
.message-list {
  @apply space-y-0.5;
}
</style>
