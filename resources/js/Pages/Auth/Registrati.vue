<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { loadStripe, type StripeEmbeddedCheckout } from '@stripe/stripe-js'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'

// Tre passi nella stessa pagina: 1) account, 2) carta per la prova (modulo Stripe incorporato),
// 3) prenotazione della call col tecnico (calendario del CRM incorporato).
const props = defineProps<{
  passo?: 'account' | 'carta' | 'onboarding'
  bookingUrl?: string
  provaFinoAl?: string
  agenzia?: string
  giorniProva?: number
  prezzoMensile?: number
  stripeKey?: string | null
  attivita?: { value: string; label: string }[]
  sinistri?: string[]
}>()

const passoCarta = computed(() => props.passo === 'carta')
const passoTecnico = computed(() => props.passo === 'onboarding')
const numeroPasso = computed(() => (passoTecnico.value ? 3 : passoCarta.value ? 2 : 1))

// ── Passo 1: account ──────────────────────────────────────────────────
const form = useForm({
  nome: '',
  agenzia: '',
  email: '',
  telefono: '',
  attivita: '',
  sinistri: '',
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
// ── Passo 3: call col tecnico ─────────────────────────────────────────
const altezzaCalendario = ref(640)

function messaggioCalendario(e: MessageEvent) {
  if (!props.bookingUrl || e.origin !== new URL(props.bookingUrl).origin) return
  if (e.data?.type === 'fusion-booking:height') altezzaCalendario.value = Math.max(400, Number(e.data.height) + 8)
  if (e.data?.type === 'fusion-booking:booked' || e.data?.type === 'fusion-booking:redirect') {
    setTimeout(() => router.visit(route('registrati.inizia')), 2500)
  }
}

watch(passoTecnico, (tecnico) => {
  window.removeEventListener('message', messaggioCalendario)
  if (tecnico) window.addEventListener('message', messaggioCalendario)
}, { immediate: true })

onBeforeUnmount(() => {
  checkout?.destroy()
  window.removeEventListener('message', messaggioCalendario)
})
</script>

<template>
  <Head :title="passoTecnico ? 'Prenota il tecnico — SinistroPro' : passoCarta ? 'Attiva la prova — SinistroPro' : 'Prova gratis 30 giorni — SinistroPro'" />

  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-12">
    <div class="mb-6 text-center">
      <p class="text-gray-900 font-bold text-2xl">SinistroPro</p>
      <p class="text-xs font-semibold tracking-widest uppercase mt-0.5" style="color: #1e3a8a;">Piattaforma assicurativa</p>
    </div>

    <!-- Passi -->
    <ol class="flex items-center gap-2 sm:gap-3 text-xs font-semibold mb-5">
      <template v-for="(etichetta, i) in ['Account', 'Prova', 'Tecnico']" :key="etichetta">
        <li v-if="i > 0" class="w-5 sm:w-8 h-px bg-gray-300"></li>
        <li :class="i + 1 < numeroPasso ? 'text-emerald-700' : i + 1 === numeroPasso ? 'text-[#1e3a8a]' : 'text-gray-400'">
          <span :class="['inline-flex w-5 h-5 items-center justify-center rounded-full mr-1.5', i + 1 < numeroPasso ? 'bg-emerald-100' : i + 1 === numeroPasso ? 'bg-[#1e3a8a] text-white' : 'bg-gray-200']">{{ i + 1 < numeroPasso ? '✓' : i + 1 }}</span>{{ etichetta }}
        </li>
      </template>
    </ol>

    <!-- Passo 1: account -->
    <div v-if="!passoCarta && !passoTecnico" class="w-full max-w-[400px] bg-white rounded-xl border border-gray-200 shadow-sm p-8">
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
          <label for="telefono" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Cellulare</label>
          <input id="telefono" v-model="form.telefono" type="tel" autocomplete="tel" required placeholder="333 123 4567" :class="campo(form.errors.telefono)" />
          <InputError :message="form.errors.telefono" class="mt-1.5" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="attivita" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Sei un</label>
            <select id="attivita" v-model="form.attivita" required :class="[...campo(form.errors.attivita), 'bg-white']">
              <option value="" disabled>Scegli…</option>
              <option v-for="a in attivita" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
            <InputError :message="form.errors.attivita" class="mt-1.5" />
          </div>
          <div>
            <label for="sinistri" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Sinistri al mese</label>
            <select id="sinistri" v-model="form.sinistri" required :class="[...campo(form.errors.sinistri), 'bg-white']">
              <option value="" disabled>Scegli…</option>
              <option v-for="n in sinistri" :key="n" :value="n">{{ n }}</option>
            </select>
            <InputError :message="form.errors.sinistri" class="mt-1.5" />
          </div>
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
    <div v-else-if="passoCarta" class="w-full max-w-[520px] bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
      <h1 class="text-xl font-bold text-gray-900">Ultimo passo: attiva la prova</h1>
      <p class="text-gray-500 text-sm mt-1">{{ agenzia }}</p>

      <div class="mt-5 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-700 space-y-1">
        <div class="flex justify-between"><span>Oggi</span><strong class="text-slate-900">0 €</strong></div>
        <div class="flex justify-between"><span>Dal {{ fineProva }}</span><span>{{ prezzo }} / mese, IVA inclusa</span></div>
      </div>

      <!-- Rassicurazioni: chi si ferma qui di solito ha paura di restare legato o da solo -->
      <ul class="mt-4 space-y-3">
        <li class="flex gap-3">
          <span class="rassicura">✓</span>
          <div><p class="font-semibold text-gray-900">Disdici con un clic, quando vuoi</p><p class="text-sm text-gray-500">In SinistroPro vai su <b>Abbonamento → Gestisci pagamento → Annulla</b>, oppure scrivici su WhatsApp e lo facciamo noi. Se disdici prima del {{ fineProva }} non paghi niente; ti avvisiamo via email 7 giorni prima.</p></div>
        </li>
        <li class="flex gap-3">
          <span class="rassicura">✓</span>
          <div><p class="font-semibold text-gray-900">Un tecnico imposta tutto con te</p><p class="text-sm text-gray-500">Subito dopo scegli quando fare 45 minuti in videochiamata: carichiamo i tuoi clienti, colleghiamo il tuo WhatsApp e la tua email e impostiamo le automazioni.</p></div>
        </li>
        <li class="flex gap-3">
          <span class="rassicura">✓</span>
          <div><p class="font-semibold text-gray-900">Supporto diretto su WhatsApp</p><p class="text-sm text-gray-500">Rispondono le persone che hanno costruito SinistroPro.</p></div>
        </li>
      </ul>

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

      <div class="mt-5 border-t border-gray-100 pt-4 text-center">
        <p class="text-sm text-gray-600">Preferisci prima vederlo con qualcuno?</p>
        <a href="https://sinistropro.it/demo/" class="text-sm font-semibold underline underline-offset-2" style="color: #1e3a8a;">Prenota una demo gratuita di 20 minuti</a>
      </div>
    </div>

    <!-- Passo 3: call col tecnico -->
    <div v-if="passoTecnico" class="w-full max-w-[620px] bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
      <p class="text-sm font-semibold text-emerald-700">Prova attiva{{ provaFinoAl ? ` fino al ${provaFinoAl}` : '' }} ✓</p>
      <h1 class="text-xl font-bold text-gray-900 mt-1">Prenota 45 minuti con il tecnico</h1>
      <p class="text-gray-500 text-sm mt-1">
        In videochiamata carichiamo insieme i tuoi clienti e le tue pratiche, colleghiamo il tuo WhatsApp e la tua email
        e impostiamo le automazioni: promemoria delle scadenze e messaggi ai clienti. Alla fine della call SinistroPro è già pronto sul tuo lavoro.
      </p>

      <iframe
        v-if="bookingUrl"
        :src="bookingUrl"
        title="Prenota la call con il tecnico"
        class="w-full mt-5 border-0"
        :style="{ height: altezzaCalendario + 'px' }"
      ></iframe>

      <p class="text-sm text-center mt-4">
        <Link :href="route('registrati.inizia')" class="text-gray-500 hover:text-gray-700 underline underline-offset-2">Salta per ora: inizio da solo dal primo sinistro</Link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.crea-btn { background-color: #1e3a8a; }
.crea-btn:hover:not(:disabled) { background-color: #172554; }
.rassicura { flex: none; display: inline-flex; width: 1.5rem; height: 1.5rem; align-items: center; justify-content: center; border-radius: 9999px; background: #dcfce7; color: #15803d; font-weight: 700; font-size: .8rem; margin-top: .1rem; }
</style>
