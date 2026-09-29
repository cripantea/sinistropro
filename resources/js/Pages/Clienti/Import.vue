<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="route('clienti.index')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </Link>
        <h1 class="text-base font-semibold text-slate-800">Importa Clienti</h1>
      </div>
    </template>

    <div class="p-6 max-w-3xl space-y-6">

      <!-- Step indicator -->
      <div class="flex items-center gap-2">
        <div
          v-for="(label, i) in STEPS"
          :key="i"
          class="flex items-center gap-2"
        >
          <div :class="[
            'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold',
            step > i + 1 ? 'bg-green-500 text-white' :
            step === i + 1 ? 'bg-indigo-600 text-white' :
            'bg-slate-200 text-slate-500'
          ]">
            <svg v-if="step > i + 1" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span :class="['text-sm', step === i + 1 ? 'font-semibold text-slate-800' : 'text-slate-400']">{{ label }}</span>
          <svg v-if="i < STEPS.length - 1" class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
      </div>

      <!-- ── Step 1: Upload ─────────────────────────────── -->
      <div v-if="step === 1" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div>
          <h2 class="text-sm font-semibold text-slate-700 mb-1">Carica il file</h2>
          <p class="text-xs text-slate-500">Supportati: CSV (virgola), JSON (array di oggetti), XML. Max {{ MAX_MB }} MB.</p>
        </div>

        <!-- Format selector -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Formato file</label>
          <div class="flex gap-2">
            <button
              v-for="fmt in FORMATS"
              :key="fmt.value"
              type="button"
              @click="format = fmt.value"
              :class="[
                'flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-lg border transition',
                format === fmt.value
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'text-slate-600 border-slate-300 hover:bg-slate-50'
              ]"
            >
              {{ fmt.label }}
            </button>
          </div>
        </div>

        <!-- Drop zone -->
        <div
          class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
          :class="dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 hover:border-slate-400'"
          @dragover.prevent="dragOver = true"
          @dragleave="dragOver = false"
          @drop.prevent="onDrop"
          @click="(fileInput as HTMLInputElement).click()"
        >
          <input ref="fileInput" type="file" class="hidden" :accept="acceptAttr" @change="onFileChange" />

          <div v-if="!selectedFile">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <p class="text-sm text-slate-500">Trascina il file qui oppure <span class="text-indigo-600 font-medium">clicca per selezionarlo</span></p>
            <p class="text-xs text-slate-400 mt-1">{{ acceptAttr.toUpperCase().replace(/\./g, '').replace(/,/g, ', ') }}</p>
          </div>
          <div v-else class="flex items-center justify-center gap-3">
            <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <div class="text-left">
              <p class="text-sm font-semibold text-slate-700">{{ selectedFile.name }}</p>
              <p class="text-xs text-slate-400">{{ (selectedFile.size / 1024).toFixed(0) }} KB</p>
            </div>
            <button type="button" @click.stop="clearFile" class="p-1 text-slate-400 hover:text-red-500 ml-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>

        <div v-if="uploadError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">{{ uploadError }}</div>

        <!-- Format-specific hints -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-1.5">
          <p class="text-xs font-semibold text-slate-600">Formato atteso</p>
          <pre v-if="format === 'csv'" class="text-xs text-slate-500 overflow-x-auto">nome,telefono,email,scadenza_patente
Mario Rossi,+39333123,mario@mail.it,2027-05-20
Anna Bianchi,+39344456,,</pre>
          <pre v-else-if="format === 'json'" class="text-xs text-slate-500 overflow-x-auto">[
  { "nome": "Mario Rossi", "telefono": "+39333123", "email": "mario@mail.it" },
  { "nome": "Anna Bianchi", "telefono": "+39344456" }
]</pre>
          <pre v-else class="text-xs text-slate-500 overflow-x-auto">&lt;clienti&gt;
  &lt;cliente&gt;
    &lt;nome&gt;Mario Rossi&lt;/nome&gt;
    &lt;telefono&gt;+39333123&lt;/telefono&gt;
  &lt;/cliente&gt;
