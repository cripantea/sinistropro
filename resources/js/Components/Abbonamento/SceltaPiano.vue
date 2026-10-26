<template>
  <!-- Mensile o annuale, con il risparmio dell'annuale rispetto a 12 mesi. -->
  <div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <button
        v-for="p in piani"
        :key="p.interval"
        type="button"
        @click="scelto = p.interval"
        :class="[
          'text-left rounded-xl border-2 px-5 py-4 transition',
          scelto === p.interval ? 'border-indigo-600 bg-indigo-50/50' : 'border-gray-200 bg-white hover:border-gray-300'
        ]"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-gray-700">{{ p.label }}</span>
          <span v-if="p.risparmio" class="text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full px-2 py-0.5">
            risparmi {{ euro(p.risparmio) }}
          </span>
        </div>
        <p class="mt-2">
          <span class="text-2xl font-bold text-gray-900">{{ euro(p.cents) }}</span>
          <span class="text-sm text-gray-500"> / {{ p.unita }}</span>
        </p>
        <p class="text-xs text-gray-500 mt-1">IVA inclusa{{ p.nota ? ` · ${p.nota}` : '' }}</p>
      </button>
    </div>

    <p v-if="billing.setup_fee_cents" class="text-sm text-gray-600">
      Con il primo pagamento si aggiunge il costo di attivazione di <strong>{{ euro(billing.setup_fee_cents) }}</strong> (una volta sola).
    </p>

    <button
      type="button"
      :disabled="disabled || processing"
      @click="emit('paga', scelto)"
      class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-3 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {{ processing ? 'Apertura pagamento…' : `Paga ${euro(totaleOggi)} e attiva` }}
    </button>
    <p class="text-xs text-gray-500">
      Pagamento sicuro con Stripe (carta, Apple Pay, Google Pay). Soddisfatto o rimborsato entro 30 giorni.
      Disdici quando vuoi, con un clic.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { BillingSummary } from '@/types'

const props = defineProps<{ billing: BillingSummary; processing?: boolean; disabled?: boolean }>()
const emit = defineEmits<{ paga: [interval: 'month' | 'year'] }>()

const scelto = ref<'month' | 'year'>('month')

const piani = computed(() => {
  const m = props.billing.price_monthly_cents
  const y = props.billing.price_yearly_cents
  const risparmio = m * 12 - y
  return [
    { interval: 'month' as const, label: 'Mensile', cents: m, unita: 'mese', nota: '', risparmio: 0 },
    { interval: 'year' as const, label: 'Annuale', cents: y, unita: 'anno', nota: `${euro(Math.round(y / 12))} al mese`, risparmio: risparmio > 0 ? risparmio : 0 },
  ]
})

const totaleOggi = computed(() =>
  (scelto.value === 'year' ? props.billing.price_yearly_cents : props.billing.price_monthly_cents) + props.billing.setup_fee_cents
)

function euro(cents: number): string {
  const v = cents / 100
  return v.toLocaleString('it-IT', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) + ' €'
}
</script>
