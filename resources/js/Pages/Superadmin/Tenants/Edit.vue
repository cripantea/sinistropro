<template>
  <SuperadminLayout :title="`Modifica Tenant: ${tenant.name}`">

    <!-- ── Tab bar ──────────────────────────────────────────────────────────── -->
    <div class="px-6 pt-6">
      <nav class="flex gap-1 bg-slate-100 rounded-xl p-1 max-w-3xl">
        <button
          v-for="tab in TABS"
          :key="tab.id"
          type="button"
          @click="activeTab = tab.id"
          :class="[
            'flex-1 text-sm font-medium py-2 px-4 rounded-lg transition',
            activeTab === tab.id
              ? 'bg-white text-slate-900 shadow-sm'
              : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/60'
          ]"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- ── Tab 1: Configurazione ─────────────────────────────────────────────── -->
    <form v-show="activeTab === 'config'" @submit.prevent="submit" class="p-6 max-w-3xl space-y-8">

      <FormSection title="Informazioni generali">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="field-label">Nome azienda / tenant *</label>
            <input v-model="form.name" type="text" class="field-input" />
            <FieldError :message="form.errors.name" />
          </div>
          <div>
            <label class="field-label">Giorni preavviso default *</label>
            <input v-model.number="form.default_notice_days" type="number" min="1" max="365" class="field-input" />
            <FieldError :message="form.errors.default_notice_days" />
          </div>
        </div>
      </FormSection>

      <FormSection title="Campi personalizzati delle pratiche">
        <div class="space-y-3">
          <TransitionGroup name="list" tag="div" class="space-y-3">
            <div v-for="(field, i) in form.custom_fields_schema" :key="field._uid" class="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3">
              <div class="grid grid-cols-3 gap-3 flex-1">
                <div>
                  <label class="field-label text-xs">Nome tecnico</label>
                  <input v-model="field.name" type="text" class="field-input font-mono text-sm" @input="field.name = field.name.toLowerCase().replace(/[^a-z0-9_]/g, '_')" />
                  <FieldError :message="form.errors[`custom_fields_schema.${i}.name`]" />
                </div>
                <div>
                  <label class="field-label text-xs">Etichetta</label>
                  <input v-model="field.label" type="text" class="field-input" />
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
              <button type="button" @click="removeField(i)" class="mt-5 text-red-400 hover:text-red-600 transition p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>
        <button type="button" @click="addField" class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi campo
        </button>
      </FormSection>

      <FormSection title="Stati delle pratiche">
        <div class="space-y-2">
          <TransitionGroup name="list" tag="div" class="space-y-2">
            <div v-for="(status, i) in form.statuses" :key="status._uid" class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5">
              <span class="inline-block w-3 h-3 rounded-full shrink-0 border border-white shadow" :style="{ backgroundColor: status.color }" />
              <input v-model="status.name" type="text" class="field-input flex-1 py-1.5" placeholder="Nome stato" />
              <input v-model="status.color" type="color" class="w-9 h-9 rounded cursor-pointer border border-slate-300 p-0.5" />
              <label class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap cursor-pointer select-none">
                <input v-model="status.is_closed" type="checkbox" class="rounded border-slate-300 text-red-500 focus:ring-red-400" />
                Chiuso
              </label>
              <button type="button" @click="removeStatus(i)" class="text-red-400 hover:text-red-600 transition p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </TransitionGroup>
        </div>
        <button type="button" @click="addStatus" class="mt-4 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Aggiungi stato
        </button>
      </FormSection>

      <div class="flex items-center gap-4 pt-2">
        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60">
          <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          Salva modifiche
        </button>
        <Link :href="route('superadmin.tenants.index')" class="text-sm text-slate-500 hover:underline">Annulla</Link>
      </div>

    </form>

    <!-- ── Tab 2: Categorie Documenti ─────────────────────────────────────────── -->
    <form v-show="activeTab === 'categories'" @submit.prevent="submitCategories" class="p-6 max-w-3xl mt-4">
      <FormSection title="Categorie Documenti">
        <p class="text-xs text-slate-500 mb-4">Abilita le categorie disponibili per questo tenant e imposta il limite di dimensione per ogni categoria.</p>

        <div class="space-y-2">
          <div
            v-for="cat in catForm.categories"
            :key="cat.id"
            class="flex items-center gap-4 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3"
          >
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" v-model="cat.is_enabled" class="sr-only peer" />
              <div class="w-9 h-5 bg-slate-300 peer-checked:bg-indigo-600 rounded-full transition peer-focus:ring-2 peer-focus:ring-indigo-400 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
            </label>

            <span class="flex-1 text-sm font-medium text-slate-700" :class="!cat.is_enabled ? 'opacity-40' : ''">{{ cat.name }}</span>

            <div class="flex items-center gap-1.5 shrink-0" :class="!cat.is_enabled ? 'opacity-40' : ''">
              <label class="text-xs text-slate-500 shrink-0">Max</label>
              <input
                v-model.number="cat.max_file_size_mb"
                type="number"
                min="1"
                max="500"
                :disabled="!cat.is_enabled"
                class="w-16 text-sm text-center border border-slate-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-indigo-500 outline-none disabled:bg-slate-100 disabled:cursor-not-allowed"
              />
              <span class="text-xs text-slate-400 shrink-0">MB</span>
            </div>
          </div>
        </div>

        <button
          type="submit"
          :disabled="catForm.processing"
          class="mt-5 inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
        >
          <svg v-if="catForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
          Salva categorie
        </button>
      </FormSection>
    </form>

    <!-- ── Tab 3: Automazioni Workflow ───────────────────────────────────────── -->
    <div v-show="activeTab === 'automations'" class="p-6 max-w-5xl">

      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="text-base font-semibold text-slate-900">Automazioni Workflow</h3>
          <p class="text-xs text-slate-500 mt-0.5">Azioni automatiche eseguite al cambio di stato delle pratiche.</p>
        </div>
        <button
          type="button"
          @click="openCreateAuto"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Nuova Automazione
        </button>
      </div>

      <!-- Table -->
      <div v-if="automations.length > 0" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Nome</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Stato Trigger</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Canale</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Destinatario</th>
              <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Attivo</th>
              <th class="w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="auto in automations" :key="auto.id" class="hover:bg-slate-50/70 transition">
              <td class="px-4 py-3 font-medium text-slate-800">{{ auto.name }}</td>
              <td class="px-4 py-3">
                <span v-if="auto.status" class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                  <span class="inline-block w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: auto.status.color }"></span>
                  {{ auto.status.name }}
                </span>
                <span v-else class="text-xs text-slate-400 italic">Qualsiasi</span>
              </td>
              <td class="px-4 py-3">
                <span :class="channelBadgeClass(auto.channel)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ channelLabel(auto.channel) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                  {{ recipientLabel(auto.recipient) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button
                  type="button"
                  @click="toggleIsActive(auto)"
                  :class="[
                    'relative inline-flex h-5 w-9 items-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1',
                    auto.is_active ? 'bg-indigo-600' : 'bg-slate-300'
                  ]"
                  :title="auto.is_active ? 'Disattiva' : 'Attiva'"
                >
                  <span
                    :class="[
                      'inline-block h-4 w-4 transform rounded-full bg-white shadow transition',
                      auto.is_active ? 'translate-x-4' : 'translate-x-0.5'
                    ]"
                  ></span>
                </button>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 justify-end">
                  <button
                    type="button"
                    @click="openEditAuto(auto)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition"
                    title="Modifica"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button
                    type="button"
                    @click="deleteAutomation(auto)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                    title="Elimina"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-xl px-8 py-14 text-center">
        <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <p class="text-sm text-slate-500 font-medium">Nessuna automazione configurata</p>
        <p class="text-xs text-slate-400 mt-1">Clicca "Nuova Automazione" per aggiungerne una.</p>
      </div>
    </div>

    <!-- ── Automation Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="autoModalOpen"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
          @click.self="closeAutoModal"
        >
          <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0 scale-95"
          >
            <div v-if="autoModalOpen" class="bg-white rounded-2xl shadow-2xl w-full max-w-xl" @click.stop>

              <!-- Header -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-900">
                  {{ editingAutomation ? 'Modifica Automazione' : 'Nuova Automazione' }}
                </h3>
                <button type="button" @click="closeAutoModal" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Body -->
              <form @submit.prevent="submitAuto" class="px-6 py-5 space-y-4 max-h-[68vh] overflow-y-auto">

                <!-- Nome -->
                <div>
                  <label class="field-label">Nome automazione *</label>
                  <input
                    v-model="autoForm.name"
                    type="text"
                    class="field-input"
                    placeholder="Es: Email conferma ricezione"
                  />
                  <FieldError :message="autoForm.errors.name" />
                </div>

                <!-- Stato trigger -->
                <div>
                  <label class="field-label">Stato trigger</label>
                  <select v-model="autoForm.tenant_status_id" class="field-input">
                    <option :value="null">— Qualsiasi stato —</option>
                    <option v-for="s in tenant.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <FieldError :message="autoForm.errors.tenant_status_id" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <!-- Canale -->
                  <div>
                    <label class="field-label">Canale *</label>
                    <select v-model="autoForm.channel" class="field-input">
                      <option value="email">Email</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="both">Email + WhatsApp</option>
                    </select>
                    <FieldError :message="autoForm.errors.channel" />
                  </div>

                  <!-- Destinatario -->
                  <div>
                    <label class="field-label">Destinatario *</label>
                    <select v-model="autoForm.recipient" class="field-input">
                      <option value="cliente">Cliente</option>
                      <option value="perito">Perito</option>
                      <option value="gestore">Gestore</option>
                    </select>
                    <FieldError :message="autoForm.errors.recipient" />
                  </div>
                </div>

                <!-- Messaggio template -->
                <div>
                  <label class="field-label">Messaggio *</label>
                  <textarea
                    v-model="autoForm.message_template"
                    rows="5"
                    class="field-input resize-none"
                    placeholder="Gentile {nome_cliente}, la pratica #{numero_pratica} è passata allo stato {stato_corrente}."
                  ></textarea>
                  <p class="text-xs text-slate-400 mt-1">
                    Variabili:
                    <code class="bg-slate-100 px-1 rounded">{nome_cliente}</code>
                    <code class="bg-slate-100 px-1 rounded ml-1">{numero_pratica}</code>
                    <code class="bg-slate-100 px-1 rounded ml-1">{stato_corrente}</code>
                    <code class="bg-slate-100 px-1 rounded ml-1">{link_documenti}</code>
                  </p>
                  <FieldError :message="autoForm.errors.message_template" />
                </div>

                <!-- Categorie documenti -->
                <div v-if="allDocCategories.length > 0">
                  <label class="field-label">Categorie documenti da allegare</label>
                  <p class="text-xs text-slate-400 mb-2">
                    Genera link S3 per i documenti di queste categorie, inclusi come <code class="bg-slate-100 px-1 rounded">{link_documenti}</code>.
                  </p>
                  <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-2 max-h-36 overflow-y-auto">
                    <label
                      v-for="cat in allDocCategories"
                      :key="cat.id"
                      class="flex items-center gap-2 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        :checked="autoForm.document_category_ids.includes(cat.id)"
                        @change="(e) => toggleDocCategory(cat.id, (e.target as HTMLInputElement).checked)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      <span class="text-sm text-slate-700">{{ cat.name }}</span>
                    </label>
                  </div>
                </div>

                <!-- Attiva -->
                <div class="flex items-center gap-3 pt-1">
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="autoForm.is_active" class="sr-only peer" />
                    <div class="w-9 h-5 bg-slate-300 peer-checked:bg-indigo-600 rounded-full transition peer-focus:ring-2 peer-focus:ring-indigo-400 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                  </label>
                  <span class="text-sm font-medium text-slate-700">Automazione attiva</span>
                </div>

              </form>

              <!-- Footer -->
              <div class="flex items-center gap-3 px-6 py-4 border-t border-slate-100">
                <button
                  type="button"
                  :disabled="autoForm.processing"
                  @click="submitAuto"
                  class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="autoForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ autoForm.processing ? 'Salvataggio...' : 'Salva automazione' }}
                </button>
                <button
                  type="button"
                  :disabled="autoForm.processing"
                  @click="closeAutoModal"
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

  </SuperadminLayout>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm, usePage, Link, router } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import FormSection from '@/Components/Superadmin/FormSection.vue'
