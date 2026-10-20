<template>
  <!-- Gli avvisi automatici delle 07:00 non girano da più di un giorno: va sistemato subito. -->
  <div v-if="dal" class="shrink-0 bg-red-600 text-white px-4 py-2.5 text-sm font-semibold flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
    </svg>
    Attenzione: gli avvisi email automatici sono fermi (ultimo giro {{ dal }}). Contatta l'assistenza.
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const dal = computed(() => (usePage().props as { avvisiFermiDal?: string | null }).avvisiFermiDal ?? null)
</script>
