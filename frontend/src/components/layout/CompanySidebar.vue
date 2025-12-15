<template>
  <aside class="company-sidebar">
    <!-- Company Icons -->
    <div class="company-list">
      <button
        v-for="company in companyStore.companies"
        :key="company.id"
        class="company-icon"
        :class="{ active: companyStore.currentCompany?.id === company.id }"
        :title="company.name"
        @click="selectCompany(company)"
      >
        <img
          v-if="company.logo_url"
          :src="company.logo_url"
          :alt="company.name"
          class="w-full h-full object-cover"
        />
        <span v-else class="company-initials">
          {{ getInitials(company.name) }}
        </span>
      </button>

      <!-- Separator -->
      <div class="company-separator" />

      <!-- Add Company -->
      <button
        class="company-icon add-company"
        title="Firma hinzufügen"
        @click="showAddCompanyModal = true"
      >
        <PlusIcon class="w-5 h-5" />
      </button>

      <!-- Direct Messages -->
      <RouterLink
        to="/app/dm"
        class="company-icon dm-icon"
        :class="{ active: route.path.startsWith('/app/dm') }"
        title="Direktnachrichten"
      >
        <ChatBubbleLeftRightIcon class="w-5 h-5" />
        <span
          v-if="unreadDMCount > 0"
          class="dm-badge"
        >
          {{ unreadDMCount > 99 ? '99+' : unreadDMCount }}
        </span>
      </RouterLink>
    </div>

    <!-- User Menu -->
    <div class="user-menu">
      <Dropdown position="right">
        <template #trigger>
          <button class="user-avatar-btn">
            <Avatar
              :src="authStore.user?.avatar_url"
              :alt="authStore.user?.username"
              :status="authStore.user?.status"
              show-status
              size="sm"
            />
          </button>
        </template>

        <DropdownItem :icon="UserIcon" @click="goToSettings">
          Profil
        </DropdownItem>
        <DropdownItem :icon="Cog6ToothIcon" @click="goToSettings">
          Einstellungen
        </DropdownItem>
        <DropdownItem :icon="ArrowRightOnRectangleIcon" danger @click="logout">
          Abmelden
        </DropdownItem>
      </Dropdown>
    </div>

    <!-- Add Company Modal -->
    <AddCompanyModal
      :is-open="showAddCompanyModal"
      @close="showAddCompanyModal = false"
    />
  </aside>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore, type Company } from '@/stores/company'
import { useConversationStore } from '@/stores/conversation'
import {
  PlusIcon,
  ChatBubbleLeftRightIcon,
  UserIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import Dropdown from '@/components/common/Dropdown.vue'
import DropdownItem from '@/components/common/DropdownItem.vue'
import AddCompanyModal from '@/components/modals/AddCompanyModal.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const companyStore = useCompanyStore()
const conversationStore = useConversationStore()

const showAddCompanyModal = ref(false)

const unreadDMCount = computed(() => {
  return conversationStore.conversations.reduce((sum, c) => sum + c.unread_count, 0)
})

function getInitials(name: string): string {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

async function selectCompany(company: Company) {
  await companyStore.selectCompany(company)
  router.push(`/app/company/${company.id}`)
}

function goToSettings() {
  router.push('/app/settings')
}

async function logout() {
  await authStore.logout()
}
</script>

<style scoped>
.company-sidebar {
  @apply w-[72px] bg-sidebar-dark flex flex-col items-center py-3;
}

.company-list {
  @apply flex-1 flex flex-col items-center gap-2 overflow-y-auto;
}

.company-icon {
  @apply w-12 h-12 rounded-2xl bg-chat flex items-center justify-center text-gray-300 overflow-hidden;
  @apply hover:rounded-xl hover:bg-primary-600 transition-all duration-200 cursor-pointer;
}

.company-icon.active {
  @apply rounded-xl bg-primary-600;
}

.company-initials {
  @apply font-semibold text-sm;
}

.company-separator {
  @apply w-8 h-0.5 bg-chat-hover my-2 rounded-full;
}

.add-company {
  @apply bg-transparent border-2 border-dashed border-chat-hover text-green-400;
  @apply hover:border-green-400 hover:bg-green-400/10;
}

.dm-icon {
  @apply relative;
}

.dm-badge {
  @apply absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center;
}

.user-menu {
  @apply pt-2 border-t border-chat-hover;
}

.user-avatar-btn {
  @apply p-1 rounded-full hover:bg-chat-hover transition-colors;
}
</style>
