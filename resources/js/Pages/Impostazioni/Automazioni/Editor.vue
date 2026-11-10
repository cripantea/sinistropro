<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 min-w-0 w-full">
        <Link :href="route('impostazioni.automazioni.index')" class="text-gray-400 hover:text-gray-700 shrink-0" title="Torna alle automazioni" @click="esci">←</Link>
        <input
          v-model="form.name"
          type="text"
          placeholder="Nome dell'automazione"
          class="flex-1 min-w-0 text-lg font-semibold text-gray-800 bg-transparent border-0 focus:ring-0 px-0"
          :class="form.errors.name ? 'placeholder-red-400' : ''"
        />
        <button
          type="button"
          class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full"
          :class="form.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
          :title="form.is_active ? 'Accesa: clicca per spegnerla' : 'Spenta: clicca per accenderla'"
          @click="form.is_active = !form.is_active"
        >{{ form.is_active ? '● Accesa' : '○ Spenta' }}</button>
        <span v-if="modificata" class="hidden sm:inline text-xs text-amber-600 shrink-0">Modifiche non salvate</span>
        <button
          type="button"
          :disabled="form.processing"
          class="shrink-0 inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          @click="salva"
        >{{ form.processing ? 'Salvo…' : salvatoOra ? 'Salvato ✓' : 'Salva' }}</button>
      </div>
    </template>

    <div class="flex flex-col lg:flex-row lg:h-[calc(100vh-3.5rem)]">
      <!-- ── Tela ─────────────────────────────────────────────────────── -->
      <div class="flex-1 overflow-auto bg-gray-50 tela">
        <div v-if="flash?.success" class="mx-auto mt-4 max-w-md bg-green-50 border-l-4 border-green-500 px-4 py-2 text-sm text-green-800 rounded">{{ flash.success }}</div>
        <div v-if="erroriGenerali.length" class="mx-auto mt-4 max-w-md bg-red-50 border-l-4 border-red-500 px-4 py-2 text-sm text-red-800 rounded">
          <p v-for="e in erroriGenerali" :key="e">{{ e }}</p>
        </div>

        <div class="flex flex-col items-center px-6 py-10">
          <!-- Innesco -->
          <button
            type="button"
            class="w-80 max-w-full text-left rounded-xl border-2 bg-white shadow-sm overflow-hidden transition"
            :class="pannello === 'innesco' ? 'border-violet-400 ring-4 ring-violet-100' : erroriInnesco ? 'border-red-300' : 'border-violet-200 hover:border-violet-300'"
            @click="pannello = 'innesco'"
          >
            <div class="flex items-center gap-3 px-4 py-3 bg-violet-50">
              <span class="w-9 h-9 rounded-lg bg-violet-100 flex items-center justify-center text-lg">⚡</span>
              <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Quando</p>
                <p class="text-sm font-semibold text-gray-800">{{ etichettaInnesco }}</p>
              </div>
            </div>
            <p class="px-4 py-2 text-xs" :class="dettaglioInnesco.mancante ? 'text-gray-400 italic' : 'text-gray-600'">{{ dettaglioInnesco.testo }}</p>
            <p v-if="erroriInnesco" class="px-4 pb-2 text-xs text-red-600">{{ erroriInnesco }}</p>
          </button>

          <div class="w-px h-8 bg-gray-300" />

          <!-- Messaggio -->
          <button
            type="button"
            class="w-80 max-w-full text-left rounded-xl border bg-white shadow-sm transition"
            :class="pannello === 'messaggio' ? 'border-indigo-400 ring-4 ring-indigo-100' : erroriMessaggio ? 'border-red-300' : 'border-gray-200 hover:border-gray-300'"
            @click="pannello = 'messaggio'"
          >
            <div class="flex items-start gap-3 px-4 py-3">
              <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center text-lg border" :class="coloreCanale">{{ iconaCanale }}</span>
              <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">{{ etichettaCanale }}</p>
                <p class="text-sm font-semibold text-gray-800 truncate">A {{ riassuntoDestinatari }}</p>
                <p class="text-xs text-gray-500 line-clamp-2 mt-0.5 whitespace-pre-line">{{ form.message_template || 'Scrivi il messaggio…' }}</p>
                <p v-if="form.document_category_ids.length && form.channel !== 'whatsapp'" class="text-xs text-gray-600 mt-1">📎 {{ nomiCategorie }}</p>
              </div>
            </div>
            <p v-if="erroriMessaggio" class="px-4 pb-2 text-xs text-red-600">{{ erroriMessaggio }}</p>
          </button>

          <div class="w-px h-8 bg-gray-300" />
          <span class="rounded-full bg-gray-200 text-gray-500 text-[11px] font-bold px-3 py-1">FINE</span>

          <button type="button" class="mt-8 text-sm font-semibold text-indigo-600 hover:text-indigo-800" @click="apriAnteprima">👁 Anteprima con un sinistro vero</button>
        </div>
      </div>

      <!-- ── Pannello laterale ─────────────────────────────────────────── -->
      <aside class="lg:w-[400px] shrink-0 border-t lg:border-t-0 lg:border-l border-gray-200 bg-white overflow-y-auto">
        <div class="flex border-b border-gray-200 text-sm">
          <button v-for="t in schede" :key="t.id" type="button" class="flex-1 py-3 font-medium border-b-2 -mb-px transition"
            :class="pannello === t.id ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
            @click="t.id === 'anteprima' ? apriAnteprima() : (pannello = t.id)">{{ t.label }}</button>
        </div>

        <!-- Innesco -->
        <div v-if="pannello === 'innesco'" class="p-5 space-y-5">
          <div>
            <p class="field-label">Quando parte</p>
            <div class="space-y-1.5">
              <label v-for="i in opzioni.inneschi" :key="i.valore" class="flex items-center gap-3 rounded-lg border px-3 py-2 cursor-pointer text-sm transition"
                :class="form.trigger_type === i.valore ? 'border-violet-400 bg-violet-50 text-violet-900' : 'border-gray-200 hover:bg-gray-50'">
                <input v-model="form.trigger_type" type="radio" :value="i.valore" class="text-violet-600" />
                <span>{{ iconeInnesco[i.valore] }} {{ i.etichetta }}</span>
              </label>
            </div>
          </div>

          <div v-if="form.trigger_type === 'status'">
            <label class="field-label">Quando il sinistro passa allo stato</label>
            <select v-model="form.tenant_status_id" class="field-input">
              <option :value="null">Qualsiasi stato (a ogni cambio)</option>
              <option v-for="s in opzioni.stati" :key="s.id" :value="s.id">{{ s.name }}{{ s.is_closed ? ' (chiuso)' : '' }}</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Prima dell'invio, chi cambia lo stato vede il messaggio e può toglierlo o cambiare i destinatari.</p>
          </div>

          <div v-if="form.trigger_type === 'pratica_created'" class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">
            Parte appena un sinistro viene aperto, senza chiedere conferma.
          </div>

          <div v-if="['date_field', 'date_reminder'].includes(form.trigger_type)">
            <label class="field-label">Data del sinistro</label>
            <select v-model="form.watched_field" class="field-input">
              <option :value="null" disabled>Scegli la data…</option>
              <option v-for="d in opzioni.datePratica" :key="d.name" :value="d.name">{{ d.label }}</option>
            </select>
            <p v-if="opzioni.datePratica.length === 1" class="text-xs text-gray-500 mt-1">Altre date si aggiungono in <Link :href="route('impostazioni.campi')" class="underline">Campi</Link> (tipo Data).</p>
            <p v-if="form.trigger_type === 'date_field'" class="text-xs text-gray-500 mt-1">Parte quando la data viene inserita o cambiata (anche dalla Board), dopo la conferma di chi la modifica.</p>
          </div>

          <div v-if="form.trigger_type === 'date_reminder'">
            <label class="field-label">Quando</label>
            <div class="flex items-center gap-2">
              <input v-if="quando !== 'stesso'" v-model.number="giorniOffset" type="number" min="1" max="365" class="field-input w-20 text-center" />
              <select v-model="quando" class="field-input">
                <option value="prima">{{ giorniOffset === 1 ? 'giorno' : 'giorni' }} prima</option>
                <option value="dopo">{{ giorniOffset === 1 ? 'giorno' : 'giorni' }} dopo</option>
                <option value="stesso">il giorno stesso</option>
              </select>
            </div>
            <p class="text-xs text-gray-500 mt-1">Controllo ogni mattina alle 8, solo per i sinistri aperti. Ogni sinistro lo riceve una volta sola per data.</p>
          </div>

          <div v-if="form.trigger_type === 'cliente_date_field'" class="space-y-3">
            <div>
              <label class="field-label">Data del cliente</label>
              <select v-model="form.watched_field" class="field-input">
                <option :value="null" disabled>Scegli la data…</option>
                <option v-for="d in opzioni.dateCliente" :key="d.name" :value="d.name">{{ d.label }}</option>
              </select>
              <p v-if="!opzioni.dateCliente.length" class="text-xs text-amber-700 mt-1">Nessuna data tra i campi cliente: aggiungila in <Link :href="route('impostazioni.campi')" class="underline">Campi</Link>.</p>
            </div>
            <div>
              <label class="field-label">Quanti giorni prima</label>
              <input v-model.number="form.days_before" type="number" min="0" max="365" class="field-input w-24" />
              <p class="text-xs text-gray-500 mt-1">0 = il giorno stesso. Va sempre al cliente, ogni mattina alle 8.</p>
            </div>
          </div>

          <p v-if="erroriInnesco" class="text-sm text-red-600">{{ erroriInnesco }}</p>
        </div>

        <!-- Messaggio -->
        <div v-if="pannello === 'messaggio'" class="p-5 space-y-5">
          <div>
            <p class="field-label">Come</p>
            <div class="grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1">
              <button v-for="c in canali" :key="c.valore" type="button" class="rounded-md py-1.5 text-sm font-medium transition"
                :class="form.channel === c.valore ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-800'"
                @click="form.channel = c.valore">{{ c.label }}</button>
            </div>
            <p v-if="usaEmail && !opzioni.emailCollegata" class="mt-2 text-xs text-amber-700 bg-amber-50 rounded px-2 py-1.5">
              Nessuna casella email collegata: <Link :href="route('impostazioni.email')" class="underline font-semibold">collegala</Link>, altrimenti le email non partono.
            </p>
            <p v-if="usaWhatsapp" class="mt-2 text-xs rounded px-2 py-1.5" :class="opzioni.whatsappCollegato ? 'text-gray-600 bg-gray-50' : 'text-amber-700 bg-amber-50'">
              <template v-if="!opzioni.whatsappCollegato">WhatsApp non collegato: <Link :href="route('whatsapp.index')" class="underline font-semibold">collegalo</Link>. </template>
              WhatsApp consegna il testo libero solo se il destinatario ti ha scritto nelle ultime 24 ore.
            </p>
          </div>

          <div v-if="form.trigger_type !== 'cliente_date_field'">
            <p class="field-label">A chi</p>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="r in ruoli" :key="r.type" type="button" class="px-3 py-1 rounded-full text-sm border transition"
                :class="haDestinatario(r.type) ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-gray-400'"
                :title="r.aiuto" @click="toggleDestinatario({ type: r.type })">{{ r.label }}</button>
            </div>
            <p class="text-xs text-gray-500 mt-2 mb-1">Colleghi dell'agenzia</p>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="u in opzioni.utenti" :key="u.id" type="button" class="px-3 py-1 rounded-full text-sm border transition"
                :class="haDestinatario('user', u.id) ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-gray-400'"
                @click="toggleDestinatario({ type: 'user', user_id: u.id })">{{ u.name }}</button>
            </div>
            <p v-if="form.errors.recipients_to" class="text-sm text-red-600 mt-1">{{ form.errors.recipients_to }}</p>
          </div>
          <p v-else class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">Va sempre al cliente di cui scade la data.</p>

          <div v-if="usaEmail && form.trigger_type !== 'cliente_date_field' && opzioni.utenti.length">
            <p class="field-label">In copia (CC)</p>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="u in opzioni.utenti" :key="u.id" type="button" class="px-3 py-1 rounded-full text-sm border transition"
                :class="haCc(u.id) ? 'bg-gray-700 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-700 hover:border-gray-400'"
                @click="toggleCc(u.id)">{{ u.name }}</button>
            </div>
          </div>

          <div v-if="usaEmail">
            <label class="field-label">Oggetto dell'email</label>
            <input ref="campoOggetto" v-model="form.subject" type="text" class="field-input" :placeholder="oggettoPredefinito" @focus="ultimoCampo = 'subject'" />
            <p v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</p>
          </div>

          <div>
            <label class="field-label">Messaggio</label>
            <textarea ref="campoMessaggio" v-model="form.message_template" rows="8" class="field-input" placeholder="Gentile {nome_cliente}, …" @focus="ultimoCampo = 'message_template'" />
            <p v-if="form.errors.message_template" class="text-sm text-red-600 mt-1">{{ form.errors.message_template }}</p>
            <p class="text-xs text-gray-500 mt-2 mb-1">Inserisci un dato (clicca):</p>
            <div class="flex flex-wrap gap-1">
              <button v-for="v in variabili" :key="v.codice" type="button" class="px-2 py-0.5 rounded bg-violet-50 text-violet-700 text-xs border border-violet-100 hover:bg-violet-100"
                :title="v.codice" @click="inserisci(v.codice)">{{ v.etichetta }}</button>
            </div>
          </div>

          <div v-if="form.trigger_type !== 'cliente_date_field'">
            <p class="field-label">📎 Allegati: documenti del sinistro</p>
            <p v-if="!usaEmail" class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1.5">Gli allegati partono solo via email: scegli "Email" o "Entrambi".</p>
            <p v-else-if="!opzioni.categorie.length" class="text-xs text-gray-500">Nessuna categoria attiva: attivale in <Link :href="route('impostazioni.categorie')" class="underline">Categorie documenti</Link>.</p>
            <p v-else class="text-xs text-gray-500 mb-2">Nell'email arrivano i file del sinistro di queste categorie, come link da scaricare (validi 7 giorni). In anteprima vedi quali file partirebbero.</p>
            <div v-if="usaEmail" class="space-y-1">
              <label v-for="c in opzioni.categorie" :key="c.id" class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.document_category_ids" type="checkbox" :value="c.id" class="rounded border-gray-300 text-indigo-600" /> {{ c.name }}
              </label>
            </div>
          </div>
        </div>

        <!-- Anteprima -->
        <div v-if="pannello === 'anteprima'" class="p-5 space-y-4">
          <div>
            <label class="field-label">{{ perCliente ? 'Cliente di esempio' : 'Sinistro di esempio' }}</label>
            <select v-model="esempio" class="field-input" @change="caricaAnteprima">
              <option :value="null">{{ perCliente ? 'L\'ultimo cliente' : 'L\'ultimo sinistro' }}</option>
              <option v-for="p in (perCliente ? opzioni.clienti : opzioni.pratiche)" :key="p.id" :value="p.id">{{ p.etichetta }}</option>
            </select>
          </div>

          <p v-if="anteprima.caricamento" class="text-sm text-gray-500">Preparo l'anteprima…</p>
          <div v-else-if="anteprima.errori.length" class="text-sm text-red-700 bg-red-50 rounded-lg p-3 space-y-1">
            <p class="font-semibold">Completa prima questi punti:</p>
            <p v-for="e in anteprima.errori" :key="e">• {{ e }}</p>
          </div>
          <template v-else-if="anteprima.piano">
            <div class="text-sm space-y-1">
              <p class="text-gray-500">Andrebbe a:</p>
              <p v-if="!anteprima.piano.recipients.length" class="text-amber-700">Nessuno: per questo sinistro manca il recapito dei destinatari scelti.</p>
              <p v-for="r in anteprima.piano.recipients" :key="r.key" class="text-gray-800">
                <span class="font-medium">{{ r.name || r.kind }}</span>
                <span class="text-gray-500"> · {{ [usaEmail ? (r.email || 'senza email') : null, usaWhatsapp ? (r.phone || 'senza telefono') : null].filter(Boolean).join(' · ') }}</span>
              </p>
              <p v-if="anteprima.piano.cc.length" class="text-gray-500">CC: {{ elenco(anteprima.piano.cc, 'email') }}</p>
            </div>
            <p v-if="anteprima.piano.mancanti?.length" class="text-xs text-amber-800 bg-amber-50 rounded-lg px-3 py-2">
              In questo sinistro manca: {{ anteprima.piano.mancanti.join(', ') }}. Nel messaggio quel punto resterà vuoto.
            </p>
            <div class="rounded-lg border border-gray-200 overflow-hidden">
              <p v-if="usaEmail" class="px-3 py-2 bg-gray-50 border-b border-gray-200 text-sm"><span class="text-gray-500">Oggetto:</span> <span class="font-medium">{{ anteprima.piano.subject }}</span></p>
              <p class="px-3 py-3 text-sm text-gray-800 whitespace-pre-line">{{ anteprima.piano.message }}</p>
              <div v-if="anteprima.piano.documents?.length" class="px-3 pb-3 text-xs text-gray-600">
                📎 {{ elenco(anteprima.piano.documents, 'nome_file') }}
              </div>
              <p v-else-if="anteprima.piano.document_categories?.length" class="px-3 pb-3 text-xs text-amber-700">Nessun documento di queste categorie in questo sinistro.</p>
            </div>
            <div v-if="usaEmail" class="flex items-center gap-2">
              <button type="button" :disabled="prova.invio" class="text-sm font-semibold px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50" @click="inviaProva">
                {{ prova.invio ? 'Invio…' : 'Invia prova a me' }}
              </button>
              <span class="text-xs" :class="prova.ok ? 'text-emerald-600' : 'text-red-600'">{{ prova.messaggio }}</span>
            </div>
          </template>
        </div>

        <div v-if="automazione" class="px-5 py-4 border-t border-gray-100">
          <button type="button" class="text-sm text-red-600 hover:text-red-800" @click="elimina">Elimina automazione</button>
        </div>
      </aside>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { BOZZA_VUOTA, MODELLI, type BozzaAutomazione, type DestinatarioAuto } from '@/Components/Impostazioni/modelliAutomazione'
