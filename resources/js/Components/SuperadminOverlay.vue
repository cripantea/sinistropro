<template>
  <Transition
    enter-active-class="transition duration-200"
    enter-from-class="opacity-0 translate-y-2"
    leave-active-class="transition duration-150"
    leave-to-class="opacity-0 translate-y-2"
  >
    <div v-if="isImpersonating" class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">

      <!-- Panel espanso -->
      <Transition
        enter-active-class="transition duration-200 origin-bottom-right"
        enter-from-class="opacity-0 scale-95"
        leave-active-class="transition duration-150 origin-bottom-right"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="open"
          class="bg-slate-900 text-white rounded-xl shadow-2xl w-72 border border-slate-700 overflow-hidden"
        >
          <!-- Header -->
          <div class="px-4 py-3 bg-slate-800 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded bg-indigo-500 flex items-center justify-center text-[10px] font-bold shrink-0">SA</span>
              <span class="text-xs font-semibold text-slate-100">Superadmin Panel</span>
            </div>
            <span class="text-xs text-slate-400 font-medium truncate max-w-[120px]">{{ impersonating?.tenant_name }}</span>
          </div>

          <!-- Features -->
          <div class="px-4 py-3 border-b border-slate-700">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500 mb-2">Funzionalità attive</p>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="(enabled, key) in tenantFeatures"
                :key="key"
                class="text-[10px] font-medium px-2 py-0.5 rounded-full"
                :class="enabled
                  ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                  : 'bg-slate-700 text-slate-500 border border-slate-600 line-through'"
              >
                {{ FEATURE_LABELS[key] ?? key }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="px-4 py-3 flex flex-col gap-1.5">
            <a
              v-if="impersonating"
              :href="route('superadmin.tenants.edit', impersonating.tenant_id) + '?tab=features'"
              class="flex items-center gap-2 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg px-2 py-1.5 transition-colors"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
              Configura funzionalità
            </a>
            <a
              v-if="impersonating"
              :href="route('superadmin.tenants.edit', impersonating.tenant_id)"
              class="flex items-center gap-2 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg px-2 py-1.5 transition-colors"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Modifica configurazione
            </a>
            <a
              v-if="impersonating"
              :href="route('superadmin.audit-logs') + '?tenant_id=' + impersonating.tenant_id"
              class="flex items-center gap-2 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg px-2 py-1.5 transition-colors"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
              Audit log tenant
            </a>
            <button
              @click="leave"
              class="flex items-center gap-2 text-xs text-red-400 hover:text-red-300 hover:bg-slate-700 rounded-lg px-2 py-1.5 transition-colors text-left"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              Esci dall'assistenza
            </button>
          </div>
        </div>
      </Transition>

      <!-- Toggle chip -->
      <button
        @click="open = !open"
        class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-2 rounded-full shadow-lg border border-slate-700 transition-colors"
        title="Pannello Superadmin"
      >
        <span class="w-4 h-4 rounded bg-indigo-500 flex items-center justify-center text-[9px] font-bold shrink-0">SA</span>
        <span class="hidden sm:inline">{{ impersonating?.tenant_name }}</span>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
      </button>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'

const page            = usePage<PageProps>()
const isImpersonating = computed(() => page.props.auth.isImpersonating)
const impersonating   = computed(() => page.props.impersonating)
const tenantFeatures  = computed(() => page.props.tenantFeatures ?? {})

const FEATURE_LABELS: Record<string, string> = {
  whatsapp:             'WhatsApp',
  moduli_pdf:           'PDF',
  automazioni:          'Automazioni',
  kanban:               'Kanban',
  lista_personalizzate: 'Liste custom',
  import_clienti:       'Import',
}

const open = ref(false)

function leave() {
  router.post(route('impersonate.leave'), {}, {
    onSuccess: () => { window.location.href = '/superadmin' },
  })
}
</script>
