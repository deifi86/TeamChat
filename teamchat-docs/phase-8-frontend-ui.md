# Phase 8: Frontend UI (Woche 17-20)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Alle UI-Komponenten implementiert
- Login/Register Screens
- Haupt-Layout mit Sidebar
- Chat-Interface mit Nachrichten
- Direct Messages UI
- Datei-Upload und Preview
- Settings-Seite
- Responsive Design

---

## 8.1 Basis-Komponenten [FE]

### 8.1.1 TitleBar Komponente (Electron)
- [x] **Erledigt**

**Datei:** `frontend/src/components/layout/TitleBar.vue`
```vue
<template>
  <div class="titlebar">
    <div class="titlebar-drag">
      <div class="titlebar-logo">
        <img src="@/assets/logo.svg" alt="TeamChat" class="w-5 h-5" />
        <span class="ml-2 font-semibold text-sm">TeamChat</span>
      </div>
    </div>

    <div class="titlebar-controls">
      <button
        @click="minimize"
        class="titlebar-btn hover:bg-chat-hover"
        title="Minimieren"
      >
        <MinusIcon class="w-4 h-4" />
      </button>
      <button
        @click="maximize"
        class="titlebar-btn hover:bg-chat-hover"
        title="Maximieren"
      >
        <StopIcon v-if="isMaximized" class="w-4 h-4" />
        <Square2StackIcon v-else class="w-4 h-4" />
      </button>
      <button
        @click="close"
        class="titlebar-btn hover:bg-red-600"
        title="Schließen"
      >
        <XMarkIcon class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { MinusIcon, XMarkIcon, Square2StackIcon, StopIcon } from '@heroicons/vue/24/outline'

const isMaximized = ref(false)

const isElectron = !!window.electronAPI

function minimize() {
  if (isElectron) {
    window.electronAPI.minimizeWindow()
  }
}

async function maximize() {
  if (isElectron) {
    window.electronAPI.maximizeWindow()
    isMaximized.value = await window.electronAPI.isMaximized()
  }
}

function close() {
  if (isElectron) {
    window.electronAPI.closeWindow()
  }
}

onMounted(async () => {
  if (isElectron) {
    isMaximized.value = await window.electronAPI.isMaximized()
  }
})
</script>

<style scoped>
.titlebar {
  @apply flex items-center justify-between h-9 bg-sidebar-dark px-2;
  -webkit-app-region: drag;
}

.titlebar-drag {
  @apply flex-1 flex items-center;
}

.titlebar-logo {
  @apply flex items-center text-gray-300;
  -webkit-app-region: no-drag;
}

.titlebar-controls {
  @apply flex items-center;
  -webkit-app-region: no-drag;
}

.titlebar-btn {
  @apply w-10 h-9 flex items-center justify-center text-gray-400 transition-colors;
}
</style>
```

---

### 8.1.2 Avatar Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/Avatar.vue`
```vue
<template>
  <div class="avatar" :class="sizeClasses">
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      class="avatar-image"
      @error="onError"
    />
    <div v-else class="avatar-fallback">
      {{ initials }}
    </div>

    <!-- Status Indicator -->
    <span
      v-if="showStatus && status"
      class="avatar-status"
      :class="statusClasses"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
  src?: string | null
  alt?: string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  status?: 'available' | 'busy' | 'away' | 'offline' | null
  showStatus?: boolean
}>(), {
  src: null,
  alt: 'Avatar',
  size: 'md',
  status: null,
  showStatus: false,
})

const imageError = ref(false)

const initials = computed(() => {
  if (!props.alt) return '?'
  const words = props.alt.split(' ')
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase()
  }
  return props.alt.substring(0, 2).toUpperCase()
})

const sizeClasses = computed(() => {
  const sizes = {
    xs: 'w-6 h-6 text-xs',
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-base',
    lg: 'w-12 h-12 text-lg',
    xl: 'w-16 h-16 text-xl',
  }
  return sizes[props.size]
})

const statusClasses = computed(() => {
  const colors = {
    available: 'bg-green-500',
    busy: 'bg-red-500',
    away: 'bg-yellow-500',
    offline: 'bg-gray-500',
  }
  return props.status ? colors[props.status] : ''
})

function onError() {
  imageError.value = true
}
</script>

<style scoped>
.avatar {
  @apply relative inline-flex items-center justify-center rounded-full bg-chat-hover overflow-hidden flex-shrink-0;
}

.avatar-image {
  @apply w-full h-full object-cover;
}

.avatar-fallback {
  @apply font-medium text-gray-300;
}

.avatar-status {
  @apply absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-sidebar;
}

.avatar.w-6 .avatar-status {
  @apply w-2 h-2;
}

.avatar.w-8 .avatar-status {
  @apply w-2.5 h-2.5;
}
</style>
```

---

### 8.1.3 Button Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/Button.vue`
```vue
<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
    @click="$emit('click', $event)"
  >
    <LoadingSpinner v-if="loading" class="w-4 h-4 mr-2" />
    <component
      v-else-if="icon"
      :is="icon"
      class="w-4 h-4"
      :class="{ 'mr-2': !!$slots.default }"
    />
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'
import LoadingSpinner from './LoadingSpinner.vue'

const props = withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset'
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'link'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  icon?: Component
  fullWidth?: boolean
}>(), {
  type: 'button',
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  fullWidth: false,
})

defineEmits(['click'])

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-sidebar disabled:opacity-50 disabled:cursor-not-allowed'

  const variants = {
    primary: 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
    secondary: 'bg-chat text-gray-100 hover:bg-chat-hover focus:ring-chat-active',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    ghost: 'text-gray-400 hover:text-gray-100 hover:bg-chat-hover focus:ring-chat-active',
    link: 'text-primary-400 hover:text-primary-300 underline-offset-4 hover:underline',
  }

  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
  }

  return [
    base,
    variants[props.variant],
    sizes[props.size],
    props.fullWidth ? 'w-full' : '',
  ]
})
</script>
```

