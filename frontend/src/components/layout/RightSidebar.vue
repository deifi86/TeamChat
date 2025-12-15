<template>
  <aside class="right-sidebar">
    <div class="sidebar-header">
      <h3 class="sidebar-title">Mitglieder</h3>
      <button class="close-btn" @click="$emit('close')">
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>

    <div class="sidebar-content">
      <!-- Channel/Conversation Members -->
      <div v-if="members.length > 0" class="members-list">
        <div
          v-for="member in members"
          :key="member.id"
          class="member-item"
        >
          <Avatar
            :src="member.avatar_url"
            :alt="member.username"
            :status="member.status"
            show-status
            size="sm"
          />
          <div class="member-info">
            <span class="member-name">{{ member.username }}</span>
            <span class="member-role">{{ getRoleLabel(member.role) }}</span>
          </div>
        </div>
      </div>

      <p v-else class="text-sm text-gray-500 text-center py-8">
        Keine Mitglieder
      </p>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useCompanyStore } from '@/stores/company'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'

defineEmits(['close'])

const companyStore = useCompanyStore()

const members = computed(() => {
  return companyStore.currentChannel?.members || []
})

function getRoleLabel(role: string): string {
  const labels: Record<string, string> = {
    owner: 'Inhaber',
    admin: 'Administrator',
    member: 'Mitglied',
  }
  return labels[role] || role
}
</script>

<style scoped>
.right-sidebar {
  @apply w-60 bg-sidebar-light border-l border-chat-hover flex flex-col;
}

.sidebar-header {
  @apply h-12 px-4 flex items-center justify-between border-b border-chat-hover;
}

.sidebar-title {
  @apply font-semibold text-gray-100;
}

.close-btn {
  @apply p-1.5 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.sidebar-content {
  @apply flex-1 overflow-y-auto p-4;
}

.members-list {
  @apply space-y-2;
}

.member-item {
  @apply flex items-center gap-3 py-2;
}

.member-info {
  @apply flex-1 min-w-0;
}

.member-name {
  @apply block text-sm font-medium text-gray-200 truncate;
}

.member-role {
  @apply block text-xs text-gray-500;
}
</style>
