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

const props = defineProps<{ agenzia: { name: string; default_notice_days: number } }>()
const form = useForm({ ...props.agenzia })
const salva = () => form.post(route('impostazioni.generale.update'), { preserveScroll: true })
</script>
