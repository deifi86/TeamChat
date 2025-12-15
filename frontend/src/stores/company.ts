import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { companyAPI, channelAPI } from '@/services/api'
import { subscribeToCompanyPresence, leaveChannel } from '@/services/websocket'

export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  owner: {
    id: number
    username: string
    avatar_url: string
  }
  members_count: number
  my_role: 'admin' | 'user'
  is_owner: boolean
  created_at: string
}

export interface Channel {
  id: number
  name: string
  description: string | null
  is_private: boolean
  is_member: boolean
  members_count: number
  has_pending_request?: boolean
}

export interface OnlineUser {
  id: number
  username: string
  avatar_url: string
  status: string
}

export const useCompanyStore = defineStore('company', () => {
  // State
  const companies = ref<Company[]>([])
  const currentCompany = ref<Company | null>(null)
  const channels = ref<Channel[]>([])
  const currentChannel = ref<Channel | null>(null)
  const onlineUsers = ref<OnlineUser[]>([])
  const loading = ref(false)

  // Getters
  const myChannels = computed(() => channels.value.filter(c => c.is_member))
  const otherChannels = computed(() => channels.value.filter(c => !c.is_member))
  const isAdmin = computed(() => currentCompany.value?.my_role === 'admin')
  const isOwner = computed(() => currentCompany.value?.is_owner)

  // Actions
  async function fetchCompanies() {
    loading.value = true
    try {
      const response = await companyAPI.myCompanies()
      companies.value = response.data.companies
    } finally {
      loading.value = false
    }
  }

  async function selectCompany(company: Company) {
    // Alte Company-Subscription verlassen
    if (currentCompany.value) {
      leaveChannel(`presence-company.${currentCompany.value.id}`)
    }

    currentCompany.value = company
    currentChannel.value = null

    // Channels laden
    await fetchChannels()

    // Presence Channel subscriben
    subscribeToCompanyPresence(company.id, {
      onHere: (users) => {
        onlineUsers.value = users
      },
      onJoining: (user) => {
        if (!onlineUsers.value.find(u => u.id === user.id)) {
          onlineUsers.value.push(user)
        }
      },
      onLeaving: (user) => {
        onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id)
      },
      onStatusChanged: (data) => {
        const user = onlineUsers.value.find(u => u.id === data.user_id)
        if (user) {
          user.status = data.status
        }
      },
    })
  }

  async function fetchChannels() {
    if (!currentCompany.value) return

    const response = await channelAPI.list(currentCompany.value.id)
    channels.value = response.data.channels
  }

  async function selectChannel(channel: Channel) {
    currentChannel.value = channel
  }

  async function createCompany(data: { name: string; join_password: string }) {
    const response = await companyAPI.create(data)
    companies.value.push(response.data.company)
    return response.data.company
  }

  async function joinCompany(companyId: number, password: string) {
    const response = await companyAPI.join(companyId, password)
    companies.value.push(response.data.company)
    return response.data.company
  }

  async function leaveCompany(companyId: number) {
    await companyAPI.leave(companyId)
    companies.value = companies.value.filter(c => c.id !== companyId)
    if (currentCompany.value?.id === companyId) {
      currentCompany.value = null
      channels.value = []
    }
  }

  async function createChannel(data: { name: string; description?: string; is_private?: boolean }) {
    if (!currentCompany.value) return

    const response = await channelAPI.create(currentCompany.value.id, data)
    channels.value.push({
      ...response.data.channel,
      is_member: true,
      has_pending_request: false,
    })
    return response.data.channel
  }

  async function requestJoinChannel(channelId: number, message?: string) {
    await channelAPI.requestJoin(channelId, message)
    const channel = channels.value.find(c => c.id === channelId)
    if (channel) {
      channel.has_pending_request = true
    }
  }

  function isUserOnline(userId: number): boolean {
    return onlineUsers.value.some(u => u.id === userId)
  }

  function getUserStatus(userId: number): string {
    const user = onlineUsers.value.find(u => u.id === userId)
    return user?.status || 'offline'
  }

  return {
    // State
    companies,
    currentCompany,
    channels,
    currentChannel,
    onlineUsers,
    loading,
    // Getters
    myChannels,
    otherChannels,
    isAdmin,
    isOwner,
    // Actions
    fetchCompanies,
    selectCompany,
    fetchChannels,
    selectChannel,
    createCompany,
    joinCompany,
    leaveCompany,
    createChannel,
    requestJoinChannel,
    isUserOnline,
    getUserStatus,
  }
})
