<template>
  <SuperadminLayout title="Registro Attività">

    <div class="p-6 space-y-5">

      <!-- Filtri -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
        <select
          v-model="filterForm.action"
          @change="applyFilters"
          class="rounded-lg border-slate-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
        >
          <option value="">Tutte le azioni</option>
          <option value="create">Create</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
          <option value="view">View</option>
        </select>

        <select
          v-model="filterForm.tenant_id"
          @change="applyFilters"
          class="rounded-lg border-slate-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
        >
          <option value="">Tutti i tenant</option>
          <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>

        <input
          v-model="filterForm.model"
          @keyup.enter="applyFilters"
          placeholder="Modello (es. Pratica)"
          class="rounded-lg border-slate-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-48"
        />

        <button
          @click="applyFilters"
          class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition"
        >Filtra</button>

        <button
          @click="resetFilters"
          class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded-lg hover:bg-slate-200 transition"
        >Reset</button>
      </div>

      <!-- Tabella -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Data</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tenant</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Utente</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Azione</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Modello</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Modifiche</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="logs.data.length === 0">
              <td colspan="8" class="px-4 py-10 text-center text-slate-400">Nessun log trovato.</td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50 transition">
              <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs">{{ formatDate(log.created_at) }}</td>
              <td class="px-4 py-3">
                <span class="text-xs font-medium text-slate-700">{{ log.tenant?.name ?? '—' }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">{{ log.user?.name ?? '—' }}</div>
                <div v-if="log.impersonator" class="text-xs text-amber-600 mt-0.5">via {{ log.impersonator.name }}</div>
              </td>
              <td class="px-4 py-3">
                <span :class="actionBadgeClass(log.action)" class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ shortType(log.auditable_type) }}</td>
              <td class="px-4 py-3 text-slate-400 text-xs">#{{ log.auditable_id }}</td>
              <td class="px-4 py-3 max-w-xs">
                <div v-if="log.old_values || log.new_values">
                  <button @click="toggleDiff(log.id)" class="text-indigo-600 hover:underline text-xs">
                    {{ openDiffs.has(log.id) ? 'Nascondi' : 'Mostra diff' }}
                  </button>
                  <div v-if="openDiffs.has(log.id)" class="mt-2 space-y-1">
                    <div v-for="(val, key) in log.new_values" :key="key" class="text-xs">
                      <span class="text-slate-500">{{ key }}:</span>
                      <span class="line-through text-red-500 ml-1">{{ log.old_values?.[key] ?? '—' }}</span>
                      <span class="text-green-600 ml-1">→ {{ val }}</span>
                    </div>
                  </div>
                </div>
                <span v-else class="text-slate-300 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-slate-400 text-xs">{{ log.ip_address ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginazione -->
      <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ logs.from }}–{{ logs.to }} di {{ logs.total }} risultati</p>
        <div class="flex gap-1">
          <Link
            v-for="link in logs.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :class="[
              'px-3 py-1 rounded text-xs border transition',
              link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-slate-600 border-slate-300 hover:bg-slate-100',
              !link.url ? 'opacity-40 pointer-events-none' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>

    </div>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'

interface LogUser  { id: number; name: string; email: string }
interface LogTenant { id: number; name: string }

interface AuditLogEntry {
  id: number
  action: string
  auditable_type: string
  auditable_id: number
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
  user: LogUser | null
  impersonator: LogUser | null
  tenant: LogTenant | null
}

interface PaginatedLogs {
  data: AuditLogEntry[]
  from: number; to: number; total: number
  links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
  logs: PaginatedLogs
  tenants: { id: number; name: string }[]
  filters: { action?: string; tenant_id?: number; model?: string }
}>()

const filterForm = reactive({
  action:    props.filters.action    ?? '',
  tenant_id: props.filters.tenant_id ?? '',
  model:     props.filters.model     ?? '',
})

const openDiffs = ref(new Set<number>())

function toggleDiff(id: number) {
  openDiffs.value.has(id) ? openDiffs.value.delete(id) : openDiffs.value.add(id)
}

function applyFilters() {
  router.get(route('superadmin.audit-logs'), filterForm, { preserveState: true, replace: true })
}

function resetFilters() {
  filterForm.action = ''
  filterForm.tenant_id = ''
  filterForm.model = ''
  router.get(route('superadmin.audit-logs'), {}, { replace: true })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' })
}

const MODEL_LABELS: Record<string, string> = { Pratica: 'Sinistro' }

function shortType(type: string): string {
  const short = type.split('\\').pop() ?? type
  return MODEL_LABELS[short] ?? short
}

function actionBadgeClass(action: string): string {
  const map: Record<string, string> = {
    create: 'bg-green-100 text-green-700',
    update: 'bg-blue-100 text-blue-700',
    delete: 'bg-red-100 text-red-700',
    view:   'bg-slate-100 text-slate-600',
  }
  return map[action] ?? 'bg-slate-100 text-slate-600'
}
</script>