import type { PageProps } from '@/types'

interface Voce { id: number; etichetta: string }
interface Opzioni {
  stati: { id: number; name: string; color: string; is_closed: boolean }[]
  datePratica: { name: string; label: string }[]
  dateCliente: { name: string; label: string }[]
  utenti: { id: number; name: string; email: string }[]
  categorie: { id: number; name: string }[]
  variabiliPratica: { codice: string; etichetta: string }[]
  variabiliCliente: { codice: string; etichetta: string }[]
  pratiche: Voce[]
  clienti: Voce[]
  emailCollegata: boolean
  whatsappCollegato: boolean
  inneschi: { valore: string; etichetta: string }[]
}

const props = defineProps<{ automazione: (BozzaAutomazione & { id: number }) | null; modello: string | null; opzioni: Opzioni }>()
const page = usePage<PageProps>()
const flash = computed(() => page.props.flash)

// ── Stato del modulo ────────────────────────────────────────────────────────
const iniziale = (): BozzaAutomazione => {
  if (props.automazione) return { ...BOZZA_VUOTA, ...props.automazione }
  const m = MODELLI.find((x) => x.chiave === props.modello)
  return { ...BOZZA_VUOTA, ...(m?.bozza ?? {}) }
}
const form = useForm<BozzaAutomazione>(iniziale())
const fotografia = ref(JSON.stringify(form.data()))
const modificata = computed(() => JSON.stringify(form.data()) !== fotografia.value)
const salvatoOra = ref(false)

