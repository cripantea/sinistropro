<template>
  <div class="space-y-3">

    <!-- Page navigator -->
    <div v-if="maxPage > 1" class="flex items-center gap-2">
      <button type="button" :disabled="currentPage <= 1"
              @click="currentPage = Math.max(1, currentPage - 1)"
              class="px-2 py-1 text-xs border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-40 transition">
        ◀ Prec.
      </button>
      <span class="text-xs text-slate-500">Pagina {{ currentPage }} / {{ maxPage }}</span>
      <button type="button" :disabled="currentPage >= maxPage"
              @click="currentPage = Math.min(maxPage, currentPage + 1)"
              class="px-2 py-1 text-xs border border-slate-300 rounded hover:bg-slate-50 disabled:opacity-40 transition">
        Succ. ▶
      </button>
    </div>

    <!-- Main layout: canvas + sidebar -->
    <div class="flex gap-4 min-h-0">

      <!-- ── PDF Canvas ── -->
      <div class="flex-1 min-w-0">
        <div
          ref="canvasRef"
          class="relative rounded-lg border border-slate-200 overflow-hidden select-none bg-slate-100"
          :class="isDragging ? 'cursor-grabbing' : 'cursor-default'"
          @mousemove.prevent="onMouseMove"
          @mouseup="onMouseUp"
          @mouseleave="onMouseUp"
        >
          <!-- Loading -->
          <div v-if="loadingPreview" class="h-96 flex items-center justify-center">
            <svg class="animate-spin w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <span class="ml-2 text-sm text-slate-400">Caricamento anteprima...</span>
          </div>

          <!-- Error -->
          <div v-else-if="previewError" class="h-96 flex items-center justify-center text-center px-4">
            <span class="text-sm text-red-400">{{ previewError }}</span>
          </div>

          <!-- No s3Key -->
          <div v-else-if="!props.s3Key" class="h-96 flex items-center justify-center">
            <span class="text-sm text-slate-400 italic">Carica un PDF per vedere l'anteprima</span>
          </div>

          <!-- PDF image -->
          <img v-else-if="previewDataUrl"
               :src="previewDataUrl"
               class="w-full block"
               draggable="false"
               alt="Anteprima PDF" />

          <!-- Field overlays (only when image loaded) -->
          <template v-if="previewDataUrl">
            <div
              v-for="field in visibleFields"
              :key="field._uid"
              class="absolute rounded-sm border overflow-hidden whitespace-nowrap text-[9px] font-semibold leading-none px-0.5 flex items-center"
              :class="[
                selectedUid === field._uid
                  ? 'border-red-500 bg-red-400/35 text-red-800 ring-1 ring-red-400'
                  : 'border-indigo-400 bg-indigo-400/25 text-indigo-800',
                isDragging && dragState?.uid === field._uid ? 'cursor-grabbing' : 'cursor-grab',
              ]"
              :style="{
                left:   field.x + '%',
                top:    field.y + '%',
                width:  field.w + '%',
                height: field.type === 'textarea' ? (field.h ?? DEFAULT_TEXTAREA_H) + '%' : '14px',
              }"
              @mousedown.prevent="startDrag(field._uid, $event)"
            >
              <span class="truncate">{{ field.label }}</span>
            </div>
          </template>
        </div>

        <p class="text-[10px] text-slate-400 mt-1.5 text-center">
          Trascina i rettangoli per riposizionare • Seleziona un campo per il pannello di controllo
        </p>
      </div>

      <!-- ── Sidebar ── -->
      <div class="w-52 shrink-0 flex flex-col gap-3">

        <!-- Field list -->
        <div class="border border-slate-200 rounded-lg overflow-hidden">
          <div class="bg-slate-50 px-2.5 py-1.5 text-[10px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-200">
            Campi (pag. {{ currentPage }})
          </div>
          <div class="max-h-48 overflow-y-auto divide-y divide-slate-100">
            <button
              v-for="field in visibleFields"
              :key="field._uid"
              type="button"
              class="w-full text-left px-2.5 py-1.5 text-xs transition truncate"
              :class="selectedUid === field._uid
                ? 'bg-indigo-50 text-indigo-700 font-semibold'
                : 'text-slate-700 hover:bg-slate-50'"
              @click="selectedUid = field._uid"
            >
              {{ field.label }}
            </button>
            <p v-if="visibleFields.length === 0" class="px-2.5 py-3 text-xs text-slate-400 italic text-center">
              Nessun campo su questa pagina
            </p>
          </div>
        </div>

        <!-- Controls (visible only when a field is selected) -->
        <div v-if="selectedField" class="border border-slate-200 rounded-lg p-3 space-y-3">

          <!-- Coords readout -->
          <div class="text-center">
            <p class="text-[10px] font-semibold text-slate-500 mb-0.5 uppercase tracking-wide truncate">{{ selectedField.label }}</p>
            <div class="flex justify-center gap-2 text-[10px] font-mono text-slate-600">
              <span>x&nbsp;{{ fmt(selectedField.x) }}</span>
              <span class="text-slate-300">|</span>
              <span>y&nbsp;{{ fmt(selectedField.y) }}</span>
              <span class="text-slate-300">|</span>
              <span>w&nbsp;{{ fmt(selectedField.w) }}</span>
              <template v-if="selectedField.type === 'textarea'">
                <span class="text-slate-300">|</span>
                <span>h&nbsp;{{ fmt(selectedField.h ?? DEFAULT_TEXTAREA_H) }}</span>
              </template>
            </div>
          </div>

          <!-- D-pad -->
          <div class="grid grid-cols-3 gap-1 place-items-center">
            <div/>
            <button type="button" @click="nudge(0, -STEP)"    title="Su"   class="dpad-btn">▲</button>
            <div/>
            <button type="button" @click="nudge(-STEP, 0)"   title="Sinistra" class="dpad-btn">◀</button>
            <div class="w-7 h-7 rounded bg-slate-100 flex items-center justify-center">
              <svg class="w-2.5 h-2.5 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="3"/></svg>
            </div>
            <button type="button" @click="nudge(STEP, 0)"    title="Destra" class="dpad-btn">▶</button>
            <div/>
            <button type="button" @click="nudge(0, STEP)"    title="Giù"  class="dpad-btn">▼</button>
            <div/>
          </div>

          <!-- Step selector -->
          <div class="flex items-center justify-center gap-1.5">
            <span class="text-[10px] text-slate-400">Passo:</span>
            <button v-for="s in STEPS" :key="s" type="button"
                    @click="STEP = s"
                    class="px-1.5 py-0.5 text-[10px] rounded border transition"
                    :class="STEP === s
                      ? 'bg-indigo-600 text-white border-indigo-600'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-50'">
              {{ s }}%
            </button>
          </div>

          <!-- Width controls -->
          <div class="flex items-center justify-between gap-1">
            <span class="text-[10px] text-slate-500 shrink-0">Larghezza (w)</span>
            <div class="flex items-center gap-1">
              <button type="button" @click="adjustWidth(-STEP)" class="dpad-btn">−</button>
              <span class="text-[10px] font-mono text-slate-600 w-10 text-center">{{ fmt(selectedField.w) }}%</span>
              <button type="button" @click="adjustWidth(STEP)"  class="dpad-btn">+</button>
            </div>
          </div>

          <!-- Height controls (solo per campi multiriga) -->
          <div v-if="selectedField.type === 'textarea'" class="flex items-center justify-between gap-1">
            <span class="text-[10px] text-slate-500 shrink-0">Altezza (h)</span>
            <div class="flex items-center gap-1">
              <button type="button" @click="adjustHeight(-STEP)" class="dpad-btn">−</button>
              <span class="text-[10px] font-mono text-slate-600 w-10 text-center">{{ fmt(selectedField.h ?? DEFAULT_TEXTAREA_H) }}%</span>
              <button type="button" @click="adjustHeight(STEP)"  class="dpad-btn">+</button>
            </div>
          </div>

        </div>

        <p v-else class="text-xs text-slate-400 italic text-center px-2">
          Seleziona un campo per le opzioni di spostamento
        </p>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'

