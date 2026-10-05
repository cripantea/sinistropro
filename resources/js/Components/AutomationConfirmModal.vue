<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

          <div class="flex items-start gap-3 px-6 py-4 border-b border-slate-100 shrink-0">
            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-800">
                {{ automations.length === 1 ? 'Sta per partire 1 messaggio automatico' : `Stanno per partire ${automations.length} messaggi automatici` }}
              </p>
              <p class="text-xs text-slate-500 mt-0.5">Controlla testo e destinatari: puoi togliere o aggiungere persone prima di inviare.</p>
            </div>
          </div>

          <div class="px-6 py-4 space-y-4 overflow-y-auto">
            <div
              v-for="a in state"
              :key="a.id"
              class="rounded-xl border"
              :class="a.send ? 'border-slate-200' : 'border-slate-200 bg-slate-50 opacity-70'"
            >
              <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
                <input type="checkbox" v-model="a.send" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-slate-800 truncate">{{ a.name }}</p>
                  <p class="text-[11px] text-slate-500">{{ channelLabel(a.channel) }}<span v-if="a.channel !== 'whatsapp'"> · Oggetto: {{ a.subject }}</span></p>
                </div>
                <span v-if="!a.send" class="text-[11px] font-semibold text-slate-500">Non verrà inviato</span>
              </div>

              <div v-if="a.send" class="px-4 py-3 space-y-3">
                <!-- Messaggio -->
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Messaggio</p>
                  <pre class="whitespace-pre-wrap font-sans text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">{{ a.message }}</pre>
                </div>

                <!-- Allegati -->
                <div v-if="a.documents.length || a.document_categories.length">
                  <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">
                    Allegati ({{ a.documents.length }})
                  </p>
                  <ul v-if="a.documents.length" class="space-y-1.5">
                    <li v-for="d in a.documents" :key="d.id" class="flex items-center gap-2 text-sm bg-white border border-slate-200 rounded-lg px-3 py-1.5">
                      <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                      <span class="flex-1 min-w-0 truncate text-slate-800">{{ d.nome_file }}</span>
                      <span v-if="d.categoria" class="text-[10px] uppercase text-slate-400 shrink-0">{{ d.categoria }}</span>
                      <button type="button" @click="openDocument(d.id)" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 shrink-0">Apri</button>
                    </li>
                  </ul>
                  <p v-else class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                    Nessun allegato trovato per le categorie collegate ({{ a.document_categories.join(', ') }}): il messaggio partirà senza documenti.
                  </p>
                  <p v-if="a.documents.length" class="text-[11px] text-slate-400 mt-1">I documenti arrivano al destinatario come link di download (validi 7 giorni).</p>
                </div>

                <!-- Destinatari -->
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Destinatari ({{ a.recipients.length }})</p>
                  <ul class="space-y-1.5">
                    <li v-for="(r, i) in a.recipients" :key="i" class="flex items-center gap-2 text-sm bg-white border border-slate-200 rounded-lg px-3 py-1.5">
                      <div class="flex-1 min-w-0">
                        <span class="font-medium text-slate-800">{{ r.name || 'Senza nome' }}</span>
                        <span v-if="r.kind && r.kind !== 'extra'" class="ml-1.5 text-[10px] uppercase text-slate-400">{{ kindLabel(r.kind) }}</span>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                          <template v-if="usesEmail(a.channel)">
                            <input v-if="!r.email" v-model="r.emailInput" type="email" placeholder="Email mancante: inseriscila" class="text-xs border border-amber-300 bg-amber-50 rounded px-2 py-0.5 w-52 focus:ring-1 focus:ring-indigo-500 outline-none" />
                            <span v-else class="text-xs text-slate-500">✉️ {{ r.email }}</span>
                          </template>
                          <template v-if="usesPhone(a.channel)">
                            <input v-if="!r.phone" v-model="r.phoneInput" type="text" placeholder="Telefono mancante: inseriscilo" class="text-xs border border-amber-300 bg-amber-50 rounded px-2 py-0.5 w-52 focus:ring-1 focus:ring-indigo-500 outline-none" />
                            <span v-else class="text-xs text-slate-500">📱 {{ r.phone }}</span>
                          </template>
                        </div>
                      </div>
                      <button type="button" @click="a.recipients.splice(i, 1)" class="text-slate-400 hover:text-red-600 p-1" title="Togli destinatario">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      </button>
                    </li>
                    <li v-if="a.recipients.length === 0" class="text-xs text-red-600">Nessun destinatario: questo messaggio non partirà.</li>
                  </ul>

                  <!-- Aggiungi dalla rubrica -->
                  <div v-if="rubrica.length" class="mt-2">
                    <select
                      class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 bg-white w-full sm:w-80 focus:ring-1 focus:ring-indigo-500 outline-none"
                      @change="addFromRubrica(a, $event)"
                    >
                      <option value="">+ Aggiungi dalla rubrica…</option>
                      <option v-for="c in rubricaFor(a)" :key="c.id" :value="c.id">
                        {{ c.nome }}{{ c.tags?.length ? ' · ' + c.tags.join(', ') : '' }}
                      </option>
                    </select>
                  </div>

                  <!-- Aggiungi destinatario a mano -->
                  <div class="flex flex-wrap items-center gap-2 mt-2">
                    <input v-model="a.addName" type="text" placeholder="Nome (facoltativo)" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 w-36 focus:ring-1 focus:ring-indigo-500 outline-none" />
                    <input v-if="usesEmail(a.channel)" v-model="a.addEmail" type="email" placeholder="Email" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 w-48 focus:ring-1 focus:ring-indigo-500 outline-none" @keydown.enter.prevent="addRecipient(a)" />
                    <input v-if="usesPhone(a.channel)" v-model="a.addPhone" type="text" placeholder="Telefono" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 w-36 focus:ring-1 focus:ring-indigo-500 outline-none" @keydown.enter.prevent="addRecipient(a)" />
                    <button type="button" @click="addRecipient(a)" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 px-2 py-1.5">+ Aggiungi destinatario</button>
                  </div>
                  <p v-if="a.addError" class="text-xs text-red-600 mt-1">{{ a.addError }}</p>
                </div>

                <!-- CC (solo email) -->
                <div v-if="usesEmail(a.channel)">
                  <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">In copia (CC)</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="(c, i) in a.cc" :key="c" class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-700 rounded-full pl-2.5 pr-1 py-0.5">
                      {{ c }}
                      <button type="button" @click="a.cc.splice(i, 1)" class="text-slate-400 hover:text-red-600 p-0.5">×</button>
                    </span>
                    <input v-model="a.addCc" type="email" placeholder="Aggiungi email in copia + Invio" class="text-xs border border-slate-300 rounded-full px-2.5 py-0.5 w-52 focus:ring-1 focus:ring-indigo-500 outline-none" @keydown.enter.prevent="addCc(a)" @blur="addCc(a)" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-2 px-6 py-4 border-t border-slate-100 shrink-0">
            <button
              type="button"
              @click="accept"
              class="flex-1 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition"
            >
              {{ sendCount > 0 ? `Conferma e invia (${sendCount})` : 'Procedi senza inviare' }}
            </button>
            <button
              type="button"
              @click="$emit('block-automations')"
              class="flex-1 border border-slate-300 text-slate-700 text-sm font-medium py-2.5 rounded-lg hover:bg-slate-50 transition"
            >
              {{ blockAutomationsLabel }}
            </button>
            <button
              type="button"
              @click="$emit('block-action')"
              class="text-slate-500 text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-slate-50 transition"
            >
              {{ blockActionLabel }}
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import axios from 'axios'