const pannello = ref<'innesco' | 'messaggio' | 'anteprima'>(props.automazione ? 'messaggio' : 'innesco')
const schede = [
  { id: 'innesco' as const, label: '⚡ Quando' },
  { id: 'messaggio' as const, label: '✉️ Messaggio' },
  { id: 'anteprima' as const, label: '👁 Anteprima' },
]

// ── Innesco ─────────────────────────────────────────────────────────────────
const iconeInnesco: Record<string, string> = { status: '🔄', pratica_created: '🆕', date_field: '📝', date_reminder: '📅', cliente_date_field: '⏰' }
const etichettaInnesco = computed(() => props.opzioni.inneschi.find((i) => i.valore === form.trigger_type)?.etichetta ?? '')
const nomeData = (lista: { name: string; label: string }[]) => lista.find((d) => d.name === form.watched_field)?.label

const dettaglioInnesco = computed<{ testo: string; mancante?: boolean }>(() => {
  switch (form.trigger_type) {
    case 'status': {
      const s = props.opzioni.stati.find((x) => x.id === form.tenant_status_id)
      return { testo: s ? `Quando lo stato diventa «${s.name}»` : 'A ogni cambio di stato' }
    }
    case 'pratica_created': return { testo: 'Appena il sinistro viene aperto' }
    case 'date_field': {
      const n = nomeData(props.opzioni.datePratica)
      return n ? { testo: `Quando cambia «${n}»` } : { testo: 'Scegli la data da osservare', mancante: true }
    }
    case 'date_reminder': {
      const n = nomeData(props.opzioni.datePratica)
      if (!n) return { testo: 'Scegli la data', mancante: true }
      const g = Math.abs(form.offset_days)
      const quanto = form.offset_days === 0 ? 'Il giorno di' : `${g} ${g === 1 ? 'giorno' : 'giorni'} ${form.offset_days < 0 ? 'prima di' : 'dopo'}`
      return { testo: `${quanto} «${n}», alle 8:00` }
    }
    case 'cliente_date_field': {
      const n = nomeData(props.opzioni.dateCliente)
      if (!n) return { testo: 'Scegli la data del cliente', mancante: true }
      return { testo: `${form.days_before ? `${form.days_before} giorni prima di` : 'Il giorno di'} «${n}», alle 8:00` }
    }
  }
  return { testo: '' }
})

