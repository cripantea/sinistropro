<template>
  <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', BADGE[state].classe]">{{ BADGE[state].testo }}</span>
</template>

<script setup lang="ts">
import type { BillingState } from '@/types'

defineProps<{ state: BillingState }>()

const BADGE: Record<BillingState, { testo: string; classe: string }> = {
  esterno:    { testo: 'Fatturato a parte', classe: 'bg-slate-100 text-slate-600' },
  attivo:     { testo: 'Attivo', classe: 'bg-emerald-100 text-emerald-800' },
  disdetto:   { testo: 'Disdetto', classe: 'bg-gray-100 text-gray-700' },
  in_ritardo: { testo: 'Pagamento fallito', classe: 'bg-amber-100 text-amber-800' },
  gratis:     { testo: 'Gratis', classe: 'bg-indigo-100 text-indigo-800' },
  non_attivo: { testo: 'Non pagato', classe: 'bg-red-100 text-red-800' },
}
</script>