---

### 8.1.4 Input Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/Input.vue`
```vue
<template>
  <div class="input-wrapper">
    <label v-if="label" :for="id" class="input-label">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <div class="input-container" :class="{ 'has-icon': !!icon }">
      <component
        v-if="icon"
        :is="icon"
        class="input-icon"
      />
      
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :autocomplete="autocomplete"
        class="input"
        :class="{ 'has-error': !!error, 'pl-10': !!icon }"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
      />
    </div>

    <p v-if="error" class="input-error">{{ error }}</p>
    <p v-else-if="hint" class="input-hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string
  type?: string
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
  autocomplete?: string
  icon?: Component
  id?: string
}>(), {
  type: 'text',
  disabled: false,
  required: false,
})

defineEmits(['update:modelValue', 'blur', 'focus'])

const id = computed(() => props.id || `input-${Math.random().toString(36).substr(2, 9)}`)
</script>

<style scoped>
.input-wrapper {
  @apply space-y-1;
}

.input-label {
  @apply block text-sm font-medium text-gray-300;
}

.input-container {
  @apply relative;
}

.input-icon {
  @apply absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500;
}

.input {
  @apply w-full px-4 py-2.5 bg-chat border border-chat-hover rounded-lg text-gray-100 placeholder-gray-500;
  @apply focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
  @apply transition-colors duration-200;
}

.input.has-error {
  @apply border-red-500 focus:ring-red-500;
}

.input-error {
  @apply text-sm text-red-400;
}

.input-hint {
  @apply text-sm text-gray-500;
}
</style>
```

---

### 8.1.5 Modal Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/Modal.vue`
```vue
<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" class="relative z-50" @close="closeModal">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/60" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="modal-panel"
              :class="sizeClasses"
            >
              <div class="modal-header" v-if="title || $slots.header">
                <slot name="header">
                  <DialogTitle class="modal-title">
                    {{ title }}
                  </DialogTitle>
                </slot>
                <button
                  v-if="showClose"
                  type="button"
                  class="modal-close"
                  @click="closeModal"
                >
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>

              <div class="modal-body">
                <slot />
              </div>

              <div v-if="$slots.footer" class="modal-footer">
                <slot name="footer" />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
  DialogTitle,
} from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(defineProps<{
  isOpen: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full'
  showClose?: boolean
  closeOnClickOutside?: boolean
}>(), {
  size: 'md',
  showClose: true,
  closeOnClickOutside: true,
})

const emit = defineEmits(['close'])

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    full: 'max-w-4xl',
  }
  return sizes[props.size]
})

function closeModal() {
  if (props.closeOnClickOutside) {
    emit('close')
  }
}
</script>

<style scoped>
.modal-panel {
  @apply w-full transform overflow-hidden rounded-xl bg-sidebar-light text-left align-middle shadow-xl transition-all;
}

.modal-header {
  @apply flex items-center justify-between px-6 py-4 border-b border-chat-hover;
}

.modal-title {
  @apply text-lg font-semibold text-gray-100;
}

.modal-close {
  @apply text-gray-400 hover:text-gray-200 transition-colors;
}

.modal-body {
  @apply px-6 py-4;
}

.modal-footer {
  @apply px-6 py-4 border-t border-chat-hover bg-sidebar-dark/50 flex justify-end gap-3;
}
</style>
```

---

### 8.1.6 LoadingSpinner Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/LoadingSpinner.vue`
```vue
<template>
  <svg
    class="animate-spin"
    :class="sizeClass"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
  >
    <circle
      class="opacity-25"
      cx="12"
      cy="12"
      r="10"
      stroke="currentColor"
      stroke-width="4"
    />
    <path
      class="opacity-75"
      fill="currentColor"
      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
    />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg'
}>(), {
  size: 'md',
})

const sizeClass = computed(() => ({
  sm: 'w-4 h-4',
  md: 'w-6 h-6',
  lg: 'w-8 h-8',
}[props.size]))
</script>
```

---

### 8.1.7 Dropdown Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/common/Dropdown.vue`
```vue
<template>
  <Menu as="div" class="relative inline-block text-left">
    <MenuButton as="template">
      <slot name="trigger" />
    </MenuButton>

    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <MenuItems
        class="dropdown-menu"
        :class="[positionClasses, widthClass]"
      >
        <slot />
      </MenuItems>
    </transition>
  </Menu>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Menu, MenuButton, MenuItems } from '@headlessui/vue'

const props = withDefaults(defineProps<{
  position?: 'left' | 'right'
  width?: 'auto' | 'sm' | 'md' | 'lg'
}>(), {
  position: 'right',
  width: 'auto',
})

const positionClasses = computed(() => ({
  left: 'left-0 origin-top-left',
  right: 'right-0 origin-top-right',
}[props.position]))

const widthClass = computed(() => ({
  auto: 'w-auto',
  sm: 'w-40',
  md: 'w-48',
  lg: 'w-56',
}[props.width]))
</script>

<style scoped>
.dropdown-menu {
  @apply absolute z-50 mt-2 rounded-lg bg-sidebar-light shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none py-1;
}
</style>
```

