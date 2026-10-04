<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Registro email inviate</h2>
    </template>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
      <p class="text-sm text-gray-500">
        Avvisi, automazioni e notifiche di stato: cosa è partito, cosa è fallito e cosa è stato saltato (con il motivo).
      </p>

      <div class="flex gap-2">
        <button
          v-for="f in filtersList"
          :key="f.value ?? 'all'"
          @click="setFilter(f.value)"
          :class="[
            'text-xs font-medium px-3 py-1.5 rounded-full border transition',
            (filters.status ?? null) === f.value ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'
          ]"
        >{{ f.label }}</button>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
              <th class="text-left px-4 py-2.5">Data</th>
              <th class="text-left px-4 py-2.5">Esito</th>
              <th class="text-left px-4 py-2.5">Tipo</th>
              <th class="text-left px-4 py-2.5">Destinatario</th>
              <th class="text-left px-4 py-2.5">Oggetto</th>
              <th class="text-left px-4 py-2.5">Sinistro</th>
              <th class="text-left px-4 py-2.5">Dettaglio</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="log in logs.data" :key="log.id">
              <td class="px-4 py-2.5 whitespace-nowrap text-gray-600">{{ formatDate(log.created_at) }}</td>
              <td class="px-4 py-2.5">
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statusClass[log.status]]">{{ statusLabel[log.status] }}</span>
              </td>
              <td class="px-4 py-2.5 text-gray-600">{{ tipoLabel[log.tipo] ?? log.tipo }}</td>
              <td class="px-4 py-2.5 text-gray-800">{{ log.to_address ?? '—' }}</td>
              <td class="px-4 py-2.5 text-gray-600 max-w-xs truncate">{{ log.subject ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <Link v-if="log.pratica_id" :href="route('pratiche.show', log.pratica_id)" class="text-indigo-600 hover:underline">#{{ log.pratica_id }}</Link>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-2.5 text-xs text-red-600 max-w-sm">{{ log.error }}</td>
            </tr>
            <tr v-if="logs.data.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-gray-400">Nessuna email registrata.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="logs.last_page > 1" class="flex items-center justify-between text-sm">
        <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="text-indigo-600 hover:underline">← Più recenti</Link>
        <span v-else />
        <span class="text-gray-400">Pagina {{ logs.current_page }} di {{ logs.last_page }}</span>
        <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="text-indigo-600 hover:underline">Meno recenti →</Link>
        <span v-else />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface Log {
  id: number
  tipo: string
  to_address: string | null
  subject: string | null
  status: 'sent' | 'failed' | 'skipped'
  error: string | null
  pratica_id: number | null
  created_at: string
}

defineProps<{
  logs: { data: Log[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null }
  filters: { status: string | null }
}>()

const filtersList = [
  { value: null, label: 'Tutte' },
  { value: 'sent', label: 'Inviate' },
  { value: 'failed', label: 'Fallite' },
  { value: 'skipped', label: 'Saltate' },
]

const statusLabel: Record<string, string> = { sent: 'Inviata', failed: 'Fallita', skipped: 'Saltata' }
const statusClass: Record<string, string> = {
  sent: 'bg-green-50 text-green-700',
  failed: 'bg-red-50 text-red-600',
  skipped: 'bg-amber-50 text-amber-700',
}
const tipoLabel: Record<string, string> = {
  automazione: 'Automazione',
  avviso: 'Promemoria giornaliero',
  stato: 'Cambio stato',
  promemoria_cliente: 'Scadenza cliente',
}

function setFilter(status: string | null) {
  router.get(route('email-log.index'), status ? { status } : {}, { preserveState: true })
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' })
}
</script>
