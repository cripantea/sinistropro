<template>
  <AutomationConfirmModal
    :show="aperta"
    :automations="piani"
    :rubrica="rubrica"
    block-automations-label="Non inviare nulla"
    block-action-label="Chiudi"
    @accept="conferma"
    @block-automations="nessuna"
    @block-action="nessuna"
  />
</template>

<script setup lang="ts">
// Dopo l'apertura di un sinistro: se ci sono automazioni "Nuovo sinistro aperto" si chiede conferma
// (testo e destinatari), come per i cambi di stato. Montato nel layout: vale per scheda e Board.
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AutomationConfirmModal, { type AutomationOverrides, type AutomationPlan, type RubricaContatto } from '@/Components/AutomationConfirmModal.vue'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const aperta = ref(false)
const piani = ref<AutomationPlan[]>([])
const rubrica = ref<RubricaContatto[]>([])
const praticaId = ref<number | null>(null)
const giaChiesto = new Set<number>()

watch(() => page.props.flash?.apertura_pratica_id, async (id) => {
  if (!id || giaChiesto.has(id)) return
  giaChiesto.add(id)
  try {
    const { data } = await axios.post(route('pratiche.automations.preview', id), { apertura: true })
    if (!data.automations?.length) return
    praticaId.value = id
    piani.value = data.automations
    rubrica.value = data.rubrica ?? []
    aperta.value = true
  } catch {
    // Senza anteprima non si invia nulla di nascosto: la finestra semplicemente non compare.
  }
}, { immediate: true })

async function invia(payload: Record<string, unknown>) {
  aperta.value = false
  if (praticaId.value) await axios.post(route('pratiche.automations.apertura', praticaId.value), payload)
}
const conferma = (overrides: AutomationOverrides) => invia({ automation_overrides: overrides })
const nessuna = () => invia({ skip: true })
</script>
