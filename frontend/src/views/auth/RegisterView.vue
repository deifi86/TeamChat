<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="@/assets/logo.svg" alt="TeamChat" class="auth-logo" />
        <h1 class="auth-title">Konto erstellen</h1>
        <p class="auth-subtitle">Starte mit TeamChat</p>
      </div>

      <form @submit.prevent="handleSubmit" class="auth-form">
        <Input
          v-model="form.username"
          type="text"
          label="Benutzername"
          placeholder="Max Mustermann"
          :error="errors.username"
          :icon="UserIcon"
          required
        />

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
          placeholder="Mindestens 8 Zeichen"
          :error="errors.password"
          :icon="LockClosedIcon"
          hint="Mind. 8 Zeichen, Groß- und Kleinbuchstaben, Zahlen"
          required
          autocomplete="new-password"
        />

        <Input
          v-model="form.password_confirmation"
          type="password"
          label="Passwort bestätigen"
          placeholder="Passwort wiederholen"
          :error="errors.password_confirmation"
          :icon="LockClosedIcon"
          required
          autocomplete="new-password"
        />

        <div class="flex items-start">
          <input
            v-model="form.terms"
            type="checkbox"
            class="checkbox mt-1"
            required
          />
          <span class="ml-2 text-sm text-gray-400">
            Ich akzeptiere die
            <a href="#" class="text-primary-400 hover:text-primary-300">Nutzungsbedingungen</a>
            und
            <a href="#" class="text-primary-400 hover:text-primary-300">Datenschutzrichtlinie</a>
          </span>
        </div>

        <Button
          type="submit"
          :loading="authStore.loading"
          full-width
          size="lg"
        >
          Registrieren
        </Button>

        <p v-if="authStore.error" class="text-sm text-red-400 text-center">
          {{ authStore.error }}
        </p>
      </form>

      <div class="auth-footer">
        <p class="text-gray-400">
          Bereits registriert?
          <RouterLink to="/login" class="text-primary-400 hover:text-primary-300 font-medium">
            Anmelden
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { UserIcon, EnvelopeIcon, LockClosedIcon } from '@heroicons/vue/24/outline'
import Input from '@/components/common/Input.vue'
import Button from '@/components/common/Button.vue'

const authStore = useAuthStore()

const form = reactive({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  terms: false,
})

const errors = reactive({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
})

async function handleSubmit() {
  // Reset errors
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = ''
  })

  // Validation
  if (!form.username) {
    errors.username = 'Benutzername ist erforderlich'
    return
  }

  if (form.password !== form.password_confirmation) {
    errors.password_confirmation = 'Passwörter stimmen nicht überein'
    return
  }

  try {
    await authStore.register({
      username: form.username,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
  } catch (err: any) {
    if (err.response?.data?.errors) {
      const apiErrors = err.response.data.errors
      errors.username = apiErrors.username?.[0] || ''
      errors.email = apiErrors.email?.[0] || ''
      errors.password = apiErrors.password?.[0] || ''
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
