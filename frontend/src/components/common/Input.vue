<template>
  <div class="input-wrapper">
    <label v-if="label" :for="id" class="input-label">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <div class="input-container" :class="{ 'has-icon': !!icon }">
      <component
        v-if="icon"
        :is="icon"
        class="input-icon"
      />

      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :autocomplete="autocomplete"
        class="input"
        :class="{ 'has-error': !!error, 'pl-10': !!icon }"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
      />
    </div>

    <p v-if="error" class="input-error">{{ error }}</p>
    <p v-else-if="hint" class="input-hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string
  type?: string
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
  autocomplete?: string
  icon?: Component
  id?: string
}>(), {
  type: 'text',
  disabled: false,
  required: false,
})

defineEmits(['update:modelValue', 'blur', 'focus'])

const id = computed(() => props.id || `input-${Math.random().toString(36).substr(2, 9)}`)
</script>

<style scoped>
.input-wrapper {
  @apply space-y-1;
}

.input-label {
  @apply block text-sm font-medium text-gray-300;
}

.input-container {
  @apply relative;
}

.input-icon {
  @apply absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500;
}

.input {
  @apply w-full px-4 py-2.5 bg-chat border border-chat-hover rounded-lg text-gray-100 placeholder-gray-500;
  @apply focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
  @apply transition-colors duration-200;
}

.input.has-error {
  @apply border-red-500 focus:ring-red-500;
}

.input-error {
  @apply text-sm text-red-400;
}

.input-hint {
  @apply text-sm text-gray-500;
}
</style>
