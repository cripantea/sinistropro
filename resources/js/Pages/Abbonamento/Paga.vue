<template>
  <Head :title="`Attiva SinistroPro — ${tenantName}`" />
  <div class="min-h-screen bg-slate-50 flex items-start sm:items-center justify-center px-4 py-10">
    <div class="w-full max-w-xl bg-white rounded-2xl shadow-sm border border-gray-200 px-6 py-7 sm:px-8">
      <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">SinistroPro</p>
      <h1 class="text-xl font-bold text-gray-900 mt-1">Attiva l'abbonamento di {{ tenantName }}</h1>

      <div v-if="flash?.error" class="bg-red-50 border-l-4 border-red-500 px-4 py-3 text-sm text-red-800 rounded mt-5">{{ flash.error }}</div>

      <div v-if="billing.state === 'esterno' || billing.has_subscription" class="mt-5 text-sm text-gray-700">
        L'abbonamento è già attivo. Puoi accedere a SinistroPro con le tue credenziali.
        <div class="mt-4"><Link :href="route('login')" class="text-indigo-600 font-semibold hover:underline">Vai all'accesso →</Link></div>
      </div>

      <div v-else class="mt-6">
        <SceltaPiano :billing="billing" :processing="processing" :disabled="!stripeReady" @paga="paga" />
        <p v-if="!stripeReady" class="text-sm text-amber-700 mt-3">Il pagamento online non è ancora disponibile: riprova tra poco.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import SceltaPiano from '@/Components/Abbonamento/SceltaPiano.vue'
import type { BillingSummary, PageProps } from '@/types'

const props = defineProps<{ tenantName: string; billing: BillingSummary; stripeReady: boolean; checkoutUrl: string }>()

const flash = computed(() => usePage<PageProps>().props.flash)
const processing = ref(false)

function paga(interval: 'month' | 'year') {
  router.post(props.checkoutUrl, { interval }, {
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
  })
}
</script>
