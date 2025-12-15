<template>
  <div class="emoji-picker" @click.stop>
    <div class="emoji-grid">
      <button
        v-for="emoji in commonEmojis"
        :key="emoji"
        class="emoji-btn"
        @click="$emit('select', emoji)"
      >
        {{ emoji }}
      </button>
    </div>
    <button class="close-btn" @click="$emit('close')">
      <XMarkIcon class="w-4 h-4" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineEmits(['select', 'close'])

const commonEmojis = [
  '👍', '❤️', '😂', '😮', '😢', '😡',
  '🎉', '🔥', '✅', '❌', '👀', '💯',
  '🙏', '💪', '🚀', '⭐', '✨', '💡',
]
</script>

<style scoped>
.emoji-picker {
  @apply absolute bottom-full right-0 mb-2 p-3 bg-sidebar-light rounded-lg shadow-xl border border-chat-hover z-50;
}

.emoji-grid {
  @apply grid grid-cols-6 gap-1 mb-2;
}

.emoji-btn {
  @apply w-8 h-8 text-xl hover:bg-chat-hover rounded transition-colors;
}

.close-btn {
  @apply w-full p-1 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}
</style>
