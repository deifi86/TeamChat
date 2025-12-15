<template>
  <div class="avatar" :class="sizeClasses">
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      class="avatar-image"
      @error="onError"
    />
    <div v-else class="avatar-fallback">
      {{ initials }}
    </div>

    <!-- Status Indicator -->
    <span
      v-if="showStatus && status"
      class="avatar-status"
      :class="statusClasses"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
  src?: string | null
  alt?: string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  status?: 'available' | 'busy' | 'away' | 'offline' | null
  showStatus?: boolean
}>(), {
  src: null,
  alt: 'Avatar',
  size: 'md',
  status: null,
  showStatus: false,
})

const imageError = ref(false)

const initials = computed(() => {
  if (!props.alt) return '?'
  const words = props.alt.split(' ')
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase()
  }
  return props.alt.substring(0, 2).toUpperCase()
})

const sizeClasses = computed(() => {
  const sizes = {
    xs: 'w-6 h-6 text-xs',
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-base',
    lg: 'w-12 h-12 text-lg',
    xl: 'w-16 h-16 text-xl',
  }
  return sizes[props.size]
})

const statusClasses = computed(() => {
  const colors = {
    available: 'bg-green-500',
    busy: 'bg-red-500',
    away: 'bg-yellow-500',
    offline: 'bg-gray-500',
  }
  return props.status ? colors[props.status] : ''
})

function onError() {
  imageError.value = true
}
</script>

<style scoped>
.avatar {
  @apply relative inline-flex items-center justify-center rounded-full bg-chat-hover overflow-hidden flex-shrink-0;
}

.avatar-image {
  @apply w-full h-full object-cover;
}

.avatar-fallback {
  @apply font-medium text-gray-300;
}

.avatar-status {
  @apply absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-sidebar;
}

.avatar.w-6 .avatar-status {
  @apply w-2 h-2;
}

.avatar.w-8 .avatar-status {
  @apply w-2.5 h-2.5;
}
</style>
