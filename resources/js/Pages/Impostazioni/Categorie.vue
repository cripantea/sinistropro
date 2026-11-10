<template>
  <ImpostazioniLayout>
    <form @submit.prevent="salva">
      <Sezione titolo="Categorie dei documenti" descrizione="Le categorie accese compaiono quando carichi un allegato nel sinistro e si possono allegare alle automazioni.">
        <div class="divide-y divide-gray-100">
          <div v-for="c in form.categorie" :key="c.id" class="flex items-center gap-3 py-2.5">
            <label class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
              <input v-model="c.is_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm truncate" :class="c.is_enabled ? 'text-gray-800 font-medium' : 'text-gray-400'">{{ c.name }}</span>
            </label>
            <div v-if="c.is_enabled" class="flex items-center gap-1.5 text-xs text-gray-500 shrink-0">
              max
              <input v-model.number="c.max_file_size_mb" type="number" min="1" max="500" class="field-input py-1 w-16 text-center" />
              MB
            </div>
          </div>
        </div>
        <template #azioni><BottoneSalva :caricamento="form.processing" :salvato="form.recentlySuccessful" /></template>
      </Sezione>
    </form>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'
import Sezione from '@/Components/Impostazioni/Sezione.vue'
import BottoneSalva from '@/Components/Impostazioni/BottoneSalva.vue'

const props = defineProps<{ categorie: { id: number; name: string; is_enabled: boolean; max_file_size_mb: number }[] }>()
const form = useForm({ categorie: props.categorie.map((c) => ({ ...c })) })
const salva = () => form.post(route('impostazioni.categorie.update'), { preserveScroll: true })
</script>
