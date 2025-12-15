<template>
  <div class="file-preview">
    <!-- Image Preview -->
    <div v-if="file.type?.startsWith('image/')" class="image-preview">
      <img
        :src="file.url"
        :alt="file.name"
        class="preview-image"
        @click="openFullscreen"
      />
    </div>

    <!-- File Download -->
    <a
      v-else
      :href="file.url"
      :download="file.name"
      class="file-download"
      target="_blank"
    >
      <DocumentIcon class="w-8 h-8 text-gray-400" />
      <div class="file-info">
        <span class="file-name">{{ file.name }}</span>
        <span class="file-size">{{ formatFileSize(file.size) }}</span>
      </div>
      <ArrowDownTrayIcon class="w-5 h-5 text-gray-400" />
    </a>
  </div>
</template>

<script setup lang="ts">
import { DocumentIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

defineProps<{
  file: {
    name: string
    url: string
    type?: string
    size?: number
  }
}>()

function formatFileSize(bytes?: number): string {
  if (!bytes) return ''
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  const mb = kb / 1024
  return `${mb.toFixed(1)} MB`
}

function openFullscreen() {
  // TODO: Implement fullscreen image viewer
}
</script>

<style scoped>
.file-preview {
  @apply mt-2;
}

.image-preview {
  @apply max-w-md cursor-pointer;
}

.preview-image {
  @apply rounded-lg shadow-lg hover:opacity-90 transition-opacity;
}

.file-download {
  @apply flex items-center gap-3 p-3 bg-sidebar-light border border-chat-hover rounded-lg hover:bg-chat-hover transition-colors;
}

.file-info {
  @apply flex-1 flex flex-col;
}

.file-name {
  @apply text-sm text-gray-200 font-medium;
}

.file-size {
  @apply text-xs text-gray-500;
}
</style>
