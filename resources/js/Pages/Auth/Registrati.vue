<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { loadStripe, type StripeEmbeddedCheckout } from '@stripe/stripe-js'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'

// Due passi nella stessa pagina: 1) account, 2) carta per la prova (modulo Stripe incorporato).
const props = defineProps<{
  passo?: 'account' | 'carta'
  agenzia?: string
  giorniProva?: number
  prezzoMensile?: number
  stripeKey?: string | null
}>()

const passoCarta = computed(() => props.passo === 'carta')

// ── Passo 1: account ──────────────────────────────────────────────────
const form = useForm({
  nome: '',
  agenzia: '',
  email: '',
  password: '',
  sito: '', // campo nascosto anti-bot
})

const mostraPassword = ref(false)

const submit = () => {
  form.post(route('registrati'), {
    onFinish: () => form.reset('password'),
  })
}

const campo = (errore?: string) => [
  'w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 transition',
  errore ? 'border-red-400 focus:ring-red-300' : 'border-gray-300 focus:ring-blue-300 focus:border-blue-500',
]

// ── Passo 2: carta ────────────────────────────────────────────────────
const contenitore = ref<HTMLElement | null>(null)
const caricamento = ref(true)
const errore = ref('')
let checkout: StripeEmbeddedCheckout | null = null

const fineProva = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + (props.giorniProva ?? 30))
  return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'long' })
})

const prezzo = computed(() => {
  const v = (props.prezzoMensile ?? 9900) / 100
  return v.toLocaleString('it-IT', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) + ' €'
})

async function montaPagamento() {
  if (!props.stripeKey || !contenitore.value) {
    caricamento.value = false
    return
  }
  try {
    const stripe = await loadStripe(props.stripeKey, { locale: 'it' })
    if (!stripe) throw new Error('Stripe non disponibile')
    checkout = await stripe.createEmbeddedCheckoutPage({
      fetchClientSecret: async () => (await axios.post(route('abbonamento.sessione'), { interval: 'month' })).data.clientSecret,
    })
    checkout.mount(contenitore.value)
  } catch (e) {
    errore.value = 'Non riusciamo a caricare il modulo di pagamento. Ricarica la pagina o usa il pulsante qui sotto.'
  } finally {
    caricamento.value = false
  }
}

// Senza chiave pubblica (o se il modulo non si carica): pagina Stripe classica.
const vaiAStripe = () => router.post(route('abbonamento.checkout'), { interval: 'month' })

// Dal passo 1 Inertia riusa questa stessa pagina: si parte quando compare il passo carta, non solo al primo caricamento.
watch(passoCarta, (carta) => { if (carta && !checkout) montaPagamento() }, { immediate: true, flush: 'post' })
onBeforeUnmount(() => checkout?.destroy())
</script>

