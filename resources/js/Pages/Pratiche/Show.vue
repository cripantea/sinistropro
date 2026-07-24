<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <Link :href="route('pratiche.index')" class="text-sm text-gray-500 hover:text-gray-700 transition shrink-0">← Lista</Link>
          <h2 class="text-xl font-semibold text-gray-800 leading-tight truncate">
            Sinistro <span class="font-mono text-indigo-600">#{{ pratica.id }}</span>
          </h2>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <Link
            :href="route('pratiche.edit', pratica.id)"
            class="text-xs border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition"
          >Modifica</Link>
          <a
            :href="route('pratiche.export-pdf', pratica.id)"
            target="_blank"
            class="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-700 transition"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Scarica Pacchetto PDF
          </a>
        </div>
      </div>
    </template>

    <!-- Flash messages -->
    <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
        {{ flash.success }}
      </div>
    </Transition>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

      <!-- Metadati essenziali -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
          <div>
            <span class="text-gray-400 text-xs uppercase tracking-wide">Creato da</span>
            <p class="font-medium text-gray-800 text-sm mt-0.5">{{ pratica.utente_creatore?.name ?? '—' }}</p>
          </div>
          <div>
            <span class="text-gray-400 text-xs uppercase tracking-wide">Data creazione</span>
            <p class="font-medium text-gray-800 text-sm mt-0.5">{{ formatDate(pratica.created_at) }}</p>
          </div>
          <div>
            <span class="text-gray-400 text-xs uppercase tracking-wide">Data apertura</span>
            <p class="font-medium text-gray-800 text-sm mt-0.5">{{ formatDate(pratica.created_at) }}</p>
          </div>
          <div>
            <span class="text-gray-400 text-xs uppercase tracking-wide">Prossimo avviso</span>
            <p
              class="text-sm mt-0.5"
              :class="isOverdue(pratica.data_prossimo_avviso) ? 'text-red-600 font-semibold' : 'font-medium text-gray-800'"
            >
              {{ pratica.data_prossimo_avviso ? formatDate(pratica.data_prossimo_avviso) : '—' }}
            </p>
          </div>
          <div>
            <span class="text-gray-400 text-xs uppercase tracking-wide">Stato attuale</span>
            <div class="mt-0.5">
              <span
                v-if="pratica.current_status"
                class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold"
                :style="{ backgroundColor: pratica.current_status.color + '22', color: pratica.current_status.color }"
              >
                {{ pratica.current_status.name }}
              </span>
              <span v-else class="text-gray-400 text-xs italic">Nessuno stato</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Dati del sinistro (solo se il tenant ha campi personalizzati configurati) -->
      <div v-if="schema.length > 0" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Dati del sinistro</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          <div v-for="field in schema" :key="field.name">
            <span class="text-gray-400 text-xs uppercase tracking-wide">{{ field.label }}</span>
            <p class="mt-0.5 text-sm font-medium text-gray-800">
              <template v-if="field.type === 'boolean'">
                <span :class="customFields[field.name] ? 'text-green-600' : 'text-gray-400'">
                  {{ customFields[field.name] ? 'Sì' : 'No' }}
                </span>
              </template>
              <template v-else>{{ customFields[field.name] ?? '—' }}</template>
            </p>
          </div>
        </div>
      </div>

      <!-- Ispezione / Perito assegnato -->
      <div v-if="externalUsers.length > 0" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Sopralluogo / Perito</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

          <!-- Perito -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Perito assegnato</label>
            <select
              v-model="ispezioneForm.assegnato_a_user_id"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white"
              :disabled="ispezioneForm.processing"
            >
              <option :value="null">— Nessuno —</option>
              <option v-for="u in externalUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <!-- Data appuntamento -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Data appuntamento</label>
            <input
              v-model="ispezioneForm.data_appuntamento"
              type="date"
              :disabled="ispezioneForm.processing"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-gray-50"
            />
          </div>

          <!-- Note -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Note sopralluogo</label>
            <input
              v-model="ispezioneForm.note_sopralluogo"
              type="text"
              placeholder="Es. accesso posteriore"
              :disabled="ispezioneForm.processing"
              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-gray-50"
            />
          </div>
        </div>

        <div class="flex items-center gap-3 mt-4">
          <button
            type="button"
            :disabled="ispezioneForm.processing"
            @click="saveIspezione"
            class="inline-flex items-center gap-2 bg-indigo-600 text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
          >
            <svg v-if="ispezioneForm.processing" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            Salva
          </button>
          <!-- Perito corrente -->
          <span v-if="ispezione?.assegnatoa" class="text-xs text-gray-500">
            Assegnato a <span class="font-medium text-gray-700">{{ ispezione.assegnatoa.name }}</span>
            ({{ ispezione.assegnatoa.email }})
          </span>
        </div>
      </div>

      <!-- ALLEGATI — una riga per categoria -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-gray-700">Allegati</h3>
          <span class="text-xs text-gray-400">{{ allegatiList.length }} file totali</span>
        </div>

        <!-- Hidden shared file input -->
        <input ref="tileFileInput" type="file" multiple class="hidden" @change="handleTileFileInput"/>

        <div class="space-y-3">

          <!-- Una riga per categoria abilitata -->
          <div
            v-for="cat in categories"
            :key="cat.id"
            class="rounded-xl border-2 bg-white p-4 transition-colors"
            :class="isRowSigned(cat.id) ? 'border-green-500' : 'border-gray-200'"
          >
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-gray-700">{{ cat.name }}</h4>
              <span class="text-xs text-gray-400">{{ filesForCategory(cat.id).length }} file · max {{ cat.max_file_size_mb }} MB</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

              <!-- Colonna sinistra: genera modulo -->
              <div class="space-y-2">
                <p v-if="templatesForCategory(cat.id).length === 0" class="text-xs text-gray-400 italic">
                  Nessun modulo configurato per questa categoria.
                </p>
                <div v-for="tpl in templatesForCategory(cat.id)" :key="tpl.id" class="flex items-center gap-2 flex-wrap">
                  <button
                    type="button"
                    :disabled="!!latestGeneratedAllegato(tpl.id)"
                    @click="openGenerateModal(tpl)"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border transition"
                    :class="latestGeneratedAllegato(tpl.id)
                      ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'
                      : 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100'"
                  >
                    <svg v-if="latestGeneratedAllegato(tpl.id)" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Compila Modulo
                  </button>
                  <template v-if="latestGeneratedAllegato(tpl.id)">
                    <button
                      type="button"
                      @click="downloadAllegato(latestGeneratedAllegato(tpl.id)!.id)"
                      class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:underline"
                    >
                      ↓ {{ latestGeneratedAllegato(tpl.id)!.nome_file }}
                    </button>
                    <button
                      type="button"
                      :disabled="!!recompiling[tpl.id]"
                      @click="rigenera(tpl.id)"
                      class="text-xs text-slate-500 hover:text-slate-700 underline disabled:opacity-50"
                    >
                      Rigenera
                    </button>
                  </template>
                  <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                    <span v-if="recompileMsg[tpl.id]" class="text-xs font-medium"
                          :class="recompileMsg[tpl.id]?.startsWith('✓') ? 'text-green-600' : 'text-amber-600'">
                      {{ recompileMsg[tpl.id] }}
                    </span>
                  </Transition>
                </div>
              </div>

              <!-- Colonna destra: upload file firmato -->
              <div
                class="rounded-lg border-2 border-dashed transition-all overflow-hidden"
                :class="tileDragOver[cat.id] ? 'border-indigo-400 bg-indigo-50 shadow-md' : 'border-gray-200 bg-gray-50/70 hover:bg-gray-100'"
                @dragover.prevent="tileDragOver[cat.id] = true"
                @dragleave.prevent="tileDragOver[cat.id] = false"
                @drop.prevent="handleTileDrop($event, cat.id)"
              >
                <button
                  type="button"
                  class="w-full p-3 text-left flex items-center gap-2 cursor-pointer"
                  @click="openTilePicker(cat.id)"
                >
                  <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                  </svg>
                  <span class="text-xs text-gray-500">Trascina qui il file firmato o clicca per selezionarlo</span>
                </button>

                <!-- Coda upload -->
                <div v-if="queueForCategory(cat.id).length > 0" class="px-3 pb-2 space-y-1">
                  <div v-for="item in queueForCategory(cat.id)" :key="item.id" class="flex items-center gap-2 text-xs">
                    <span class="flex-1 truncate text-gray-600">{{ item.name }}</span>
                    <div class="w-16 bg-gray-200 rounded-full h-1 overflow-hidden shrink-0">
                      <div class="h-full rounded-full transition-all" :class="item.error ? 'bg-red-500' : 'bg-indigo-500'" :style="{ width: `${item.progress}%` }"/>
                    </div>
                    <span v-if="item.error" class="text-red-500 shrink-0">!</span>
                    <span v-else-if="item.progress < 100" class="text-gray-400 tabular-nums shrink-0">{{ item.progress }}%</span>
                    <span v-else class="text-green-600 shrink-0">✓</span>
                  </div>
                </div>

                <!-- File firmati caricati in questa categoria (esclusi i moduli generati, che restano a sinistra) -->
                <div v-if="signedFilesForCategory(cat.id).length > 0" class="border-t border-gray-200 divide-y divide-gray-100 bg-white/60">
                  <div
                    v-for="allegato in signedFilesForCategory(cat.id)"
                    :key="allegato.id"
                    class="flex items-center gap-2 px-3 py-2 text-xs"
                  >
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="flex-1 text-gray-700 truncate">{{ allegato.nome_file }}</span>
                    <button @click="downloadAllegato(allegato.id)" class="text-indigo-500 hover:underline shrink-0">↓</button>
                    <button @click="deleteAllegato(allegato.id)" class="text-red-400 hover:text-red-600 shrink-0">✕</button>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Riga "Altri Documenti" (senza categoria) -->
          <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-gray-700">Altri Documenti</h4>
              <span class="text-xs text-gray-400">{{ filesForCategory(null).length }} file · senza categoria</span>
            </div>
            <div
              class="rounded-lg border-2 border-dashed transition-all overflow-hidden"
              :class="tileDragOver['none'] ? 'border-gray-400 bg-gray-100 shadow-md' : 'border-gray-200 bg-gray-50/70 hover:bg-gray-100'"
              @dragover.prevent="tileDragOver['none'] = true"
              @dragleave.prevent="tileDragOver['none'] = false"
              @drop.prevent="handleTileDrop($event, null)"
            >
              <button
                type="button"
                class="w-full p-3 text-left flex items-center gap-2 cursor-pointer"
                @click="openTilePicker(null)"
              >
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span class="text-xs text-gray-500">Trascina qui altri documenti o clicca per selezionarli</span>
              </button>

              <div v-if="queueForCategory(null).length > 0" class="px-3 pb-2 space-y-1">
                <div v-for="item in queueForCategory(null)" :key="item.id" class="flex items-center gap-2 text-xs">
                  <span class="flex-1 truncate text-gray-600">{{ item.name }}</span>
                  <div class="w-16 bg-gray-200 rounded-full h-1 overflow-hidden shrink-0">
                    <div class="h-full rounded-full transition-all" :class="item.error ? 'bg-red-500' : 'bg-gray-500'" :style="{ width: `${item.progress}%` }"/>
                  </div>
                  <span v-if="item.error" class="text-red-500 shrink-0">!</span>
                  <span v-else-if="item.progress < 100" class="text-gray-400 tabular-nums shrink-0">{{ item.progress }}%</span>
                  <span v-else class="text-green-600 shrink-0">✓</span>
                </div>
              </div>

              <div v-if="filesForCategory(null).length > 0" class="border-t border-gray-200 divide-y divide-gray-100 bg-white/60">
                <div
                  v-for="allegato in filesForCategory(null)"
                  :key="allegato.id"
                  class="flex items-center gap-2 px-3 py-2 text-xs"
                >
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  <span class="flex-1 text-gray-700 truncate">{{ allegato.nome_file }}</span>
                  <button @click="downloadAllegato(allegato.id)" class="text-indigo-500 hover:underline shrink-0">↓</button>
                  <button @click="deleteAllegato(allegato.id)" class="text-red-400 hover:text-red-600 shrink-0">✕</button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- NOTE -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Note</h3>

        <div class="flex-1 overflow-y-auto max-h-96 space-y-4 mb-4 pr-1">
          <p v-if="pratica.note.length === 0" class="text-sm text-gray-400 italic text-center py-6">
            Nessuna nota ancora.
          </p>
          <div v-for="nota in pratica.note" :key="nota.id" class="flex gap-3">
            <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-xs font-bold">
              {{ initials(nota.user?.name) }}
            </div>
            <div class="flex-1 bg-gray-50 rounded-xl px-3.5 py-2.5">
              <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-xs font-semibold text-gray-700">{{ nota.user?.name ?? 'Utente eliminato' }}</span>
                <span class="text-xs text-gray-400">{{ formatDatetime(nota.created_at) }}</span>
              </div>
              <p class="text-sm text-gray-700 whitespace-pre-line">{{ nota.nota }}</p>
              <div class="mt-1.5 text-right">
                <button
                  v-if="canDeleteNota(nota)"
                  @click="deleteNota(nota.id)"
                  class="text-xs text-red-400 hover:text-red-600 transition"
                >Elimina</button>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submitNota" class="border-t border-gray-100 pt-4">
          <textarea
            v-model="notaForm.nota"
            rows="3"
            placeholder="Scrivi una nota…"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 resize-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          />
          <p v-if="notaForm.errors.nota" class="text-xs text-red-600 mt-1">{{ notaForm.errors.nota }}</p>
          <button
            type="submit"
            :disabled="notaForm.processing || !notaForm.nota.trim()"
            class="mt-2 w-full bg-indigo-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
          >
            Aggiungi nota
          </button>
        </form>
      </div>

      <!-- WHATSAPP -->
      <PraticaChatPanel :pratica-id="props.pratica.id" />

    </div>

    <!-- Modulo dinamico modal -->
    <ModuleFormModal
      :show="moduleModalOpen"
      :pratica-id="pratica.id"
      :templates="activeModuleTemplates"
      :pratica-modules="praticaModules"
      @close="moduleModalOpen = false"
      @saved="onModuleSaved"
    />

  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { Link, useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const http = axios.create()
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ModuleFormModal from '@/Components/ModuleFormModal.vue'
import PraticaChatPanel from '@/Components/Whatsapp/PraticaChatPanel.vue'
import type { PageProps } from '@/types'

// ----- Types -----
interface FieldSchema { name: string; label: string; type: 'text' | 'date' | 'number' | 'boolean'; required?: boolean; options?: string[] }

interface ModuleTemplate {
  id: number
  name: string
  fields_schema: FieldSchema[]
  pdf_template_s3_key: string | null
  output_document_category_id: number | null
}

interface PraticaModule {
  id: number
  module_template_id: number
  values: Record<string, unknown>
}
interface ExternalUser { id: number; name: string; email: string }
interface Ispezione {
  id: number
  assegnato_a_user_id: number | null
  data_appuntamento: string | null
  note_sopralluogo: string | null
  stato: string
  assegnatoa: ExternalUser | null
}
interface TenantStatus { id: number; name: string; color: string }
interface Nota { id: number; nota: string; user: { id: number; name: string } | null; created_at: string }
interface DocumentCategory { id: number; name: string; max_file_size_mb: number }
interface Allegato {
  id: number
  nome_file: string
  created_at: string
  document_category_id: number | null
  source: 'generato' | 'caricato'
  module_template_id: number | null
  category: { id: number; name: string } | null
}

interface Pratica {
  id: number
  custom_fields: Record<string, string | boolean> | null
  data_prossimo_avviso: string | null
  created_at: string
  current_status: TenantStatus | null
  current_status_id: number | null
  utente_creatore: { id: number; name: string; email: string } | null
  tenant: { id: number; settings: { custom_fields_schema: FieldSchema[] } | null; statuses: TenantStatus[] }
  note: Nota[]
  allegati: Allegato[]
  ispezioni: Ispezione[]
}

const props   = defineProps<{
  pratica: Pratica
  categories: DocumentCategory[]
  moduleTemplates: ModuleTemplate[]
  praticaModules: PraticaModule[]
  externalUsers: ExternalUser[]
}>()
const page    = usePage<PageProps>()
const flash   = computed(() => page.props.flash)
const authUser = computed(() => page.props.auth.user)

// ----- Custom fields -----
const schema       = computed<FieldSchema[]>(() => props.pratica.tenant.settings?.custom_fields_schema ?? [])
const customFields = computed(() => props.pratica.custom_fields ?? {})

// ----- Note -----
const notaForm = useForm({ nota: '' })

function submitNota() {
  notaForm.post(route('pratiche.note.store', props.pratica.id), {
    preserveScroll: true,
    onSuccess: () => notaForm.reset(),
  })
}

function deleteNota(id: number) {
  if (!confirm('Eliminare questa nota?')) return
  router.delete(route('pratiche.note.destroy', [props.pratica.id, id]), { preserveScroll: true })
}

function canDeleteNota(nota: Nota) {
  return nota.user?.id === authUser.value.id || authUser.value.role === 'tenant-admin'
}

// ----- Ispezione / Perito -----
const ispezione = computed<Ispezione | null>(() => props.pratica.ispezioni?.[0] ?? null)

const ispezioneForm = useForm({
  assegnato_a_user_id: ispezione.value?.assegnato_a_user_id ?? null as number | null,
  data_appuntamento:   ispezione.value?.data_appuntamento?.substring(0, 10) ?? '' as string,
  note_sopralluogo:    ispezione.value?.note_sopralluogo ?? '' as string,
})

function saveIspezione() {
  ispezioneForm.post(route('ispezioni.store', props.pratica.id), { preserveScroll: true })
}

// ----- Moduli dinamici -----
const moduleModalOpen       = ref(false)
const activeModuleTemplates = ref<ModuleTemplate[]>([])

function openGenerateModal(tpl: ModuleTemplate) {
  activeModuleTemplates.value = [tpl]
  moduleModalOpen.value = true
}

function onModuleSaved(allegato: Allegato | null, warning: string | null) {
  if (allegato) {
    allegatiList.value.push(allegato)
  }
  if (!warning) {
    moduleModalOpen.value = false
  }
}

const recompiling  = reactive<Record<number, boolean>>({})
const recompileMsg = reactive<Record<number, string | null>>({})

async function recompile(mod: PraticaModule) {
  recompiling[mod.module_template_id] = true
  recompileMsg[mod.module_template_id] = null
  try {
    const resp = await http.post<{ module: PraticaModule; allegato: Allegato | null; warning: string | null }>(
      route('pratica-modules.store', props.pratica.id),
      { module_template_id: mod.module_template_id, values: mod.values }
    )
    if (resp.data.allegato) {
      allegatiList.value.push(resp.data.allegato)
    }
    recompileMsg[mod.module_template_id] = resp.data.warning ? '⚠ PDF non generato' : '✓ PDF generato'
  } catch {
    recompileMsg[mod.module_template_id] = '✗ Errore'
  } finally {
    recompiling[mod.module_template_id] = false
    setTimeout(() => { recompileMsg[mod.module_template_id] = null }, 4000)
  }
}

function rigenera(templateId: number) {
  const mod = props.praticaModules.find(m => m.module_template_id === templateId)
  if (mod) recompile(mod)
}

// ----- Allegati -----
const allegatiList = ref<Allegato[]>([...props.pratica.allegati])

// Per-riga drag state keyed by category id o 'none'
const tileDragOver   = reactive<Record<string | number, boolean>>({})
const tileFileInput  = ref<HTMLInputElement | null>(null)
const activeCatId    = ref<number | null>(null)

interface UploadItem { id: number; name: string; progress: number; error: boolean; categoryId: number | null }
const uploadQueue = ref<UploadItem[]>([])
let _uid = 0

function filesForCategory(catId: number | null): Allegato[] {
  return allegatiList.value.filter(a => a.document_category_id === catId)
}

// Solo i file caricati manualmente (il firmato dal cliente) — i moduli generati
// restano visibili/scaricabili solo a sinistra, accanto al pulsante "Compila Modulo".
function signedFilesForCategory(catId: number): Allegato[] {
  return filesForCategory(catId).filter(a => a.source === 'caricato')
}

function queueForCategory(catId: number | null): UploadItem[] {
  return uploadQueue.value.filter(q => q.categoryId === catId)
}

function templatesForCategory(catId: number): ModuleTemplate[] {
  return props.moduleTemplates.filter(t => t.output_document_category_id === catId)
}

function latestGeneratedAllegato(templateId: number): Allegato | null {
  const matches = allegatiList.value
    .filter(a => a.source === 'generato' && a.module_template_id === templateId)
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  return matches[0] ?? null
}

function isRowSigned(catId: number): boolean {
  return filesForCategory(catId).some(a => a.source === 'caricato')
}

function openTilePicker(catId: number | null) {
  activeCatId.value = catId
  tileFileInput.value?.click()
}

function handleTileFileInput(e: Event) {
  Array.from((e.target as HTMLInputElement).files ?? []).forEach(f => uploadFile(f, activeCatId.value))
  if (tileFileInput.value) tileFileInput.value.value = ''
}

function handleTileDrop(e: DragEvent, catId: number | null) {
  tileDragOver[catId ?? 'none'] = false
  Array.from(e.dataTransfer?.files ?? []).forEach(f => uploadFile(f, catId))
}

async function uploadFile(file: File, categoryId: number | null) {
  const item: UploadItem = { id: ++_uid, name: file.name, progress: 0, error: false, categoryId }
  uploadQueue.value.push(item)

  const data = new FormData()
  data.append('file', file)
  if (categoryId !== null) data.append('document_category_id', String(categoryId))

  try {
    const resp = await axios.post<{ allegato: Allegato }>(
      route('allegati.store', props.pratica.id),
      data,
      { onUploadProgress: (e) => { item.progress = e.total ? Math.round((e.loaded * 100) / e.total) : 50 } }
    )
    item.progress = 100
    allegatiList.value.push(resp.data.allegato)
  } catch (err: unknown) {
    item.error = true
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    if (msg) alert(msg)
  }
}

async function downloadAllegato(id: number) {
  const resp = await axios.get<{ url: string }>(route('allegati.download', id))
  window.open(resp.data.url, '_blank')
}

function deleteAllegato(id: number) {
  if (!confirm('Eliminare questo allegato?')) return
  router.delete(route('allegati.web.destroy', id), {
    preserveScroll: true,
    onSuccess: () => { allegatiList.value = allegatiList.value.filter(a => a.id !== id) },
  })
}

// ----- Helpers -----
function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('it-IT')
}
function formatDatetime(iso: string): string {
  return new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function isOverdue(iso: string | null): boolean {
  if (!iso) return false
  return new Date(iso) < new Date()
}
function initials(name?: string | null): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
}
</script>