// "N giorni prima/dopo" ↔ offset_days con segno
const giorniOffset = ref(Math.abs(form.offset_days) || 1)
const quando = ref<'prima' | 'dopo' | 'stesso'>(form.offset_days === 0 ? 'stesso' : form.offset_days > 0 ? 'dopo' : 'prima')
watch([giorniOffset, quando], () => {
  form.offset_days = quando.value === 'stesso' ? 0 : (quando.value === 'prima' ? -1 : 1) * Math.max(1, giorniOffset.value || 1)
})
watch(() => form.trigger_type, (t, prima) => {
  // Cambiando innesco la data scelta prima può non valere più (sinistro ↔ cliente).
  const listaNuova = t === 'cliente_date_field' ? props.opzioni.dateCliente : props.opzioni.datePratica
  if (!listaNuova.some((d) => d.name === form.watched_field)) form.watched_field = null
  if (t === 'cliente_date_field' && prima !== t) form.recipients_to = [{ type: 'cliente' }]
})

// ── Messaggio ───────────────────────────────────────────────────────────────
const canali = [
  { valore: 'email' as const, label: '✉️ Email' },
  { valore: 'whatsapp' as const, label: '💬 WhatsApp' },
  { valore: 'both' as const, label: 'Entrambi' },
]
const usaEmail = computed(() => form.channel !== 'whatsapp')
const usaWhatsapp = computed(() => form.channel !== 'email')
const etichettaCanale = computed(() => ({ email: 'Invia email', whatsapp: 'Invia WhatsApp', both: 'Invia email e WhatsApp' })[form.channel])
const iconaCanale = computed(() => ({ email: '✉️', whatsapp: '💬', both: '📨' })[form.channel])
const coloreCanale = computed(() => ({ email: 'bg-blue-50 border-blue-200', whatsapp: 'bg-green-50 border-green-200', both: 'bg-teal-50 border-teal-200' })[form.channel])

