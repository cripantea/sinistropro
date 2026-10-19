<template>
  <!-- Data sempre all'italiana (gg/mm/aaaa) con calendario in italiano, qualunque sia la
       lingua del browser. Il valore resta in formato ISO (aaaa-mm-gg) come gli <input type="date">. -->
  <div ref="root" class="relative">
    <input
      :value="testo"
      type="text"
      inputmode="numeric"
      placeholder="gg/mm/aaaa"
      maxlength="10"
      :required="required"
      :disabled="disabled"
      :class="[inputClass, 'pr-10', nonValida ? '!border-red-400' : '']"
      @input="onInput"
      @blur="onBlur"
      @focus="aperto = false"
    />
    <button
      type="button"
      :disabled="disabled"
      title="Apri il calendario"
      class="absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 hover:text-indigo-600 disabled:opacity-50"
      @click="apriCalendario"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    </button>
    <p v-if="nonValida" class="text-xs text-red-600 mt-1">Scrivi la data come gg/mm/aaaa, per esempio 24/07/2026.</p>

    <div
      v-if="aperto"
      class="absolute z-50 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl"
    >
      <div class="flex items-center justify-between mb-2">
        <button type="button" class="w-9 h-9 rounded-lg text-lg text-slate-600 hover:bg-slate-100" title="Mese prima" @click="cambiaMese(-1)">‹</button>
        <span class="text-sm font-semibold text-slate-800 capitalize">{{ MESI[meseVista] }} {{ annoVista }}</span>
        <button type="button" class="w-9 h-9 rounded-lg text-lg text-slate-600 hover:bg-slate-100" title="Mese dopo" @click="cambiaMese(1)">›</button>
      </div>
      <div class="grid grid-cols-7 text-center text-[11px] font-semibold text-slate-400 mb-1">
        <span v-for="g in GIORNI" :key="g">{{ g }}</span>
      </div>
      <div class="grid grid-cols-7 gap-0.5">
        <span v-for="n in vuotiIniziali" :key="'v' + n" />
        <button
          v-for="g in giorniNelMese"
          :key="g"
          type="button"
          :class="[
            'h-9 rounded-lg text-sm transition',
            iso(annoVista, meseVista, g) === modelValue
              ? 'bg-indigo-600 text-white font-semibold'
              : iso(annoVista, meseVista, g) === oggi
                ? 'text-indigo-700 font-semibold ring-1 ring-indigo-300 hover:bg-indigo-50'
                : 'text-slate-700 hover:bg-slate-100',
          ]"
          @click="scegli(g)"
        >{{ g }}</button>
      </div>
      <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
        <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="scegliOggi">Oggi</button>
        <button v-if="modelValue" type="button" class="text-xs text-slate-500 hover:underline" @click="svuota">Cancella</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string | null
  required?: boolean
  disabled?: boolean
  inputClass?: string
}>(), {
  modelValue: '',
  inputClass: 'w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre']
const GIORNI = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom']

const pad = (n: number) => String(n).padStart(2, '0')
const iso = (a: number, m: number, g: number) => `${a}-${pad(m + 1)}-${pad(g)}`
const adesso = new Date()
const oggi = iso(adesso.getFullYear(), adesso.getMonth(), adesso.getDate())

// Accetta sia ISO (anche con orario) sia gg/mm/aaaa già scritto all'italiana.
function daValore(v: string | null | undefined): { a: number; m: number; g: number } | null {
  const s = String(v ?? '').trim()
  let r = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (r) return { a: +r[1], m: +r[2] - 1, g: +r[3] }
  r = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)
  if (r) return { a: +r[3], m: +r[2] - 1, g: +r[1] }
  return null
}
function valida(a: number, m: number, g: number): boolean {
  const d = new Date(a, m, g)
  return a >= 1900 && a <= 2100 && d.getFullYear() === a && d.getMonth() === m && d.getDate() === g
}
function inTesto(v: string | null | undefined): string {
  const d = daValore(v)
  return d ? `${pad(d.g)}/${pad(d.m + 1)}/${d.a}` : ''
}

const testo = ref(inTesto(props.modelValue))
const nonValida = ref(false)
const aperto = ref(false)
const root = ref<HTMLElement | null>(null)

const iniziale = daValore(props.modelValue)
const annoVista = ref(iniziale?.a ?? adesso.getFullYear())
const meseVista = ref(iniziale?.m ?? adesso.getMonth())

watch(() => props.modelValue, (v) => {
  // Aggiorna il testo solo se il valore arriva da fuori (non mentre si sta scrivendo).
  const d = daValore(testo.value)
  const corrente = d && valida(d.a, d.m, d.g) ? iso(d.a, d.m, d.g) : ''
  if ((v ?? '') !== corrente) testo.value = inTesto(v)
})

function onInput(e: Event) {
  const el = e.target as HTMLInputElement
  // Inserisce da solo le barre: 24072026 → 24/07/2026
  // (le barre scritte a mano vanno bene finché ogni parte ha al massimo le sue cifre: 1/3/2028)
  let cifre = el.value.replace(/[^\d/]/g, '')
  if (!/^\d{0,2}(\/\d{0,2}(\/\d{0,4})?)?$/.test(cifre)) {
    cifre = cifre.replace(/\D/g, '').slice(0, 8)
    if (cifre.length > 4) cifre = `${cifre.slice(0, 2)}/${cifre.slice(2, 4)}/${cifre.slice(4)}`
    else if (cifre.length > 2) cifre = `${cifre.slice(0, 2)}/${cifre.slice(2)}`
  }
  testo.value = cifre
  el.value = cifre
  nonValida.value = false

  if (cifre === '') { emit('update:modelValue', ''); return }
  const d = daValore(cifre)
  if (d && valida(d.a, d.m, d.g)) {
    emit('update:modelValue', iso(d.a, d.m, d.g))
    annoVista.value = d.a
    meseVista.value = d.m
  }
}

function onBlur() {
  if (testo.value === '') return
  const d = daValore(testo.value)
  if (d && valida(d.a, d.m, d.g)) {
    testo.value = `${pad(d.g)}/${pad(d.m + 1)}/${d.a}`
  } else {
    nonValida.value = true
    emit('update:modelValue', '')
  }
}

function apriCalendario() {
  const d = daValore(props.modelValue)
  if (d) { annoVista.value = d.a; meseVista.value = d.m }
  aperto.value = !aperto.value
}
function cambiaMese(delta: number) {
  const m = meseVista.value + delta
  annoVista.value += Math.floor(m / 12)
  meseVista.value = ((m % 12) + 12) % 12
}
function scegli(g: number) {
  const v = iso(annoVista.value, meseVista.value, g)
  testo.value = inTesto(v)
  nonValida.value = false
  emit('update:modelValue', v)
  aperto.value = false
}
function scegliOggi() {
  annoVista.value = adesso.getFullYear()
  meseVista.value = adesso.getMonth()
  scegli(adesso.getDate())
}
function svuota() {
  testo.value = ''
  emit('update:modelValue', '')
  aperto.value = false
}

const giorniNelMese = computed(() => new Date(annoVista.value, meseVista.value + 1, 0).getDate())
// La settimana italiana parte dal lunedì.
const vuotiIniziali = computed(() => (new Date(annoVista.value, meseVista.value, 1).getDay() + 6) % 7)

function fuori(e: MouseEvent) {
  if (aperto.value && root.value && !root.value.contains(e.target as Node)) aperto.value = false
}
onMounted(() => document.addEventListener('mousedown', fuori))
onBeforeUnmount(() => document.removeEventListener('mousedown', fuori))
</script>
