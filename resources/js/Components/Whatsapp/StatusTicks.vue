<template>
  <svg v-if="status === 'read'" class="w-3.5 h-3.5 text-blue-500" viewBox="0 0 16 11" fill="currentColor">
    <path d="M11.071.653a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.08.013L.97 6.09a.75.75 0 1 1 1.06-1.062l2.051 2.05 5.93-6.4a.75.75 0 0 1 1.06-.025zm3 0a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.085.003l-.725-.73a.75.75 0 1 1 1.062-1.06l.193.195 5.97-6.443a.75.75 0 0 1 1.06-.025z"/>
  </svg>
  <svg v-else-if="status === 'delivered'" class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 16 11" fill="currentColor">
    <path d="M11.071.653a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.08.013L.97 6.09a.75.75 0 1 1 1.06-1.062l2.051 2.05 5.93-6.4a.75.75 0 0 1 1.06-.025zm3 0a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.085.003l-.725-.73a.75.75 0 1 1 1.062-1.06l.193.195 5.97-6.443a.75.75 0 0 1 1.06-.025z"/>
  </svg>
  <svg v-else-if="status === 'failed'" class="w-3.5 h-3.5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
  </svg>
  <svg v-else-if="status === 'pending'" class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
  </svg>
  <svg v-else class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 16 11" fill="currentColor">
    <path d="M11.071.653a.75.75 0 0 1 .025 1.06l-6.5 7a.75.75 0 0 1-1.08.013L.97 6.09a.75.75 0 1 1 1.06-1.062l2.051 2.05 5.93-6.4a.75.75 0 0 1 1.06-.025z"/>
  </svg>
</template>

<script setup lang="ts">
defineProps<{ status: string }>()
</script>
