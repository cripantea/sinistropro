<template>
  <form @submit.prevent="emit('salva')" class="space-y-2">
    <div>
      <input v-model="modelValue.nome" type="text" maxlength="80" placeholder="Nome (es. Ufficio sinistri)" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
      <p v-if="errori.nome" class="text-xs text-red-600 mt-1">{{ errori.nome }}</p>
    </div>
    <div>
      <textarea v-model="modelValue.testo" rows="5" maxlength="2000" :placeholder="'Mario Rossi\nUfficio sinistri — Agenzia …\nTel. 02 …'" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
      <p v-if="errori.testo" class="text-xs text-red-600 mt-1">{{ errori.testo }}</p>
    </div>
    <label v-if="puoAgenzia" class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
      <input v-model="modelValue.agenzia" type="checkbox" class="rounded text-indigo-600" />
      Per tutta l'agenzia (la possono scegliere tutti i colleghi)
    </label>
    <div class="flex justify-end gap-2">
      <button type="button" @click="emit('annulla')" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 rounded-lg">Annulla</button>
      <button type="submit" :disabled="salvando" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">{{ salvando ? 'Salvo…' : 'Salva firma' }}</button>
    </div>
  </form>
</template>

<script setup lang="ts">
import type { BozzaFirma } from '@/Components/Email/GestioneFirme.vue'

defineProps<{ modelValue: BozzaFirma; puoAgenzia: boolean; errori: Record<string, string>; salvando: boolean }>()
const emit = defineEmits<{ salva: []; annulla: [] }>()
</script>
