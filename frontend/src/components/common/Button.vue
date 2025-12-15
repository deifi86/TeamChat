<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
    @click="$emit('click', $event)"
  >
    <LoadingSpinner v-if="loading" class="w-4 h-4 mr-2" />
    <component
      v-else-if="icon"
      :is="icon"
      class="w-4 h-4"
      :class="{ 'mr-2': !!$slots.default }"
    />
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'
import LoadingSpinner from './LoadingSpinner.vue'

const props = withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset'
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'link'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  icon?: Component
  fullWidth?: boolean
}>(), {
  type: 'button',
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  fullWidth: false,
})

defineEmits(['click'])

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-sidebar disabled:opacity-50 disabled:cursor-not-allowed'

  const variants = {
    primary: 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
    secondary: 'bg-chat text-gray-100 hover:bg-chat-hover focus:ring-chat-active',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    ghost: 'text-gray-400 hover:text-gray-100 hover:bg-chat-hover focus:ring-chat-active',
    link: 'text-primary-400 hover:text-primary-300 underline-offset-4 hover:underline',
  }

  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
  }

  return [
    base,
    variants[props.variant],
    sizes[props.size],
    props.fullWidth ? 'w-full' : '',
  ]
})
</script>
