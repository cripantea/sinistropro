<template>
  <Teleport to="body">
    <div v-if="src" class="fixed inset-0 z-[100] bg-black/85 flex flex-col" @click.self="$emit('close')">
      <div class="flex items-center justify-between gap-3 px-4 py-3 text-white shrink-0">
        <p class="text-sm truncate">{{ titolo }}</p>
        <div class="flex items-center gap-2 shrink-0">
          <a :href="src" target="_blank" rel="noopener" class="text-sm px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20">Apri originale</a>
          <button type="button" @click="$emit('close')" class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center" aria-label="Chiudi">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <div class="flex-1 min-h-0 flex items-center justify-center p-4" @click.self="$emit('close')">
        <img :src="src" :alt="titolo" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" />
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'

defineProps<{ src: string | null; titolo?: string }>()
const emit = defineEmits<{ close: [] }>()

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>