**Datei:** `frontend/src/components/common/DropdownItem.vue`
```vue
<template>
  <MenuItem v-slot="{ active }">
    <button
      type="button"
      class="dropdown-item"
      :class="[
        active ? 'bg-chat-hover text-gray-100' : 'text-gray-300',
        danger ? 'text-red-400 hover:text-red-300' : '',
      ]"
      :disabled="disabled"
      @click="$emit('click')"
    >
      <component v-if="icon" :is="icon" class="w-4 h-4 mr-3" />
      <slot />
    </button>
  </MenuItem>
</template>

<script setup lang="ts">
import { type Component } from 'vue'
import { MenuItem } from '@headlessui/vue'

defineProps<{
  icon?: Component
  danger?: boolean
  disabled?: boolean
}>()

defineEmits(['click'])
</script>

<style scoped>
.dropdown-item {
  @apply flex items-center w-full px-4 py-2 text-sm text-left;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
}
</style>
```

---

## 8.2 Auth Views [FE]

### 8.2.1 Login View
- [x] **Erledigt**

**Datei:** `frontend/src/views/auth/LoginView.vue`
```vue
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
```

---

### 8.2.2 Register View
- [x] **Erledigt**

**Datei:** `frontend/src/views/auth/RegisterView.vue`
```vue
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
```

---

## 8.3 Main Layout [FE]

### 8.3.1 MainLayout View
- [x] **Erledigt**

**Datei:** `frontend/src/views/MainLayout.vue`
```vue
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
```

---

### 8.3.2 CompanySidebar Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/layout/CompanySidebar.vue`
```vue
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
```

---

### 8.3.3 ChannelSidebar Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/layout/ChannelSidebar.vue`
```vue
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
```

---

## 8.4 Chat Components [FE]

### 8.4.1 ChannelView
- [x] **Erledigt**

**Datei:** `frontend/src/views/ChannelView.vue`
```vue
<template>
  <div class="channel-view">
    <!-- Channel Header -->
    <div class="channel-header">
      <div class="header-info">
        <HashtagIcon class="w-5 h-5 text-gray-400 mr-2" />
        <h1 class="channel-title">{{ companyStore.currentChannel?.name }}</h1>
        <span v-if="companyStore.currentChannel?.description" class="channel-description">
          {{ companyStore.currentChannel.description }}
        </span>
      </div>
      <div class="header-actions">
        <button class="header-btn" title="Mitglieder">
          <UsersIcon class="w-5 h-5" />
          <span>{{ companyStore.currentChannel?.members_count }}</span>
        </button>
        <button class="header-btn" title="Suchen">
          <MagnifyingGlassIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Messages Area -->
    <div
      ref="messagesContainer"
      class="messages-container"
      @scroll="handleScroll"
    >
      <!-- Load More -->
      <div v-if="chatStore.hasMore" class="load-more">
        <button
          class="load-more-btn"
          :disabled="chatStore.loading"
          @click="loadMore"
        >
          <LoadingSpinner v-if="chatStore.loading" size="sm" />
          <span v-else>Ältere Nachrichten laden</span>
        </button>
      </div>

      <!-- Messages -->
      <MessageList :messages="chatStore.sortedMessages" />

      <!-- Typing Indicator -->
      <TypingIndicator
        v-if="chatStore.typingUsernames.length > 0"
        :usernames="chatStore.typingUsernames"
      />
    </div>

    <!-- Message Input -->
    <MessageInput
      :channel-id="channelId"
      @send="sendMessage"
      @typing="handleTyping"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useCompanyStore } from '@/stores/company'
import { useChatStore } from '@/stores/chat'
import { HashtagIcon, UsersIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import MessageList from '@/components/chat/MessageList.vue'
import MessageInput from '@/components/chat/MessageInput.vue'
import TypingIndicator from '@/components/chat/TypingIndicator.vue'

const route = useRoute()
const companyStore = useCompanyStore()
const chatStore = useChatStore()

const messagesContainer = ref<HTMLElement | null>(null)

const channelId = computed(() => Number(route.params.channelId))

// Channel wechseln
watch(channelId, async (newId, oldId) => {
  if (newId && newId !== oldId) {
    await loadChannel(newId)
  }
}, { immediate: true })

async function loadChannel(id: number) {
  // Channel in Store setzen
  const channel = companyStore.channels.find(c => c.id === id)
  if (channel) {
    await companyStore.selectChannel(channel)
  }

  // Nachrichten laden
  chatStore.clearMessages()
  await chatStore.loadMessages(id)

  // WebSocket subscriben
  chatStore.subscribeToChannelMessages(id)

  // Zum Ende scrollen
  await nextTick()
  scrollToBottom()
}

async function loadMore() {
  await chatStore.loadMessages(channelId.value, true)
}

function sendMessage(content: string) {
  chatStore.sendMessage(channelId.value, content)
  nextTick(() => scrollToBottom())
}

function handleTyping() {
  chatStore.sendTyping(channelId.value)
}

function handleScroll(event: Event) {
  const target = event.target as HTMLElement
  // Auto-Load wenn oben angekommen
  if (target.scrollTop < 100 && chatStore.hasMore && !chatStore.loading) {
    loadMore()
  }
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

onMounted(() => {
  // Scroll bei neuen Nachrichten (wenn bereits unten)
  watch(() => chatStore.messages.length, () => {
    if (messagesContainer.value) {
      const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value
      const isNearBottom = scrollHeight - scrollTop - clientHeight < 100
      if (isNearBottom) {
        nextTick(() => scrollToBottom())
      }
    }
  })
})

onUnmounted(() => {
  chatStore.clearMessages()
})
</script>

<style scoped>
.channel-view {
  @apply flex flex-col h-full;
}

.channel-header {
  @apply h-12 px-4 flex items-center justify-between border-b border-chat-hover bg-chat flex-shrink-0;
}

.header-info {
  @apply flex items-center;
}

.channel-title {
  @apply font-semibold text-gray-100;
}

.channel-description {
  @apply ml-3 text-sm text-gray-400 truncate max-w-md;
}

.header-actions {
  @apply flex items-center gap-2;
}

.header-btn {
  @apply flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.messages-container {
  @apply flex-1 overflow-y-auto px-4 py-4;
}

.load-more {
  @apply flex justify-center py-4;
}

.load-more-btn {
  @apply px-4 py-2 text-sm text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors disabled:opacity-50;
}
</style>
```

