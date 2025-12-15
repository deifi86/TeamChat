<template>
  <MenuItem v-slot="{ active }">
    <button
      type="button"
      class="dropdown-item"
      :class="[
        active ? 'bg-chat-hover text-gray-100' : 'text-gray-300',
        danger ? 'text-red-400 hover:text-red-300' : '',
      ]"
      :disabled="disabled"
      @click="$emit('click')"
    >
      <component v-if="icon" :is="icon" class="w-4 h-4 mr-3" />
      <slot />
    </button>
  </MenuItem>
</template>

<script setup lang="ts">
import { type Component } from 'vue'
import { MenuItem } from '@headlessui/vue'

defineProps<{
  icon?: Component
  danger?: boolean
  disabled?: boolean
}>()

defineEmits(['click'])
</script>

<style scoped>
.dropdown-item {
  @apply flex items-center w-full px-4 py-2 text-sm text-left;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
}
</style>
