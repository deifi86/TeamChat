<template>
  <Modal
    :is-open="isOpen"
    title="Kanal erstellen"
    @close="$emit('close')"
  >
    <form @submit.prevent="createChannel" class="space-y-4">
      <Input
        v-model="form.name"
        label="Kanalname"
        placeholder="z.B. entwicklung"
        :error="errors.name"
        required
      />

      <Input
        v-model="form.description"
        label="Beschreibung (optional)"
        placeholder="Worum geht es in diesem Kanal?"
      />

      <div class="privacy-toggle">
        <label class="flex items-center justify-between">
          <div>
            <span class="toggle-label">Privater Kanal</span>
            <p class="toggle-description">
              {{ form.is_private
                ? 'Nur eingeladene Mitglieder können beitreten'
                : 'Alle Firmenmitglieder werden automatisch hinzugefügt'
              }}
            </p>
          </div>
          <button
            type="button"
            class="toggle-switch"
            :class="{ active: form.is_private }"
            @click="form.is_private = !form.is_private"
          >
            <span class="toggle-dot" />
          </button>
        </label>
      </div>
    </form>

    <template #footer>
      <Button variant="ghost" @click="$emit('close')">
        Abbrechen
      </Button>
      <Button
        type="submit"
        :loading="loading"
        @click="createChannel"
      >
        Erstellen
      </Button>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useCompanyStore } from '@/stores/company'
import Modal from '@/components/common/Modal.vue'
import Input from '@/components/common/Input.vue'
import Button from '@/components/common/Button.vue'

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits(['close'])

const router = useRouter()
const companyStore = useCompanyStore()

const loading = ref(false)

const form = reactive({
  name: '',
  description: '',
  is_private: true,
})

const errors = reactive({
  name: '',
})

async function createChannel() {
  loading.value = true
  errors.name = ''

  try {
    const channel = await companyStore.createChannel(form)
    router.push(`/app/company/${companyStore.currentCompany?.id}/channel/${channel.id}`)
    emit('close')

    // Reset form
    form.name = ''
    form.description = ''
    form.is_private = true
  } catch (err: any) {
    errors.name = err.response?.data?.errors?.name?.[0] || 'Fehler beim Erstellen'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.privacy-toggle {
  @apply p-4 bg-chat rounded-lg;
}

.toggle-label {
  @apply font-medium text-gray-200;
}

.toggle-description {
  @apply text-sm text-gray-500 mt-0.5;
}

.toggle-switch {
  @apply relative w-11 h-6 bg-chat-hover rounded-full transition-colors;
}

.toggle-switch.active {
  @apply bg-primary-600;
}

.toggle-dot {
  @apply absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform;
}

.toggle-switch.active .toggle-dot {
  @apply translate-x-5;
}
</style>
