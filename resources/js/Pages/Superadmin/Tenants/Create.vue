<template>
  <SuperadminLayout title="Nuovo Tenant">

    <form @submit.prevent="submit" class="p-6 max-w-3xl space-y-8">

      <!-- ── Sezione 1: Info base ──────────────────────── -->
      <FormSection title="Informazioni generali">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="field-label">Nome azienda / tenant *</label>
            <input v-model="form.name" type="text" class="field-input" placeholder="es. Assicura S.r.l." />
            <FieldError :message="form.errors.name" />
          </div>
          <div>
            <label class="field-label">Giorni preavviso default *</label>
            <input v-model.number="form.default_notice_days" type="number" min="1" max="365" class="field-input" />
            <p class="text-xs text-slate-400 mt-1">Giorni tra un avviso e il successivo per i sinistri di questo tenant.</p>
            <FieldError :message="form.errors.default_notice_days" />
          </div>
        </div>
      </FormSection>

      <!-- ── Sezione 2: Campi personalizzati ─────────── -->
      <FormSection title="Campi personalizzati dei sinistri">
        <p class="text-sm text-slate-500 mb-4">
          Definisci i campi aggiuntivi che gli utenti di questo tenant vedranno nei sinistri. Il nome tecnico (snake_case) viene usato come chiave JSON.
        </p>

        <div class="space-y-3">
          <TransitionGroup name="list" tag="div" class="space-y-3">
            <div
              v-for="(field, i) in form.custom_fields_schema"
              :key="field._uid"
              class="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3"
            >
              <div class="grid grid-cols-3 gap-3 flex-1">
                <div>
                  <label class="field-label text-xs">Nome tecnico</label>
                  <input
                    v-model="field.name"
                    type="text"
                    class="field-input font-mono text-sm"
                    placeholder="numero_polizza"
                    @input="field.name = field.name.toLowerCase().replace(/[^a-z0-9_]/g, '_')"
                  />
                  <FieldError :message="form.errors[`custom_fields_schema.${i}.name`]" />
                </div>
                <div>
                  <label class="field-label text-xs">Etichetta visibile</label>
                  <input v-model="field.label" type="text" class="field-input" placeholder="Numero Polizza" />
                  <FieldError :message="form.errors[`custom_fields_schema.${i}.label`]" />
                </div>
                <div>
                  <label class="field-label text-xs">Tipo</label>
                  <select v-model="field.type" class="field-input">
                    <option value="text">Testo</option>
                    <option value="date">Data</option>
                    <option value="number">Numero</option>
                    <option value="boolean">Sì / No</option>
                  </select>
                </div>
              </div>
              <label class="mt-5 flex items-center gap-1.5 text-xs text-slate-600 shrink-0 cursor-pointer" title="Il campo diventa obbligatorio nel form del sinistro">
                <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                Obbligatorio
              </label>
              <button type="button" @click="removeField(i)" class="mt-5 text-red-400 hover:text-red-600 transition p-1" title="Rimuovi campo">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>

        <button
          type="button"
          @click="addField"
          class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi campo
        </button>
      </FormSection>

      <!-- ── Sezione 3: Stati personalizzati ─────────── -->
      <FormSection title="Stati dei sinistri">
        <p class="text-sm text-slate-500 mb-4">
          Definisci il workflow degli stati (es. Aperto → In lavorazione → Chiuso). Almeno uno stato "Chiuso" serve per i promemoria automatici.
        </p>

        <div class="space-y-2">
          <TransitionGroup name="list" tag="div" class="space-y-2">
            <div
              v-for="(status, i) in form.statuses"
              :key="status._uid"
              class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5"
            >
              <!-- Preview badge -->
              <span
                class="inline-block w-3 h-3 rounded-full shrink-0 border border-white shadow"
                :style="{ backgroundColor: status.color }"
              />

              <!-- Nome -->
              <input
                v-model="status.name"
                type="text"
                class="field-input flex-1 py-1.5"
                placeholder="Nome stato"
              />

              <!-- Color picker -->
              <div class="relative">
                <input
                  v-model="status.color"
                  type="color"
                  class="w-9 h-9 rounded cursor-pointer border border-slate-300 p-0.5"
                  title="Scegli colore"
                />
              </div>

              <!-- Is closed toggle -->
              <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                <input
                  v-model="status.is_closed"
                  type="checkbox"
                  class="rounded border-slate-300 text-red-500 focus:ring-red-400"
                />
                <span>Chiuso</span>
              </label>

              <!-- Initial toggle -->
              <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                <input
                  :checked="status.is_initial"
                  @change="setInitialStatus(i)"
                  type="radio"
                  name="initial-status"
                  class="border-slate-300 text-indigo-600 focus:ring-indigo-400"
                />
                <span>Iniziale</span>
              </label>

              <!-- Remove -->
              <button type="button" @click="removeStatus(i)" class="text-red-400 hover:text-red-600 transition p-1" title="Rimuovi stato">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>

        <button
          type="button"
          @click="addStatus"
          class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi stato
        </button>
      </FormSection>

      <!-- ── Submit ─────────────────────────────────── -->
      <div class="flex items-center gap-4 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
        >
          <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          Crea Tenant
        </button>
        <Link :href="route('superadmin.tenants.index')" class="text-sm text-slate-500 hover:underline">Annulla</Link>
      </div>

    </form>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import FormSection from '@/Components/Superadmin/FormSection.vue'
import FieldError from '@/Components/Superadmin/FieldError.vue'

interface CustomField  { _uid: number; name: string; label: string; type: 'text' | 'date' | 'number' | 'boolean'; required: boolean }
interface StatusDraft  { _uid: number; name: string; color: string; is_closed: boolean; is_initial: boolean }

let _uid = 0
const uid = () => ++_uid

const PALETTE = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#6B7280']

const form = useForm({
  name:                 '',
  default_notice_days:  30,
  custom_fields_schema: [] as CustomField[],
  statuses:             [] as StatusDraft[],
})

function addField() {
  form.custom_fields_schema.push({ _uid: uid(), name: '', label: '', type: 'text', required: false })
}

function removeField(i: number) {
  form.custom_fields_schema.splice(i, 1)
}

function addStatus() {
  const color = PALETTE[form.statuses.length % PALETTE.length]
  const isFirst = form.statuses.length === 0
  form.statuses.push({ _uid: uid(), name: '', color, is_closed: false, is_initial: isFirst })
}

function removeStatus(i: number) {
  form.statuses.splice(i, 1)
}

function setInitialStatus(i: number) {
  form.statuses.forEach((s, idx) => { s.is_initial = idx === i })
}

function submit() {
  // Rimuove _uid prima dell'invio: è un helper locale, non un campo DB.
  form
    .transform((data) => ({
      ...data,
      custom_fields_schema: data.custom_fields_schema.map(({ _uid: _u, ...f }: CustomField) => f),
      statuses:             data.statuses.map(({ _uid: _u, ...s }: StatusDraft) => s),
    }))
    .post(route('superadmin.tenants.store'))
}
</script>

<style scoped>
.field-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.field-input {
  @apply block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
         focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none
         bg-white placeholder-slate-400;
}
.list-move, .list-enter-active, .list-leave-active { transition: all 0.2s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateY(-6px); }
.list-leave-active { position: absolute; }
</style>
