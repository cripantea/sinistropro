<template>
  <!-- Abbonamento non attivo: SinistroPro è in sola lettura e gli avvisi automatici sono fermi. -->
  <div v-if="abbonamento && !abbonamento.has_access" class="shrink-0 bg-amber-500 text-slate-900 px-4 py-2.5 text-sm font-semibold flex flex-wrap items-center gap-x-3 gap-y-1">
    <span>Abbonamento non attivo: puoi consultare tutto ma non modificare, e gli avvisi automatici sono fermi.</span>
    <Link :href="route('abbonamento')" class="underline underline-offset-2">Attiva l'abbonamento →</Link>
  </div>
  <div v-else-if="abbonamento?.state === 'in_ritardo'" class="shrink-0 bg-amber-100 text-amber-900 px-4 py-2 text-sm font-medium flex flex-wrap items-center gap-x-3">
    <span>L'ultimo pagamento non è andato a buon fine: Stripe riproverà nei prossimi giorni.</span>
    <Link :href="route('abbonamento')" class="underline underline-offset-2">Aggiorna la carta →</Link>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'

const abbonamento = computed(() => usePage<PageProps>().props.abbonamento ?? null)
</script>