&lt;/clienti&gt;</pre>
        </div>

        <button
          type="button"
          :disabled="!selectedFile || uploading"
          @click="doPreview"
          class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition flex items-center justify-center gap-2"
        >
          <svg v-if="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          {{ uploading ? 'Analisi in corso…' : 'Analizza file →' }}
        </button>
      </div>

      <!-- ── Step 2: Mapping ────────────────────────────── -->
      <div v-else-if="step === 2" class="space-y-5">

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Mappa le colonne</h2>
              <p class="text-xs text-slate-500 mt-0.5">{{ preview.total }} righe rilevate. Assegna ogni colonna del file a un campo cliente.</p>
            </div>
            <button type="button" @click="step = 1" class="text-xs text-slate-500 hover:underline">← Cambia file</button>
          </div>

          <div class="space-y-2">
            <div
              v-for="header in preview.headers"
              :key="header"
              class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5"
            >
              <span class="w-40 text-xs font-mono text-slate-600 truncate" :title="header">{{ header }}</span>
              <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              <select
                v-model="mapping[header]"
                class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white"
              >
                <option value="">— Ignora —</option>
                <optgroup label="Campi base">
                  <option value="nome">Nome *</option>
                  <option value="telefono">Telefono</option>
                  <option value="email">Email</option>
                </optgroup>
                <optgroup v-if="schema.length > 0" label="Campi personalizzati">
                  <option v-for="f in schema" :key="f.name" :value="`custom_fields.${f.name}`">{{ f.label }}</option>
                </optgroup>
              </select>
            </div>
          </div>

          <p v-if="!hasMappedNome" class="text-xs text-amber-600 mt-3">Devi mappare almeno la colonna "Nome" per procedere.</p>
        </div>

        <!-- Preview table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wider">
            Anteprima (prime {{ preview.rows.length }} righe su {{ preview.total }})
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50">
                <tr>
                  <th v-for="h in preview.headers" :key="h" class="px-4 py-2 text-left font-medium text-slate-500 whitespace-nowrap">
                    {{ h }}
                    <span v-if="mapping[h]" class="ml-1 text-indigo-500 font-semibold">→ {{ mappingLabel(mapping[h]) }}</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(row, i) in preview.rows" :key="i" class="hover:bg-slate-50">
                  <td v-for="h in preview.headers" :key="h" class="px-4 py-2 text-slate-600 max-w-[160px] truncate">
                    {{ row[h] ?? '' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3">
          <button type="button" @click="step = 1" class="text-sm text-slate-500 hover:underline">Annulla</button>
          <button
            type="button"
            :disabled="!hasMappedNome || executing"
            @click="doExecute"
            class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition"
          >
            <svg v-if="executing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            {{ executing ? 'Importazione…' : `Importa ${preview.total} clienti →` }}
          </button>
        </div>
      </div>

      <!-- ── Step 3: Results ────────────────────────────── -->
      <div v-else-if="step === 3" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="results.errors.length ? 'bg-amber-100' : 'bg-green-100'">
            <svg v-if="!results.errors.length" class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <svg v-else class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Importazione {{ results.errors.length ? 'completata con avvisi' : 'completata' }}</h2>
            <p class="text-xs text-slate-500 mt-0.5">Il file è stato elaborato correttamente.</p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-green-700">{{ results.created }}</p>
            <p class="text-xs text-green-600 mt-1">Creati</p>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ results.updated }}</p>
            <p class="text-xs text-blue-600 mt-1">Aggiornati</p>
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-slate-600">{{ results.skipped }}</p>
            <p class="text-xs text-slate-500 mt-1">Saltati (no nome)</p>
          </div>
        </div>

        <div v-if="results.errors.length" class="bg-red-50 border border-red-200 rounded-lg p-4">
          <p class="text-xs font-semibold text-red-700 mb-2">{{ results.errors.length }} errori:</p>
          <ul class="text-xs text-red-600 space-y-1 max-h-32 overflow-y-auto">
            <li v-for="(e, i) in results.errors" :key="i">• {{ e }}</li>
          </ul>
        </div>

        <div class="flex items-center gap-3">
          <Link :href="route('clienti.index')" class="flex-1 text-center py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
            Vai alla lista clienti →
          </Link>
          <button type="button" @click="reset" class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
            Nuova importazione
          </button>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

interface FieldSchema { name: string; label: string; type: string }

const props = defineProps<{
  schema: FieldSchema[]
}>()

const MAX_MB  = 5
const STEPS   = ['Carica file', 'Mappa colonne', 'Risultati']
const FORMATS = [
  { value: 'csv',  label: 'CSV' },
  { value: 'json', label: 'JSON' },
  { value: 'xml',  label: 'XML' },
] as const

