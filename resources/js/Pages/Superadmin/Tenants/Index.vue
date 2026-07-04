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
              <th class="px-5 py-3 text-left font-medium text-slate-500">Pratiche</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Stati</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Avviso (gg)</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Creato</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="tenants.data.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-slate-400">Nessun tenant creato.</td>
            </tr>
            <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-slate-50 transition-colors group">
              <td class="px-5 py-3.5 font-semibold text-slate-800">{{ tenant.name }}</td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.users_count }}</td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.pratiche_count }}</td>
              <td class="px-5 py-3.5 text-slate-600 tabular-nums">{{ tenant.statuses_count }}</td>
              <td class="px-5 py-3.5 text-slate-600">{{ tenant.settings?.default_notice_days ?? 30 }} gg</td>
              <td class="px-5 py-3.5 text-slate-400 text-xs">{{ formatDate(tenant.created_at) }}</td>
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <Link :href="route('superadmin.tenants.edit', tenant.id)" class="text-xs text-indigo-600 hover:underline">Modifica</Link>
                  <Link
                    :href="route('superadmin.tenants.destroy', tenant.id)"
                    method="delete"
                    as="button"
                    class="text-xs text-red-500 hover:underline"
                    @click.prevent="confirmDelete(tenant)"
                  >Elimina</Link>
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
  </SuperadminLayout>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'

interface TenantRow {
  id: number; name: string; users_count: number; pratiche_count: number
  statuses_count: number; settings: { default_notice_days?: number } | null; created_at: string
}
interface PaginationLink { url: string | null; label: string; active: boolean }

defineProps<{
  tenants: { data: TenantRow[]; total: number; links: PaginationLink[] }
}>()

function formatDate(iso: string) { return new Date(iso).toLocaleDateString('it-IT') }

function confirmDelete(tenant: TenantRow) {
  if (confirm(`Eliminare il tenant "${tenant.name}"? L'operazione è irreversibile.`)) {
    router.delete(route('superadmin.tenants.destroy', tenant.id))
  }
}
</script>
