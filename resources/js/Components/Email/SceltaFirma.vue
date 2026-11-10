<template>
  <div class="text-xs">
    <div class="flex items-center gap-2">
      <label class="text-gray-500 shrink-0">Firma</label>
      <select :value="modelValue ?? ''" @change="scegli" class="text-xs border border-gray-300 rounded-md py-1 pl-2 pr-7 bg-white max-w-[14rem]">
        <option value="">Nessuna firma</option>
        <option v-for="f in stato.firme" :key="f.id" :value="f.id">{{ f.nome }}{{ f.agenzia ? ' (agenzia)' : '' }}</option>
      </select>
      <button type="button" @click="gestione = true" class="text-indigo-600 hover:underline shrink-0">{{ stato.firme.length ? 'Gestisci firme' : '+ Crea una firma' }}</button>
    </div>
    <p v-if="scelta" class="mt-1.5 text-gray-500 whitespace-pre-line border-l-2 border-gray-200 pl-2 line-clamp-4">{{ scelta.testo }}</p>

    <GestioneFirme v-if="gestione" :stato="stato" @close="gestione = false" @creata="(id) => emit('update:modelValue', id)" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import GestioneFirme, { type StatoFirme } from '@/Components/Email/GestioneFirme.vue'

const props = defineProps<{ stato: StatoFirme; modelValue: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>()

const gestione = ref(false)
const scelta = computed(() => props.stato.firme.find((f) => f.id === props.modelValue) ?? null)

function scegli(e: Event) {
  const v = (e.target as HTMLSelectElement).value
  emit('update:modelValue', v ? Number(v) : null)
}
</script>
