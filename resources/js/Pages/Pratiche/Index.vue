<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">I tuoi Sinistri</h2>
        <Link
          :href="route('pratiche.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Nuovo Sinistro
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
            placeholder="Cerca per ID, cliente o valore campo…"
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

        <!-- Selettore campo personalizzato: aggiunge una colonna, la rende ordinabile e filtrabile -->
        <select
          v-if="customFieldsSchema.length > 0"
          v-model="filterField"
          class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
        >
          <option value="">+ Campo…</option>
          <option v-for="f in customFieldsSchema" :key="f.name" :value="f.name">{{ f.label }}</option>
        </select>

        <template v-if="selectedFieldSchema">
          <select
            v-if="selectedFieldSchema.type === 'boolean'"
            v-model="filterValue"
            class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
          >
            <option value="">Tutti</option>
            <option value="true">Sì</option>
            <option value="false">No</option>
          </select>
          <input
            v-else
            v-model="filterValue"
            :type="selectedFieldSchema.type === 'date' ? 'date' : selectedFieldSchema.type === 'number' ? 'number' : 'text'"
            :placeholder="`Filtra ${selectedFieldSchema.label}…`"
            class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          />
        </template>

        <button v-if="hasFilters" @click="resetFilters" class="text-xs text-gray-500 hover:text-red-500 transition underline">
          Resetta
        </button>

        <span class="ml-auto text-sm text-gray-400">{{ pratiche.total }} sinistri</span>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="px-5 py-3 text-left font-medium text-gray-500 w-16 cursor-pointer select-none hover:text-gray-700" @click="sortBy('id')">
                ID <SortIcon :active="sortField === 'id'" :dir="sortDir" />
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700" @click="sortBy('current_status_id')">
                Stato <SortIcon :active="sortField === 'current_status_id'" :dir="sortDir" />
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700" @click="sortBy('cliente')">
                Cliente <SortIcon :active="sortField === 'cliente'" :dir="sortDir" />
              </th>
              <th
                v-if="selectedFieldSchema"
                class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700"
                @click="sortBy(selectedFieldSchema.name)"
              >
                {{ selectedFieldSchema.label }} <SortIcon :active="sortField === selectedFieldSchema.name" :dir="sortDir" />
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700" @click="sortBy('data_prossimo_avviso')">
                Prossimo avviso <SortIcon :active="sortField === 'data_prossimo_avviso'" :dir="sortDir" />
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700" @click="sortBy('utente_creatore')">
                Creata da <SortIcon :active="sortField === 'utente_creatore'" :dir="sortDir" />
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 cursor-pointer select-none hover:text-gray-700" @click="sortBy('created_at')">
                Aperta il <SortIcon :active="sortField === 'created_at'" :dir="sortDir" />
              </th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="pratiche.data.length === 0">
              <td :colspan="selectedFieldSchema ? 8 : 7" class="px-5 py-10 text-center text-gray-400">Nessun sinistro trovato.</td>
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

              <td class="px-5 py-3.5">
                <span v-if="pratica.cliente" class="text-sm font-medium text-gray-800">{{ pratica.cliente.nome }}</span>
                <span v-else class="text-gray-300 text-xs italic">—</span>
              </td>

              <td v-if="selectedFieldSchema" class="px-5 py-3.5 text-xs text-gray-600">
                {{ formatFieldValue(pratica.custom_fields?.[selectedFieldSchema.name], selectedFieldSchema.type) }}
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
import SortIcon from '@/Components/SortIcon.vue'

interface Status { id: number; name: string; color: string }
interface CustomFieldSchema { name: string; label: string; type: 'text' | 'date' | 'number' | 'boolean' }
interface PraticaRow {
  id: number
  current_status: Status | null
  cliente: { id: number; nome: string } | null
  custom_fields: Record<string, string | boolean> | null
  data_prossimo_avviso: string | null
  utente_creatore: { name: string } | null
  created_at: string
}
interface PaginationLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
  pratiche: { data: PraticaRow[]; from: number; to: number; total: number; links: PaginationLink[] }
  statuses: Status[]
  customFieldsSchema: CustomFieldSchema[]
  filters: { search?: string; status_id?: string; sort_field?: string; sort_dir?: string; filter_field?: string; filter_value?: string }
}>()

const search         = ref(props.filters.search ?? '')
const selectedStatus = ref(props.filters.status_id ?? '')
const sortField       = ref(props.filters.sort_field ?? 'data_prossimo_avviso')
const sortDir         = ref<'asc' | 'desc'>(props.filters.sort_dir === 'asc' ? 'asc' : 'desc')
const filterField     = ref(props.filters.filter_field ?? '')
const filterValue     = ref(props.filters.filter_value ?? '')

const hasFilters = computed(() => !!search.value || !!selectedStatus.value || !!filterField.value)

const selectedFieldSchema = computed(() =>
  props.customFieldsSchema.find(f => f.name === filterField.value) ?? null
)

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, (v) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => navigate(), v ? 350 : 0)
})
watch(selectedStatus, () => navigate())
watch(sortField, () => navigate())
watch(sortDir, () => navigate())

let filterValueTimer: ReturnType<typeof setTimeout> | null = null
watch(filterValue, (v) => {
  if (filterValueTimer) clearTimeout(filterValueTimer)
  filterValueTimer = setTimeout(() => navigate(), v ? 350 : 0)
})
watch(filterField, () => {
  filterValue.value = ''
  navigate()
})

function sortBy(field: string) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

function navigate() {
  router.get(route('pratiche.index'), {
    search:       search.value || undefined,
    status_id:    selectedStatus.value || undefined,
    sort_field:   sortField.value || undefined,
    sort_dir:     sortDir.value || undefined,
    filter_field: filterField.value || undefined,
    filter_value: filterValue.value || undefined,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  search.value = ''
  selectedStatus.value = ''
  filterField.value = ''
  filterValue.value = ''
}

function goTo(id: number) { router.visit(route('pratiche.show', id)) }

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}

function isOverdue(iso: string): boolean {
  return new Date(iso) < new Date()
}

function formatFieldValue(value: string | boolean | undefined, type: string): string {
  if (value === undefined || value === null || value === '') return '—'
  if (type === 'boolean') return value === true || value === 'true' ? 'Sì' : 'No'
  if (type === 'date') return formatDate(String(value))
  return String(value)
}
</script>
