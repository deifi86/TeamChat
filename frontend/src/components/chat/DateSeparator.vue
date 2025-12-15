<template>
  <div class="date-separator">
    <div class="separator-line" />
    <span class="separator-text">{{ formattedDate }}</span>
    <div class="separator-line" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { format, parseISO, isToday, isYesterday } from 'date-fns'
import { de } from 'date-fns/locale'

const props = defineProps<{
  date: string
}>()

const formattedDate = computed(() => {
  const parsedDate = parseISO(props.date)

  if (isToday(parsedDate)) {
    return 'Heute'
  }

  if (isYesterday(parsedDate)) {
    return 'Gestern'
  }

  return format(parsedDate, 'd. MMMM yyyy', { locale: de })
})
</script>

<style scoped>
.date-separator {
  @apply flex items-center gap-3 my-4;
}

.separator-line {
  @apply flex-1 h-px bg-chat-hover;
}

.separator-text {
  @apply text-xs font-semibold text-gray-500 uppercase;
}
</style>
