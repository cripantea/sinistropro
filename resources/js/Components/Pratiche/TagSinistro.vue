<template>
  <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium leading-4" :class="colore">
    <slot>{{ tag }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ tag: string }>()

// Stesso tag, stesso colore ovunque (scheda, elenco, Board), senza doverlo scegliere.
const PALETTE = [
  'bg-sky-50 text-sky-700 border-sky-200',
  'bg-violet-50 text-violet-700 border-violet-200',
  'bg-amber-50 text-amber-800 border-amber-200',
  'bg-emerald-50 text-emerald-700 border-emerald-200',
  'bg-rose-50 text-rose-700 border-rose-200',
  'bg-indigo-50 text-indigo-700 border-indigo-200',
  'bg-teal-50 text-teal-700 border-teal-200',
  'bg-orange-50 text-orange-700 border-orange-200',
]
const colore = computed(() => {
  let h = 0
  for (const c of props.tag.toLowerCase()) h = (h * 31 + c.charCodeAt(0)) >>> 0
  return PALETTE[h % PALETTE.length]
})
</script>
