<template>
  <div class="main-layout">
    <!-- Electron Title Bar -->
    <TitleBar v-if="isElectron" />

    <div class="main-content">
      <!-- Company Sidebar -->
      <CompanySidebar />

      <!-- Channel/DM Sidebar -->
      <ChannelSidebar v-if="companyStore.currentCompany" />
      <DMSidebar v-else-if="route.path.startsWith('/app/dm')" />

      <!-- Main Area -->
      <main class="main-area">
        <RouterView />
      </main>

      <!-- Right Sidebar (Members, Details) -->
      <RightSidebar v-if="showRightSidebar" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCompanyStore } from '@/stores/company'
import { useConversationStore } from '@/stores/conversation'
import { useAuthStore } from '@/stores/auth'
import TitleBar from '@/components/layout/TitleBar.vue'
import CompanySidebar from '@/components/layout/CompanySidebar.vue'
import ChannelSidebar from '@/components/layout/ChannelSidebar.vue'
import DMSidebar from '@/components/layout/DMSidebar.vue'
import RightSidebar from '@/components/layout/RightSidebar.vue'

const route = useRoute()
const authStore = useAuthStore()
const companyStore = useCompanyStore()
const conversationStore = useConversationStore()

const isElectron = !!window.electronAPI

const showRightSidebar = computed(() => {
  return route.name === 'channel' || route.name === 'conversation'
})

onMounted(async () => {
  // Firmen laden
  await companyStore.fetchCompanies()

  // Conversations laden
  await conversationStore.fetchConversations()

  // User-Notifications subscriben
  if (authStore.currentUserId) {
    conversationStore.subscribeToUserNotifications(authStore.currentUserId)
  }
})
</script>

<style scoped>
.main-layout {
  @apply flex flex-col h-screen bg-sidebar overflow-hidden;
}

.main-content {
  @apply flex flex-1 overflow-hidden;
}

.main-area {
  @apply flex-1 flex flex-col overflow-hidden bg-chat;
}
</style>