const ruoli = [
  { type: 'cliente', label: 'Cliente', aiuto: 'Il cliente del sinistro (email/telefono della scheda cliente)' },
  { type: 'perito', label: 'Perito', aiuto: 'Il perito del sopralluogo' },
  { type: 'carrozzeria', label: 'Carrozzeria', aiuto: 'La carrozzeria del sopralluogo' },
  { type: 'gestore', label: 'Chi ha aperto il sinistro', aiuto: 'Il collega che ha aperto il sinistro' },
]
const chiave = (r: DestinatarioAuto) => `${r.type}:${r.user_id ?? ''}`
const haDestinatario = (type: string, userId?: number) => form.recipients_to.some((r) => chiave(r) === chiave({ type, user_id: userId }))
function toggleDestinatario(r: DestinatarioAuto) {
  form.recipients_to = haDestinatario(r.type, r.user_id)
    ? form.recipients_to.filter((x) => chiave(x) !== chiave(r))
    : [...form.recipients_to, r]
}
const haCc = (id: number) => form.recipients_cc.some((r) => r.user_id === id)
function toggleCc(id: number) {
  form.recipients_cc = haCc(id) ? form.recipients_cc.filter((r) => r.user_id !== id) : [...form.recipients_cc, { type: 'user', user_id: id }]
}
const riassuntoDestinatari = computed(() => {
  if (form.trigger_type === 'cliente_date_field') return 'cliente'
  const nomi = form.recipients_to.map((r) => r.type === 'user'
    ? (props.opzioni.utenti.find((u) => u.id === r.user_id)?.name ?? 'collega')
    : ruoli.find((x) => x.type === r.type)?.label.toLowerCase() ?? r.type)
  return nomi.length ? nomi.join(', ') : 'nessuno — scegli i destinatari'
})

