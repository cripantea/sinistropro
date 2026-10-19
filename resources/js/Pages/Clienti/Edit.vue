<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <TastoIndietro :href="indietro">{{ etichettaIndietro }}</TastoIndietro>
        <h1 class="text-base font-semibold text-slate-800">
          {{ cliente ? `Modifica ${cliente.nome}` : 'Nuovo Cliente' }}
        </h1>
      </div>
    </template>

    <div class="p-6 max-w-3xl space-y-5">
      <form @submit.prevent="submit" class="space-y-5">

        <!-- Errori globali -->
        <div v-if="form.errors && Object.keys(form.errors).length" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
          </ul>
        </div>

        <!-- Dati contatto -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati contatto</h2>

          <Campo label="Cognome e nome" required :error="form.errors.nome">
            <input v-model="form.nome" type="text" :class="inputClass(form.errors.nome)" placeholder="Es. Rossi Mario" />
          </Campo>

          <div class="grid sm:grid-cols-2 gap-4">
            <Campo label="Telefono" :error="form.errors.telefono">
              <input v-model="form.telefono" type="tel" :class="inputClass(form.errors.telefono)" placeholder="+39 333 1234567" />
            </Campo>
            <Campo label="Email" :error="form.errors.email">
              <input v-model="form.email" type="email" :class="inputClass(form.errors.email)" placeholder="mario@esempio.it" />
            </Campo>
          </div>
        </div>

        <!-- Anagrafica -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Anagrafica</h2>

          <div class="grid sm:grid-cols-3 gap-4">
            <Campo label="Codice fiscale" :error="form.errors.codice_fiscale">
              <input v-model="form.codice_fiscale" type="text" maxlength="16" :class="[inputClass(form.errors.codice_fiscale), 'uppercase font-mono']" />
            </Campo>
            <Campo label="Partita IVA" :error="form.errors.partita_iva">
              <input v-model="form.partita_iva" type="text" maxlength="20" :class="[inputClass(form.errors.partita_iva), 'font-mono']" />
            </Campo>
            <Campo label="Codice cliente (gestionale)" :error="form.errors.codice_esterno" hint="Serve all'import per riconoscere il cliente.">
              <input v-model="form.codice_esterno" type="text" maxlength="50" :class="[inputClass(form.errors.codice_esterno), 'font-mono']" />
            </Campo>
          </div>

          <Campo label="Indirizzo" :error="form.errors.indirizzo">
            <input v-model="form.indirizzo" type="text" :class="inputClass(form.errors.indirizzo)" placeholder="Via Roma, 1" />
          </Campo>

          <div class="grid grid-cols-[7rem_1fr_5rem] gap-4">
            <Campo label="CAP" :error="form.errors.cap">
              <input v-model="form.cap" type="text" maxlength="10" :class="inputClass(form.errors.cap)" />
            </Campo>
            <Campo label="Città" :error="form.errors.citta">
              <input v-model="form.citta" type="text" :class="inputClass(form.errors.citta)" />
            </Campo>
            <Campo label="Prov." :error="form.errors.provincia">
              <input v-model="form.provincia" type="text" maxlength="5" :class="[inputClass(form.errors.provincia), 'uppercase']" />
            </Campo>
          </div>
        </div>

        <!-- Campi personalizzati -->
        <div v-if="schema.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati aggiuntivi</h2>

          <div v-for="field in schema" :key="field.name">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              {{ field.label }}
              <span v-if="field.required" class="text-red-500">*</span>
            </label>

            <input
              v-if="field.type === 'text' || field.type === 'number'"
              v-model="form.custom_fields[field.name]"
              :type="field.type"
              :class="inputClass()"
            />
            <input
              v-else-if="field.type === 'date'"
              v-model="form.custom_fields[field.name]"
              type="date"
              :class="inputClass()"
            />
            <select
              v-else-if="field.type === 'select'"
              v-model="form.custom_fields[field.name]"
              :class="[inputClass(), 'bg-white']"
            >
              <option value="">— Seleziona —</option>
              <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2">
              <input
                :id="`cf_${field.name}`"
                v-model="form.custom_fields[field.name]"
                type="checkbox"
                :true-value="'1'"
                :false-value="'0'"
                class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500"
              />
              <label :for="`cf_${field.name}`" class="text-sm text-slate-700">{{ field.label }}</label>
            </div>
          </div>
        </div>

        <!-- Azioni -->
        <div class="flex items-center justify-end gap-3">
          <Link :href="indietro" class="inline-flex items-center h-10 px-4 rounded-lg border border-gray-300 bg-white text-[15px] font-semibold text-gray-800 hover:bg-gray-50 transition">
            Annulla
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition"
          >
            <span v-if="form.processing">Salvataggio…</span>
            <span v-else>{{ cliente ? 'Salva modifiche' : 'Crea cliente' }}</span>
          </button>
        </div>
      </form>

      <!-- Veicoli: si salvano subito, uno per uno -->
      <div v-if="cliente" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
          <div>
            <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Veicoli ({{ veicoli.length }})</h2>
            <p class="text-xs text-slate-400 mt-0.5">Le modifiche ai veicoli si salvano subito.</p>
          </div>
          <button v-if="editing !== 'nuovo'" type="button" @click="apri('nuovo')" class="text-sm font-medium text-green-700 hover:underline">+ Aggiungi veicolo</button>
        </div>

        <input
          v-if="veicoli.length > 6"
          v-model="filtro"
          type="text"
          placeholder="Filtra per targa, telaio, marca o modello…"
          class="block w-full border-0 border-b border-slate-100 px-5 py-2.5 text-sm focus:ring-0 outline-none"
        />

        <ul class="divide-y divide-slate-100 max-h-[32rem] overflow-y-auto">
          <!-- Nuovo veicolo -->
          <li v-if="editing === 'nuovo'" class="px-5 py-4 bg-slate-50">
            <VeicoloForm v-model="bozza" :errori="erroriVeicolo" :saving="savingVeicolo" @salva="salvaVeicolo" @annulla="chiudi" />
          </li>

          <li v-for="v in veicoliFiltrati" :key="v.id" :class="editing === v.id ? 'px-5 py-4 bg-slate-50' : 'px-5 py-3'">
            <VeicoloForm v-if="editing === v.id" v-model="bozza" :errori="erroriVeicolo" :saving="savingVeicolo" @salva="salvaVeicolo" @annulla="chiudi" />
            <div v-else class="flex items-center gap-4">
              <span v-if="v.targa" class="font-mono text-sm font-semibold text-slate-800 w-24 shrink-0">{{ v.targa }}</span>
              <span v-else class="font-mono text-xs text-slate-400 w-24 shrink-0">senza targa</span>
              <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-700 truncate">{{ [v.marca, v.modello].filter(Boolean).join(' ') || '—' }}</p>
                <p class="text-xs text-slate-400 truncate">
                  {{ [v.telaio, v.data_immatricolazione && `imm. ${formatDate(v.data_immatricolazione)}`, v.scadenza_revisione && `revisione ${formatDate(v.scadenza_revisione)}`, v.km && `${v.km.toLocaleString('it-IT')} km`].filter(Boolean).join(' · ') }}
                </p>
              </div>
              <button type="button" @click="apri(v.id)" class="text-xs text-indigo-600 hover:underline shrink-0">Modifica</button>
              <button type="button" @click="elimina(v)" class="text-xs text-slate-400 hover:text-red-600 shrink-0">Elimina</button>
            </div>
          </li>

          <li v-if="!veicoli.length && editing !== 'nuovo'" class="px-5 py-8 text-center text-sm text-slate-400">Nessun veicolo.</li>
        </ul>
        <p v-if="erroreLista" class="px-5 py-2 text-xs text-red-600 border-t border-slate-100">{{ erroreLista }}</p>
      </div>
      <p v-else class="text-xs text-slate-400">Potrai aggiungere i veicoli dopo aver creato il cliente.</p>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, ref, type PropType } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TastoIndietro from '@/Components/TastoIndietro.vue'