import FieldError from '@/Components/Superadmin/FieldError.vue'

// ── Interfaces ───────────────────────────────────────────────────────────────

interface CustomField   { _uid: number; name: string; label: string; type: string }
interface StatusRow     { _uid: number; id?: number; name: string; color: string; is_closed: boolean; order: number }
interface CategoryConfig { id: number; name: string; description: string | null; is_enabled: boolean; max_file_size_mb: number }
interface DocCategory   { id: number; name: string }

interface Automation {
  id: number
  name: string
  tenant_status_id: number | null
  channel: string
  recipient: string
  message_template: string
  is_active: boolean
  document_category_ids: number[]
  status: { id: number; name: string; color: string } | null
}

interface TenantFull {
  id: number
  name: string
  settings: { default_notice_days: number; custom_fields_schema: Omit<CustomField, '_uid'>[] } | null
  statuses: Omit<StatusRow, '_uid'>[]
}

// ── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
  tenant: TenantFull
  categoriesConfig: CategoryConfig[]
  automations: Automation[]
  allDocCategories: DocCategory[]
}>()

// ── Tab navigation ───────────────────────────────────────────────────────────

const TABS = [
  { id: 'config',      label: 'Configurazione' },
  { id: 'categories',  label: 'Categorie Documenti' },
  { id: 'automations', label: 'Automazioni Workflow' },
] as const

