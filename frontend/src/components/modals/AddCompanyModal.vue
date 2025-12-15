<template>
  <Modal
    :is-open="isOpen"
    title="Firma hinzufügen"
    @close="$emit('close')"
  >
    <!-- Tabs -->
    <div class="tabs">
      <button
        class="tab"
        :class="{ active: activeTab === 'create' }"
        @click="activeTab = 'create'"
      >
        Erstellen
      </button>
      <button
        class="tab"
        :class="{ active: activeTab === 'join' }"
        @click="activeTab = 'join'"
      >
        Beitreten
      </button>
    </div>

    <!-- Create Tab -->
    <form v-if="activeTab === 'create'" @submit.prevent="createCompany" class="form">
      <Input
        v-model="createForm.name"
        label="Firmenname"
        placeholder="Meine Firma GmbH"
        :error="createErrors.name"
        required
      />
      <Input
        v-model="createForm.join_password"
        type="password"
        label="Beitrittspasswort"
        placeholder="Mindestens 6 Zeichen"
        hint="Dieses Passwort können Mitarbeiter nutzen, um der Firma beizutreten"
        :error="createErrors.join_password"
        required
      />
    </form>

    <!-- Join Tab -->
    <form v-else @submit.prevent="joinCompany" class="form">
      <div class="search-section">
        <Input
          v-model="searchQuery"
          label="Firma suchen"
          placeholder="Firmenname eingeben..."
          :icon="MagnifyingGlassIcon"
          @update:modelValue="searchCompanies"
        />

        <div v-if="searchResults.length > 0" class="search-results">
          <button
            v-for="company in searchResults"
            :key="company.id"
            type="button"
            class="search-result"
            :class="{ selected: selectedCompany?.id === company.id }"
            @click="selectedCompany = company"
          >
            <span class="company-name">{{ company.name }}</span>
            <span class="company-members">{{ company.members_count }} Mitglieder</span>
          </button>
        </div>
      </div>

      <Input
        v-if="selectedCompany"
        v-model="joinForm.password"
        type="password"
        label="Beitrittspasswort"
        placeholder="Passwort eingeben"
        :error="joinErrors.password"
        required
      />
    </form>

    <template #footer>
      <Button variant="ghost" @click="$emit('close')">
        Abbrechen
      </Button>
      <Button
        v-if="activeTab === 'create'"
        type="submit"
        :loading="loading"
        @click="createCompany"
      >
        Erstellen
      </Button>
      <Button
        v-else
        type="submit"
        :loading="loading"
        :disabled="!selectedCompany"
        @click="joinCompany"
      >
        Beitreten
      </Button>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCompanyStore } from '@/stores/company'
import { companyAPI } from '@/services/api'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { debounce } from 'lodash-es'
import Modal from '@/components/common/Modal.vue'
import Input from '@/components/common/Input.vue'
import Button from '@/components/common/Button.vue'

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits(['close'])

const router = useRouter()
const companyStore = useCompanyStore()

const activeTab = ref<'create' | 'join'>('create')
const loading = ref(false)

// Create Form
const createForm = reactive({
  name: '',
  join_password: '',
})
const createErrors = reactive({
  name: '',
  join_password: '',
})

// Join Form
const searchQuery = ref('')
const searchResults = ref<any[]>([])
const selectedCompany = ref<any>(null)
const joinForm = reactive({
  password: '',
})
const joinErrors = reactive({
  password: '',
})

// Reset on tab change
watch(activeTab, () => {
  createForm.name = ''
  createForm.join_password = ''
  createErrors.name = ''
  createErrors.join_password = ''
  searchQuery.value = ''
  searchResults.value = []
  selectedCompany.value = null
  joinForm.password = ''
  joinErrors.password = ''
})

const searchCompanies = debounce(async (query: string) => {
  if (query.length < 2) {
    searchResults.value = []
    return
  }

  try {
    const response = await companyAPI.search(query)
    searchResults.value = response.data.companies
  } catch (err) {
    console.error('Search error:', err)
  }
}, 300)

async function createCompany() {
  loading.value = true
  createErrors.name = ''
  createErrors.join_password = ''

  try {
    const company = await companyStore.createCompany(createForm)
    await companyStore.selectCompany(company)
    router.push(`/app/company/${company.id}`)
    emit('close')
  } catch (err: any) {
    if (err.response?.data?.errors) {
      createErrors.name = err.response.data.errors.name?.[0] || ''
      createErrors.join_password = err.response.data.errors.join_password?.[0] || ''
    }
  } finally {
    loading.value = false
  }
}

async function joinCompany() {
  if (!selectedCompany.value) return

  loading.value = true
  joinErrors.password = ''

  try {
    const company = await companyStore.joinCompany(selectedCompany.value.id, joinForm.password)
    await companyStore.selectCompany(company)
    router.push(`/app/company/${company.id}`)
    emit('close')
  } catch (err: any) {
    joinErrors.password = err.response?.data?.message || 'Beitritt fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.tabs {
  @apply flex gap-1 p-1 bg-chat rounded-lg mb-6;
}

.tab {
  @apply flex-1 py-2 px-4 text-sm font-medium text-gray-400 rounded-md transition-colors;
}

.tab.active {
  @apply bg-sidebar-light text-white;
}

.form {
  @apply space-y-4;
}

.search-section {
  @apply space-y-2;
}

.search-results {
  @apply max-h-48 overflow-y-auto space-y-1 border border-chat-hover rounded-lg p-1;
}

.search-result {
  @apply w-full flex justify-between items-center px-3 py-2 text-left rounded hover:bg-chat-hover transition-colors;
}

.search-result.selected {
  @apply bg-primary-600/20 border border-primary-500;
}

.company-name {
  @apply text-gray-200;
}

.company-members {
  @apply text-xs text-gray-500;
}
</style>