interface FieldSchemaRow {
  _uid: number
  name: string; label: string; type: string; required: boolean
  page?: number; x?: number; y?: number; w?: number; h?: number
}

const DEFAULT_TEXTAREA_H = 8

const props = defineProps<{
  fields: FieldSchemaRow[]
  s3Key:  string | null
  tenantId: number
}>()

const emit = defineEmits<{
  'update:fields': [fields: FieldSchemaRow[]]
}>()

// ── State ────────────────────────────────────────────────────────────────────

const STEPS        = [0.2, 0.5, 1.0]
const STEP         = ref(0.5)
const canvasRef    = ref<HTMLDivElement | null>(null)
const currentPage  = ref(1)
const selectedUid  = ref<number | null>(null)
const loadingPreview = ref(false)
const previewDataUrl = ref<string | null>(null)
const previewError   = ref<string | null>(null)
const http = axios.create()

// ── Computed ─────────────────────────────────────────────────────────────────

const maxPage = computed(() =>
  Math.max(1, ...props.fields.map(f => f.page ?? 1))
)

const visibleFields = computed(() =>
  props.fields.filter(f => (f.page ?? 1) === currentPage.value)
)

const selectedField = computed<FieldSchemaRow | null>(() =>
  selectedUid.value !== null
    ? (props.fields.find(f => f._uid === selectedUid.value) ?? null)
    : null
)