---

### 8.4.2 MessageList Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/chat/MessageList.vue`
```vue
<template>
  <div class="message-list">
    <template v-for="(message, index) in messages" :key="message.id">
      <!-- Date Separator -->
      <DateSeparator
        v-if="shouldShowDateSeparator(message, messages[index - 1])"
        :date="message.created_at"
      />

      <!-- Message -->
      <MessageItem
        :message="message"
        :show-avatar="shouldShowAvatar(message, messages[index - 1])"
        :is-own="message.sender.id === authStore.currentUserId"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import type { Message } from '@/stores/chat'
import MessageItem from './MessageItem.vue'
import DateSeparator from './DateSeparator.vue'
import { isSameDay, parseISO } from 'date-fns'

defineProps<{
  messages: Message[]
}>()

const authStore = useAuthStore()

function shouldShowDateSeparator(current: Message, previous?: Message): boolean {
  if (!previous) return true
  return !isSameDay(parseISO(current.created_at), parseISO(previous.created_at))
}

function shouldShowAvatar(current: Message, previous?: Message): boolean {
  if (!previous) return true
  if (current.sender.id !== previous.sender.id) return true
  
  // Mehr als 5 Minuten Unterschied
  const currentTime = parseISO(current.created_at).getTime()
  const previousTime = parseISO(previous.created_at).getTime()
  return currentTime - previousTime > 5 * 60 * 1000
}
</script>

<style scoped>
.message-list {
  @apply space-y-0.5;
}
</style>
```

---

### 8.4.3 MessageItem Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/chat/MessageItem.vue`
```vue
<template>
  <div
    class="message-item"
    :class="{ 'is-own': isOwn, 'has-avatar': showAvatar }"
    @mouseenter="showActions = true"
    @mouseleave="showActions = false"
  >
    <!-- Avatar -->
    <div class="message-avatar">
      <Avatar
        v-if="showAvatar"
        :src="message.sender.avatar_url"
        :alt="message.sender.username"
        size="sm"
      />
    </div>

    <!-- Content -->
    <div class="message-content">
      <!-- Header (nur wenn Avatar sichtbar) -->
      <div v-if="showAvatar" class="message-header">
        <span class="sender-name">{{ message.sender.username }}</span>
        <span class="message-time">{{ formatTime(message.created_at) }}</span>
      </div>

      <!-- Body -->
      <div class="message-body">
        <!-- Text Content -->
        <p v-if="message.content_type === 'text'" class="message-text">
          {{ message.content }}
          <span v-if="message.edited_at" class="edited-badge">(bearbeitet)</span>
        </p>

        <!-- File/Image Content -->
        <FilePreview
          v-else-if="message.content_type === 'file' || message.content_type === 'image'"
          :file="message.file"
        />

        <!-- Reactions -->
        <MessageReactions
          v-if="message.reactions.length > 0"
          :reactions="message.reactions"
          :message-id="message.id"
        />
      </div>
    </div>

    <!-- Actions -->
    <div v-show="showActions" class="message-actions">
      <button
        class="action-btn"
        title="Emoji hinzufügen"
        @click="showEmojiPicker = true"
      >
        <FaceSmileIcon class="w-4 h-4" />
      </button>
      <button
        v-if="isOwn"
        class="action-btn"
        title="Bearbeiten"
        @click="$emit('edit', message)"
      >
        <PencilIcon class="w-4 h-4" />
      </button>
      <button
        v-if="isOwn"
        class="action-btn"
        title="Löschen"
        @click="$emit('delete', message)"
      >
        <TrashIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Emoji Picker Popover -->
    <EmojiPicker
      v-if="showEmojiPicker"
      @select="handleEmojiSelect"
      @close="showEmojiPicker = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { format, parseISO } from 'date-fns'
import { de } from 'date-fns/locale'
import type { Message } from '@/stores/chat'
import { useChatStore } from '@/stores/chat'
import { FaceSmileIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import FilePreview from './FilePreview.vue'
import MessageReactions from './MessageReactions.vue'
import EmojiPicker from './EmojiPicker.vue'

const props = defineProps<{
  message: Message
  showAvatar: boolean
  isOwn: boolean
}>()

defineEmits(['edit', 'delete'])

const chatStore = useChatStore()
const showActions = ref(false)
const showEmojiPicker = ref(false)

function formatTime(dateString: string): string {
  return format(parseISO(dateString), 'HH:mm', { locale: de })
}

async function handleEmojiSelect(emoji: string) {
  await chatStore.toggleReaction(props.message.id, emoji)
  showEmojiPicker.value = false
}
</script>

<style scoped>
.message-item {
  @apply relative flex gap-3 py-0.5 px-2 -mx-2 rounded hover:bg-chat-hover/50 group;
}

.message-item.has-avatar {
  @apply mt-4;
}

.message-avatar {
  @apply w-10 flex-shrink-0;
}

.message-content {
  @apply flex-1 min-w-0;
}

.message-header {
  @apply flex items-baseline gap-2 mb-0.5;
}

.sender-name {
  @apply font-medium text-gray-100;
}

.message-time {
  @apply text-xs text-gray-500;
}

.message-body {
  @apply space-y-1;
}

.message-text {
  @apply text-gray-200 whitespace-pre-wrap break-words;
}

.edited-badge {
  @apply text-xs text-gray-500 ml-1;
}

.message-actions {
  @apply absolute right-2 -top-3 flex items-center gap-0.5 px-1 py-0.5 bg-sidebar-light rounded-lg shadow-lg border border-chat-hover;
}

.action-btn {
  @apply p-1.5 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}
</style>
```

