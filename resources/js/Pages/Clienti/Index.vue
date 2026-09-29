<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h1 class="text-base font-semibold text-slate-800">Clienti</h1>
        <div class="flex items-center gap-2">
          <Link
            v-if="$page.props.tenantFeatures?.import_clienti"
            :href="route('clienti.import')"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-700 border border-slate-300 px-3 py-2 rounded-lg hover:bg-slate-100 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Importa
          </Link>
          <Link
            :href="route('clienti.create')"
            class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuovo Cliente
          </Link>
        </div>
      </div>
    </template>

    <div class="p-6 space-y-5">

      <!-- Search -->
      <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            v-model="search"
            type="text"
            placeholder="Cerca per nome, telefono, email…"
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
            @input="onSearch"
          />
        </div>
        <span class="text-sm text-slate-500">{{ clienti.total }} clienti</span>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200">
              <th class="px-5 py-3 text-left font-medium text-slate-500">Nome</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Telefono</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Email</th>
              <th
                v-for="field in dateFields"
                :key="field.name"
                class="px-5 py-3 text-left font-medium text-slate-500"
              >
                {{ field.label }}
              </th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Pratiche</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Creato</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="clienti.data.length === 0">
              <td :colspan="5 + dateFields.length" class="px-5 py-12 text-center text-slate-400">
                {{ filters.search ? 'Nessun risultato per la ricerca.' : 'Nessun cliente ancora. Creane uno!' }}
              </td>
            </tr>
            <tr
              v-for="cliente in clienti.data"
              :key="cliente.id"
              class="hover:bg-slate-50 transition-colors group cursor-pointer"
              @click="go(cliente.id)"
            >
              <td class="px-5 py-3.5">
                <span class="font-semibold text-slate-800">{{ cliente.nome }}</span>
              </td>
              <td class="px-5 py-3.5 text-slate-600">{{ cliente.telefono ?? '—' }}</td>
              <td class="px-5 py-3.5 text-slate-500 text-xs">{{ cliente.email ?? '—' }}</td>
              <td
                v-for="field in dateFields"
                :key="field.name"
                class="px-5 py-3.5"
              >
                <span
                  v-if="cliente.custom_fields?.[field.name]"
                  :class="isExpiringSoon(cliente.custom_fields[field.name]) ? 'text-amber-600 font-semibold' : 'text-slate-600'"
                >
                  {{ formatDate(cliente.custom_fields[field.name]) }}
                </span>
                <span v-else class="text-slate-300">—</span>
              </td>
              <td class="px-5 py-3.5">
                <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                  {{ cliente.pratiche_count }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-slate-400 text-xs">{{ formatDate(cliente.created_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                  <Link
                    :href="route('clienti.edit', cliente.id)"
                    class="text-xs text-indigo-600 hover:underline"
                    @click.stop
                  >Modifica</Link>
                  <button
                    class="text-xs text-red-500 hover:underline"
                    @click.stop="confirmDelete(cliente)"
                  >Elimina</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end gap-1">
        <Link
          v-for="link in clienti.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="[
            'px-3 py-1 rounded text-xs border',
            link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-slate-600 border-slate-300 hover:bg-slate-100',
            !link.url ? 'opacity-40 pointer-events-none' : '',
          ]"
          v-html="link.label"
        />
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface FieldSchema { name: string; label: string; type: string }
interface ClienteRow {
  id: number; nome: string; telefono: string | null; email: string | null
  custom_fields: Record<string, string> | null; pratiche_count: number; created_at: string
}
interface PaginationLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
  clienti: { data: ClienteRow[]; total: number; links: PaginationLink[] }
  schema: FieldSchema[]
  filters: { search: string }
}>()

const search = ref(props.filters.search)

const dateFields = computed(() => props.schema.filter(f => f.type === 'date'))

function onSearch() {
  router.get(route('clienti.index'), { search: search.value }, { preserveState: true, replace: true })
}

function go(id: number) {
  router.visit(route('clienti.show', id))
}

function confirmDelete(cliente: ClienteRow) {
  if (confirm(`Eliminare "${cliente.nome}"? L'operazione è irreversibile.`)) {
    router.delete(route('clienti.destroy', cliente.id))
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}

function isExpiringSoon(iso: string): boolean {
  const diff = (new Date(iso).getTime() - Date.now()) / (1000 * 60 * 60 * 24)
  return diff >= 0 && diff <= 30
}
</script>
