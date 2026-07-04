<template>
  <div class="flex h-screen bg-slate-50 overflow-hidden">

    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside class="w-64 shrink-0 flex flex-col bg-slate-900 text-slate-100">
      <!-- Logo / brand -->
      <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-700">
        <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
          SA
        </div>
        <div>
          <div class="font-semibold text-sm leading-tight">Superadmin</div>
          <div class="text-xs text-slate-400 leading-tight">Panel di sistema</div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <SidebarLink :href="route('superadmin.dashboard')" :active="route().current('superadmin.dashboard')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          </template>
          Dashboard
        </SidebarLink>

        <SidebarLink :href="route('superadmin.users')" :active="route().current('superadmin.users')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          </template>
          Utenti
        </SidebarLink>

        <SidebarLink :href="route('superadmin.tenants.index')" :active="route().current('superadmin.tenants.*')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          </template>
          Tenant
        </SidebarLink>

        <SidebarLink :href="route('superadmin.document-categories.index')" :active="route().current('superadmin.document-categories.*')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
          </template>
          Categorie Doc.
        </SidebarLink>

        <SidebarLink :href="route('audit-logs.index')" :active="route().current('audit-logs.*')">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
          </template>
          Audit Log
        </SidebarLink>
      </nav>

      <!-- User info + logout -->
      <div class="px-4 py-4 border-t border-slate-700">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
            {{ initials }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium text-slate-100 truncate">{{ $page.props.auth.user.name }}</div>
            <div class="text-xs text-slate-400 truncate">{{ $page.props.auth.user.email }}</div>
          </div>
        </div>
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="w-full text-left text-xs text-slate-400 hover:text-red-400 transition-colors"
        >
          Esci dall'account
        </Link>
      </div>
    </aside>

    <!-- ── Main area ───────────────────────────────────── -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Top bar -->
      <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between shrink-0">
        <div>
          <h1 v-if="title" class="text-base font-semibold text-slate-800">{{ title }}</h1>
          <slot name="breadcrumb" />
        </div>
        <div class="flex items-center gap-3">
          <!-- Flash message -->
          <Transition enter-from-class="opacity-0 translate-y-1" enter-active-class="transition" leave-active-class="transition" leave-to-class="opacity-0">
            <div v-if="flash" class="text-sm bg-emerald-50 text-emerald-700 border border-emerald-200 rounded px-3 py-1">
              {{ flash }}
            </div>
          </Transition>
          <Link :href="route('dashboard')" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">
            ← Esci dal pannello
          </Link>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SidebarLink from '@/Components/Superadmin/SidebarLink.vue'

defineProps<{ title?: string }>()

const page = usePage()
const flash = computed(() => page.props.flash?.success as string | undefined)
const initials = computed(() => {
  const name = page.props.auth.user.name as string
  return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
})
</script>