export interface PlanRecipient { key?: string; kind?: string; name: string | null; email: string | null; phone: string | null }
export interface RubricaContatto { id: number; nome: string; tags: string[] | null; telefono: string | null; email: string | null }
export interface AutomationPlan {
  id: number
  name: string
  channel: 'email' | 'whatsapp' | 'both' | string
  subject?: string
  message: string
  documents: { id: number; nome_file: string; categoria: string | null }[]
  document_categories: string[]
  recipients: PlanRecipient[]
  cc: { name?: string | null; email: string }[]
}
export interface AutomationOverride {
  send: boolean
  recipients: { name: string | null; email: string | null; phone: string | null }[]
  cc: string[]
}
export type AutomationOverrides = Record<number, AutomationOverride>

interface EditableRecipient extends PlanRecipient { emailInput: string; phoneInput: string }
interface EditableAutomation extends Omit<AutomationPlan, 'recipients' | 'cc'> {
  send: boolean
  recipients: EditableRecipient[]
  cc: string[]
  addName: string; addEmail: string; addPhone: string; addCc: string; addError: string
}

const props = withDefaults(defineProps<{
  show: boolean
  automations: AutomationPlan[]
  blockAutomationsLabel?: string
  blockActionLabel?: string
  rubrica?: RubricaContatto[]
}>(), {
  rubrica: () => [],
  blockAutomationsLabel: 'Procedi senza automazioni',
  blockActionLabel: 'Annulla azione',
})

