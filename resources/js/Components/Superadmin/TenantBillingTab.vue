<template>
  <form @submit.prevent="salva" class="p-6 max-w-3xl space-y-8">

    <FormSection title="Stato">
      <div class="flex flex-wrap items-center gap-3">
        <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badge.classe]">{{ badge.testo }}</span>
        <span v-if="billing.subscription_status" class="text-xs text-slate-500">Stripe: {{ billing.subscription_status }}</span>
        <span v-if="billing.interval" class="text-sm text-slate-600">{{ billing.interval === 'year' ? 'Annuale' : 'Mensile' }}</span>
        <span v-if="billing.ends_at" class="text-sm text-slate-600">
          {{ billing.cancel_at_period_end ? 'Termina il' : 'Rinnovo il' }} {{ data(billing.ends_at) }}
        </span>
        <a v-if="billing.stripe_customer_url" :href="billing.stripe_customer_url" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline">Apri in Stripe →</a>
      </div>
      <p v-if="!billing.stripe_ready" class="text-xs text-amber-700 mt-3">Chiavi Stripe non configurate sul server (STRIPE_SECRET / STRIPE_WEBHOOK_SECRET).</p>
    </FormSection>

    <FormSection title="Modalità">
      <div class="space-y-3">
        <label class="flex items-start gap-3">
          <input type="radio" value="stripe" v-model="form.billing_mode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
          <span class="text-sm"><span class="font-medium text-slate-800">Paga con Stripe</span><br>
            <span class="text-slate-500">Senza abbonamento attivo l'agenzia è in sola lettura e gli avvisi automatici sono fermi.</span></span>
        </label>
        <label class="flex items-start gap-3">
          <input type="radio" value="esterno" v-model="form.billing_mode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
          <span class="text-sm"><span class="font-medium text-slate-800">Fatturato a parte</span><br>
            <span class="text-slate-500">Accesso sempre pieno, nessun pagamento in app (es. contratti con setup e canone).</span></span>
        </label>
      </div>
      <FieldError :message="form.errors.billing_mode" />
    </FormSection>

    <template v-if="form.billing_mode === 'stripe'">
      <FormSection title="Prezzo (IVA inclusa)">
        <p class="text-xs text-slate-500 -mt-2 mb-4">
          Lascia vuoto per il prezzo standard. Se l'agenzia paga già, il nuovo prezzo vale dal prossimo rinnovo.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="field-label">Mensile (€)</label>
            <input v-model="form.price_monthly" type="number" step="0.01" min="1" class="field-input" :placeholder="String(billing.standard_monthly)" />
            <FieldError :message="form.errors.price_monthly" />
          </div>
          <div>
            <label class="field-label">Annuale (€)</label>
            <input v-model="form.price_yearly" type="number" step="0.01" min="1" class="field-input" :placeholder="String(billing.standard_yearly)" />
            <FieldError :message="form.errors.price_yearly" />
          </div>
          <div>
            <label class="field-label">Attivazione una tantum (€)</label>
            <input v-model="form.setup_fee" type="number" step="0.01" min="0" class="field-input" placeholder="0" :disabled="!!billing.setup_fee_paid_at" />
            <p v-if="billing.setup_fee_paid_at" class="text-xs text-emerald-700 mt-1">Pagata il {{ data(billing.setup_fee_paid_at) }}</p>
            <FieldError :message="form.errors.setup_fee" />
          </div>
        </div>
      </FormSection>

      <FormSection title="Accesso gratuito">
        <div class="max-w-xs">
          <label class="field-label">Accesso pieno fino al (compreso)</label>
          <input v-model="form.free_until" type="date" class="field-input" />
          <FieldError :message="form.errors.free_until" />
        </div>
        <p class="text-xs text-slate-500 mt-2">Per dare qualche giorno prima del pagamento. Vuoto = nessuno.</p>
      </FormSection>

      <FormSection title="Link di pagamento">
        <p class="text-xs text-slate-500 -mt-2 mb-3">
          Da mandare al cliente dopo la demo: sceglie mensile o annuale e paga, senza dover accedere.
          Salva prima di copiarlo se hai cambiato i prezzi.
        </p>
        <div class="flex gap-2">
          <input :value="billing.payment_link" readonly class="field-input font-mono text-xs" @focus="($event.target as HTMLInputElement).select()" />
          <button type="button" @click="copia" class="shrink-0 bg-white border border-slate-300 text-slate-700 text-sm font-semibold px-4 rounded-lg hover:bg-slate-50">
            {{ copiato ? 'Copiato' : 'Copia' }}
          </button>
        </div>
      </FormSection>
    </template>

    <FormSection v-if="billing.billing_details" title="Dati per la fattura (dal pagamento)">
      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
        <template v-for="(label, key) in DETTAGLI" :key="key">
          <div v-if="billing.billing_details[key]">
            <dt class="text-xs text-slate-500">{{ label }}</dt>
            <dd class="text-slate-800">{{ billing.billing_details[key] }}</dd>
          </div>
        </template>
      </dl>
    </FormSection>

    <div class="flex items-center gap-4">
      <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
        {{ form.processing ? 'Salvataggio...' : 'Salva abbonamento' }}
      </button>
      <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600 font-medium">Salvato.</span>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import FormSection from '@/Components/Superadmin/FormSection.vue'
