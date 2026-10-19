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
        @mousedown.self="$emit('close')"
      >
        <Transition
          enter-active-class="transition duration-200"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition duration-150"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="show" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
              <div>
                <p class="text-sm font-semibold text-slate-800">Compila Modulo</p>
                <p class="text-xs text-slate-400 mt-0.5">
                  {{ selectedTemplate ? selectedTemplate.name : 'Seleziona un template' }}
                </p>
              </div>
              <button
                @click="$emit('close')"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

              <!-- Error / warning banners — always at top so they're visible -->
              <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-xs text-red-700">
                {{ errorMsg }}
              </div>
              <div v-if="warningMsg" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-700">
                {{ warningMsg }}
              </div>

              <!-- Template selector (solo se c'è più di un template tra cui scegliere) -->
              <div v-if="templates.length > 1">
                <label class="block text-xs font-medium text-slate-700 mb-1">Template modulo *</label>
                <select
                  v-model="selectedTemplateId"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white"
                  :disabled="submitting"
                >
                  <option :value="null" disabled>— Scegli un template —</option>
                  <option v-for="t in templates" :key="t.id" :value="t.id">
                    {{ t.name }}
                    <template v-if="!t.pdf_template_s3_key"> (nessun PDF matrice)</template>
                  </option>
                </select>
                <p v-if="selectedTemplate && !selectedTemplate.pdf_template_s3_key" class="text-xs text-amber-600 mt-1">
                  Attenzione: questo template non ha un PDF matrice configurato — il PDF non verrà generato.
                </p>
              </div>
              <p v-else-if="selectedTemplate && !selectedTemplate.pdf_template_s3_key" class="text-xs text-amber-600">
                Attenzione: questo template non ha un PDF matrice configurato — il PDF non verrà generato.
              </p>

              <!-- Dynamic fields from fields_schema (un solo input per nome univoco,
                   anche se il campo compare più volte nel PDF) -->
              <template v-if="uniqueFields.length > 0">
                <div
                  v-for="field in uniqueFields"
                  :key="field.name"
                >
                  <label v-if="field.type !== 'checkbox'" class="block text-xs font-medium text-slate-700 mb-1">
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
                    <span v-if="autoFilledFields.has(field.name)" class="text-emerald-600 font-normal">(precompilato)</span>
                  </label>

                  <input
                    v-if="field.type === 'text'"
                    v-model="values[field.name]"
                    type="text"
                    :placeholder="field.label"
                    :required="field.required"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                  />

                  <textarea
                    v-else-if="field.type === 'textarea'"
                    v-model="values[field.name] as string"
                    :placeholder="field.label"
                    :required="field.required"
                    :disabled="submitting"
                    rows="4"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50 resize-y"
                  />

                  <DataItaliana
                    v-else-if="field.type === 'date'"
                    v-model="values[field.name] as string"
                    :required="field.required"
                    :disabled="submitting"
                  />

                  <div v-else-if="field.type === 'radio'" class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    <label
                      v-for="opt in field.options ?? []"
                      :key="opt"
                      :class="[
                        'flex items-center gap-2.5 rounded-lg border px-3 py-2 cursor-pointer select-none transition text-sm',
                        values[field.name] === opt ? 'border-indigo-500 bg-indigo-50 text-indigo-900 font-semibold' : 'border-slate-300 text-slate-700 hover:bg-slate-50',
                      ]"
                    >
                      <input
                        type="radio"
                        :name="field.name"
                        :value="opt"
                        :checked="values[field.name] === opt"
                        :disabled="submitting"
                        class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                        @change="values[field.name] = opt"
                      />
                      {{ opt }}
                    </label>
                    <button
                      v-if="values[field.name]"
                      type="button"
                      :disabled="submitting"
                      class="sm:col-span-2 justify-self-start text-xs text-slate-500 hover:text-red-600 hover:underline"
                      @click="values[field.name] = ''"
                    >Togli scelta</button>
                  </div>

                  <label
                    v-else-if="field.type === 'checkbox'"
                    :class="[
                      'flex items-center gap-3 rounded-lg border px-3 py-2.5 cursor-pointer select-none transition',
                      isChecked(values[field.name]) ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 hover:bg-slate-50',
                    ]"
                  >
                    <input
                      type="checkbox"
                      :checked="isChecked(values[field.name])"
                      :disabled="submitting"
                      class="w-5 h-5 rounded border-slate-400 text-indigo-600 focus:ring-indigo-500"
                      @change="values[field.name] = ($event.target as HTMLInputElement).checked ? 'true' : ''"
                    />
                    <span class="text-sm text-slate-800">{{ field.label }}</span>
                  </label>

                  <input
                    v-else-if="field.type === 'number'"
                    v-model.number="values[field.name]"
                    type="number"
                    :placeholder="field.label"
                    :required="field.required"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                  />

                  <select
                    v-else-if="field.type === 'boolean'"
                    v-model="values[field.name]"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50 bg-white"
                  >
                    <option value="">—</option>
                    <option value="true">Sì</option>
                    <option value="false">No</option>
                  </select>

                  <select
                    v-else-if="field.type === 'select' && field.options"
                    v-model="values[field.name]"
                    :required="field.required"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50 bg-white"
                  >
                    <option value="">— Scegli —</option>
                    <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                  </select>

                  <!-- Fallback for unknown types -->
                  <input
                    v-else
                    v-model="values[field.name]"
                    type="text"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                  />
                </div>
              </template>

              <p v-else-if="selectedTemplate" class="text-xs text-slate-400 italic">
                Questo template non ha campi configurati.
              </p>

            </form>

            <!-- Footer -->
            <div class="flex items-center gap-3 px-6 py-4 border-t border-slate-100">
              <button
                type="button"
                :disabled="!selectedTemplateId || submitting || savingDraft"
                @click="save"
                title="Salva i campi compilati finora, senza generare il PDF — utile se il modulo è ancora parziale"
                class="inline-flex items-center justify-center gap-2 border border-slate-300 text-slate-700 text-sm font-semibold py-2.5 px-4 rounded-lg hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="savingDraft" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                {{ savingDraft ? 'Salvataggio...' : 'Salva' }}
              </button>
              <button
                type="button"
                :disabled="!selectedTemplateId || submitting || savingDraft"
                @click="submit"
                class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ submitting ? 'Generazione PDF...' : 'Genera PDF' }}
              </button>
              <button
                type="button"
                :disabled="submitting || savingDraft"
                @click="$emit('close')"
                class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition disabled:opacity-50"
              >
                Annulla
              </button>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import DataItaliana from '@/Components/DataItaliana.vue'

