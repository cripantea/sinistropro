<template>
  <ImpostazioniLayout>
    <form @submit.prevent="salva">
      <Sezione titolo="Dati dell'agenzia" descrizione="Il nome compare nelle email inviate ai clienti e nei moduli.">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="field-label">Nome dell'agenzia</label>
            <input v-model="form.name" type="text" class="field-input" />
            <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="field-label">Giorni di avviso predefiniti</label>
            <input v-model.number="form.default_notice_days" type="number" min="1" max="365" class="field-input" />
            <p class="text-xs text-gray-500 mt-1">Ogni quanti giorni ricordarti un sinistro aperto. Puoi cambiarli per singolo stato in <Link :href="route('impostazioni.stati')" class="underline">Stati e avvisi</Link>.</p>
            <p v-if="form.errors.default_notice_days" class="text-sm text-red-600 mt-1">{{ form.errors.default_notice_days }}</p>
          </div>
        </div>
        <div class="mt-4 sm:w-1/2 sm:pr-2">
          <label class="field-label">Numero del sinistro</label>
          <select v-model="form.numero_sinistro_campo" class="field-input">
            <option :value="null">Numero interno di SinistroPro (#123)</option>
            <option v-for="c in campiTesto" :key="c.name" :value="c.name">{{ c.label }}</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">Scegliendo un campo (es. il numero sinistro della compagnia) quel numero si vede in Board, elenco, scheda e nei messaggi. Se nel sinistro è vuoto resta #123.</p>
          <p v-if="form.errors.numero_sinistro_campo" class="text-sm text-red-600 mt-1">{{ form.errors.numero_sinistro_campo }}</p>
        </div>
        <template #azioni><BottoneSalva :caricamento="form.processing" :salvato="form.recentlySuccessful" /></template>
      </Sezione>
    </form>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'
import Sezione from '@/Components/Impostazioni/Sezione.vue'
import BottoneSalva from '@/Components/Impostazioni/BottoneSalva.vue'

const props = defineProps<{
  agenzia: { name: string; default_notice_days: number; numero_sinistro_campo: string | null }
  campiTesto: { name: string; label: string }[]
}>()
const form = useForm({ ...props.agenzia })
const salva = () => form.post(route('impostazioni.generale.update'), { preserveScroll: true })
</script>
