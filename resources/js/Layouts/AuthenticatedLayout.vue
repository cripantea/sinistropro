<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue'
import type { PageProps, AppNotification } from '@/types'

const collapsed = ref(localStorage.getItem('kd-sidebar-collapsed') === 'true')

function toggleSidebar() {
  collapsed.value = !collapsed.value
  localStorage.setItem('kd-sidebar-collapsed', String(collapsed.value))
}

const page           = usePage<PageProps>()
const user           = computed(() => page.props.auth.user)
const isSuperadmin   = computed(() => user.value.role === 'superadmin')
const isTenantAdmin  = computed(() => user.value.role === 'tenant-admin')
const initials       = computed(() =>
  user.value.name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
)

const currentPath = computed(() => {
  const url = (page as any).url ?? ''
  return typeof url === 'string' ? url : ''
})
const isOnDashboard = computed(() => currentPath.value === '/dashboard')
const isOnPratiche  = computed(() => currentPath.value.startsWith('/pratiche') && currentPath.value !== '/pratiche/kanban')
const isOnKanban    = computed(() => currentPath.value === '/pratiche/kanban')
const isOnTeam      = computed(() => currentPath.value.startsWith('/team'))
const isOnWhatsapp  = computed(() => currentPath.value.startsWith('/whatsapp'))

function navigate(url: string) {
  window.location.href = url
}

// ── Notification bell ─────────────────────────────────────────────────────
const notifications  = computed(() => (page.props.notifications ?? []) as AppNotification[])
const bellOpen       = ref(false)

const ACTION_LABELS: Record<string, string> = {
  create: 'ha creato',
  update: 'ha modificato',
  delete: 'ha eliminato',
  view:   'ha visualizzato',
}
const MODEL_LABELS: Record<string, string> = {
  Pratica:     'un sinistro',
  Allegato:    'un allegato',
  PraticaNota: 'una nota',
}

function notifLabel(n: AppNotification): string {
  const action = ACTION_LABELS[n.action] ?? n.action
  const model  = MODEL_LABELS[n.model]  ?? n.model.toLowerCase()
  return `${n.user_name} ${action} ${model}`
}

function timeAgo(iso: string): string {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (seconds < 60)  return 'Adesso'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60)  return `${minutes} min fa`
  const hours = Math.floor(minutes / 60)
  if (hours < 24)    return `${hours}h fa`
  const days = Math.floor(hours / 24)
  return days === 1 ? 'Ieri' : `${days} giorni fa`
}