const emit = defineEmits<{
  accept: [overrides: AutomationOverrides]
  'block-automations': []
  'block-action': []
}>()

const state = reactive<EditableAutomation[]>([])

// Ogni volta che arriva un nuovo elenco (o la modale si riapre) ripartiamo dai valori calcolati.
watch(() => [props.show, props.automations] as const, () => {
  state.splice(0, state.length, ...props.automations.map(a => ({
    ...a,
    send: true,
    recipients: a.recipients.map(r => ({ ...r, emailInput: '', phoneInput: '' })),
    cc: a.cc.map(c => c.email),
    addName: '', addEmail: '', addPhone: '', addCc: '', addError: '',
  })))
}, { immediate: true })

const usesEmail = (c: string) => c === 'email' || c === 'both'
const usesPhone = (c: string) => c === 'whatsapp' || c === 'both'
const channelLabel = (c: string) => ({ email: 'Email', whatsapp: 'WhatsApp', both: 'Email + WhatsApp' } as Record<string, string>)[c] ?? c
const kindLabel = (k: string) => ({ cliente: 'cliente', perito: 'perito', carrozzeria: 'carrozzeria', gestore: 'gestore', user: 'team' } as Record<string, string>)[k] ?? k

const sendCount = computed(() => state.filter(a => a.send && a.recipients.length > 0).length)

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function addRecipient(a: EditableAutomation) {
  const email = a.addEmail.trim()
  const phone = a.addPhone.trim()
  a.addError = ''
  if (usesEmail(a.channel) && !usesPhone(a.channel) && !EMAIL_RE.test(email)) { a.addError = 'Inserisci un indirizzo email valido.'; return }
  if (usesPhone(a.channel) && !usesEmail(a.channel) && !phone) { a.addError = 'Inserisci un numero di telefono.'; return }
  if (a.channel === 'both' && !EMAIL_RE.test(email) && !phone) { a.addError = 'Inserisci email o telefono.'; return }
  a.recipients.push({
    kind: 'extra', name: a.addName.trim() || null,
    email: EMAIL_RE.test(email) ? email : null, phone: phone || null,
    emailInput: '', phoneInput: '',
  })
  a.addName = a.addEmail = a.addPhone = ''
}

// Contatti della rubrica utilizzabili sul canale dell'automazione, non già tra i destinatari.
function rubricaFor(a: EditableAutomation) {
  return props.rubrica.filter(c => {
    const ok = a.channel === 'whatsapp' ? !!c.telefono : a.channel === 'both' ? !!(c.email || c.telefono) : !!c.email
    const already = a.recipients.some(r => (r.email && r.email === c.email) || (r.phone && r.phone === c.telefono))
    return ok && !already
  })
}

function addFromRubrica(a: EditableAutomation, ev: Event) {
  const sel = ev.target as HTMLSelectElement
  const c = props.rubrica.find(x => String(x.id) === sel.value)
  sel.value = ''
  if (!c) return
  a.recipients.push({ kind: 'extra', name: c.nome, email: c.email, phone: c.telefono, emailInput: '', phoneInput: '' })
}

function addCc(a: EditableAutomation) {
  const e = a.addCc.trim()
  if (!e) return
  if (EMAIL_RE.test(e) && !a.cc.includes(e)) a.cc.push(e)
  a.addCc = ''
}

async function openDocument(id: number) {
  const { data } = await axios.get<{ url: string }>(route('allegati.download', id))
  window.open(data.url, '_blank')
}

function accept() {
  const overrides: AutomationOverrides = {}
  for (const a of state) {
    overrides[a.id] = {
      send: a.send,
      recipients: a.recipients.map(r => ({
        name: r.name,
        email: (r.email || r.emailInput.trim()) || null,
        phone: (r.phone || r.phoneInput.trim()) || null,
      })),
      cc: a.cc,
    }
  }
  emit('accept', overrides)
}
</script>
