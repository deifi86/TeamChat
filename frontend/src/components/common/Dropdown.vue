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
