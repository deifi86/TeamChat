<template>
  <div class="message-input-container">
    <!-- File Preview -->
    <div v-if="attachedFile" class="attached-file">
      <div class="file-preview">
        <img
          v-if="isImage"
          :src="filePreviewUrl"
          class="preview-image"
        />
        <DocumentIcon v-else class="w-8 h-8 text-gray-400" />
        <span class="file-name">{{ attachedFile.name }}</span>
        <button class="remove-file" @click="removeFile">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Input Area -->
    <div class="input-wrapper">
      <!-- Attach Button -->
      <button class="input-btn" @click="triggerFileInput">
        <PlusIcon class="w-5 h-5" />
      </button>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        @change="handleFileSelect"
      />

      <!-- Text Input -->
      <div class="text-input-wrapper">
        <textarea
          ref="textarea"
          v-model="message"
          :placeholder="placeholder"
          class="text-input"
          rows="1"
          @input="handleInput"
          @keydown="handleKeydown"
        />
      </div>

      <!-- Emoji Button -->
      <button class="input-btn" @click="showEmojiPicker = !showEmojiPicker">
        <FaceSmileIcon class="w-5 h-5" />
      </button>

      <!-- Send Button -->
      <button
        class="send-btn"
        :disabled="!canSend"
        @click="send"
      >
        <PaperAirplaneIcon class="w-5 h-5" />
      </button>
    </div>

    <!-- Emoji Picker -->
    <div v-if="showEmojiPicker" class="emoji-picker-container">
      <EmojiPicker
        @select="insertEmoji"
        @close="showEmojiPicker = false"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import {
  PlusIcon,
  FaceSmileIcon,
  PaperAirplaneIcon,
  XMarkIcon,
  DocumentIcon,
} from '@heroicons/vue/24/outline'
import EmojiPicker from './EmojiPicker.vue'

const props = withDefaults(defineProps<{
  channelId: number
  placeholder?: string
}>(), {
  placeholder: 'Nachricht schreiben...',
})

const emit = defineEmits(['send', 'typing', 'sendFile'])

const message = ref('')
const attachedFile = ref<File | null>(null)
const filePreviewUrl = ref<string | null>(null)
const showEmojiPicker = ref(false)
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const canSend = computed(() => {
  return message.value.trim().length > 0 || attachedFile.value !== null
})

const isImage = computed(() => {
  return attachedFile.value?.type.startsWith('image/')
})

function handleInput() {
  emit('typing')
  autoResize()
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    send()
  }
}

function send() {
  if (!canSend.value) return

  if (attachedFile.value) {
    emit('sendFile', attachedFile.value, message.value)
    removeFile()
  } else {
    emit('send', message.value)
  }

  message.value = ''
  nextTick(() => autoResize())
}

function autoResize() {
  if (textarea.value) {
    textarea.value.style.height = 'auto'
    textarea.value.style.height = Math.min(textarea.value.scrollHeight, 200) + 'px'
  }
}

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (file) {
    attachedFile.value = file

    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => {
        filePreviewUrl.value = e.target?.result as string
      }
      reader.readAsDataURL(file)
    }
  }

  input.value = ''
}

function removeFile() {
  attachedFile.value = null
  filePreviewUrl.value = null
}

function insertEmoji(emoji: string) {
  message.value += emoji
  showEmojiPicker.value = false
  textarea.value?.focus()
}
</script>

<style scoped>
.message-input-container {
  @apply relative px-4 py-3 border-t border-chat-hover bg-chat;
}

.attached-file {
  @apply mb-3;
}

.file-preview {
  @apply inline-flex items-center gap-2 px-3 py-2 bg-sidebar-light rounded-lg;
}

.preview-image {
  @apply w-12 h-12 object-cover rounded;
}

.file-name {
  @apply text-sm text-gray-300 max-w-[200px] truncate;
}

.remove-file {
  @apply p-1 text-gray-400 hover:text-red-400 transition-colors;
}

.input-wrapper {
  @apply flex items-end gap-2 bg-sidebar-light rounded-xl px-3 py-2;
}

.input-btn {
  @apply p-2 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded-lg transition-colors flex-shrink-0;
}

.text-input-wrapper {
  @apply flex-1 min-w-0;
}

.text-input {
  @apply w-full bg-transparent text-gray-100 placeholder-gray-500 resize-none;
  @apply focus:outline-none;
  max-height: 200px;
}

.send-btn {
  @apply p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex-shrink-0;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
}

.emoji-picker-container {
  @apply absolute bottom-full right-4 mb-2;
}
</style>
