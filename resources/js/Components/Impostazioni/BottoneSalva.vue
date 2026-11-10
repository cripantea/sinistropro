<template>
  <button type="submit" :disabled="caricamento" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
    <svg v-if="caricamento" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
    {{ caricamento ? 'Salvataggio…' : (testo || 'Salva') }}
  </button>
  <span v-if="salvato" class="text-sm text-emerald-600 font-medium">Salvato ✓</span>
</template>

<script setup lang="ts">
defineProps<{ caricamento: boolean; salvato?: boolean; testo?: string }>()
</script>
