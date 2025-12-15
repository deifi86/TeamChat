import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/app',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/app',
      name: 'app',
      component: () => import('@/views/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/views/HomeView.vue'),
        },
        {
          path: 'company/:companyId',
          name: 'company',
          component: () => import('@/views/CompanyView.vue'),
          children: [
            {
              path: 'channel/:channelId',
              name: 'channel',
              component: () => import('@/views/ChannelView.vue'),
            },
          ],
        },
        {
          path: 'dm',
          name: 'direct-messages',
          component: () => import('@/views/DirectMessagesView.vue'),
          children: [
            {
              path: ':conversationId',
              name: 'conversation',
              component: () => import('@/views/ConversationView.vue'),
            },
          ],
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/SettingsView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

// Navigation Guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Token vorhanden aber User nicht geladen
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  // Route erfordert Auth
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Route ist nur für Gäste
  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: 'app' })
  }

  next()
})

export default router