---

### 8.4.4 MessageInput Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/chat/MessageInput.vue`
```vue
<template>
  <div class="message-input-container">
    <!-- File Preview -->
    <div v-if="attachedFile" class="attached-file">
      <div class="file-preview">
        <img
          v-if="isImage"
          :src="filePreviewUrl"
          class="preview-image"
        />
        <DocumentIcon v-else class="w-8 h-8 text-gray-400" />
        <span class="file-name">{{ attachedFile.name }}</span>
        <button class="remove-file" @click="removeFile">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Input Area -->
    <div class="input-wrapper">
      <!-- Attach Button -->
      <button class="input-btn" @click="triggerFileInput">
        <PlusIcon class="w-5 h-5" />
      </button>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        @change="handleFileSelect"
      />

      <!-- Text Input -->
      <div class="text-input-wrapper">
        <textarea
          ref="textarea"
          v-model="message"
          :placeholder="placeholder"
          class="text-input"
          rows="1"
          @input="handleInput"
          @keydown="handleKeydown"
        />
      </div>

      <!-- Emoji Button -->
      <button class="input-btn" @click="showEmojiPicker = !showEmojiPicker">
        <FaceSmileIcon class="w-5 h-5" />
      </button>

      <!-- Send Button -->
      <button
        class="send-btn"
        :disabled="!canSend"
        @click="send"
      >
        <PaperAirplaneIcon class="w-5 h-5" />
      </button>
    </div>

    <!-- Emoji Picker -->
    <div v-if="showEmojiPicker" class="emoji-picker-container">
      <EmojiPicker
        @select="insertEmoji"
        @close="showEmojiPicker = false"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import {
  PlusIcon,
  FaceSmileIcon,
  PaperAirplaneIcon,
  XMarkIcon,
  DocumentIcon,
} from '@heroicons/vue/24/outline'
import EmojiPicker from './EmojiPicker.vue'

const props = withDefaults(defineProps<{
  channelId: number
  placeholder?: string
}>(), {
  placeholder: 'Nachricht schreiben...',
})

const emit = defineEmits(['send', 'typing', 'sendFile'])

const message = ref('')
const attachedFile = ref<File | null>(null)
const filePreviewUrl = ref<string | null>(null)
const showEmojiPicker = ref(false)
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const canSend = computed(() => {
  return message.value.trim().length > 0 || attachedFile.value !== null
})

const isImage = computed(() => {
  return attachedFile.value?.type.startsWith('image/')
})

function handleInput() {
  emit('typing')
  autoResize()
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    send()
  }
}

function send() {
  if (!canSend.value) return

  if (attachedFile.value) {
    emit('sendFile', attachedFile.value, message.value)
    removeFile()
  } else {
    emit('send', message.value)
  }

  message.value = ''
  nextTick(() => autoResize())
}

function autoResize() {
  if (textarea.value) {
    textarea.value.style.height = 'auto'
    textarea.value.style.height = Math.min(textarea.value.scrollHeight, 200) + 'px'
  }
}

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  
  if (file) {
    attachedFile.value = file
    
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => {
        filePreviewUrl.value = e.target?.result as string
      }
      reader.readAsDataURL(file)
    }
  }
  
  input.value = ''
}

function removeFile() {
  attachedFile.value = null
  filePreviewUrl.value = null
}

function insertEmoji(emoji: string) {
  message.value += emoji
  showEmojiPicker.value = false
  textarea.value?.focus()
}
</script>

<style scoped>
.message-input-container {
  @apply relative px-4 py-3 border-t border-chat-hover bg-chat;
}

.attached-file {
  @apply mb-3;
}

.file-preview {
  @apply inline-flex items-center gap-2 px-3 py-2 bg-sidebar-light rounded-lg;
}

.preview-image {
  @apply w-12 h-12 object-cover rounded;
}

.file-name {
  @apply text-sm text-gray-300 max-w-[200px] truncate;
}

.remove-file {
  @apply p-1 text-gray-400 hover:text-red-400 transition-colors;
}

.input-wrapper {
  @apply flex items-end gap-2 bg-sidebar-light rounded-xl px-3 py-2;
}

.input-btn {
  @apply p-2 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded-lg transition-colors flex-shrink-0;
}

.text-input-wrapper {
  @apply flex-1 min-w-0;
}

.text-input {
  @apply w-full bg-transparent text-gray-100 placeholder-gray-500 resize-none;
  @apply focus:outline-none;
  max-height: 200px;
}

.send-btn {
  @apply p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors flex-shrink-0;
  @apply disabled:opacity-50 disabled:cursor-not-allowed;
}

.emoji-picker-container {
  @apply absolute bottom-full right-4 mb-2;
}
</style>
```

