<template>
  <ImpostazioniLayout largo>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-lg font-semibold text-gray-800">Template WhatsApp</h3>
        <p class="text-sm text-gray-500">Messaggi approvati da Meta: servono per scrivere a chi non vi ha scritto nelle ultime 24 ore (promemoria, solleciti, automazioni).</p>
      </div>
      <div v-if="collegato" class="flex gap-2">
        <button type="button" @click="sincronizza" :disabled="sincronizzando" class="text-sm font-semibold px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
          {{ sincronizzando ? 'Sincronizzo…' : '↻ Sincronizza' }}
        </button>
        <button type="button" @click="apri()" class="text-sm font-semibold px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">+ Nuovo template</button>
      </div>
    </div>

    <div v-if="!collegato" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
      Collega prima il numero WhatsApp dell'agenzia: <Link :href="route('whatsapp.index')" class="font-semibold underline">vai a WhatsApp</Link>.
    </div>

    <!-- Modelli pronti -->
    <div v-if="collegato && !templates.length" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <p class="text-sm font-semibold text-gray-800">Parti da un modello</p>
      <p class="text-sm text-gray-500 mt-0.5">Sono di tipo "Servizio": Meta di solito li approva in pochi minuti.</p>
      <div class="grid sm:grid-cols-2 gap-2 mt-3">
        <button v-for="m in modelli" :key="m.name" type="button" @click="apri(undefined, m)" class="text-left rounded-lg border border-gray-200 p-3 hover:border-indigo-300 hover:bg-indigo-50/40">
          <p class="text-sm font-semibold text-gray-800">{{ m.titolo }}</p>
          <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ m.corpo }}</p>
        </button>
      </div>
    </div>

    <!-- Elenco -->
    <div v-if="templates.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <p class="px-4 py-2.5 text-xs text-gray-500 border-b border-gray-100">{{ templates.length }} template</p>
      <div v-for="t in templates" :key="t.id" class="flex flex-wrap sm:flex-nowrap items-start gap-3 px-4 py-3 border-b border-gray-100 last:border-0">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-mono font-semibold text-gray-800 truncate">{{ t.name }} <span class="font-sans font-normal text-xs text-gray-400">· {{ t.language }} · {{ t.category === 'MARKETING' ? 'Marketing' : 'Servizio' }}</span></p>
          <p class="text-sm text-gray-600 mt-0.5 whitespace-pre-line line-clamp-3">{{ t.corpo || t.body }}</p>
          <p v-if="t.statusReason && ['REJECTED', 'DISABLED'].includes(t.status)" class="text-xs text-red-600 mt-1">Motivo: {{ t.statusReason }}</p>
          <p v-if="usati[t.id]?.length" class="text-xs text-gray-400 mt-1">Usato da: {{ usati[t.id].join(', ') }}</p>
        </div>
        <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 shrink-0" :class="coloreStato(t.status)">{{ t.statusLabel }}</span>
        <div class="flex gap-3 text-xs shrink-0 pt-0.5">
          <button v-if="t.status !== 'PENDING'" type="button" @click="apri(t)" class="text-gray-600 hover:underline">Modifica</button>
          <button type="button" @click="elimina(t)" class="text-red-600 hover:underline">Elimina</button>
        </div>
      </div>
    </div>

    <!-- Editor -->
    <div v-if="editor" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50 px-4" @click.self="chiudi">
      <form @submit.prevent="salva" class="bg-white rounded-xl shadow-lg w-full max-w-3xl max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">{{ modifica ? `Modifica ${modifica.name}` : 'Nuovo template' }}</h3>
          <button type="button" @click="chiudi" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
        <div class="grid md:grid-cols-[1fr_260px] gap-5 p-5">
          <div class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="field-label">Nome</label>
                <input v-model="form.name" :disabled="!!modifica" type="text" class="field-input font-mono" placeholder="sollecito_documenti" @input="form.name = nomeMeta(form.name)" />
                <p class="text-xs text-gray-500 mt-1">Solo minuscole, numeri e _</p>
                <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
              </div>
              <div>
                <label class="field-label">Tipo</label>
                <select v-model="form.category" :disabled="modifica?.status === 'APPROVED'" class="field-input">
                  <option value="UTILITY">Servizio (aggiornamenti, promemoria)</option>
                  <option value="MARKETING">Marketing (promozioni)</option>
                </select>
              </div>
            </div>
            <div>
              <label class="field-label">Intestazione <span class="font-normal text-gray-400">(facoltativa)</span></label>
              <input v-model="form.header_text" type="text" maxlength="60" class="field-input" placeholder="Aggiornamento sinistro" />
            </div>
            <div>
              <label class="field-label">Messaggio</label>
              <textarea ref="campo" v-model="form.corpo" rows="7" maxlength="1024" class="field-input" placeholder="Gentile {nome_cliente}, …" />
              <div class="flex flex-wrap gap-1.5 mt-2">
                <button v-for="v in variabili" :key="v.codice" type="button" @click="inserisci(v.codice)" class="text-xs rounded-full border border-gray-200 px-2 py-0.5 text-gray-600 hover:border-indigo-300 hover:text-indigo-700" :title="v.codice">{{ v.etichetta }}</button>
              </div>
              <p class="text-xs text-gray-500 mt-1.5">Formattazione: *grassetto*, _corsivo_. Il messaggio non può iniziare o finire con una variabile.</p>
              <p v-if="form.errors.corpo" class="text-sm text-red-600 mt-1">{{ form.errors.corpo }}</p>
            </div>
            <div>
              <label class="field-label">Piè di pagina <span class="font-normal text-gray-400">(facoltativo)</span></label>
              <input v-model="form.footer_text" type="text" maxlength="60" class="field-input" :placeholder="'Es. Rispondi a questo messaggio per info'" />
            </div>
            <p v-if="modifica" class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1.5">La modifica viene inviata a Meta e il template torna in revisione. Per i template approvati Meta consente una modifica al giorno.</p>
          </div>

          <!-- Anteprima telefono -->
          <div>
            <p class="field-label">Anteprima</p>
            <div class="rounded-2xl bg-[#e5ddd5] p-3 min-h-[200px]">
              <div class="bg-white rounded-lg rounded-tl-none shadow-sm px-3 py-2 text-sm text-gray-800 max-w-full">
                <p v-if="form.header_text" class="font-semibold mb-1">{{ form.header_text }}</p>
                <p class="whitespace-pre-line break-words" v-html="anteprima" />
                <p v-if="form.footer_text" class="text-xs text-gray-400 mt-1.5">{{ form.footer_text }}</p>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">I dati veri (nome, numero sinistro…) si inseriscono da soli a ogni invio.</p>
          </div>
        </div>
        <div class="flex justify-end gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl">
          <button type="button" @click="chiudi" class="px-4 py-2 text-sm text-gray-600 hover:bg-white rounded-lg">Annulla</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
            {{ form.processing ? 'Invio a Meta…' : 'Invia per approvazione' }}
          </button>
        </div>
      </form>
    </div>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'