const nomiCategorie = computed(() => props.opzioni.categorie.filter((c) => form.document_category_ids.includes(c.id)).map((c) => c.name).join(', '))
const perCliente = computed(() => form.trigger_type === 'cliente_date_field')
const variabili = computed(() => perCliente.value ? props.opzioni.variabiliCliente : props.opzioni.variabiliPratica)
const oggettoPredefinito = computed(() => perCliente.value ? 'Promemoria — {nome_cliente}' : 'Sinistro #{numero_pratica} — {stato_corrente}')

const campoMessaggio = ref<HTMLTextAreaElement | null>(null)
const campoOggetto = ref<HTMLInputElement | null>(null)
const ultimoCampo = ref<'subject' | 'message_template'>('message_template')
function inserisci(codice: string) {
  const el = ultimoCampo.value === 'subject' ? campoOggetto.value : campoMessaggio.value
  const chiaveCampo = ultimoCampo.value
  const testo = (form[chiaveCampo] as string | null) ?? ''
  const da = el?.selectionStart ?? testo.length
  const a = el?.selectionEnd ?? testo.length
  form[chiaveCampo] = testo.slice(0, da) + codice + testo.slice(a)
  nextTick(() => { el?.focus(); el?.setSelectionRange(da + codice.length, da + codice.length) })
}