---

### 8.4.5 MessageReactions Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/chat/MessageReactions.vue`
```vue
<template>
  <div class="reactions-container">
    <button
      v-for="reaction in reactions"
      :key="reaction.emoji"
      class="reaction-badge"
      :class="{ 'is-own': hasReacted(reaction) }"
      @click="toggleReaction(reaction.emoji)"
    >
      <span class="reaction-emoji">{{ reaction.emoji }}</span>
      <span class="reaction-count">{{ reaction.count }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useChatStore, type Reaction } from '@/stores/chat'

const props = defineProps<{
  reactions: Reaction[]
  messageId: number
}>()

const authStore = useAuthStore()
const chatStore = useChatStore()

function hasReacted(reaction: Reaction): boolean {
  return reaction.user_ids.includes(authStore.currentUserId!)
}

function toggleReaction(emoji: string) {
  chatStore.toggleReaction(props.messageId, emoji)
}
</script>

<style scoped>
.reactions-container {
  @apply flex flex-wrap gap-1 mt-1;
}

.reaction-badge {
  @apply inline-flex items-center gap-1 px-2 py-0.5 bg-chat-hover rounded-full text-sm;
  @apply hover:bg-chat-active transition-colors;
}

.reaction-badge.is-own {
  @apply bg-primary-600/20 border border-primary-500/50;
}

.reaction-emoji {
  @apply text-base;
}

.reaction-count {
  @apply text-gray-400 text-xs;
}

.reaction-badge.is-own .reaction-count {
  @apply text-primary-400;
}
</style>
```

---

### 8.4.6 TypingIndicator Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/chat/TypingIndicator.vue`
```vue
<template>
  <div class="typing-indicator">
    <div class="typing-dots">
      <span class="dot" />
      <span class="dot" />
      <span class="dot" />
    </div>
    <span class="typing-text">
      {{ typingText }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  usernames: string[]
}>()

const typingText = computed(() => {
  const names = props.usernames
  
  if (names.length === 1) {
    return `${names[0]} tippt...`
  }
  
  if (names.length === 2) {
    return `${names[0]} und ${names[1]} tippen...`
  }
  
  if (names.length > 2) {
    return `${names[0]} und ${names.length - 1} weitere tippen...`
  }
  
  return ''
})
</script>

<style scoped>
.typing-indicator {
  @apply flex items-center gap-2 py-2 text-sm text-gray-400;
}

.typing-dots {
  @apply flex gap-1;
}

.dot {
  @apply w-2 h-2 bg-gray-400 rounded-full;
  animation: bounce 1.4s infinite ease-in-out;
}

.dot:nth-child(1) {
  animation-delay: 0s;
}

.dot:nth-child(2) {
  animation-delay: 0.2s;
}

.dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes bounce {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-4px);
  }
}
</style>
```

---

## 8.5 Direct Messages [FE]

### 8.5.1 DMSidebar Komponente
- [x] **Erledigt**