type TabId = typeof TABS[number]['id']

function tabFromUrl(url: string): TabId {
  const search = url.includes('?') ? url.split('?')[1] : ''
  const tab = new URLSearchParams(search).get('tab')
  return (['config', 'categories', 'automations'] as const).includes(tab as TabId)
    ? (tab as TabId)
    : 'config'
}

const page      = usePage()
const activeTab = ref<TabId>(tabFromUrl(page.url))

watch(() => page.url, (url) => { activeTab.value = tabFromUrl(url) })

// ── Configurazione form ──────────────────────────────────────────────────────

let _uid = 0
const uid = () => ++_uid

const form = useForm({
  name:                 props.tenant.name,
  default_notice_days:  props.tenant.settings?.default_notice_days ?? 30,
  custom_fields_schema: (props.tenant.settings?.custom_fields_schema ?? []).map(f => ({ ...f, _uid: uid() })) as CustomField[],
  statuses:             (props.tenant.statuses ?? []).map(s => ({ ...s, _uid: uid() })) as StatusRow[],
})

const PALETTE = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#6B7280']

function addField()         { form.custom_fields_schema.push({ _uid: uid(), name: '', label: '', type: 'text' }) }
function removeField(i: number) { form.custom_fields_schema.splice(i, 1) }

function addStatus() {
  const color = PALETTE[form.statuses.length % PALETTE.length]
  form.statuses.push({ _uid: uid(), name: '', color, is_closed: false, order: form.statuses.length })
}
function removeStatus(i: number) { form.statuses.splice(i, 1) }