interface FieldSchema {
  name: string
  label: string
  type: 'text' | 'textarea' | 'date' | 'number' | 'boolean' | 'select' | 'checkbox' | 'radio'
  required?: boolean
  options?: string[]
}

interface ModuleTemplate {
  id: number
  name: string
  fields_schema: FieldSchema[]
  pdf_template_s3_key: string | null
}

interface PraticaModule {
  id: number
  module_template_id: number
  values: Record<string, unknown>
}

interface Allegato {
  id: number
  nome_file: string
  created_at: string
  document_category_id: number | null
  source: 'generato' | 'caricato'
  module_template_id: number | null
  category: { id: number; name: string } | null
}

interface DictEntry {
  key: string
  source_type: 'manual' | 'cliente' | 'pratica_field'
  source_field: string | null
}

interface ClienteInfo {
  nome: string
  telefono: string | null
  email: string | null
  indirizzo?: string | null
  cap?: string | null
  citta?: string | null
  provincia?: string | null
  codice_fiscale?: string | null
  partita_iva?: string | null
  custom_fields?: Record<string, unknown> | null
}

interface SchemaField { name: string; label: string }

interface VeicoloInfo {
  targa: string | null
  marca: string | null
  modello: string | null
  data_immatricolazione?: string | null
}

const props = defineProps<{
  show: boolean
  praticaId: number
  templates: ModuleTemplate[]
  praticaModules: PraticaModule[]
  fieldDictionary?: DictEntry[]
  cliente?: ClienteInfo | null
  customFields?: Record<string, unknown> | null
  customFieldsSchema?: SchemaField[]
  clienteSchema?: SchemaField[]
  compagnia?: string | null
  veicolo?: VeicoloInfo | null
  aperturaSinistro?: string | null
  sharedModuleValues?: Record<string, unknown> | null
}>()

const emit = defineEmits<{
  close: []
  saved: [module: PraticaModule, allegato: Allegato | null, warning: string | null, sharedModuleValues: Record<string, unknown>]
}>()