// close bell on Escape or outside click
function onKeydown(e: KeyboardEvent) { if (e.key === 'Escape') bellOpen.value = false }
onMounted(()   => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="h-screen flex flex-col overflow-hidden bg-slate-50">

    <!-- ── Impersonation banner (fixed, sempre sopra tutto) ── -->
    <ImpersonationBanner />

    <!-- ── Superadmin back-banner ─────────────────────────── -->
    <div v-if="isSuperadmin" class="shrink-0 bg-indigo-600 text-white">
      <div class="flex items-center justify-between px-5 py-2">
        <div class="flex items-center gap-2 text-sm">
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          <span class="font-medium">Modalità Superadmin</span>
          <span class="hidden sm:inline text-indigo-200">— stai visualizzando l'area tenant</span>
        </div>
        <Link
          :href="route('superadmin.dashboard')"
          class="flex items-center gap-1.5 rounded-md bg-white/15 px-3 py-1 text-sm font-semibold hover:bg-white/25 transition-colors"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Pannello Superadmin
        </Link>
      </div>
    </div>

    <!-- ── App Shell ────────────────────────────────────────── -->
    <div class="flex flex-1 overflow-hidden min-h-0">

      <!-- ────── SIDEBAR ────────────────────────────────────── -->
      <aside
        class="flex flex-col bg-slate-900 text-slate-100 shrink-0 transition-all duration-300 ease-in-out overflow-hidden"
        :class="collapsed ? 'w-16' : 'w-64'"
      >
        <!-- Brand -->
        <div
          class="h-14 flex items-center border-b border-slate-700 shrink-0 overflow-hidden"
          :class="collapsed ? 'justify-center px-0' : 'px-5 gap-3'"
        >
          <div class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold text-xs shrink-0">
            K
          </div>
          <span
            v-show="!collapsed"
            class="font-semibold text-sm text-white whitespace-nowrap overflow-hidden"
          >
            Kryptodoc
          </span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto overflow-x-hidden">

          <!-- Dashboard -->
          <a
            :href="route('dashboard')"
            @click.prevent="navigate(route('dashboard'))"
            :class="[
              'flex items-center rounded-lg text-sm font-medium transition-colors',
              collapsed ? 'justify-center px-0 py-2.5 w-full' : 'gap-3 px-3 py-2.5',
              isOnDashboard
                ? 'bg-indigo-600 text-white'
                : 'text-slate-300 hover:bg-slate-700 hover:text-white'
            ]"
            :title="collapsed ? 'Dashboard' : undefined"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span v-show="!collapsed" class="truncate">Dashboard</span>
          </a>

          <!-- Pratiche -->
          <a
            :href="route('pratiche.index')"
            @click.prevent="navigate(route('pratiche.index'))"
            :class="[
              'flex items-center rounded-lg text-sm font-medium transition-colors',
              collapsed ? 'justify-center px-0 py-2.5 w-full' : 'gap-3 px-3 py-2.5',
              isOnPratiche
                ? 'bg-indigo-600 text-white'
                : 'text-slate-300 hover:bg-slate-700 hover:text-white'
            ]"
            :title="collapsed ? 'Sinistri' : undefined"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span v-show="!collapsed" class="truncate">Sinistri</span>
          </a>

          <!-- Board Kanban -->
          <a
            :href="route('pratiche.kanban')"
            @click.prevent="navigate(route('pratiche.kanban'))"
            :class="[
              'flex items-center rounded-lg text-sm font-medium transition-colors',
              collapsed ? 'justify-center px-0 py-2.5 w-full' : 'gap-3 px-3 py-2.5',
              isOnKanban
                ? 'bg-indigo-600 text-white'
                : 'text-slate-300 hover:bg-slate-700 hover:text-white'
            ]"
            :title="collapsed ? 'Board Kanban' : undefined"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            <span v-show="!collapsed" class="truncate">Board</span>
          </a>

          <!-- WhatsApp -->
          <a
            v-if="!isSuperadmin"
            :href="route('whatsapp.index')"
            @click.prevent="navigate(route('whatsapp.index'))"
            :class="[
              'flex items-center rounded-lg text-sm font-medium transition-colors',
              collapsed ? 'justify-center px-0 py-2.5 w-full' : 'gap-3 px-3 py-2.5',
              isOnWhatsapp
                ? 'bg-indigo-600 text-white'
                : 'text-slate-300 hover:bg-slate-700 hover:text-white'
            ]"
            :title="collapsed ? 'WhatsApp' : undefined"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span v-show="!collapsed" class="truncate">WhatsApp</span>
          </a>

          <!-- Team (tenant-admin only) -->
          <a
            v-if="isTenantAdmin"
            :href="route('team.index')"
            @click.prevent="navigate(route('team.index'))"
            :class="[
              'flex items-center rounded-lg text-sm font-medium transition-colors',
              collapsed ? 'justify-center px-0 py-2.5 w-full' : 'gap-3 px-3 py-2.5',
              isOnTeam
                ? 'bg-indigo-600 text-white'
                : 'text-slate-300 hover:bg-slate-700 hover:text-white'
            ]"
            :title="collapsed ? 'Team' : undefined"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span v-show="!collapsed" class="truncate">Team</span>
          </a>

        </nav>

        <!-- User info + logout -->
        <div class="border-t border-slate-700 px-3 py-3 shrink-0">
          <div
            :class="collapsed ? 'flex justify-center mb-2' : 'flex items-center gap-3 mb-2 min-w-0'"
          >
            <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
              {{ initials }}
            </div>
            <div v-show="!collapsed" class="min-w-0">
              <div class="text-sm font-medium text-slate-100 truncate">{{ user.name }}</div>
              <div class="text-xs text-slate-400 truncate">{{ user.email }}</div>
            </div>
          </div>
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            :class="[
              'w-full text-xs text-slate-400 hover:text-red-400 transition-colors flex items-center gap-2',
              collapsed ? 'justify-center' : ''
            ]"
            :title="collapsed ? 'Esci' : undefined"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span v-show="!collapsed">Esci dall'account</span>
          </Link>
        </div>
      </aside>

      <!-- ────── MAIN AREA ───────────────────────────────────── -->
      <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

        <!-- Top header -->
        <header class="h-14 bg-white border-b border-slate-200 flex items-center gap-3 px-4 shrink-0">

          <!-- Sidebar toggle -->
          <button
            @click="toggleSidebar"
            class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
            :title="collapsed ? 'Espandi menu' : 'Comprimi menu'"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>

          <!-- Page title slot -->
          <div class="flex-1 min-w-0">
            <slot name="header" />
          </div>

          <!-- Notification bell -->
          <div class="relative shrink-0">
            <!-- Overlay to close on outside click -->
            <div v-if="bellOpen" class="fixed inset-0 z-40" @click="bellOpen = false"/>

            <button
              @click="bellOpen = !bellOpen"
              class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors relative"
              title="Notifiche recenti"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
              </svg>
              <!-- Badge -->
              <span
                v-if="notifications.length > 0"
                class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"
              />
            </button>

            <!-- Dropdown panel -->
            <Transition
              enter-active-class="transition ease-out duration-150"
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-to-class="opacity-100 scale-100 translate-y-0"
              leave-active-class="transition ease-in duration-100"
              leave-from-class="opacity-100 scale-100 translate-y-0"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
              <div
                v-if="bellOpen"
                class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden"
              >
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                  <span class="text-sm font-semibold text-gray-700">Attività recenti</span>
                  <span class="text-xs text-gray-400">{{ notifications.length }} eventi</span>
                </div>

                <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                  Nessuna attività recente.
                </div>

                <ul v-else class="divide-y divide-gray-50">
                  <li v-for="n in notifications" :key="n.id" class="px-4 py-3 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-3">
                      <!-- Action icon -->
                      <div class="mt-0.5 w-6 h-6 rounded-full flex items-center justify-center shrink-0"
                        :class="{
                          'bg-green-100 text-green-600': n.action === 'create',
                          'bg-blue-100 text-blue-600':  n.action === 'update',
                          'bg-red-100 text-red-500':    n.action === 'delete',
                          'bg-gray-100 text-gray-500':  n.action === 'view',
                        }"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                          <path v-if="n.action === 'create'"  stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                          <path v-else-if="n.action === 'update'" stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                          <path v-else-if="n.action === 'delete'" stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-700 leading-snug">{{ notifLabel(n) }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ timeAgo(n.created_at) }}</p>
                      </div>
                    </div>
                  </li>
                </ul>

                <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-center">
                  <a :href="route('audit-logs.index')" class="text-xs text-indigo-600 hover:underline font-medium">
                    Vedi tutti i log →
                  </a>
                </div>
              </div>
            </Transition>
          </div>

          <!-- User dropdown -->
          <div class="shrink-0">
            <Dropdown align="right" width="48">
              <template #trigger>
                <button
                  type="button"
                  class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                >
                  <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white">
                    {{ initials }}
                  </div>
                  <span class="hidden sm:inline font-medium">{{ user.name }}</span>
                  <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                  </svg>
                </button>
              </template>
              <template #content>
                <DropdownLink :href="route('profile.edit')">Profilo</DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">Esci</DropdownLink>
              </template>
            </Dropdown>
          </div>

        </header>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto">
          <slot />
        </main>

      </div>
    </div>
  </div>
</template>
