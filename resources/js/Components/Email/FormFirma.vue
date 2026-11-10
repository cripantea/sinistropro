<template>
  <form @submit.prevent="emit('salva')" class="space-y-2">
    <div>
      <input v-model="modelValue.nome" type="text" maxlength="80" placeholder="Nome (es. Ufficio sinistri)" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
      <p v-if="errori.nome" class="text-xs text-red-600 mt-1">{{ errori.nome }}</p>
    </div>
    <div>
      <textarea v-model="modelValue.testo" rows="4" maxlength="2000" :placeholder="'Testo (facoltativo se carichi l\'immagine)\nMario Rossi — Ufficio sinistri'" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2" />
      <p v-if="errori.testo" class="text-xs text-red-600 mt-1">{{ errori.testo }}</p>
    </div>
    <div>
      <p class="text-xs font-medium text-gray-600 mb-1">Immagine della firma <span class="font-normal text-gray-400">(PNG o JPG, es. firma grafica con logo)</span></p>
      <img v-if="anteprima" :src="anteprima" alt="" class="max-h-28 w-auto rounded border border-gray-100 mb-1.5" />
      <div class="flex items-center gap-3">
        <input type="file" accept="image/png,image/jpeg,image/gif" class="text-xs text-gray-600" @change="scegliFile" />
        <button v-if="anteprima" type="button" class="text-xs text-red-600 hover:underline" @click="togli">Togli immagine</button>
      </div>
      <p v-if="errori.immagine" class="text-xs text-red-600 mt-1">{{ errori.immagine }}</p>
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
import { computed } from 'vue'
import type { BozzaFirma } from '@/Components/Email/GestioneFirme.vue'

const props = defineProps<{ modelValue: BozzaFirma; puoAgenzia: boolean; errori: Record<string, string>; salvando: boolean }>()

const anteprima = computed(() => props.modelValue.immagine ? URL.createObjectURL(props.modelValue.immagine) : (props.modelValue.togliImmagine ? null : props.modelValue.immagineUrl ?? null))
function scegliFile(e: Event) {
  props.modelValue.immagine = (e.target as HTMLInputElement).files?.[0] ?? null
  props.modelValue.togliImmagine = false
}
function togli() {
  props.modelValue.immagine = null
  props.modelValue.togliImmagine = true
}
const emit = defineEmits<{ salva: []; annulla: [] }>()
</script>