// ── Preview fetch ─────────────────────────────────────────────────────────────

watch([() => props.s3Key, currentPage], async ([key]) => {
  previewDataUrl.value = null
  previewError.value   = null
  if (! key) return

  loadingPreview.value = true
  try {
    const resp = await http.get<{ image: string }>(
      route('superadmin.tenants.module-templates.preview'),
      { params: { s3_key: key, page: currentPage.value } }
    )
    previewDataUrl.value = resp.data.image
  } catch {
    previewError.value = 'Impossibile caricare l\'anteprima del PDF.'
  } finally {
    loadingPreview.value = false
  }
}, { immediate: true })

// Reset to page 1 on key change
watch(() => props.s3Key, () => { currentPage.value = 1 })

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmt(v?: number): string {
  return (v ?? 0).toFixed(1)
}

function clamp(v: number): number {
  return Math.round(Math.max(0, Math.min(100, v)) * 10) / 10
}

function updateField(uid: number, patch: Partial<FieldSchemaRow>) {
  emit('update:fields', props.fields.map(f => f._uid === uid ? { ...f, ...patch } : f))
}

function nudge(dx: number, dy: number) {
  if (! selectedField.value) return
  updateField(selectedField.value._uid, {
    x: clamp((selectedField.value.x ?? 0) + dx),
    y: clamp((selectedField.value.y ?? 0) + dy),
  })
}

function adjustWidth(dw: number) {
  if (! selectedField.value) return
  updateField(selectedField.value._uid, {
    w: clamp((selectedField.value.w ?? 20) + dw),
  })
}

function adjustHeight(dh: number) {
  if (! selectedField.value) return
  updateField(selectedField.value._uid, {
    h: clamp((selectedField.value.h ?? DEFAULT_TEXTAREA_H) + dh),
  })
}

// ── Drag & drop ───────────────────────────────────────────────────────────────

interface DragState { uid: number; startX: number; startY: number; origX: number; origY: number }
const dragState  = ref<DragState | null>(null)
const isDragging = computed(() => dragState.value !== null)

function startDrag(uid: number, e: MouseEvent) {
  selectedUid.value = uid
  const field = props.fields.find(f => f._uid === uid)!
  dragState.value = {
    uid,
    startX: e.clientX,
    startY: e.clientY,
    origX:  field.x ?? 0,
    origY:  field.y ?? 0,
  }
}

function onMouseMove(e: MouseEvent) {
  if (! dragState.value || ! canvasRef.value) return
  const rect = canvasRef.value.getBoundingClientRect()
  const dx = ((e.clientX - dragState.value.startX) / rect.width)  * 100
  const dy = ((e.clientY - dragState.value.startY) / rect.height) * 100
  updateField(dragState.value.uid, {
    x: clamp(dragState.value.origX + dx),
    y: clamp(dragState.value.origY + dy),
  })
}

function onMouseUp() {
  dragState.value = null
}
</script>

<style scoped>
.dpad-btn {
  @apply w-7 h-7 flex items-center justify-center rounded border border-slate-300
         hover:bg-slate-100 text-slate-700 text-xs font-bold transition active:scale-90;
}
</style>