interface FieldSchema { name: string; label: string; type: string; required?: boolean; options?: string[] }
interface Veicolo {
  id: number; targa: string | null; telaio: string | null; marca: string | null; modello: string | null
  descrizione: string | null; data_immatricolazione: string | null; scadenza_revisione: string | null; km: number | null
}
interface Cliente {
  id: number; nome: string; telefono: string | null; email: string | null
  codice_esterno: string | null; codice_fiscale: string | null; partita_iva: string | null
  indirizzo: string | null; cap: string | null; citta: string | null; provincia: string | null
  custom_fields: Record<string, string> | null
  veicoli?: Veicolo[]
}
type Bozza = Record<'targa' | 'telaio' | 'marca' | 'modello' | 'descrizione' | 'data_immatricolazione' | 'scadenza_revisione' | 'km', string>

const props = defineProps<{
  cliente: Cliente | null
  schema: FieldSchema[]
  ritorno?: string | null
}>()

// Indietro, Annulla e Salva riportano da dove si è arrivati (es. il sinistro).
const indietro = computed(() => props.ritorno ?? (props.cliente ? route('clienti.show', props.cliente.id) : route('clienti.index')))
const etichettaIndietro = computed(() => {
  if (props.ritorno?.startsWith('/pratiche')) return props.ritorno.startsWith('/pratiche/kanban') ? 'Torna alla Board' : 'Torna al sinistro'
  if (props.ritorno?.startsWith('/clienti?') || props.ritorno === '/clienti' || !props.cliente) return 'Torna ai clienti'
  return 'Torna al cliente'
})