<template>
  <Head :title="passoCarta ? 'Attiva la prova — SinistroPro' : 'Prova gratis 30 giorni — SinistroPro'" />

  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
    <div class="mb-6 text-center">
      <p class="text-gray-900 font-bold text-2xl">SinistroPro</p>
      <p class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color: #1e3a8a;">Piattaforma assicurativa</p>
    </div>

    <!-- Passi -->
    <ol class="flex items-center gap-3 text-xs font-semibold mb-5">
      <li :class="passoCarta ? 'text-emerald-700' : 'text-[#1e3a8a]'">
        <span :class="['inline-flex w-5 h-5 items-center justify-center rounded-full mr-1.5', passoCarta ? 'bg-emerald-100' : 'bg-[#1e3a8a] text-white']">{{ passoCarta ? '✓' : '1' }}</span>Account
      </li>
      <li class="w-8 h-px bg-gray-300"></li>
      <li :class="passoCarta ? 'text-[#1e3a8a]' : 'text-gray-400'">
        <span :class="['inline-flex w-5 h-5 items-center justify-center rounded-full mr-1.5', passoCarta ? 'bg-[#1e3a8a] text-white' : 'bg-gray-200']">2</span>Attiva la prova
      </li>
    </ol>

    <!-- Passo 1: account -->
    <div v-if="!passoCarta" class="w-full max-w-[400px] bg-white rounded-xl border border-gray-200 shadow-sm p-8">
      <h1 class="text-xl font-bold text-gray-900">Prova SinistroPro gratis per 30 giorni</h1>
      <p class="text-gray-500 text-sm mt-1">Poi 99 € al mese. Disdici quando vuoi, con un clic.</p>

      <form @submit.prevent="submit" class="space-y-4 mt-6">
        <div>
          <label for="nome" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nome e cognome</label>
          <input id="nome" v-model="form.nome" type="text" autocomplete="name" required autofocus placeholder="Mario Rossi" :class="campo(form.errors.nome)" />
          <InputError :message="form.errors.nome" class="mt-1.5" />
        </div>

        <div>
          <label for="agenzia" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Nome dell'agenzia o dello studio</label>
          <input id="agenzia" v-model="form.agenzia" type="text" autocomplete="organization" required placeholder="Agenzia Rossi Assicurazioni" :class="campo(form.errors.agenzia)" />
          <InputError :message="form.errors.agenzia" class="mt-1.5" />
        </div>

        <div>
          <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Email</label>
          <input id="email" v-model="form.email" type="email" autocomplete="username" required placeholder="mario@agenzia.it" :class="campo(form.errors.email)" />
          <InputError :message="form.errors.email" class="mt-1.5" />
        </div>

        <div>
          <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Password</label>
          <div class="relative">
            <input id="password" v-model="form.password" :type="mostraPassword ? 'text' : 'password'" autocomplete="new-password" required minlength="8" placeholder="Almeno 8 caratteri" :class="[...campo(form.errors.password), 'pr-16']" />
            <button type="button" @click="mostraPassword = !mostraPassword" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-gray-500 hover:text-gray-700">
              {{ mostraPassword ? 'Nascondi' : 'Mostra' }}
            </button>
          </div>
          <InputError :message="form.errors.password" class="mt-1.5" />
        </div>

        <input v-model="form.sito" type="text" name="sito" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" />

        <button type="submit" :disabled="form.processing" class="crea-btn w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-white text-sm font-semibold transition-opacity disabled:opacity-60 disabled:cursor-not-allowed">
          <svg v-if="form.processing" class="animate-spin h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          {{ form.processing ? 'Creazione in corso…' : 'Continua' }}
        </button>
      </form>

      <p class="text-sm text-gray-500 text-center mt-6">
        Hai già un account?
        <Link :href="route('login')" class="font-medium" style="color: #1e3a8a;">Accedi</Link>
      </p>
    </div>

    <!-- Passo 2: carta -->
    <div v-else class="w-full max-w-[520px] bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
      <h1 class="text-xl font-bold text-gray-900">Ultimo passo: attiva la prova</h1>
      <p class="text-gray-500 text-sm mt-1">{{ agenzia }}</p>

      <div class="mt-5 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-700 space-y-1">
        <div class="flex justify-between"><span>Oggi</span><strong class="text-slate-900">0 €</strong></div>
        <div class="flex justify-between"><span>Dal {{ fineProva }}</span><span>{{ prezzo }} / mese, IVA inclusa</span></div>
        <p class="text-xs text-slate-500 pt-1">Disdici prima del {{ fineProva }} e non paghi niente. Ti avvisiamo 7 giorni prima.</p>
      </div>

      <div class="mt-5">
        <p v-if="caricamento" class="text-sm text-gray-500 py-10 text-center">Caricamento del pagamento sicuro…</p>
        <div ref="contenitore"></div>

        <template v-if="!caricamento && (!stripeKey || errore)">
          <p v-if="errore" class="text-sm text-red-700 mb-3">{{ errore }}</p>
          <button type="button" @click="vaiAStripe" class="crea-btn w-full py-2.5 px-4 rounded-lg text-white text-sm font-semibold">
            Inserisci la carta e inizia i {{ giorniProva }} giorni gratis
          </button>
        </template>
      </div>

      <p class="text-xs text-gray-400 text-center mt-5">Pagamento protetto da Stripe. I dati della carta non passano dai nostri server.</p>
    </div>
  </div>
</template>

<style scoped>
.crea-btn { background-color: #1e3a8a; }
.crea-btn:hover:not(:disabled) { background-color: #172554; }
</style>
