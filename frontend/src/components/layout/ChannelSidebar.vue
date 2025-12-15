<template>
  <aside class="channel-sidebar">
    <!-- Company Header -->
    <div class="sidebar-header">
      <Dropdown position="left" width="md">
        <template #trigger>
          <button class="company-header-btn">
            <span class="company-name">{{ companyStore.currentCompany?.name }}</span>
            <ChevronDownIcon class="w-4 h-4 ml-1" />
          </button>
        </template>

        <DropdownItem :icon="Cog6ToothIcon" @click="showCompanySettings = true">
          Einstellungen
        </DropdownItem>
        <DropdownItem :icon="UserPlusIcon" @click="showInviteModal = true">
          Mitglieder einladen
        </DropdownItem>
        <DropdownItem
          v-if="!companyStore.isOwner"
          :icon="ArrowRightOnRectangleIcon"
          danger
          @click="leaveCompany"
        >
          Firma verlassen
        </DropdownItem>
      </Dropdown>
    </div>

    <!-- Channel List -->
    <div class="sidebar-content">
      <!-- My Channels -->
      <div class="channel-section">
        <button
          class="section-header"
          @click="toggleSection('myChannels')"
        >
          <ChevronRightIcon
            class="section-chevron"
            :class="{ rotated: expandedSections.myChannels }"
          />
          <span>Meine Kanäle</span>
        </button>

        <div v-show="expandedSections.myChannels" class="channel-list">
          <RouterLink
            v-for="channel in companyStore.myChannels"
            :key="channel.id"
            :to="`/app/company/${companyStore.currentCompany?.id}/channel/${channel.id}`"
            class="channel-item"
            :class="{ active: companyStore.currentChannel?.id === channel.id }"
          >
            <HashtagIcon v-if="!channel.is_private" class="channel-icon" />
            <LockClosedIcon v-else class="channel-icon" />
            <span class="channel-name">{{ channel.name }}</span>
          </RouterLink>
        </div>
      </div>

      <!-- Other Channels -->
      <div v-if="companyStore.otherChannels.length > 0" class="channel-section">
        <button
          class="section-header"
          @click="toggleSection('otherChannels')"
        >
          <ChevronRightIcon
            class="section-chevron"
            :class="{ rotated: expandedSections.otherChannels }"
          />
          <span>Weitere Kanäle</span>
        </button>

        <div v-show="expandedSections.otherChannels" class="channel-list">
          <button
            v-for="channel in companyStore.otherChannels"
            :key="channel.id"
            class="channel-item other"
            @click="requestJoinChannel(channel)"
          >
            <HashtagIcon v-if="!channel.is_private" class="channel-icon" />
            <LockClosedIcon v-else class="channel-icon" />
            <span class="channel-name">{{ channel.name }}</span>
            <span
              v-if="channel.has_pending_request"
              class="pending-badge"
            >
              Angefragt
            </span>
          </button>
        </div>
      </div>

      <!-- Add Channel Button -->
      <button
        v-if="companyStore.isAdmin"
        class="add-channel-btn"
        @click="showCreateChannelModal = true"
      >
        <PlusIcon class="w-4 h-4 mr-2" />
        Kanal erstellen
      </button>
    </div>

    <!-- Online Members -->
    <div class="online-members">
      <div class="section-header">
        <span>Online — {{ companyStore.onlineUsers.length }}</span>
      </div>
      <div class="members-list">
        <div
          v-for="user in companyStore.onlineUsers.slice(0, 10)"
          :key="user.id"
          class="member-item"
        >
          <Avatar
            :src="user.avatar_url"
            :alt="user.username"
            :status="user.status"
            show-status
            size="xs"
          />
          <span class="member-name">{{ user.username }}</span>
        </div>
        <button
          v-if="companyStore.onlineUsers.length > 10"
          class="show-all-btn"
        >
          +{{ companyStore.onlineUsers.length - 10 }} weitere
        </button>
      </div>
    </div>

    <!-- Modals -->
    <CreateChannelModal
      :is-open="showCreateChannelModal"
      @close="showCreateChannelModal = false"
    />
  </aside>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useCompanyStore, type Channel } from '@/stores/company'
import {
  ChevronDownIcon,
  ChevronRightIcon,
  HashtagIcon,
  LockClosedIcon,
  PlusIcon,
  Cog6ToothIcon,
  UserPlusIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import Dropdown from '@/components/common/Dropdown.vue'
import DropdownItem from '@/components/common/DropdownItem.vue'
import CreateChannelModal from '@/components/modals/CreateChannelModal.vue'

const router = useRouter()
const companyStore = useCompanyStore()

const showCompanySettings = ref(false)
const showInviteModal = ref(false)
const showCreateChannelModal = ref(false)

const expandedSections = reactive({
  myChannels: true,
  otherChannels: true,
})

function toggleSection(section: keyof typeof expandedSections) {
  expandedSections[section] = !expandedSections[section]
}

async function requestJoinChannel(channel: Channel) {
  if (channel.has_pending_request) return
  await companyStore.requestJoinChannel(channel.id)
}

async function leaveCompany() {
  if (!companyStore.currentCompany) return

  if (confirm('Möchtest du diese Firma wirklich verlassen?')) {
    await companyStore.leaveCompany(companyStore.currentCompany.id)
    router.push('/app')
  }
}
</script>

<style scoped>
.channel-sidebar {
  @apply w-60 bg-sidebar-light flex flex-col border-r border-chat-hover;
}

.sidebar-header {
  @apply h-12 px-4 flex items-center border-b border-chat-hover;
}

.company-header-btn {
  @apply flex items-center text-gray-100 font-semibold hover:text-white transition-colors;
}

.company-name {
  @apply truncate max-w-[180px];
}

.sidebar-content {
  @apply flex-1 overflow-y-auto py-4;
}

.channel-section {
  @apply mb-4;
}

.section-header {
  @apply flex items-center px-4 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 cursor-pointer;
}

.section-chevron {
  @apply w-3 h-3 mr-1 transition-transform;
}

.section-chevron.rotated {
  @apply rotate-90;
}

.channel-list {
  @apply mt-1;
}

.channel-item {
  @apply flex items-center px-4 py-1.5 mx-2 rounded text-gray-400 hover:text-gray-200 hover:bg-chat-hover transition-colors;
}

.channel-item.active {
  @apply bg-chat-hover text-white;
}

.channel-item.other {
  @apply opacity-60;
}

.channel-icon {
  @apply w-4 h-4 mr-2 flex-shrink-0;
}

.channel-name {
  @apply truncate;
}

.pending-badge {
  @apply ml-auto text-xs bg-yellow-500/20 text-yellow-400 px-1.5 py-0.5 rounded;
}

.add-channel-btn {
  @apply flex items-center px-4 py-2 mx-2 text-sm text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.online-members {
  @apply border-t border-chat-hover p-4;
}

.members-list {
  @apply mt-2 space-y-1;
}

.member-item {
  @apply flex items-center gap-2 py-1;
}

.member-name {
  @apply text-sm text-gray-400 truncate;
}

.show-all-btn {
  @apply text-xs text-gray-500 hover:text-gray-300 mt-2;
}
</style>
