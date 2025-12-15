<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="@/assets/logo.svg" alt="TeamChat" class="auth-logo" />
        <h1 class="auth-title">Willkommen zurück</h1>
        <p class="auth-subtitle">Melde dich an, um fortzufahren</p>
      </div>

      <form @submit.prevent="handleSubmit" class="auth-form">
        <Input
          v-model="form.email"
          type="email"
          label="E-Mail"
          placeholder="name@firma.de"
          :error="errors.email"
          :icon="EnvelopeIcon"
          required
          autocomplete="email"
        />

        <Input
          v-model="form.password"
          type="password"
          label="Passwort"
          placeholder="••••••••"
          :error="errors.password"
          :icon="LockClosedIcon"
          required
          autocomplete="current-password"
        />

        <div class="flex items-center justify-between">
          <label class="flex items-center">
            <input
              v-model="form.remember"
              type="checkbox"
              class="checkbox"
            />
            <span class="ml-2 text-sm text-gray-400">Angemeldet bleiben</span>
          </label>

          <a href="#" class="text-sm text-primary-400 hover:text-primary-300">
            Passwort vergessen?
          </a>
        </div>

        <Button
          type="submit"
          :loading="authStore.loading"
          full-width
          size="lg"
        >
          Anmelden
        </Button>

        <p v-if="authStore.error" class="text-sm text-red-400 text-center">
          {{ authStore.error }}
        </p>
      </form>

      <div class="auth-footer">
        <p class="text-gray-400">
          Noch kein Konto?
          <RouterLink to="/register" class="text-primary-400 hover:text-primary-300 font-medium">
            Jetzt registrieren
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { EnvelopeIcon, LockClosedIcon } from '@heroicons/vue/24/outline'
import Input from '@/components/common/Input.vue'
import Button from '@/components/common/Button.vue'

const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const errors = reactive({
  email: '',
  password: '',
})

async function handleSubmit() {
  errors.email = ''
  errors.password = ''

  if (!form.email) {
    errors.email = 'E-Mail ist erforderlich'
    return
  }

  if (!form.password) {
    errors.password = 'Passwort ist erforderlich'
    return
  }

  try {
    await authStore.login(form.email, form.password)
  } catch (err: any) {
    if (err.response?.data?.errors) {
      errors.email = err.response.data.errors.email?.[0] || ''
      errors.password = err.response.data.errors.password?.[0] || ''
    }
  }
}
</script>

<style scoped>
.auth-container {
  @apply min-h-screen flex items-center justify-center p-4 bg-sidebar;
}

.auth-card {
  @apply w-full max-w-md bg-sidebar-light rounded-2xl shadow-xl p-8;
}

.auth-header {
  @apply text-center mb-8;
}

.auth-logo {
  @apply w-12 h-12 mx-auto mb-4;
}

.auth-title {
  @apply text-2xl font-bold text-gray-100;
}

.auth-subtitle {
  @apply text-gray-400 mt-1;
}

.auth-form {
  @apply space-y-5;
}

.checkbox {
  @apply w-4 h-4 rounded bg-chat border-chat-hover text-primary-600 focus:ring-primary-500 focus:ring-offset-sidebar-light;
}

.auth-footer {
  @apply mt-8 pt-6 border-t border-chat-hover text-center;
}
</style>