// ── Errori sui nodi ─────────────────────────────────────────────────────────
const errore = (k: string) => (form.errors as Record<string, string | undefined>)[k]
const erroriInnesco = computed(() => ['trigger_type', 'tenant_status_id', 'watched_field', 'days_before', 'offset_days'].map(errore).find(Boolean))
const erroriMessaggio = computed(() => Object.keys(form.errors).filter((k) => /^(channel|subject|message_template|recipients|document)/.test(k)).map(errore).find(Boolean))
const erroriGenerali = computed(() => [errore('name')].filter(Boolean) as string[])

// ── Salvataggio ─────────────────────────────────────────────────────────────
function salva() {
  if (!form.name.trim()) form.name = etichettaInnesco.value ? `${etichettaInnesco.value}` : 'Nuova automazione'
  const opzioniInvio = {
    preserveScroll: true,
    onSuccess: () => {
      fotografia.value = JSON.stringify(form.data())
      salvatoOra.value = true
      setTimeout(() => { salvatoOra.value = false }, 2000)
    },
    onError: () => { pannello.value = erroriInnesco.value ? 'innesco' : erroriMessaggio.value ? 'messaggio' : pannello.value },
  }
  if (props.automazione) form.put(route('impostazioni.automazioni.update', props.automazione.id), opzioniInvio)
  else form.post(route('impostazioni.automazioni.store'), opzioniInvio)
}

