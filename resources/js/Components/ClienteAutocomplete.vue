<template>
  <div class="relative" ref="root">
    <div class="relative">
      <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
      <input
        :id="id"
        ref="input"
        v-model="q"
        type="text"
        autocomplete="off"
        :placeholder="placeholder"
        class="block w-full rounded-lg border border-gray-300 pl-9 pr-9 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
        @focus="open = q.length >= 2"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="onEnter"
        @keydown.esc="open = false"
      />
      <svg v-if="loading" class="animate-spin w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
    </div>

    <div v-if="open" class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
      <ul v-if="risultati.length" class="max-h-80 overflow-y-auto py-1">
        <li
          v-for="(r, i) in risultati"
          :key="r.id"
          @mousedown.prevent="choose(r)"
          @mouseenter="active = i"
          :class="['px-3 py-2 cursor-pointer', i === active ? 'bg-indigo-50' : '']"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-gray-900 truncate">{{ r.nome }}</span>
            <span v-if="r.veicolo" class="shrink-0 font-mono text-xs font-semibold bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ r.veicolo.targa }}</span>
            <span v-else-if="r.veicoli_count" class="shrink-0 text-xs text-gray-400">{{ r.veicoli_count }} veicol{{ r.veicoli_count === 1 ? 'o' : 'i' }}</span>
          </div>
          <p v-if="r.dettaglio" class="text-xs text-gray-500 truncate">{{ r.dettaglio }}</p>
        </li>
      </ul>
      <p v-else-if="!loading" class="px-3 py-3 text-sm text-gray-500">Nessun cliente trovato per "{{ q }}".</p>
      <button
        type="button"
        @mousedown.prevent="$emit('nuovo', q); open = false"
        class="w-full text-left px-3 py-2 text-sm font-medium text-green-700 border-t border-gray-100 hover:bg-green-50"
      >+ Aggiungi nuovo cliente</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'

export interface RisultatoCliente {
  id: number
  nome: string
  dettaglio: string
  veicoli_count: number
  veicolo: { id: number; targa: string; marca: string | null; modello: string | null } | null
}

withDefaults(defineProps<{ id?: string; placeholder?: string }>(), {
  placeholder: 'Cerca per nome, telefono, codice fiscale o targa…',
})
const emit = defineEmits<{ select: [r: RisultatoCliente]; nuovo: [testo: string] }>()

const q = ref('')
const risultati = ref<RisultatoCliente[]>([])
const active = ref(0)
const open = ref(false)
const loading = ref(false)
const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)

let timer: ReturnType<typeof setTimeout> | null = null
let seq = 0
let invioInAttesa = false

function onEnter() {
  if (loading.value || timer) {
    invioInAttesa = true
    return
  }
  choose(risultati.value[active.value])
}

// Si cerca sul server mentre si digita (con una breve pausa), mostrando solo l'ultima risposta.
watch(q, (val) => {
  if (timer) clearTimeout(timer)
  timer = null
  if (val.trim().length < 2) {
    invioInAttesa = false
    risultati.value = []
    open.value = false
    return
  }
  timer = setTimeout(async () => {
    timer = null
    const mine = ++seq
    loading.value = true
    open.value = true
    try {
      const { data } = await axios.get(route('clienti.cerca'), { params: { q: val.trim() } })
      if (mine === seq) {
        risultati.value = data.risultati
        active.value = 0
        // Invio premuto mentre la ricerca era in corso: si prende il primo risultato appena arriva.
        if (invioInAttesa) {
          invioInAttesa = false
          choose(risultati.value[0])
        }
      }
    } finally {
      if (mine === seq) loading.value = false
    }
  }, 200)
})

function move(delta: number) {
  if (!risultati.value.length) return
  open.value = true
  active.value = (active.value + delta + risultati.value.length) % risultati.value.length
}

function choose(r: RisultatoCliente | undefined) {
  if (!r) return
  emit('select', r)
  open.value = false
  q.value = ''
}

function onClickOutside(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  input.value?.focus()
})
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>
