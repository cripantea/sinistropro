<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Le tue Pratiche</h2>
        <Link
          :href="route('pratiche.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Nuova Pratica
        </Link>
      </div>
    </template>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px]">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="search"
            type="search"
            placeholder="Cerca per ID o valore campo…"
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          />
        </div>

        <select
          v-model="selectedStatus"
          class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
        >
          <option value="">Tutti gli stati</option>
          <option v-for="s in statuses" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
        </select>

        <button v-if="hasFilters" @click="resetFilters" class="text-xs text-gray-500 hover:text-red-500 transition underline">
          Resetta
        </button>

        <span class="ml-auto text-sm text-gray-400">{{ pratiche.total }} pratiche</span>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="px-5 py-3 text-left font-medium text-gray-500 w-16">ID</th>
              <th class="px-5 py-3 text-left font-medium text-gray-500">Stato</th>
              <th class="px-5 py-3 text-left font-medium text-gray-500">Campi principali</th>
              <th class="px-5 py-3 text-left font-medium text-gray-500">Prossimo avviso</th>
              <th class="px-5 py-3 text-left font-medium text-gray-500">Creata da</th>
              <th class="px-5 py-3 text-left font-medium text-gray-500">Aperta il</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="pratiche.data.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-gray-400">Nessuna pratica trovata.</td>
            </tr>
            <tr
              v-for="pratica in pratiche.data"
              :key="pratica.id"
              class="hover:bg-gray-50 transition-colors group cursor-pointer"
              @click="goTo(pratica.id)"
            >
              <td class="px-5 py-3.5 text-gray-400 tabular-nums font-mono text-xs">#{{ pratica.id }}</td>

              <td class="px-5 py-3.5">
                <span
                  v-if="pratica.current_status"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :style="{ backgroundColor: pratica.current_status.color + '22', color: pratica.current_status.color }"
                >
                  {{ pratica.current_status.name }}
                </span>
                <span v-else class="text-gray-300 text-xs italic">—</span>
              </td>

              <td class="px-5 py-3.5 max-w-xs">
                <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                  <span
                    v-for="(val, key) in summarize(pratica.custom_fields)"
                    :key="key"
                    class="text-xs text-gray-600"
                  >
                    <span class="text-gray-400">{{ String(key).replace(/_/g, ' ') }}:</span>
                    {{ val }}
                  </span>
                </div>
              </td>

              <td class="px-5 py-3.5">
                <span
                  v-if="pratica.data_prossimo_avviso"
                  :class="isOverdue(pratica.data_prossimo_avviso) ? 'text-red-600 font-semibold' : 'text-gray-700'"
                  class="text-xs tabular-nums"
                >
                  {{ formatDate(pratica.data_prossimo_avviso) }}
                </span>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>

              <td class="px-5 py-3.5 text-gray-600 text-xs">{{ pratica.utente_creatore?.name ?? '—' }}</td>
              <td class="px-5 py-3.5 text-gray-400 text-xs tabular-nums">{{ formatDate(pratica.created_at) }}</td>

              <td class="px-5 py-3.5 text-right">
                <Link
                  :href="route('pratiche.show', pratica.id)"
                  class="text-xs text-indigo-600 hover:underline opacity-0 group-hover:opacity-100 transition-opacity"
                  @click.stop
                >
                  Apri →
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
          <span class="text-xs text-gray-500">{{ pratiche.from }}–{{ pratiche.to }} di {{ pratiche.total }}</span>
          <div class="flex gap-1">
            <Link
              v-for="link in pratiche.links"
              :key="link.label"
              :href="link.url ?? '#'"
              :class="[
                'px-3 py-1 rounded text-xs border transition',
                link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-300 hover:bg-gray-100',
                !link.url ? 'opacity-40 pointer-events-none' : ''
              ]"
              preserve-state
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface Status { id: number; name: string; color: string }
interface PraticaRow {
  id: number
  current_status: Status | null
  custom_fields: Record<string, string> | null
  data_prossimo_avviso: string | null
  utente_creatore: { name: string } | null
  created_at: string
}
interface PaginationLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
  pratiche: { data: PraticaRow[]; from: number; to: number; total: number; links: PaginationLink[] }
  statuses: Status[]
  filters: { search?: string; status_id?: string }
}>()

const search         = ref(props.filters.search ?? '')
const selectedStatus = ref(props.filters.status_id ?? '')
const hasFilters     = computed(() => !!search.value || !!selectedStatus.value)

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, (v) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => navigate(), v ? 350 : 0)
})
watch(selectedStatus, () => navigate())

function navigate() {
  router.get(route('pratiche.index'), {
    search:    search.value || undefined,
    status_id: selectedStatus.value || undefined,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  search.value = ''
  selectedStatus.value = ''
}

function goTo(id: number) { router.visit(route('pratiche.show', id)) }

function summarize(fields: Record<string, string> | null, max = 3): Record<string, string> {
  if (!fields) return {}
  return Object.fromEntries(Object.entries(fields).slice(0, max))
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}

function isOverdue(iso: string): boolean {
  return new Date(iso) < new Date()
}
</script>
