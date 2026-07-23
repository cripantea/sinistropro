<template>
  <div class="relative" ref="root">
    <input
      ref="inputEl"
      :id="id"
      type="text"
      :value="open ? query : (selectedOption?.label ?? '')"
      @focus="onFocus"
      @input="onInput"
      @keydown.down.prevent="moveHighlight(1)"
      @keydown.up.prevent="moveHighlight(-1)"
      @keydown.enter.prevent="selectHighlighted"
      @keydown.esc="close"
      :placeholder="placeholder"
      autocomplete="off"
      class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-8 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
    />
    <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>

    <ul
      v-if="open"
      class="absolute z-20 mt-1 w-full max-h-60 overflow-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1"
    >
      <li
        v-for="(opt, i) in filtered"
        :key="opt.id"
        @mousedown.prevent="select(opt)"
        @mouseenter="highlighted = i"
        :class="[
          'px-3 py-2 text-sm cursor-pointer',
          i === highlighted ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700'
        ]"
      >
        {{ opt.label }}
      </li>
      <li v-if="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400 italic">
        Nessun risultato
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

interface Option {
  id: number
  label: string
}

const props = defineProps<{
  modelValue: number | null
  options: Option[]
  placeholder?: string
  id?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const root = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLInputElement | null>(null)
const open = ref(false)
const query = ref('')
const highlighted = ref(0)

const selectedOption = computed(
  () => props.options.find(o => o.id === props.modelValue) ?? null
)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter(o => o.label.toLowerCase().includes(q))
})

function onFocus() {
  open.value        = true
  query.value        = ''
  highlighted.value = 0
}

function onInput(e: Event) {
  query.value = (e.target as HTMLInputElement).value
  open.value = true
  highlighted.value = 0
}

function moveHighlight(delta: number) {
  if (!open.value) {
    open.value = true
    return
  }
  const max = filtered.value.length - 1
  if (max < 0) return
  highlighted.value = Math.min(max, Math.max(0, highlighted.value + delta))
}

function selectHighlighted() {
  const opt = filtered.value[highlighted.value]
  if (opt) select(opt)
}

function select(opt: Option) {
  emit('update:modelValue', opt.id)
  query.value = ''
  open.value  = false
}

function close() {
  open.value  = false
  query.value = ''
  inputEl.value?.blur()
}

function onClickOutside(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) close()
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))

watch(() => props.options, () => { highlighted.value = 0 })
</script>
