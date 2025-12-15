<template>
  <div class="typing-indicator">
    <div class="typing-dots">
      <span class="dot" />
      <span class="dot" />
      <span class="dot" />
    </div>
    <span class="typing-text">
      {{ typingText }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  usernames: string[]
}>()

const typingText = computed(() => {
  const names = props.usernames

  if (names.length === 1) {
    return `${names[0]} tippt...`
  }

  if (names.length === 2) {
    return `${names[0]} und ${names[1]} tippen...`
  }

  if (names.length > 2) {
    return `${names[0]} und ${names.length - 1} weitere tippen...`
  }

  return ''
})
</script>

<style scoped>
.typing-indicator {
  @apply flex items-center gap-2 py-2 text-sm text-gray-400;
}

.typing-dots {
  @apply flex gap-1;
}

.dot {
  @apply w-2 h-2 bg-gray-400 rounded-full;
  animation: bounce 1.4s infinite ease-in-out;
}

.dot:nth-child(1) {
  animation-delay: 0s;
}

.dot:nth-child(2) {
  animation-delay: 0.2s;
}

.dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes bounce {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-4px);
  }
}
</style>