**Datei:** `frontend/src/components/layout/DMSidebar.vue`
```vue
<template>
  <aside class="dm-sidebar">
    <div class="sidebar-header">
      <h2 class="sidebar-title">Direktnachrichten</h2>
      <button
        class="new-dm-btn"
        title="Neue Nachricht"
        @click="showNewDMModal = true"
      >
        <PlusIcon class="w-5 h-5" />
      </button>
    </div>

    <div class="sidebar-content">
      <!-- Pending Requests -->
      <div v-if="conversationStore.pendingConversations.length > 0" class="section">
        <h3 class="section-title">Anfragen</h3>
        <div class="conversation-list">
          <button
            v-for="conv in conversationStore.pendingConversations"
            :key="conv.id"
            class="conversation-item pending"
            @click="selectConversation(conv)"
          >
            <Avatar
              :src="conv.other_user.avatar_url"
              :alt="conv.other_user.username"
              :status="conv.other_user.status"
              show-status
              size="sm"
            />
            <div class="conversation-info">
              <span class="conversation-name">{{ conv.other_user.username }}</span>
              <span class="pending-badge">Neue Anfrage</span>
            </div>
          </button>
        </div>
      </div>

      <!-- Active Conversations -->
      <div class="section">
        <div class="conversation-list">
          <RouterLink
            v-for="conv in conversationStore.acceptedConversations"
            :key="conv.id"
            :to="`/app/dm/${conv.id}`"
            class="conversation-item"
            :class="{ active: conversationStore.currentConversation?.id === conv.id }"
          >
            <Avatar
              :src="conv.other_user.avatar_url"
              :alt="conv.other_user.username"
              :status="conv.other_user.status"
              show-status
              size="sm"
            />
            <div class="conversation-info">
              <span class="conversation-name">{{ conv.other_user.username }}</span>
              <span v-if="conv.last_message" class="last-message">
                {{ conv.last_message.is_mine ? 'Du: ' : '' }}{{ truncate(conv.last_message.content, 30) }}
              </span>
            </div>
            <span
              v-if="conv.unread_count > 0"
              class="unread-badge"
            >
              {{ conv.unread_count }}
            </span>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- New DM Modal -->
    <NewDMModal
      :is-open="showNewDMModal"
      @close="showNewDMModal = false"
    />
  </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useConversationStore, type Conversation } from '@/stores/conversation'
import { PlusIcon } from '@heroicons/vue/24/outline'
import Avatar from '@/components/common/Avatar.vue'
import NewDMModal from '@/components/modals/NewDMModal.vue'

const router = useRouter()
const conversationStore = useConversationStore()

const showNewDMModal = ref(false)

function selectConversation(conversation: Conversation) {
  conversationStore.selectConversation(conversation)
  router.push(`/app/dm/${conversation.id}`)
}

function truncate(text: string, length: number): string {
  if (text.length <= length) return text
  return text.substring(0, length) + '...'
}
</script>

<style scoped>
.dm-sidebar {
  @apply w-60 bg-sidebar-light flex flex-col border-r border-chat-hover;
}

.sidebar-header {
  @apply h-12 px-4 flex items-center justify-between border-b border-chat-hover;
}

.sidebar-title {
  @apply font-semibold text-gray-100;
}

.new-dm-btn {
  @apply p-1.5 text-gray-400 hover:text-gray-200 hover:bg-chat-hover rounded transition-colors;
}

.sidebar-content {
  @apply flex-1 overflow-y-auto py-2;
}

.section {
  @apply mb-4;
}

.section-title {
  @apply px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider;
}

.conversation-list {
  @apply space-y-0.5;
}

.conversation-item {
  @apply flex items-center gap-3 px-4 py-2 mx-2 rounded text-gray-300 hover:bg-chat-hover transition-colors;
}

.conversation-item.active {
  @apply bg-chat-hover text-white;
}

.conversation-item.pending {
  @apply bg-yellow-500/10 border border-yellow-500/30;
}

.conversation-info {
  @apply flex-1 min-w-0;
}

.conversation-name {
  @apply block font-medium truncate;
}

.last-message {
  @apply block text-xs text-gray-500 truncate;
}

.pending-badge {
  @apply block text-xs text-yellow-400;
}

.unread-badge {
  @apply min-w-[20px] h-5 px-1.5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center;
}
</style>
```

---

### 8.5.2 ConversationView
- [x] **Erledigt**

**Datei:** `frontend/src/views/ConversationView.vue`
```vue
<template>
  <div class="conversation-view">
    <!-- Header -->
    <div class="conversation-header">
      <div class="header-info">
        <Avatar
          :src="conversationStore.currentConversation?.other_user.avatar_url"
          :alt="conversationStore.currentConversation?.other_user.username"
          :status="conversationStore.currentConversation?.other_user.status"
          show-status
          size="sm"
        />
        <div class="user-info">
          <span class="username">{{ conversationStore.currentConversation?.other_user.username }}</span>
          <span class="status-text">{{ statusText }}</span>
        </div>
      </div>
    </div>

    <!-- Pending Request Notice -->
    <div
      v-if="conversationStore.currentConversation?.is_pending_my_acceptance"
      class="pending-notice"
    >
      <p class="notice-text">
        <strong>{{ conversationStore.currentConversation?.other_user.username }}</strong>
        möchte dir eine Nachricht senden.
      </p>
      <div class="notice-actions">
        <Button variant="primary" @click="acceptConversation">
          Akzeptieren
        </Button>
        <Button variant="ghost" @click="rejectConversation">
          Ablehnen
        </Button>
      </div>
    </div>

    <!-- Messages -->
    <template v-else>
      <div ref="messagesContainer" class="messages-container">
        <div v-if="conversationStore.hasMore" class="load-more">
          <button class="load-more-btn" @click="loadMore">
            Ältere Nachrichten laden
          </button>
        </div>

        <MessageList :messages="conversationStore.sortedMessages" />
      </div>

      <MessageInput
        :channel-id="conversationId"
        placeholder="Nachricht schreiben..."
        @send="sendMessage"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConversationStore } from '@/stores/conversation'
import Avatar from '@/components/common/Avatar.vue'
import Button from '@/components/common/Button.vue'
import MessageList from '@/components/chat/MessageList.vue'
import MessageInput from '@/components/chat/MessageInput.vue'

const route = useRoute()
const router = useRouter()
const conversationStore = useConversationStore()

const messagesContainer = ref<HTMLElement | null>(null)

const conversationId = computed(() => Number(route.params.conversationId))

const statusText = computed(() => {
  const status = conversationStore.currentConversation?.other_user.status
  const map = {
    available: 'Online',
    busy: 'Beschäftigt',
    away: 'Abwesend',
    offline: 'Offline',
  }
  return status ? map[status] : ''
})

watch(conversationId, async (newId) => {
  if (newId) {
    const conv = conversationStore.conversations.find(c => c.id === newId)
    if (conv) {
      await conversationStore.selectConversation(conv)
      nextTick(() => scrollToBottom())
    }
  }
}, { immediate: true })

async function loadMore() {
  await conversationStore.loadMessages(conversationId.value, true)
}

async function sendMessage(content: string) {
  await conversationStore.sendMessage(conversationId.value, content)
  nextTick(() => scrollToBottom())
}

async function acceptConversation() {
  await conversationStore.acceptConversation(conversationId.value)
}

async function rejectConversation() {
  await conversationStore.rejectConversation(conversationId.value)
  router.push('/app/dm')
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}
</script>

<style scoped>
.conversation-view {
  @apply flex flex-col h-full;
}

.conversation-header {
  @apply h-12 px-4 flex items-center border-b border-chat-hover bg-chat;
}

.header-info {
  @apply flex items-center gap-3;
}

.user-info {
  @apply flex flex-col;
}

.username {
  @apply font-semibold text-gray-100;
}

.status-text {
  @apply text-xs text-gray-400;
}

.pending-notice {
  @apply flex-1 flex flex-col items-center justify-center p-8 text-center;
}

.notice-text {
  @apply text-gray-300 mb-4;
}

.notice-actions {
  @apply flex gap-3;
}

.messages-container {
  @apply flex-1 overflow-y-auto px-4 py-4;
}

.load-more {
  @apply flex justify-center py-4;
}

.load-more-btn {
  @apply text-sm text-gray-400 hover:text-gray-200;
}
</style>
```

