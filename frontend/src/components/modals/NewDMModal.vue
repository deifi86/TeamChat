<template>
  <Modal
    :is-open="isOpen"
    title="Neue Direktnachricht"
    @close="$emit('close')"
  >
    <div class="space-y-4">
      <Input
        v-model="searchQuery"
        label="Empfänger suchen"
        placeholder="Benutzername eingeben..."
        :icon="MagnifyingGlassIcon"
        @update:modelValue="searchUsers"
      />

      <div v-if="searchResults.length > 0" class="user-list">
        <button
          v-for="user in searchResults"
          :key="user.id"
          class="user-item"
          @click="startConversation(user)"
        >
          <Avatar
            :src="user.avatar_url"
            :alt="user.username"
            :status="user.status"
            show-status
            size="sm"
          />
          <span class="username">{{ user.username }}</span>
        </button>
      </div>

      <p v-else-if="searchQuery.length > 0" class="text-sm text-gray-500 text-center py-4">
        Keine Benutzer gefunden
      </p>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useConversationStore } from '@/stores/conversation'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { debounce } from 'lodash-es'
import Modal from '@/components/common/Modal.vue'
import Input from '@/components/common/Input.vue'
import Avatar from '@/components/common/Avatar.vue'

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits(['close'])

const router = useRouter()
const conversationStore = useConversationStore()

const searchQuery = ref('')
const searchResults = ref<any[]>([])

const searchUsers = debounce(async (query: string) => {
  if (query.length < 2) {
    searchResults.value = []
    return
  }

  try {
    searchResults.value = await conversationStore.searchUsers(query)
  } catch (err) {
    console.error('Search error:', err)
  }
}, 300)

async function startConversation(user: any) {
  try {
    const conversation = await conversationStore.createConversation(user.id)
    router.push(`/app/dm/${conversation.id}`)
    emit('close')
  } catch (err) {
    console.error('Error starting conversation:', err)
  }
}
</script>

<style scoped>
.user-list {
  @apply space-y-1 max-h-64 overflow-y-auto;
}

.user-item {
  @apply w-full flex items-center gap-3 px-3 py-2 rounded hover:bg-chat-hover transition-colors;
}

.username {
  @apply text-gray-200;
}
</style>
