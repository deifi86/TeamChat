<template>
  <div class="app">
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

onMounted(async () => {
  // Bei App-Start: User laden wenn Token vorhanden
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }
})
</script>

<style>
.app {
  min-height: 100vh;
  background-color: var(--color-bg-primary);
}
</style>
