<template>
  <section class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <header class="px-5 pt-5">
      <h3 class="text-base font-semibold text-gray-800">{{ titolo }}</h3>
      <p v-if="descrizione" class="text-sm text-gray-500 mt-1">{{ descrizione }}</p>
    </header>
    <div class="p-5"><slot /></div>
    <footer v-if="$slots.azioni" class="px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex items-center gap-3">
      <slot name="azioni" />
    </footer>
  </section>
</template>

<script setup lang="ts">
defineProps<{ titolo: string; descrizione?: string }>()
</script>
