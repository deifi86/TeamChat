<template>
  <div class="titlebar">
    <div class="titlebar-drag">
      <div class="titlebar-logo">
        <img src="@/assets/logo.svg" alt="TeamChat" class="w-5 h-5" />
        <span class="ml-2 font-semibold text-sm">TeamChat</span>
      </div>
    </div>

    <div class="titlebar-controls">
      <button
        @click="minimize"
        class="titlebar-btn hover:bg-chat-hover"
        title="Minimieren"
      >
        <MinusIcon class="w-4 h-4" />
      </button>
      <button
        @click="maximize"
        class="titlebar-btn hover:bg-chat-hover"
        title="Maximieren"
      >
        <StopIcon v-if="isMaximized" class="w-4 h-4" />
        <Square2StackIcon v-else class="w-4 h-4" />
      </button>
      <button
        @click="close"
        class="titlebar-btn hover:bg-red-600"
        title="Schließen"
      >
        <XMarkIcon class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { MinusIcon, XMarkIcon, Square2StackIcon, StopIcon } from '@heroicons/vue/24/outline'

const isMaximized = ref(false)

const isElectron = !!window.electronAPI

function minimize() {
  if (isElectron) {
    window.electronAPI.minimizeWindow()
  }
}

async function maximize() {
  if (isElectron) {
    window.electronAPI.maximizeWindow()
    isMaximized.value = await window.electronAPI.isMaximized()
  }
}

function close() {
  if (isElectron) {
    window.electronAPI.closeWindow()
  }
}

onMounted(async () => {
  if (isElectron) {
    isMaximized.value = await window.electronAPI.isMaximized()
  }
})
</script>

<style scoped>
.titlebar {
  @apply flex items-center justify-between h-9 bg-sidebar-dark px-2;
  -webkit-app-region: drag;
}

.titlebar-drag {
  @apply flex-1 flex items-center;
}

.titlebar-logo {
  @apply flex items-center text-gray-300;
  -webkit-app-region: no-drag;
}

.titlebar-controls {
  @apply flex items-center;
  -webkit-app-region: no-drag;
}

.titlebar-btn {
  @apply w-10 h-9 flex items-center justify-center text-gray-400 transition-colors;
}
</style>