// ── State ───────────────────────────────────────────────────────────────────
const selectedTemplateId = ref<number | null>(null)
const values             = ref<Record<string, unknown>>({})
const submitting         = ref(false)
const savingDraft        = ref(false)
const errorMsg           = ref<string | null>(null)
const warningMsg         = ref<string | null>(null)

// ── Computed ────────────────────────────────────────────────────────────────
const selectedTemplate = computed<ModuleTemplate | null>(
  () => props.templates.find(t => t.id === selectedTemplateId.value) ?? null
)

const existingModule = computed<PraticaModule | null>(
  () => props.praticaModules.find(m => m.module_template_id === selectedTemplateId.value) ?? null
)

// Un solo campo per nome univoco, anche se il template lo posiziona più volte
// nel PDF (es. "Nome Cliente" in intestazione e in calce) — required vince se
// anche una sola occorrenza lo richiede.
const uniqueFields = computed<FieldSchema[]>(() => {
  const schema = selectedTemplate.value?.fields_schema ?? []
  const byName = new Map<string, FieldSchema>()
  for (const field of schema) {
    const current = byName.get(field.name)
    if (!current) {
      byName.set(field.name, { ...field })
    } else if (field.required && !current.required) {
      current.required = true
    }
  }
  return Array.from(byName.values())
})

const dictionaryByKey = computed(() => {
  const map = new Map<string, DictEntry>()
  for (const entry of props.fieldDictionary ?? []) map.set(entry.key, entry)
  return map
})

const isFilled = (v: unknown) => v !== null && v !== undefined && v !== ''
const isChecked = (v: unknown) => v === true || v === 'true' || v === '1' || v === 1


// Valore di un campo personalizzato dell'anagrafica cliente.
function clienteCustom(key: string): unknown {
  return props.cliente?.custom_fields?.[key] ?? null
}

// Primo campo dello schema (nome o etichetta) che combacia con `pattern` e non con `exclude`,
// con valore effettivamente compilato.
function firstMatching(
  schema: SchemaField[] | undefined,
  values: Record<string, unknown> | null | undefined,
  pattern: RegExp,
  exclude?: RegExp,
): unknown {
  for (const f of schema ?? []) {
    const haystack = `${f.name} ${f.label}`.toLowerCase()
    if (!pattern.test(haystack) || (exclude && exclude.test(haystack))) continue
    const v = values?.[f.name]
    if (isFilled(v)) return v
  }
  return null
}

// Campo "a parte" che non riguarda il cliente (sinistro, controparte, perito…): non va
// precompilato con i dati anagrafici del cliente anche se si chiama "indirizzo" o "telefono".
const NON_CLIENTE = /(sinistro|luogo|evento|perito|carrozz|controparte|conducente|utilizzatore|lavoro|sede|officina|noleggio)/

// Precompilazione "intelligente" per i campi del modulo che NON sono collegati nel
// dizionario: riconosce dal nome del campo i dati del cliente (email, telefono,
// residenza, codice fiscale), la descrizione dell'accaduto e la compagnia.
function resolveHeuristicValue(name: string): unknown {
  const key = name.toLowerCase()
  const c = props.cliente

  if (/(e_?mail|^mail|_mail)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.email ?? null
  }
  if (/(telefono|^tel$|^tel_|_tel$|cellulare|^cell|recapito)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.telefono ?? null
  }
  if (/^(nome_e_cognome|nome_cognome|cognome_e_nome|cognome_nome|nominativo|nome_cliente|cliente|assicurato|contraente|cedente|nome_cedente|il_sottoscritto|sottoscritto)$/.test(key)) {
    return c?.nome ?? null
  }
  if (/(codice_fiscale|^cf$|^cf_|_cf$)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.codice_fiscale ?? null
  }
  if (/(partita_iva|p_iva|^piva)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.partita_iva ?? null
  }
  if (/^cap(_|$)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.cap ?? null
  }
  if (/(^citta|^comune_residenza|^comune$|_citta$|citta_residenza)/.test(key) && !NON_CLIENTE.test(key) && !/nascita/.test(key)) {
    return c?.citta ?? null
  }
  if (/^(provincia|prov)(_|$)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.provincia ?? null
  }
  if (/(residenza|indirizzo)/.test(key) && !NON_CLIENTE.test(key)) {
    return c?.indirizzo
      ?? firstMatching(props.clienteSchema, c?.custom_fields, /(residenza|indirizzo)/, /(sinistro|luogo|evento|domicilio)/)
  }
  if (/(descrizione|accaduto|dinamica)/.test(key)) {
    return firstMatching(props.customFieldsSchema, props.customFields, /(accaduto|dinamica|descrizione)/)
  }
  if (/compagnia/.test(key) && !/controparte/.test(key)) {
    return props.compagnia ?? null
  }

  // Veicolo del sinistro (mai quello della controparte)
  const v = props.veicolo
  if (!/controparte/.test(key)) {
    if (/targa/.test(key)) return v?.targa ?? null
    if (/^marca/.test(key)) return v?.marca ?? null
    if (/^modello/.test(key)) return v?.modello ?? null
    if (/immatricolazione/.test(key)) return v?.data_immatricolazione?.slice(0, 10) ?? null
  }

  if (/data_apertura/.test(key)) return props.aperturaSinistro?.slice(0, 10) ?? null
  if (/^data_(compilazione|firma|odierna)$/.test(key)) return oggiIso()
  if (/data_(di_)?nascita/.test(key)) return nascitaDaCodiceFiscale(c?.codice_fiscale)
  return null
}

function oggiIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// Data di nascita dal codice fiscale di una persona (16 caratteri), anche con omocodia.
function nascitaDaCodiceFiscale(cf: string | null | undefined): string | null {
  const s = (cf ?? '').toUpperCase().trim()
  if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(s)) return null
  const cifra = (ch: string) => (/\d/.test(ch) ? ch : String('LMNPQRSTUV'.indexOf(ch)))
  const aa = Number(cifra(s[6]) + cifra(s[7]))
  const mese = 'ABCDEHLMPRST'.indexOf(s[8]) + 1
  let giorno = Number(cifra(s[9]) + cifra(s[10]))
  if (giorno > 40) giorno -= 40
  const annoCorrente = new Date().getFullYear() % 100
  const anno = (aa > annoCorrente ? 1900 : 2000) + aa
  if (giorno < 1 || giorno > 31) return null
  return `${anno}-${String(mese).padStart(2, '0')}-${String(giorno).padStart(2, '0')}`
}

// Stesso dato con nomi diversi nei vari moduli (es. "descrizione_accaduto" nella denuncia,
// "dinamica_sinistro" nella cessione): si scrive una volta e passa anche agli altri.
const CONCETTI: [string, RegExp, RegExp?][] = [
  ['descrizione', /(descrizione|accaduto|dinamica)/, /(veicolo|allegat)/],
  ['data_sinistro', /data.*sinistro|sinistro.*data/, /apertura/],
  ['ora_sinistro', /ora.*sinistro|sinistro.*ora/],
  ['comune_sinistro', /(comune|luogo|citta).*sinistro/],
  ['indirizzo_sinistro', /indirizzo.*sinistro|via.*sinistro/],
  ['numero_sinistro', /(numero|n|num)_sinistro/],
]
function concetto(name: string): string | null {
  const key = name.toLowerCase()
  for (const [id, pattern, exclude] of CONCETTI) {
    if (pattern.test(key) && !(exclude && exclude.test(key))) return id
  }
  return null
}

// Risolve il valore di un campo: prima dal dizionario (anagrafica cliente, campo
// personalizzato del cliente o del sinistro), se collegato; altrimenti per nome del campo.
function resolveAutoValue(name: string): unknown {
  const entry = dictionaryByKey.value.get(name)

  // Voce del dizionario senza collegamento: si prova comunque a riconoscerla dal nome.
  if (!entry || entry.source_type === 'manual' || !entry.source_field) return resolveHeuristicValue(name)

  if (entry.source_type === 'cliente') {
    // `custom:<campo>` = campo personalizzato dell'anagrafica cliente (es. indirizzo di residenza)
    if (entry.source_field.startsWith('custom:')) {
      return clienteCustom(entry.source_field.slice('custom:'.length))
    }
    return props.cliente?.[entry.source_field as keyof Omit<ClienteInfo, 'custom_fields'>] ?? null
  }
  if (entry.source_type === 'pratica_field') {
    return props.customFields?.[entry.source_field] ?? null
  }
  return null
}

