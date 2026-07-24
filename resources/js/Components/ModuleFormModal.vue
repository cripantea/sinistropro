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
        @click.self="$emit('close')"
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

              <!-- Dynamic fields from fields_schema -->
              <template v-if="selectedTemplate && selectedTemplate.fields_schema.length > 0">
                <div
                  v-for="field in selectedTemplate.fields_schema"
                  :key="field.name"
                >
                  <label class="block text-xs font-medium text-slate-700 mb-1">
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
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

                  <input
                    v-else-if="field.type === 'date'"
                    v-model="values[field.name]"
                    type="date"
                    :required="field.required"
                    :disabled="submitting"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                  />

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
                :disabled="!selectedTemplateId || submitting"
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
                :disabled="submitting"
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

interface FieldSchema {
  name: string
  label: string
  type: 'text' | 'date' | 'number' | 'boolean' | 'select'
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

const props = defineProps<{
  show: boolean
  praticaId: number
  templates: ModuleTemplate[]
  praticaModules: PraticaModule[]
}>()

const emit = defineEmits<{
  close: []
  saved: [allegato: Allegato | null, warning: string | null]
}>()

// ── State ───────────────────────────────────────────────────────────────────
const selectedTemplateId = ref<number | null>(null)
const values             = ref<Record<string, unknown>>({})
const submitting         = ref(false)
const errorMsg           = ref<string | null>(null)
const warningMsg         = ref<string | null>(null)

// ── Computed ────────────────────────────────────────────────────────────────
const selectedTemplate = computed<ModuleTemplate | null>(
  () => props.templates.find(t => t.id === selectedTemplateId.value) ?? null
)

const existingModule = computed<PraticaModule | null>(
  () => props.praticaModules.find(m => m.module_template_id === selectedTemplateId.value) ?? null
)

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

// When template changes, pre-fill from existing module or reset fields
watch(selectedTemplateId, () => {
  errorMsg.value   = null
  warningMsg.value = null
  const schema     = selectedTemplate.value?.fields_schema ?? []
  const existing   = existingModule.value?.values ?? {}

  const newValues: Record<string, unknown> = {}
  for (const field of schema) {
    newValues[field.name] = existing[field.name] ?? ''
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

    emit('saved', resp.data.allegato, resp.data.warning)

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
</script>