---

## 8.6 Modals [FE]

### 8.6.1 AddCompanyModal
- [x] **Erledigt**

**Datei:** `frontend/src/components/modals/AddCompanyModal.vue`
```vue
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
```

---

### 8.6.2 CreateChannelModal
- [x] **Erledigt**

**Datei:** `frontend/src/components/modals/CreateChannelModal.vue`
```vue
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
```

---

## 8.7 Tests & Abschluss [FE]

### 8.7.1 Component Tests
- [x] **Erledigt**

**Datei:** `frontend/src/components/__tests__/Button.spec.ts`
```typescript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from '../common/Button.vue'

describe('Button', () => {
  it('renders slot content', () => {
    const wrapper = mount(Button, {
      slots: {
        default: 'Click me',
      },
    })
    expect(wrapper.text()).toContain('Click me')
  })

  it('applies variant classes', () => {
    const wrapper = mount(Button, {
      props: { variant: 'danger' },
    })
    expect(wrapper.classes()).toContain('bg-red-600')
  })

  it('shows loading spinner when loading', () => {
    const wrapper = mount(Button, {
      props: { loading: true },
    })
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('disables button when disabled prop is true', () => {
    const wrapper = mount(Button, {
      props: { disabled: true },
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  it('emits click event', async () => {
    const wrapper = mount(Button)
    await wrapper.trigger('click')
    expect(wrapper.emitted()).toHaveProperty('click')
  })
})
```

---

### 8.7.2 View Tests
- [x] **Erledigt**

**Datei:** `frontend/src/views/__tests__/LoginView.spec.ts`
```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import LoginView from '../auth/LoginView.vue'

// Mock Router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
  RouterLink: {
    template: '<a><slot /></a>',
  },
}))

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders login form', () => {
    const wrapper = mount(LoginView)
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('shows validation error for empty email', async () => {
    const wrapper = mount(LoginView)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('E-Mail ist erforderlich')
  })
})
```

---

### 8.7.3 Alle Tests ausführen
- [x] **Erledigt**

**Durchführung:**
```bash
cd frontend
npm run test:unit
```

**Akzeptanzkriterien:**
- [ ] Alle Tests grün
- [ ] Mindestens 20 Component/View Tests

---

### 8.7.4 Build testen
- [x] **Erledigt**

**Durchführung:**
```bash
# Web Build
npm run build

# Electron Build (Development)
npm run electron:build

# Electron Build für Produktion
npm run electron:build:win   # Windows
npm run electron:build:mac   # macOS
npm run electron:build:linux # Linux
```

**Akzeptanzkriterien:**
- [ ] Web Build erfolgreich in `dist/`
- [ ] Electron Build erfolgreich in `release/`

---

### 8.7.5 Git Commit & Tag
- [x] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 8: Frontend UI - Components, Views, Styling"
git tag v0.8.0
```

---

## Phase 8 Zusammenfassung

### Erstellte Komponenten

**Layout:**
- TitleBar (Electron Titlebar)
- CompanySidebar
- ChannelSidebar
- DMSidebar
- RightSidebar

**Common:**
- Avatar
- Button
- Input
- Modal
- Dropdown / DropdownItem
- LoadingSpinner

**Chat:**
- MessageList
- MessageItem
- MessageInput
- MessageReactions
- TypingIndicator
- EmojiPicker
- FilePreview
- DateSeparator

**Modals:**
- AddCompanyModal
- CreateChannelModal
- NewDMModal

### Views
- LoginView
- RegisterView
- MainLayout
- HomeView
- CompanyView
- ChannelView
- DirectMessagesView
- ConversationView
- SettingsView
- NotFoundView

### Features
- [x] Responsive Design
- [x] Dark Mode (Standard)
- [x] Keyboard Shortcuts (Enter zum Senden)
- [x] Auto-Scroll bei neuen Nachrichten
- [x] Typing Indicator
- [x] Emoji Picker
- [x] File Upload Preview
- [x] Message Reactions
- [x] Online Status Anzeige
- [x] Unread Message Counter

### Nächste Phase
→ Weiter mit `phase-9-deployment.md`
