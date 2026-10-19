<template>
  <!-- Pulsante "indietro" unico per tutte le pagine: grande, con bordo e testo, impossibile da non vedere. -->
  <Link
    :href="href"
    class="inline-flex items-center gap-2 shrink-0 h-10 pl-3 pr-4 rounded-lg border border-gray-300 bg-white text-[15px] font-semibold text-gray-800 shadow-sm hover:bg-gray-50 hover:border-gray-400 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition"
  >
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
    </svg>
    <slot />
  </Link>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ href: string }>()
</script>