// Un campo compilato in un ALTRO modulo della stessa pratica (es. "telefono")
// ha priorità sull'autofill statico da cliente/pratica, perché riflette
// quanto l'utente ha effettivamente digitato altrove.
function resolveSharedOrAutoValue(name: string): unknown {
  const shared = props.sharedModuleValues?.[name]
  if (isFilled(shared)) return shared

  const auto = resolveAutoValue(name)
  if (isFilled(auto)) return auto

  // Stesso dato scritto in un altro modulo con un nome diverso.
  const id = concetto(name)
  if (id) {
    for (const [other, value] of Object.entries(props.sharedModuleValues ?? {})) {
      if (other !== name && isFilled(value) && concetto(other) === id) return value
    }
  }
  return null
}

// Le caselle si riprendono solo da un modulo con la stessa casella, mai indovinate dal nome.
function prefill(field: FieldSchema): unknown {
  if (field.type === 'checkbox') return props.sharedModuleValues?.[field.name] ?? null
  if (field.type === 'radio') {
    const shared = props.sharedModuleValues?.[field.name]
    return typeof shared === 'string' && field.options?.includes(shared) ? shared : null
  }
  return resolveSharedOrAutoValue(field.name)
}

// Campi effettivamente precompilati (da un altro modulo o da cliente/pratica).
const autoFilledFields = computed(() => {
  const set = new Set<string>()
  for (const field of uniqueFields.value) {
    const auto = prefill(field)
    if (auto !== null && auto !== undefined && auto !== '') set.add(field.name)
  }
  return set
})

// ── Watchers ────────────────────────────────────────────────────────────────

// When modal opens, reset state. Se c'è un solo template disponibile
// (es. pulsante "Compila Modulo" per categoria) selezionalo subito,
// evitando il passaggio manuale dal dropdown.
watch(() => props.show, (open) => {
  if (open) {
    selectedTemplateId.value = props.templates.length === 1 ? props.templates[0].id : null
    values.value             = {}
    errorMsg.value           = null
    warningMsg.value         = null
    submitting.value         = false
  }
})

// When template changes, pre-fill: 1) valore già salvato in QUESTO modulo,
// 2) valore condiviso da un ALTRO modulo della stessa pratica, 3) dizionario
// (cliente/pratica), altrimenti campo vuoto da compilare a mano.
watch(selectedTemplateId, () => {
  errorMsg.value   = null
  warningMsg.value = null
  const existing   = existingModule.value?.values ?? {}

  const newValues: Record<string, unknown> = {}
  for (const field of uniqueFields.value) {
    const savedValue = existing[field.name]
    if (savedValue !== undefined && savedValue !== '') {
      newValues[field.name] = savedValue
      continue
    }
    newValues[field.name] = prefill(field) ?? ''
  }
  values.value = newValues
})

// ── Actions ─────────────────────────────────────────────────────────────────
async function submit() {
  if (!selectedTemplateId.value) return
  errorMsg.value   = null
  warningMsg.value = null
  submitting.value = true

  try {
    const resp = await axios.post<{
      module: PraticaModule
      allegato: Allegato | null
      warning: string | null
      sharedModuleValues: Record<string, unknown>
    }>(
      route('pratica-modules.store', props.praticaId),
      {
        module_template_id: selectedTemplateId.value,
        values: values.value,
      }
    )

    if (resp.data.warning) {
      warningMsg.value = resp.data.warning
    }

    emit('saved', resp.data.module, resp.data.allegato, resp.data.warning, resp.data.sharedModuleValues)

    if (!resp.data.warning) {
      emit('close')
    }
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    errorMsg.value = msg ?? 'Errore durante la generazione del modulo.'
  } finally {
    submitting.value = false
  }
}

// Salva i valori compilati finora senza generare il PDF — pensato per moduli
// ancora parziali, da riprendere più avanti riaprendo "Compila Modulo".
async function save() {
  if (!selectedTemplateId.value) return
  errorMsg.value   = null
  warningMsg.value = null
  savingDraft.value = true

  try {
    const resp = await axios.post<{
      module: PraticaModule
      allegato: Allegato | null
      warning: string | null
      sharedModuleValues: Record<string, unknown>
    }>(
      route('pratica-modules.store', props.praticaId),
      {
        module_template_id: selectedTemplateId.value,
        values: values.value,
        generate_pdf: false,
      }
    )

    emit('saved', resp.data.module, null, null, resp.data.sharedModuleValues)
    emit('close')
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    errorMsg.value = msg ?? 'Errore durante il salvataggio della bozza.'
  } finally {
    savingDraft.value = false
  }
}
</script>