interface Template {
  id: number; name: string; language: string; category: string; status: string; statusLabel: string; statusReason: string | null
  body: string; corpo: string | null; variabili: string[]; variableCount: number; headerText: string | null; footerText: string | null
}

const props = defineProps<{
  templates: Template[]
  collegato: boolean
  variabili: { codice: string; etichetta: string }[]
  usati: Record<number, string[]>
}>()

const modelli = [
  { titolo: 'Aggiornamento stato', name: 'aggiornamento_sinistro', corpo: 'Gentile {nome_cliente}, la pratica n. {numero_pratica} è passata allo stato: {stato_corrente}. Per qualsiasi domanda rispondi a questo messaggio.' },
  { titolo: 'Promemoria perizia', name: 'promemoria_perizia', corpo: 'Gentile {nome_cliente}, ti ricordiamo la perizia per il sinistro n. {numero_pratica} fissata per {data_perizia}. Per spostarla rispondi a questo messaggio.' },
  { titolo: 'Sollecito documenti', name: 'sollecito_documenti', corpo: 'Gentile {nome_cliente}, per la pratica n. {numero_pratica} siamo ancora in attesa dei documenti richiesti. Puoi inviarli rispondendo a questo messaggio.' },
  { titolo: 'Richiesta documenti', name: 'richiesta_documenti', corpo: 'Gentile {nome_cliente}, per procedere con la pratica n. {numero_pratica} ci servono alcuni documenti. Rispondi a questo messaggio e ti diciamo quali.' },
]

