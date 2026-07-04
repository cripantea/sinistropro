<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Registro Attività</h2>
    </template>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Filtri -->
      <div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-3">
        <select
          v-model="filterForm.action"
          @change="applyFilters"
          class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
        >
          <option value="">Tutte le azioni</option>
          <option value="create">Create</option>
          <option value="view">View</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
        </select>

        <input
          v-model="filterForm.model"
          @keyup.enter="applyFilters"
          placeholder="Filtra per modello (es. Pratica)"
          class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-56"
        />

        <button
          @click="applyFilters"
          class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition"
        >Filtra</button>

        <button
          @click="resetFilters"
          class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition"
        >Reset</button>
      </div>

      <!-- Tabella -->
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Data</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Utente</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Azione</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Modello</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Modifiche</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="logs.data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400">Nessun log trovato.</td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">{{ log.user?.name ?? '—' }}</div>
                <div v-if="log.impersonator" class="text-xs text-amber-600 mt-0.5">
                  via {{ log.impersonator.name }}
                </div>
              </td>
              <td class="px-4 py-3">
                <span :class="actionBadgeClass(log.action)" class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ shortType(log.auditable_type) }}</td>
              <td class="px-4 py-3 text-gray-500">#{{ log.auditable_id }}</td>
              <td class="px-4 py-3 max-w-xs">
                <div v-if="log.old_values || log.new_values">
                  <button
                    @click="toggleDiff(log.id)"
                    class="text-indigo-600 hover:underline text-xs"
                  >
                    {{ openDiffs.has(log.id) ? 'Nascondi' : 'Mostra diff' }}
                  </button>
                  <div v-if="openDiffs.has(log.id)" class="mt-2 space-y-1">
                    <div v-for="(val, key) in log.new_values" :key="key" class="text-xs">
                      <span class="text-gray-500">{{ key }}:</span>
                      <span class="line-through text-red-500 ml-1">{{ log.old_values?.[key] ?? '—' }}</span>
                      <span class="text-green-600 ml-1">→ {{ val }}</span>
                    </div>
                  </div>
                </div>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-gray-400 text-xs">{{ log.ip_address }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginazione -->
      <div class="mt-6 flex items-center justify-between">
        <p class="text-sm text-gray-500">
          {{ logs.from }}–{{ logs.to }} di {{ logs.total }} risultati
        </p>
        <div class="flex gap-2">
          <Link
            v-for="link in logs.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :class="[
              'px-3 py-1 rounded text-sm border',
              link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-100',
              !link.url ? 'opacity-40 cursor-not-allowed' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

interface AuditLogUser {
  id: number
  name: string
  email: string
}

interface AuditLogEntry {
  id: number
  action: string
  auditable_type: string
  auditable_id: number
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
  user: AuditLogUser | null
  impersonator: AuditLogUser | null
}

interface PaginatedLogs {
  data: AuditLogEntry[]
  from: number
  to: number
  total: number
  links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
  logs: PaginatedLogs
  filters: { action?: string; user_id?: number; model?: string }
}>()

const filterForm = reactive({
  action: props.filters.action ?? '',
  model: props.filters.model ?? '',
})

const openDiffs = ref(new Set<number>())

function toggleDiff(id: number) {
  if (openDiffs.value.has(id)) {
    openDiffs.value.delete(id)
  } else {
    openDiffs.value.add(id)
  }
}

function applyFilters() {
  router.get(route('audit-logs.index'), filterForm, { preserveState: true, replace: true })
}

function resetFilters() {
  filterForm.action = ''
  filterForm.model = ''
  router.get(route('audit-logs.index'), {}, { replace: true })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' })
}

function shortType(type: string): string {
  return type.split('\\').pop() ?? type
}

function actionBadgeClass(action: string): string {
  const map: Record<string, string> = {
    create: 'bg-green-100 text-green-700',
    update: 'bg-blue-100 text-blue-700',
    delete: 'bg-red-100 text-red-700',
    view:   'bg-gray-100 text-gray-600',
  }
  return map[action] ?? 'bg-gray-100 text-gray-600'
}
</script>
