<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <TastoIndietro :href="route('clienti.index')">Clienti</TastoIndietro>
          <h1 class="text-base font-semibold text-slate-800">{{ cliente.nome }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <Link
            :href="route('clienti.edit', { cliente: cliente.id, ritorno: $page.url })"
            class="inline-flex items-center gap-2 h-10 px-4 text-[15px] font-semibold text-gray-800 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Modifica
          </Link>
          <button
            v-if="cliente.pratiche_count === 0"
            class="inline-flex items-center gap-2 h-10 px-4 text-[15px] font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition"
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

        <!-- Anagrafica (presente soprattutto per i clienti importati dal gestionale) -->
        <div v-if="anagrafica.length" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Anagrafica</h2>
          <dl class="space-y-2.5">
            <div v-for="[label, value] in anagrafica" :key="label" class="flex flex-col gap-0.5">
              <dt class="text-xs text-slate-400">{{ label }}</dt>
              <dd class="text-sm font-medium text-slate-800">{{ value }}</dd>
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

      <div class="lg:col-span-2 space-y-5">

      <!-- Veicoli -->
      <div v-if="cliente.veicoli.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Veicoli ({{ cliente.veicoli.length }})</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Targa</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Veicolo</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Telaio</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Immatr.</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Revisione</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="v in cliente.veicoli" :key="v.id">
                <td class="px-5 py-3 font-mono font-semibold text-slate-800 whitespace-nowrap">{{ v.targa ?? '—' }}</td>
                <td class="px-5 py-3 text-slate-700">
                  {{ [v.marca, v.modello].filter(Boolean).join(' ') || '—' }}
                  <span v-if="v.descrizione && v.descrizione !== v.modello" class="block text-xs text-slate-400">{{ v.descrizione }}</span>
                </td>
                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ v.telaio ?? '—' }}</td>
                <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">{{ v.data_immatricolazione ? formatDate(v.data_immatricolazione) : '—' }}</td>
                <td :class="['px-5 py-3 text-xs whitespace-nowrap', expiryClass({ name: '', label: '', type: 'date' }, v.scadenza_revisione ?? undefined)]">
                  {{ v.scadenza_revisione ? formatDate(v.scadenza_revisione) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pratiche associate -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
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

    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TastoIndietro from '@/Components/TastoIndietro.vue'

interface FieldSchema { name: string; label: string; type: string }
interface TenantStatus { id: number; name: string; color: string }
interface Pratica { id: number; current_status: TenantStatus | null; created_at: string }
interface Veicolo {
  id: number; targa: string | null; telaio: string | null; marca: string | null; modello: string | null
  descrizione: string | null; data_immatricolazione: string | null; scadenza_revisione: string | null
}
interface Cliente {
  id: number; nome: string; telefono: string | null; email: string | null
  codice_esterno: string | null; codice_fiscale: string | null; partita_iva: string | null
  indirizzo: string | null; cap: string | null; citta: string | null; provincia: string | null
  custom_fields: Record<string, string> | null; pratiche_count: number; pratiche: Pratica[]; veicoli: Veicolo[]
}

const props = defineProps<{
  cliente: Cliente
  schema: FieldSchema[]
}>()

const anagrafica = computed(() => {
  const c = props.cliente
  const luogo = [c.cap, c.citta, c.provincia ? `(${c.provincia})` : null].filter(Boolean).join(' ')
  return ([
    ['Codice cliente', c.codice_esterno],
    ['Codice fiscale', c.codice_fiscale],
    ['Partita IVA', c.partita_iva !== c.codice_fiscale ? c.partita_iva : null],
    ['Indirizzo', [c.indirizzo, luogo].filter(Boolean).join(', ')],
  ] as [string, string | null][]).filter(([, v]) => v)
})

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