function elimina() {
  if (!props.automazione || !window.confirm(`Eliminare l'automazione "${props.automazione.name}"?`)) return
  fotografia.value = JSON.stringify(form.data())
  router.delete(route('impostazioni.automazioni.destroy', props.automazione.id))
}

function esci(e: MouseEvent) {
  if (modificata.value && !window.confirm('Ci sono modifiche non salvate. Uscire lo stesso?')) e.preventDefault()
}
const avvisaUscita = (e: BeforeUnloadEvent) => { if (modificata.value) { e.preventDefault(); e.returnValue = '' } }
onMounted(() => window.addEventListener('beforeunload', avvisaUscita))
onBeforeUnmount(() => window.removeEventListener('beforeunload', avvisaUscita))

// ── Anteprima e prova ───────────────────────────────────────────────────────
const esempio = ref<number | null>(null)
const anteprima = reactive<{ caricamento: boolean; errori: string[]; piano: any }>({ caricamento: false, errori: [], piano: null })
const prova = reactive({ invio: false, ok: false, messaggio: '' })
const elenco = (lista: Record<string, string>[], campo: string) => lista.map((x) => x[campo]).join(', ')

const datiRichiesta = () => ({
  ...form.data(),
  id: props.automazione?.id,
  [perCliente.value ? 'cliente_id' : 'pratica_id']: esempio.value,
})

async function caricaAnteprima() {
  anteprima.caricamento = true
  anteprima.errori = []
  prova.messaggio = ''
  try {
    const { data } = await axios.post(route('impostazioni.automazioni.anteprima'), datiRichiesta())
    anteprima.piano = data.piano
  } catch (e: any) {
    anteprima.piano = null
    anteprima.errori = Object.values(e.response?.data?.errori ?? { x: e.response?.data?.message ?? 'Anteprima non disponibile.' }) as string[]
  } finally {
    anteprima.caricamento = false
  }
}
function apriAnteprima() {
  pannello.value = 'anteprima'
  caricaAnteprima()
}
async function inviaProva() {
  prova.invio = true
  prova.messaggio = ''
  try {
    const { data } = await axios.post(route('impostazioni.automazioni.prova'), datiRichiesta())
    prova.ok = true
    prova.messaggio = data.messaggio
  } catch (e: any) {
    prova.ok = false
    prova.messaggio = (Object.values(e.response?.data?.errori ?? {})[0] as string) ?? 'Invio non riuscito.'
  } finally {
    prova.invio = false
  }
}
</script>

<style scoped>
.tela {
  background-image: radial-gradient(circle, #d1d5db 1px, transparent 1px);
  background-size: 24px 24px;
}
</style>
