<template>
  <svg
    class="inline w-3 h-3 ml-0.5 -mt-0.5 transition-transform"
    :class="[active ? 'text-indigo-600' : 'text-gray-300', active && dir === 'desc' ? 'rotate-180' : '']"
    fill="none"
    stroke="currentColor"
    viewBox="0 0 24 24"
  >
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
  </svg>
</template>

<script setup lang="ts">
defineProps<{
  active: boolean
  dir: 'asc' | 'desc'
}>()
</script>
