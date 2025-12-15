<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" class="relative z-50" @close="closeModal">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/60" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="modal-panel"
              :class="sizeClasses"
            >
              <div class="modal-header" v-if="title || $slots.header">
                <slot name="header">
                  <DialogTitle class="modal-title">
                    {{ title }}
                  </DialogTitle>
                </slot>
                <button
                  v-if="showClose"
                  type="button"
                  class="modal-close"
                  @click="closeModal"
                >
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>

              <div class="modal-body">
                <slot />
              </div>

              <div v-if="$slots.footer" class="modal-footer">
                <slot name="footer" />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
  DialogTitle,
} from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(defineProps<{
  isOpen: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full'
  showClose?: boolean
  closeOnClickOutside?: boolean
}>(), {
  size: 'md',
  showClose: true,
  closeOnClickOutside: true,
})

const emit = defineEmits(['close'])

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    full: 'max-w-4xl',
  }
  return sizes[props.size]
})

function closeModal() {
  if (props.closeOnClickOutside) {
    emit('close')
  }
}
</script>

<style scoped>
.modal-panel {
  @apply w-full transform overflow-hidden rounded-xl bg-sidebar-light text-left align-middle shadow-xl transition-all;
}

.modal-header {
  @apply flex items-center justify-between px-6 py-4 border-b border-chat-hover;
}

.modal-title {
  @apply text-lg font-semibold text-gray-100;
}

.modal-close {
  @apply text-gray-400 hover:text-gray-200 transition-colors;
}

.modal-body {
  @apply px-6 py-4;
}

.modal-footer {
  @apply px-6 py-4 border-t border-chat-hover bg-sidebar-dark/50 flex justify-end gap-3;
}
</style>