const initialCustomFields: Record<string, string> = {}
for (const field of props.schema) {
  initialCustomFields[field.name] = props.cliente?.custom_fields?.[field.name] ?? ''
}

const c = props.cliente
const form = useForm({
  nome:           c?.nome ?? '',
  telefono:       c?.telefono ?? '',
  email:          c?.email ?? '',
  codice_fiscale: c?.codice_fiscale ?? '',
  partita_iva:    c?.partita_iva ?? '',
  codice_esterno: c?.codice_esterno ?? '',
  indirizzo:      c?.indirizzo ?? '',
  cap:            c?.cap ?? '',
  citta:          c?.citta ?? '',
  provincia:      c?.provincia ?? '',
  custom_fields:  initialCustomFields,
  ritorno:        props.ritorno ?? null,
})

function submit() {
  if (props.cliente) {
    form.put(route('clienti.update', props.cliente.id))
  } else {
    form.post(route('clienti.store'))
  }
}

function inputClass(error?: string) {
  return ['w-full text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none', error ? 'border-red-400' : 'border-slate-300']
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('it-IT')
}

// ── Veicoli ────────────────────────────────────────────────────────────────

const veicoli = ref<Veicolo[]>([...(props.cliente?.veicoli ?? [])])
const filtro = ref('')
const editing = ref<number | 'nuovo' | null>(null)
const bozza = ref<Bozza>(vuota())
const erroriVeicolo = ref<Record<string, string>>({})
const erroreLista = ref('')
const savingVeicolo = ref(false)

const veicoliFiltrati = computed(() => {
  const f = filtro.value.trim().toLowerCase().replace(/\s+/g, '')
  if (!f) return veicoli.value
  return veicoli.value.filter(v => [v.targa, v.telaio, v.marca, v.modello].some(x => (x ?? '').toLowerCase().replace(/\s+/g, '').includes(f)))
})

function vuota(): Bozza {
  return { targa: '', telaio: '', marca: '', modello: '', descrizione: '', data_immatricolazione: '', scadenza_revisione: '', km: '' }
}

function apri(id: number | 'nuovo') {
  erroriVeicolo.value = {}
  erroreLista.value = ''
  const v = id === 'nuovo' ? null : veicoli.value.find(x => x.id === id)
  bozza.value = v
    ? Object.fromEntries(Object.keys(vuota()).map(k => [k, String((v as any)[k] ?? '')])) as Bozza
    : vuota()
  editing.value = id
}

function chiudi() {
  editing.value = null
  erroriVeicolo.value = {}
}

async function salvaVeicolo() {
  if (!props.cliente || editing.value === null) return
  savingVeicolo.value = true
  erroriVeicolo.value = {}
  const payload = Object.fromEntries(Object.entries(bozza.value).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]))
  try {
    if (editing.value === 'nuovo') {
      const { data } = await axios.post(route('clienti.veicoli.store', props.cliente.id), payload)
      veicoli.value.unshift(data.veicolo)
    } else {
      const { data } = await axios.put(route('veicoli.update', editing.value), payload)
      veicoli.value = veicoli.value.map(v => v.id === data.veicolo.id ? data.veicolo : v)
    }
    chiudi()
  } catch (err: any) {
    const errors = err.response?.data?.errors ?? {}
    erroriVeicolo.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, (v as string[])[0]]))
    if (!Object.keys(erroriVeicolo.value).length) erroriVeicolo.value = { _: err.response?.data?.message ?? 'Errore durante il salvataggio.' }
  } finally {
    savingVeicolo.value = false
  }
}

