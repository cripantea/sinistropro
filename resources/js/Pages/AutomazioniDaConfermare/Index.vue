<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Promemoria da confermare</h2>
    </template>

    <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
      {{ flash.success }}
    </div>

    <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
      <p class="text-sm text-gray-500">
        Messaggi programmati (es. scadenza patente o revisione) pronti a partire. Nulla viene inviato finché non lo confermi:
        puoi rivedere il testo, togliere o aggiungere destinatari, oppure scartarlo.
      </p>

      <div v-for="it in items" :key="it.id" class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-800 truncate">{{ it.plan.name }} — {{ it.cliente }}</p>
          <p class="text-xs text-gray-500 mt-0.5">
            Scadenza {{ formatDate(it.field_value) }} · {{ it.plan.recipients.length }} destinatari
            <span v-if="it.plan.recipients.every(r => !r.email && !r.phone)" class="text-amber-600">· il cliente non ha email né telefono</span>
          </p>
        </div>
        <button @click="open(it)" class="bg-indigo-600 text-white text-xs font-semibold px-3.5 py-2 rounded-lg hover:bg-indigo-700 transition">Rivedi e invia</button>
        <button @click="discard(it.id)" class="text-xs text-gray-400 hover:text-red-600">Scarta</button>
      </div>

      <div v-if="items.length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-12 text-center text-gray-400 text-sm">
        Nessun promemoria in attesa.
      </div>
    </div>

    <AutomationConfirmModal
      :show="current !== null"
      :automations="current ? [current.plan] : []"
      block-automations-label="Scarta questo promemoria"
      block-action-label="Chiudi"
      @accept="onAccept"
      @block-automations="onDiscard"
      @block-action="current = null"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AutomationConfirmModal, { type AutomationPlan, type AutomationOverrides } from '@/Components/AutomationConfirmModal.vue'
import type { PageProps } from '@/types'

interface Item { id: number; cliente: string; field_value: string; plan: AutomationPlan }

defineProps<{ items: Item[] }>()
const flash = computed(() => usePage<PageProps>().props.flash)

const current = ref<Item | null>(null)

const formatDate = (d: string) => new Date(d).toLocaleDateString('it-IT')

function open(it: Item) { current.value = it }

function onAccept(overrides: AutomationOverrides) {
  if (!current.value) return
  const id = current.value.id
  current.value = null
  router.post(route('automation-approvals.confirm', id), { automation_overrides: overrides } as never, { preserveScroll: true })
}

function onDiscard() {
  if (!current.value) return
  discard(current.value.id)
  current.value = null
}

function discard(id: number) {
  router.post(route('automation-approvals.discard', id), {}, { preserveScroll: true })
}
</script>
