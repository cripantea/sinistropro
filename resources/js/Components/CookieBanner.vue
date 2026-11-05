<script setup lang="ts">
import { ref } from 'vue'
import { consent, pixelId, setConsent } from '@/tracking'

// Solo nelle pagine della registrazione: dentro il gestionale non ci sono pixel.
const visible = ref(!!pixelId && consent() === null)

function choose(value: 'si' | 'no') {
  setConsent(value)
  visible.value = false
}
</script>

<template>
  <div v-if="visible" class="fixed inset-x-0 bottom-0 z-50 p-2 sm:p-4 sm:bottom-4 sm:left-4 sm:right-auto sm:max-w-sm">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl px-4 py-3 sm:p-5">
      <p class="text-xs sm:text-sm text-gray-700 leading-snug">
        Usiamo cookie di Meta per misurare le nostre pubblicità. Puoi accettarli o rifiutarli: funziona tutto comunque.
        <a href="https://sinistropro.it/privacy/" target="_blank" rel="noopener" class="underline" style="color: #1e3a8a;">Informativa</a>
      </p>
      <div class="mt-3 grid grid-cols-2 gap-2">
        <button type="button" class="py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="choose('no')">Rifiuta</button>
        <button type="button" class="py-2 rounded-lg text-sm font-semibold text-white" style="background-color: #1e3a8a;" @click="choose('si')">Accetta</button>
      </div>
    </div>
  </div>
</template>