const editor = ref(false)
const modifica = ref<Template | null>(null)
const sincronizzando = ref(false)
const campo = ref<HTMLTextAreaElement | null>(null)
const form = useForm({ name: '', category: 'UTILITY', header_text: '', corpo: '', footer_text: '' })

const nomeMeta = (s: string) => s.toLowerCase().replace(/[^a-z0-9_]/g, '_')

function apri(t?: Template, modello?: { name: string; corpo: string }) {
  form.clearErrors()
  modifica.value = t ?? null
  Object.assign(form, {
    name: t?.name ?? modello?.name ?? '',
    category: t?.category ?? 'UTILITY',
    header_text: t?.headerText ?? '',
    corpo: t ? (t.corpo || t.body) : (modello?.corpo ?? ''),
    footer_text: t?.footerText ?? '',
  })
  editor.value = true
}

function chiudi() {
  if (form.processing) return
  editor.value = false
}

function salva() {
  const opzioni = { preserveScroll: true, onSuccess: () => { editor.value = false } }
  if (modifica.value) form.put(route('impostazioni.whatsapp-template.update', modifica.value.id), opzioni)
  else form.post(route('impostazioni.whatsapp-template.store'), opzioni)
}

function sincronizza() {
  sincronizzando.value = true
  router.post(route('impostazioni.whatsapp-template.sincronizza'), {}, { preserveScroll: true, onFinish: () => { sincronizzando.value = false } })
}

function elimina(t: Template) {
  if (!confirm(`Eliminare ${t.name}? Viene eliminato anche su Meta e il nome non sarà riutilizzabile per 30 giorni.`)) return
  router.delete(route('impostazioni.whatsapp-template.destroy', t.id), { preserveScroll: true })
}

function inserisci(codice: string) {
  const el = campo.value
  const da = el?.selectionStart ?? form.corpo.length
  const a = el?.selectionEnd ?? form.corpo.length
  form.corpo = form.corpo.slice(0, da) + codice + form.corpo.slice(a)
  nextTick(() => { el?.focus(); el?.setSelectionRange(da + codice.length, da + codice.length) })
}

const etichette = computed(() => Object.fromEntries(props.variabili.map((v) => [v.codice, v.etichetta])))
const escape = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const anteprima = computed(() => {
  if (!form.corpo.trim()) return '<span class="text-gray-400">Il messaggio comparirà qui</span>'
  return escape(form.corpo)
    .replace(/\{[a-z_]+(?:\.[a-z0-9_]+)?\}/gi, (c) => `<span class="bg-indigo-50 text-indigo-700 rounded px-1">${escape(etichette.value[c] ?? c)}</span>`)
    .replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>')
    .replace(/_([^_\n]+)_/g, '<em>$1</em>')
})

function coloreStato(s: string) {
  return ({ APPROVED: 'bg-emerald-50 text-emerald-700', PENDING: 'bg-amber-50 text-amber-700', REJECTED: 'bg-red-50 text-red-700' } as Record<string, string>)[s] ?? 'bg-gray-100 text-gray-600'
}
</script>