type Format = 'csv' | 'json' | 'xml'

const step         = ref(1)
const format       = ref<Format>('csv')
const selectedFile = ref<File | null>(null)
const fileInput    = ref<HTMLInputElement | null>(null)
const dragOver     = ref(false)
const uploading    = ref(false)
const executing    = ref(false)
const uploadError  = ref('')

const preview = ref<{
  file_id: string; format: string; headers: string[]; rows: Record<string, string>[]; total: number
}>({ file_id: '', format: '', headers: [], rows: [], total: 0 })

const mapping = ref<Record<string, string>>({})

const results = ref<{ created: number; updated: number; skipped: number; errors: string[] }>({
  created: 0, updated: 0, skipped: 0, errors: [],
})

const acceptAttr = computed(() => {
  return { csv: '.csv', json: '.json', xml: '.xml' }[format.value]
})

const hasMappedNome = computed(() =>
  Object.values(mapping.value).includes('nome')
)

function allDestinationFields(): Record<string, string> {
  const base: Record<string, string> = { nome: 'Nome', telefono: 'Telefono', email: 'Email' }
  for (const f of props.schema) {
    base[`custom_fields.${f.name}`] = f.label
  }
  return base
}

function mappingLabel(dest: string): string {
  return allDestinationFields()[dest] ?? dest
}

function autoMap(headers: string[]) {
  const lower = (s: string) => s.toLowerCase().trim().replace(/\s+/g, '_')
  const known: Record<string, string> = {
    nome: 'nome', name: 'nome', nominativo: 'nome',
    telefono: 'telefono', phone: 'telefono', cellulare: 'telefono', tel: 'telefono',
    email: 'email', mail: 'email',
  }
  for (const f of props.schema) {
    known[f.name] = `custom_fields.${f.name}`
    known[lower(f.label)] = `custom_fields.${f.name}`
  }

  const result: Record<string, string> = {}
  for (const h of headers) {
    result[h] = known[lower(h)] ?? ''
  }
  return result
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) setFile(file)
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) setFile(file)
}

function setFile(file: File) {
  uploadError.value = ''
  if (file.size > MAX_MB * 1024 * 1024) {
    uploadError.value = `Il file è troppo grande (max ${MAX_MB} MB).`
    return
  }
  selectedFile.value = file
  // Auto-detect format from extension
  const ext = file.name.split('.').pop()?.toLowerCase()
  if (ext === 'csv' || ext === 'json' || ext === 'xml') {
    format.value = ext as Format
  }
}

function clearFile() {
  selectedFile.value = null
  uploadError.value = ''
}

async function doPreview() {
  if (!selectedFile.value) return
  uploading.value  = true
  uploadError.value = ''

  const fd = new FormData()
  fd.append('file', selectedFile.value)
  fd.append('format', format.value)

  try {
    const res = await axios.post(route('clienti.import.preview'), fd, {
      headers: { 'Content-Type': 'multipart/form-data', 'X-Requested-With': 'XMLHttpRequest' },
    })

    preview.value = {
      file_id: res.data.file_id,
      format:  res.data.format,
      headers: res.data.headers,
      rows:    res.data.preview,
      total:   res.data.total,
    }

    mapping.value = autoMap(res.data.headers)
    step.value    = 2
  } catch (err: any) {
    uploadError.value = err.response?.data?.error ?? err.response?.data?.message ?? 'Errore durante l\'analisi del file.'
  } finally {
    uploading.value = false
  }
}

async function doExecute() {
  executing.value = true
  try {
    const res = await axios.post(route('clienti.import.execute'), {
      file_id: preview.value.file_id,
      format:  preview.value.format,
      mapping: mapping.value,
    }, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })

    results.value = res.data
    step.value    = 3
  } catch (err: any) {
    alert(err.response?.data?.error ?? 'Errore durante l\'importazione.')
  } finally {
    executing.value = false
  }
}

function reset() {
  step.value         = 1
  selectedFile.value = null
  uploadError.value  = ''
  preview.value      = { file_id: '', format: '', headers: [], rows: [], total: 0 }
  mapping.value      = {}
  results.value      = { created: 0, updated: 0, skipped: 0, errors: [] }
}
</script>
