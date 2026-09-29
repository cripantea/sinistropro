<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('clienti.index')" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </Link>
          <h1 class="text-base font-semibold text-slate-800">{{ cliente.nome }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <Link
            :href="route('clienti.edit', cliente.id)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-700 border border-slate-300 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Modifica
          </Link>
          <button
            v-if="cliente.pratiche_count === 0"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition"
            @click="confirmDelete"
          >
            Elimina
          </button>
        </div>
      </div>
    </template>

    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Left: dati cliente -->
      <div class="space-y-4">

        <!-- Info base -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati contatto</h2>
          <dl class="space-y-2.5">
            <div class="flex flex-col gap-0.5">
              <dt class="text-xs text-slate-400">Telefono</dt>
              <dd class="text-sm font-medium text-slate-800">{{ cliente.telefono ?? '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5">
              <dt class="text-xs text-slate-400">Email</dt>
              <dd class="text-sm font-medium text-slate-800">{{ cliente.email ?? '—' }}</dd>
            </div>
          </dl>
        </div>

        <!-- Custom fields -->
        <div v-if="schema.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati aggiuntivi</h2>
          <dl class="space-y-2.5">
            <div v-for="field in schema" :key="field.name" class="flex flex-col gap-0.5">
              <dt class="text-xs text-slate-400">{{ field.label }}</dt>
              <dd :class="['text-sm font-medium', expiryClass(field, cliente.custom_fields?.[field.name])]">
                {{ renderField(field, cliente.custom_fields?.[field.name]) }}
              </dd>
            </div>
          </dl>
        </div>

        <!-- Stats -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <p class="text-xs text-slate-400 mb-1">Pratiche totali</p>
          <p class="text-3xl font-bold text-slate-800">{{ cliente.pratiche_count }}</p>
        </div>

      </div>

      <!-- Right: pratiche associate -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Pratiche recenti</h2>
          <Link
            :href="`${route('pratiche.index')}?cliente_id=${cliente.id}`"
            class="text-xs text-indigo-600 hover:underline"
          >Tutte</Link>
        </div>
        <div v-if="cliente.pratiche.length === 0" class="px-5 py-10 text-center text-sm text-slate-400">
          Nessuna pratica associata.
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left font-medium text-slate-500">N.</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Stato</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Data</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="pratica in cliente.pratiche"
              :key="pratica.id"
              class="hover:bg-slate-50 transition-colors"
            >
              <td class="px-5 py-3.5">
                <Link :href="route('pratiche.show', pratica.id)" class="text-indigo-600 hover:underline font-semibold">
                  #{{ pratica.id }}
                </Link>
              </td>
              <td class="px-5 py-3.5">
                <span
                  v-if="pratica.current_status"
                  class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full"
                  :style="{ backgroundColor: pratica.current_status.color + '22', color: pratica.current_status.color }"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: pratica.current_status.color }" />
                  {{ pratica.current_status.name }}
                </span>
                <span v-else class="text-slate-400 text-xs">—</span>
              </td>
              <td class="px-5 py-3.5 text-slate-500 text-xs">{{ formatDate(pratica.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface FieldSchema { name: string; label: string; type: string }
interface TenantStatus { id: number; name: string; color: string }
interface Pratica { id: number; current_status: TenantStatus | null; created_at: string }
interface Cliente {
  id: number; nome: string; telefono: string | null; email: string | null
  custom_fields: Record<string, string> | null; pratiche_count: number; pratiche: Pratica[]
}

const props = defineProps<{
  cliente: Cliente
  schema: FieldSchema[]
}>()

function confirmDelete() {
  if (confirm(`Eliminare "${props.cliente.nome}"? L'operazione è irreversibile.`)) {
    router.delete(route('clienti.destroy', props.cliente.id))
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}

function renderField(field: FieldSchema, value: string | undefined): string {
  if (value === undefined || value === null || value === '') return '—'
  if (field.type === 'date') return formatDate(value)
  if (field.type === 'boolean') return value === '1' || value === 'true' ? 'Sì' : 'No'
  return value
}

function expiryClass(field: FieldSchema, value: string | undefined): string {
  if (field.type !== 'date' || !value) return 'text-slate-800'
  const diff = (new Date(value).getTime() - Date.now()) / (1000 * 60 * 60 * 24)
  if (diff < 0) return 'text-red-600 font-semibold'
  if (diff <= 30) return 'text-amber-600 font-semibold'
  return 'text-slate-800'
}
</script>
