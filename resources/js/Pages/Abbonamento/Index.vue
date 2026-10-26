<template>
  <AuthenticatedLayout>
    <Head title="Abbonamento" />
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Abbonamento</h2>
    </template>

    <div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 space-y-6">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 rounded">{{ flash.success }}</div>
      <div v-if="flash?.error" class="bg-red-50 border-l-4 border-red-500 px-4 py-3 text-sm text-red-800 rounded">{{ flash.error }}</div>

      <!-- Stato attuale -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5">
        <div class="flex items-center gap-3">
          <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', badge.classe]">{{ badge.testo }}</span>
          <span v-if="billing.has_subscription && billing.interval" class="text-sm text-gray-600">
            Piano {{ billing.interval === 'year' ? 'annuale' : 'mensile' }} ·
            {{ euro(billing.interval === 'year' ? billing.price_yearly_cents : billing.price_monthly_cents) }}
            / {{ billing.interval === 'year' ? 'anno' : 'mese' }}
          </span>
        </div>
        <p class="text-sm text-gray-600 mt-3">{{ descrizione }}</p>

        <div v-if="canManage && billing.has_subscription" class="flex flex-wrap gap-3 mt-5">
          <button type="button" @click="post('abbonamento.gestisci')" :disabled="processing"
                  class="inline-flex items-center bg-white border border-gray-300 text-gray-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 disabled:opacity-50">
            Gestisci pagamento e fatture
          </button>
          <button v-if="billing.interval === 'month' && !billing.cancel_at_period_end" type="button" @click="passaAnnuale" :disabled="processing"
                  class="inline-flex items-center bg-indigo-50 text-indigo-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-100 disabled:opacity-50">
            Passa all'annuale ({{ euro(billing.price_yearly_cents) }} / anno)
          </button>
        </div>
      </div>

      <!-- Attivazione -->
      <div v-if="!billing.has_subscription" class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Attiva SinistroPro</h3>
        <SceltaPiano v-if="canManage" :billing="billing" :processing="processing" :disabled="!stripeReady" @paga="paga" />
        <p v-else class="text-sm text-gray-600">Solo l'amministratore della tua agenzia può attivare l'abbonamento.</p>
        <p v-if="canManage && !stripeReady" class="text-sm text-amber-700 mt-3">Il pagamento online non è ancora disponibile: riprova tra poco.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SceltaPiano from '@/Components/Abbonamento/SceltaPiano.vue'
import type { BillingSummary, PageProps } from '@/types'

const props = defineProps<{ billing: BillingSummary; canManage: boolean; stripeReady: boolean }>()

const flash = computed(() => usePage<PageProps>().props.flash)
const processing = ref(false)

const badge = computed(() => ({
  attivo:     { testo: 'Attivo', classe: 'bg-emerald-100 text-emerald-800' },
  disdetto:   { testo: 'Disdetto', classe: 'bg-gray-100 text-gray-700' },
  in_ritardo: { testo: 'Pagamento non riuscito', classe: 'bg-amber-100 text-amber-800' },
  gratis:     { testo: 'Accesso gratuito', classe: 'bg-indigo-100 text-indigo-800' },
  non_attivo: { testo: 'Non attivo', classe: 'bg-red-100 text-red-800' },
  esterno:    { testo: 'Attivo', classe: 'bg-emerald-100 text-emerald-800' },
}[props.billing.state]))

const descrizione = computed(() => {
  const b = props.billing
  switch (b.state) {
    case 'attivo':     return `Prossimo rinnovo il ${data(b.ends_at)}.`
    case 'disdetto':   return `Hai disdetto: SinistroPro resta attivo fino al ${data(b.ends_at)}, poi passa in sola lettura. Puoi riattivarlo da "Gestisci pagamento".`
    case 'in_ritardo': return 'L\'ultimo addebito non è riuscito. Stripe riproverà nei prossimi giorni: aggiorna la carta da "Gestisci pagamento" per non interrompere il servizio.'
    case 'gratis':     return `Hai accesso completo fino al ${data(b.free_until)}. Attiva l'abbonamento per continuare senza interruzioni.`
    default:           return 'SinistroPro è in sola lettura: vedi tutti i sinistri ma non puoi crearne o modificarli, e gli avvisi automatici sono fermi. Attiva l\'abbonamento per ripartire subito.'
  }
})

function paga(interval: 'month' | 'year') {
  router.post(route('abbonamento.checkout'), { interval }, {
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
  })
}

function passaAnnuale() {
  if (!confirm(`Passare all'abbonamento annuale da ${euro(props.billing.price_yearly_cents)}? L'addebito parte oggi, scalando i giorni del mese già pagati.`)) return
  post('abbonamento.annuale')
}

function post(name: string) {
  router.post(route(name), {}, {
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
  })
}

function euro(cents: number): string {
  const v = cents / 100
  return v.toLocaleString('it-IT', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2, useGrouping: 'always' } as Intl.NumberFormatOptions) + ' €'
}

function data(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' }) : '—'
}
</script>
