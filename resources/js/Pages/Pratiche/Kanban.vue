<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full min-w-0">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight truncate">Board Kanban</h2>
        <div class="flex items-center gap-3 shrink-0 ml-4">
          <Link
            :href="route('pratiche.index')"
            class="text-sm text-gray-500 hover:text-gray-700 transition hidden sm:block"
          >
            ← Vista tabella
          </Link>
          <Link
            :href="route('pratiche.create')"
            class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-medium px-3.5 py-2 rounded-lg hover:bg-indigo-700 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Nuovo Sinistro</span>
          </Link>
        </div>
      </div>
    </template>

    <!-- Board -->
    <div class="overflow-x-auto overflow-y-hidden p-5" style="height: calc(100vh - 112px)">
      <div class="flex gap-3.5 h-full" :style="{ minWidth: `${columns.length * 296}px` }">

        <div
          v-for="col in columns"
          :key="col.id"
          class="flex flex-col w-72 shrink-0 rounded-xl overflow-hidden transition-all"
          :class="dragOverColumn === col.id ? 'ring-2 ring-indigo-400 ring-offset-1' : ''"
          @dragover.prevent="onDragOver(col.id)"
          @dragleave.self="dragOverColumn = null"
          @drop.prevent="onDrop(col.id)"
        >
          <!-- Column header -->
          <div class="flex items-center justify-between px-3.5 py-2.5 bg-white border-b border-slate-200 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
              <span
                class="w-2.5 h-2.5 rounded-full shrink-0"
                :style="{ backgroundColor: col.color }"
              />
              <span class="text-sm font-semibold text-gray-700 truncate">{{ col.name }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0 ml-2">
              <!-- External badge -->
              <span
                v-if="col.responsible_role === 'external'"
                class="text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded-full"
                title="Richiede assegnazione perito"
              >
                Perito
              </span>
              <span class="text-xs font-semibold text-gray-400 bg-slate-100 min-w-[22px] text-center px-1.5 py-0.5 rounded-full">
                {{ col.pratiche.length }}
              </span>
            </div>
          </div>

          <!-- Cards area -->
          <div
            class="flex-1 p-2 space-y-2 overflow-y-auto bg-slate-100"
            :class="dragOverColumn === col.id && col.pratiche.length === 0 ? 'bg-indigo-50' : ''"
          >
            <!-- Pratica card -->
            <div
              v-for="pratica in col.pratiche"
              :key="pratica.id"
              draggable="true"
              @dragstart="onDragStart($event, pratica.id, col.id)"
              @dragend="onDragEnd"
              class="bg-white rounded-lg border border-gray-200 p-3 cursor-grab active:cursor-grabbing hover:shadow-md hover:border-gray-300 transition-all select-none"
              :class="{ 'opacity-40 shadow-lg scale-[0.98]': draggingId === pratica.id }"
            >
              <!-- Row: ID + today indicator + open link -->
              <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-mono text-gray-400">#{{ pratica.id }}</span>
                <div class="flex items-center gap-1.5">
                  <span
                    v-if="isToday(pratica.data_prossimo_avviso)"
                    class="inline-flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-full border border-red-200"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0 animate-pulse"/>
                    Avviso oggi
                  </span>
                  <Link
                    :href="route('pratiche.show', pratica.id)"
                    @click.stop
                    class="cursor-pointer text-gray-300 hover:text-indigo-500 transition-colors"
                    title="Apri fascicolo"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                  </Link>
                </div>
              </div>

              <!-- Primary custom field -->
              <div class="leading-snug">
                <p
                  v-if="schema.length"
                  class="text-[10px] uppercase tracking-wider text-gray-400 mb-0.5"
                >
                  {{ schema[0].label ?? schema[0].name }}
                </p>
                <Link
                  v-if="getPrimaryValue(pratica)"
                  :href="route('pratiche.show', pratica.id)"
                  @click.stop
                  class="block text-sm font-semibold text-gray-800 truncate hover:text-indigo-600 hover:underline cursor-pointer transition-colors"
                >
                  {{ getPrimaryValue(pratica) }}
                </Link>
                <p v-else class="text-sm text-gray-300 italic">—</p>
              </div>

              <!-- Date badge -->
              <div v-if="pratica.data_prossimo_avviso" class="mt-2 flex items-center gap-1">
                <svg class="w-3 h-3 shrink-0" :class="isToday(pratica.data_prossimo_avviso) ? 'text-red-400' : 'text-gray-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span
                  class="text-[11px] tabular-nums"
                  :class="isToday(pratica.data_prossimo_avviso) ? 'text-red-500 font-semibold' : 'text-gray-400'"
                >
                  {{ formatDate(pratica.data_prossimo_avviso) }}
                </span>
              </div>
            </div>

            <!-- Empty drop zone -->
            <div
              v-if="col.pratiche.length === 0"
              class="flex items-center justify-center rounded-lg border-2 border-dashed transition-colors py-8"
              :class="dragOverColumn === col.id ? 'border-indigo-400 bg-indigo-50/60' : 'border-slate-300'"
            >
              <span class="text-xs text-slate-400">Trascina qui</span>
            </div>
          </div>
        </div>

        <!-- Empty board state -->
        <div v-if="columns.length === 0" class="flex-1 flex items-center justify-center text-gray-400 text-sm">
          Nessuno stato configurato per questo tenant.
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-3 scale-95"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 translate-y-3 scale-95"
    >
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-xl text-sm font-medium pointer-events-none"
        :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white'"
      >
        <svg v-if="toast.type === 'success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- ── Modale assegnazione perito ───────────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="assignModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="cancelAssign"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="opacity-0 scale-95 translate-y-2"
        >
          <div v-if="assignModal.open" class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-100">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-gray-800">Assegna incarico</h3>
                  <p class="text-xs text-gray-400 mt-0.5">
                    Sinistro #{{ assignModal.praticaId }} → colonna "<span class="font-medium text-amber-600">{{ assignModal.columnName }}</span>"
                  </p>
                </div>
                <button @click="cancelAssign" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 space-y-4">

              <!-- Info banner -->
              <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-lg px-3.5 py-3">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs text-amber-700 leading-relaxed">
                  Questo stato richiede un tecnico esterno. Puoi assegnare un perito e fissare un appuntamento ora, oppure procedere senza assegnazione.
                </p>
              </div>

              <!-- Perito select -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  Perito / Ispettore
                  <span class="text-gray-400 font-normal">(opzionale)</span>
                </label>
                <select
                  v-model="assignForm.assegnato_a_user_id"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-amber-400 outline-none"
                >
                  <option :value="null">— Nessun perito —</option>
                  <option v-for="u in externalUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <p v-if="externalUsers.length === 0" class="text-[11px] text-amber-600 mt-1">
                  Nessun tecnico esterno nel team. Aggiungine uno dalla pagina Team.
                </p>
              </div>

              <!-- Data appuntamento -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  Data appuntamento
                  <span class="text-gray-400 font-normal">(opzionale)</span>
                </label>
                <input
                  v-model="assignForm.data_appuntamento"
                  type="datetime-local"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:border-transparent outline-none"
                />
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-3 pt-1">
                <button
                  type="button"
                  :disabled="assignForm.submitting"
                  @click="submitAssign"
                  class="flex-1 bg-amber-500 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-amber-600 transition disabled:opacity-60 flex items-center justify-center gap-2"
                >
                  <svg v-if="assignForm.submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Conferma e sposta
                </button>
                <button
                  type="button"
                  @click="cancelAssign"
                  class="px-4 py-2.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                >
                  Annulla
                </button>
              </div>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>

  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface TenantStatus {
  id: number
  name: string
  color: string
  order: number
  responsible_role: string
}
interface PraticaKanban {
  id: number
  current_status_id: number | null
  data_prossimo_avviso: string | null
  custom_fields: Record<string, string> | null
}
interface FieldSchema { name: string; label: string; type: string }
interface ExternalUser  { id: number; name: string; email: string }

const props = defineProps<{
  statuses: TenantStatus[]
  pratiche: PraticaKanban[]
  schema: FieldSchema[]
  externalUsers: ExternalUser[]
}>()

// Local mutable copy for optimistic UI updates
const localPratiche = ref<PraticaKanban[]>(props.pratiche.map(p => ({ ...p })))

// D&D state
const draggingId      = ref<number | null>(null)
const draggingFromCol = ref<number | null>(null)
const dragOverColumn  = ref<number | null>(null)

// Toast state
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null

// Assignment modal state
const assignModal = reactive({
  open:       false,
  praticaId:  null as number | null,
  toStatusId: null as number | null,
  prevStatusId: null as number | null,
  columnName: '',
})
const assignForm = reactive({
  assegnato_a_user_id: null as number | null,
  data_appuntamento:   '' as string,
  submitting:          false,
})

const columns = computed(() =>
  props.statuses.map(s => ({
    ...s,
    pratiche: localPratiche.value.filter(p => p.current_status_id === s.id),
  }))
)

function getPrimaryValue(pratica: PraticaKanban): string | null {
  if (!props.schema.length || !pratica.custom_fields) return null
  const key = props.schema[0].name
  const val = pratica.custom_fields[key]
  return val != null && String(val).trim() !== '' ? String(val) : null
}

function isToday(dateStr: string | null): boolean {
  if (!dateStr) return false
  const today = new Date()
  const d     = new Date(dateStr + 'T00:00:00')
  return (
    d.getFullYear() === today.getFullYear() &&
    d.getMonth()    === today.getMonth()    &&
    d.getDate()     === today.getDate()
  )
}

function formatDate(iso: string): string {
  return new Date(iso + 'T00:00:00').toLocaleDateString('it-IT')
}

// ── Drag & Drop ──────────────────────────────────────────────────────────────

function onDragStart(e: DragEvent, praticaId: number, fromColId: number) {
  draggingId.value      = praticaId
  draggingFromCol.value = fromColId
  e.dataTransfer!.effectAllowed = 'move'
  e.dataTransfer!.setData('text/plain', String(praticaId))
}

function onDragEnd() {
  draggingId.value      = null
  draggingFromCol.value = null
  dragOverColumn.value  = null
}

function onDragOver(colId: number) {
  dragOverColumn.value = colId
}

async function onDrop(toStatusId: number) {
  dragOverColumn.value = null
  const praticaId = draggingId.value
  const fromColId = draggingFromCol.value
  draggingId.value      = null
  draggingFromCol.value = null

  if (praticaId === null || fromColId === toStatusId) return

  const pratica = localPratiche.value.find(p => p.id === praticaId)
  if (!pratica) return

  const targetStatus = props.statuses.find(s => s.id === toStatusId)

  // External column → intercept and show assignment modal
  if (targetStatus?.responsible_role === 'external') {
    const previousStatusId = pratica.current_status_id
    // Optimistic move so user sees the card in the new column immediately
    pratica.current_status_id = toStatusId

    assignModal.open        = true
    assignModal.praticaId   = praticaId
    assignModal.toStatusId  = toStatusId
    assignModal.prevStatusId = previousStatusId
    assignModal.columnName  = targetStatus.name
    assignForm.assegnato_a_user_id = null
    assignForm.data_appuntamento   = ''
    return
  }

  // Regular column → standard PATCH
  const previousStatusId = pratica.current_status_id
  pratica.current_status_id = toStatusId

  try {
    await axios.patch(route('pratiche.update-status', praticaId), {
      current_status_id: toStatusId,
    })
    showToast('Stato aggiornato.', 'success')
  } catch {
    pratica.current_status_id = previousStatusId
    showToast('Errore: impossibile aggiornare lo stato.', 'error')
  }
}

// ── Assignment modal ──────────────────────────────────────────────────────────

function cancelAssign() {
  // Rollback the optimistic move
  if (assignModal.praticaId !== null && assignModal.prevStatusId !== null) {
    const pratica = localPratiche.value.find(p => p.id === assignModal.praticaId)
    if (pratica) pratica.current_status_id = assignModal.prevStatusId
  }
  assignModal.open = false
}

async function submitAssign() {
  if (!assignModal.praticaId || !assignModal.toStatusId) return

  assignForm.submitting = true
  try {
    await axios.post(route('ispezioni.store', assignModal.praticaId), {
      current_status_id:   assignModal.toStatusId,
      assegnato_a_user_id: assignForm.assegnato_a_user_id || null,
      data_appuntamento:   assignForm.data_appuntamento   || null,
    })
    assignModal.open = false
    showToast('Incarico assegnato e stato aggiornato.', 'success')
  } catch {
    cancelAssign()
    showToast('Errore: impossibile salvare l\'assegnazione.', 'error')
  } finally {
    assignForm.submitting = false
  }
}

function showToast(message: string, type: 'success' | 'error') {
  toast.value = { message, type }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 2800)
}
</script>