function submit() {
  form
    .transform((data) => ({
      ...data,
      custom_fields_schema: data.custom_fields_schema.map(({ _uid: _u, ...f }: CustomField) => f),
      statuses:             data.statuses.map(({ _uid: _u, ...s }: StatusRow) => s),
    }))
    .put(route('superadmin.tenants.update', props.tenant.id))
}

// ── Categorie config ─────────────────────────────────────────────────────────

const catForm = useForm({
  categories: props.categoriesConfig.map(c => ({ ...c })),
})

function submitCategories() {
  catForm.post(route('superadmin.tenants.document-categories.sync', props.tenant.id))
}

// ── Automazioni ──────────────────────────────────────────────────────────────

const autoModalOpen      = ref(false)
const editingAutomation  = ref<Automation | null>(null)

const autoForm = useForm({
  name:                   '' as string,
  tenant_status_id:       null as number | null,
  channel:                'email' as string,
  recipient:              'cliente' as string,
  message_template:       '' as string,
  document_category_ids:  [] as number[],
  is_active:              true as boolean,
})

function openCreateAuto() {
  editingAutomation.value = null
  autoForm.reset()
  autoForm.document_category_ids = []
  autoModalOpen.value = true
}

function openEditAuto(auto: Automation) {
  editingAutomation.value = auto
  autoForm.name                  = auto.name
  autoForm.tenant_status_id      = auto.tenant_status_id
  autoForm.channel               = auto.channel
  autoForm.recipient             = auto.recipient
  autoForm.message_template      = auto.message_template
  autoForm.document_category_ids = [...auto.document_category_ids]
  autoForm.is_active             = auto.is_active
  autoModalOpen.value = true
}

function closeAutoModal() {
  autoModalOpen.value = false
  editingAutomation.value = null
  autoForm.reset()
  autoForm.document_category_ids = []
}

function toggleDocCategory(id: number, checked: boolean) {
  if (checked) {
    if (!autoForm.document_category_ids.includes(id)) {
      autoForm.document_category_ids = [...autoForm.document_category_ids, id]
    }
  } else {
    autoForm.document_category_ids = autoForm.document_category_ids.filter(x => x !== id)
  }
}

function submitAuto() {
  if (editingAutomation.value) {
    autoForm.patch(
      route('superadmin.tenants.automations.update', [props.tenant.id, editingAutomation.value.id]),
      { onSuccess: closeAutoModal }
    )
  } else {
    autoForm.post(
      route('superadmin.tenants.automations.store', props.tenant.id),
      { onSuccess: closeAutoModal }
    )
  }
}

function toggleIsActive(auto: Automation) {
  router.patch(
    route('superadmin.tenants.automations.update', [props.tenant.id, auto.id]),
    { is_active: !auto.is_active },
    { preserveScroll: true }
  )
}

function deleteAutomation(auto: Automation) {
  if (!confirm(`Eliminare l'automazione "${auto.name}"?`)) return
  router.delete(
    route('superadmin.tenants.automations.destroy', [props.tenant.id, auto.id]),
    { preserveScroll: true }
  )
}

// ── Badge helpers ────────────────────────────────────────────────────────────

function channelLabel(channel: string): string {
  return channel === 'email' ? 'Email' : channel === 'whatsapp' ? 'WhatsApp' : 'Email + WA'
}

function channelBadgeClass(channel: string): string {
  if (channel === 'email')     return 'bg-blue-100 text-blue-700'
  if (channel === 'whatsapp')  return 'bg-green-100 text-green-700'
  return 'bg-violet-100 text-violet-700'
}

function recipientLabel(recipient: string): string {
  return recipient === 'cliente' ? 'Cliente' : recipient === 'perito' ? 'Perito' : 'Gestore'
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
