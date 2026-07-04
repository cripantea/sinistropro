<template>
  <SuperadminLayout title="Dashboard">
    <div class="p-6 space-y-8">

      <!-- Stats cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <StatCard label="Tenant attivi" :value="stats.tenants" color="indigo">
          <template #icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          </template>
        </StatCard>

        <StatCard label="Utenti totali" :value="stats.users" color="violet">
          <template #icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          </template>
        </StatCard>

        <StatCard label="Pratiche nel sistema" :value="stats.pratiche" color="sky">
          <template #icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </template>
        </StatCard>
      </div>

      <!-- Recent tenants table -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Tenant recenti</h2>
          <Link :href="route('superadmin.tenants.create')" class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-700 transition font-medium">
            + Nuovo Tenant
          </Link>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-slate-200 overflow-hidden">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200">
                <th class="px-5 py-3 text-left font-medium text-slate-500">Tenant</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Utenti</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Pratiche</th>
                <th class="px-5 py-3 text-left font-medium text-slate-500">Creato il</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="tenant in recentTenants" :key="tenant.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-5 py-3 font-medium text-slate-800">{{ tenant.name }}</td>
                <td class="px-5 py-3 text-slate-600">{{ tenant.users_count }}</td>
                <td class="px-5 py-3 text-slate-600">{{ tenant.pratiche_count }}</td>
                <td class="px-5 py-3 text-slate-400 text-xs">{{ formatDate(tenant.created_at) }}</td>
                <td class="px-5 py-3 text-right">
                  <Link :href="route('superadmin.tenants.edit', tenant.id)" class="text-xs text-indigo-600 hover:underline">Modifica</Link>
                </td>
              </tr>
              <tr v-if="recentTenants.length === 0">
                <td colspan="5" class="px-5 py-8 text-center text-slate-400">Nessun tenant ancora.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </SuperadminLayout>
</template>

<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import StatCard from '@/Components/Superadmin/StatCard.vue'
import { Link } from '@inertiajs/vue3'

interface TenantRow {
  id: number
  name: string
  users_count: number
  pratiche_count: number
  created_at: string
}

defineProps<{
  stats: { tenants: number; users: number; pratiche: number }
  recentTenants: TenantRow[]
}>()

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}
</script>
