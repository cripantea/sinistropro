<template>
  <SuperadminLayout title="Gestione Tenant">
    <div class="p-6 space-y-5">

      <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ tenants.total }} tenant nel sistema</p>
        <Link
          :href="route('superadmin.tenants.create')"
          class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nuovo Tenant
        </Link>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200">
              <th class="px-5 py-3 text-left font-medium text-slate-500">Nome</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Utenti</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Sinistri</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Stati</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Avviso (gg)</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Abbonamento</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Creato</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="tenants.data.length === 0">
              <td colspan="8" class="px-5 py-10 text-center text-slate-400">Nessun tenant creato.</td>
            </tr>
            <tr v-for="tenant in tenants.data" :key="tenant.id" @click="apri(tenant)" class="hover:bg-slate-50 transition-colors group cursor-pointer">
              <td class="px-5 py-3.5 font-semibold text-slate-800">
                {{ tenant.name }}
                <span v-if="tenant.settings?.demo" class="ml-1.5 text-[11px] font-semibold rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700">Demo</span>
                <span v-else-if="tenant.settings?.prova" class="ml-1.5 text-[11px] font-semibold rounded-full px-2 py-0.5 bg-slate-100 text-slate-500">Prova</span>
              </td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.users_count }}</td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.pratiche_count }}</td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.statuses_count }}</td>
              <td class="px-5 py-3.5 text-slate-600">{{ tenant.settings?.default_notice_days ?? 30 }} gg</td>
              <td class="px-5 py-3.5"><BillingBadge :state="tenant.billing_state" /></td>
              <td class="px-5 py-3.5 text-slate-400 text-xs">{{ formatDate(tenant.created_at) }}</td>
              <td class="px-5 py-3.5 text-right" @click.stop>
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <Link
                    :href="route('superadmin.tenants.context.set', tenant.id)"
                    method="post"
                    as="button"
                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                    title="Naviga i dati del tenant restando superadmin"
                  >Sfoglia</Link>
                  <Link :href="route('superadmin.tenants.edit', tenant.id)" class="text-xs text-slate-600 hover:underline">Apri</Link>
                  <button type="button" @click="daEliminare = tenant" class="text-xs text-red-500 hover:underline">Elimina</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end gap-1">
        <Link
          v-for="link in tenants.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="['px-3 py-1 rounded text-xs border', link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-slate-600 border-slate-300 hover:bg-slate-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
          v-html="link.label"
        />
      </div>

    </div>

    <!-- ── Conferma eliminazione: va scritto CONFERMA ───────────────────────── -->
    <Teleport to="body">
      <div v-if="daEliminare" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="chiudi" />
        <form @submit.prevent="elimina" class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-base font-semibold text-slate-900">Eliminare "{{ daEliminare.name }}"?</h2>
          <p class="mt-2 text-sm text-slate-600">
            Vengono cancellati per sempre i suoi {{ daEliminare.pratiche_count }} sinistri con clienti, allegati e automazioni,
            e i suoi {{ daEliminare.users_count }} utenti non potranno più entrare. Non si può annullare.
          </p>
          <p v-if="daEliminare.billing_state === 'attivo' || daEliminare.billing_state === 'disdetto'" class="mt-3 text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2">
            Ha un abbonamento Stripe: annullalo prima dalla scheda Abbonamento, altrimenti Stripe continua a incassare.
          </p>
          <label class="block mt-4 text-sm font-medium text-slate-700">Scrivi <span class="font-mono font-bold">CONFERMA</span> per procedere</label>
          <input v-model="formElimina.conferma" type="text" autocomplete="off" autofocus class="mt-1.5 w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none" />
          <p v-if="formElimina.errors.conferma" class="mt-1 text-xs text-red-600">{{ formElimina.errors.conferma }}</p>
          <div class="mt-5 flex justify-end gap-3">
            <button type="button" @click="chiudi" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Annulla</button>
            <button type="submit" :disabled="formElimina.conferma !== 'CONFERMA' || formElimina.processing" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">
              Elimina per sempre
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import type { BillingState } from '@/types'
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import BillingBadge from '@/Components/Superadmin/BillingBadge.vue'

interface TenantRow {
  id: number; name: string; users_count: number; pratiche_count: number
  statuses_count: number; settings: { default_notice_days?: number; demo?: boolean; prova?: boolean } | null; created_at: string
  billing_state: BillingState
}
interface PaginationLink { url: string | null; label: string; active: boolean }

defineProps<{
  tenants: { data: TenantRow[]; total: number; links: PaginationLink[] }
}>()

function apri(tenant: TenantRow) { router.visit(route('superadmin.tenants.edit', tenant.id)) }

function formatDate(iso: string) { return new Date(iso).toLocaleDateString('it-IT') }

const daEliminare = ref<TenantRow | null>(null)
const formElimina = useForm({ conferma: '' })

function elimina() {
  if (!daEliminare.value) return
  formElimina.delete(route('superadmin.tenants.destroy', daEliminare.value.id), { preserveScroll: true, onSuccess: chiudi })
}

function chiudi() {
  daEliminare.value = null
  formElimina.reset()
  formElimina.clearErrors()
}
</script>