import FieldError from '@/Components/Superadmin/FieldError.vue'
import type { BillingSummary } from '@/types'

export interface TenantBilling extends BillingSummary {
  billing_mode: 'stripe' | 'esterno'
  price_monthly: number | null
  price_yearly: number | null
  setup_fee: number | null
  setup_fee_paid_at: string | null
  standard_monthly: number
  standard_yearly: number
  subscription_status: string | null
  billing_details: Record<string, string | null> | null
  stripe_customer_url: string | null
  payment_link: string
  stripe_ready: boolean
}

const props = defineProps<{ tenantId: number; billing: TenantBilling }>()

const DETTAGLI: Record<string, string> = {
  ragione_sociale: 'Ragione sociale', partita_iva: 'P.IVA', codice_fiscale: 'Codice fiscale',
  sdi_o_pec: 'SDI o PEC', email: 'Email', indirizzo: 'Indirizzo', cap: 'CAP', citta: 'Città',
  provincia: 'Provincia', paese: 'Paese',
}

const BADGE = {
  esterno:    { testo: 'Fatturato a parte', classe: 'bg-slate-100 text-slate-600' },
  attivo:     { testo: 'Attivo', classe: 'bg-emerald-100 text-emerald-800' },
  disdetto:   { testo: 'Disdetto (attivo fino a scadenza)', classe: 'bg-gray-100 text-gray-700' },
  in_ritardo: { testo: 'Pagamento fallito', classe: 'bg-amber-100 text-amber-800' },
  gratis:     { testo: 'Accesso gratuito', classe: 'bg-indigo-100 text-indigo-800' },
  non_attivo: { testo: 'Non pagato — sola lettura', classe: 'bg-red-100 text-red-800' },
}
const badge = computed(() => BADGE[props.billing.state])

const form = useForm({
  billing_mode:  props.billing.billing_mode,
  price_monthly: props.billing.price_monthly ?? '',
  price_yearly:  props.billing.price_yearly ?? '',
  setup_fee:     props.billing.setup_fee ?? '',
  free_until:    props.billing.free_until ?? '',
})

function salva() {
  form.post(route('superadmin.tenants.billing.update', props.tenantId), { preserveScroll: true })
}

const copiato = ref(false)
async function copia() {
  await navigator.clipboard.writeText(props.billing.payment_link)
  copiato.value = true
  setTimeout(() => (copiato.value = false), 2000)
}

function data(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<style scoped>
.field-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.field-input {
  @apply block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none
         bg-white placeholder-slate-400 disabled:bg-slate-50;
}
</style>