async function elimina(v: Veicolo) {
  if (!confirm(`Eliminare il veicolo ${v.targa ?? v.telaio ?? ''}?`)) return
  erroreLista.value = ''
  try {
    await axios.delete(route('veicoli.destroy', v.id))
    veicoli.value = veicoli.value.filter(x => x.id !== v.id)
  } catch (err: any) {
    erroreLista.value = err.response?.data?.message ?? 'Impossibile eliminare il veicolo.'
  }
}

// ── Componenti locali ──────────────────────────────────────────────────────

const Campo = defineComponent({
  props: { label: { type: String, required: true }, required: Boolean, error: String, hint: String },
  setup: (p, { slots }) => () => h('div', [
    h('label', { class: 'block text-xs font-medium text-slate-600 mb-1' }, [p.label, p.required ? h('span', { class: 'text-red-500' }, ' *') : null]),
    slots.default?.(),
    p.error ? h('p', { class: 'text-xs text-red-500 mt-1' }, p.error) : p.hint ? h('p', { class: 'text-[11px] text-slate-400 mt-1' }, p.hint) : null,
  ]),
})

const CAMPI_VEICOLO: { key: keyof Bozza; label: string; type?: string; mono?: boolean; span?: number }[] = [
  { key: 'targa', label: 'Targa', mono: true },
  { key: 'telaio', label: 'Telaio', mono: true, span: 2 },
  { key: 'marca', label: 'Marca' },
  { key: 'modello', label: 'Modello' },
  { key: 'km', label: 'Km', type: 'number' },
  { key: 'descrizione', label: 'Descrizione', span: 3 },
  { key: 'data_immatricolazione', label: 'Immatricolazione', type: 'date' },
  { key: 'scadenza_revisione', label: 'Scadenza revisione', type: 'date' },
]

const VeicoloForm = defineComponent({
  props: {
    modelValue: { type: Object as PropType<Bozza>, required: true },
    errori: { type: Object as PropType<Record<string, string>>, required: true },
    saving: Boolean,
  },
  emits: ['update:modelValue', 'salva', 'annulla'],
  setup: (p, { emit }) => () => h('div', { class: 'space-y-3' }, [
    h('div', { class: 'grid grid-cols-3 gap-3' }, CAMPI_VEICOLO.map(f => h('div', { class: f.span === 3 ? 'col-span-3' : f.span === 2 ? 'col-span-2' : '' }, [
      h('label', { class: 'block text-[11px] font-medium text-slate-500 mb-0.5' }, f.label),
      h('input', {
        type: f.type ?? 'text',
        value: p.modelValue[f.key],
        onInput: (e: Event) => emit('update:modelValue', { ...p.modelValue, [f.key]: (e.target as HTMLInputElement).value }),
        onKeydown: (e: KeyboardEvent) => { if (e.key === 'Enter') { e.preventDefault(); emit('salva') } },
        class: ['w-full text-sm border rounded-md px-2.5 py-1.5 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none',
          f.mono ? 'font-mono uppercase' : '', p.errori[f.key] ? 'border-red-400' : 'border-slate-300'],
      }),
      p.errori[f.key] ? h('p', { class: 'text-xs text-red-500 mt-0.5' }, p.errori[f.key]) : null,
    ]))),
    p.errori._ ? h('p', { class: 'text-xs text-red-600' }, p.errori._) : null,
    h('div', { class: 'flex items-center gap-3' }, [
      h('button', { type: 'button', disabled: p.saving, onClick: () => emit('salva'), class: 'text-sm font-semibold text-white bg-indigo-600 px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-60' }, p.saving ? 'Salvataggio…' : 'Salva veicolo'),
      h('button', { type: 'button', onClick: () => emit('annulla'), class: 'text-sm text-slate-500 hover:underline' }, 'Annulla'),
    ]),
  ]),
})
</script>
