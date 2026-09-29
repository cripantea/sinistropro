<template>
  <Transition
    enter-active-class="transition duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-200"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="tenantContext"
      class="w-full bg-indigo-600 text-white px-4 py-2 flex items-center justify-between gap-4 text-sm font-medium shadow-sm z-50 shrink-0"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <!-- Eye icon -->
        <svg class="w-4 h-4 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <span class="truncate">
          Superadmin — navigando
          <strong class="font-bold">{{ tenantContext.tenant_name }}</strong>
        </span>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <!-- Selettore tenant rapido (lista dropdown) -->
        <div class="relative" ref="pickerRef">
          <button
            @click="pickerOpen = !pickerOpen"
            class="flex items-center gap-1 text-xs font-semibold bg-white/15 hover:bg-white/25 text-white px-3 py-1.5 rounded-full border border-white/30 transition"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
            Cambia tenant
          </button>

          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition ease-in duration-75"
            leave-to-class="opacity-0 scale-95"
          >
            <div
              v-if="pickerOpen"
              class="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-xl ring-1 ring-black/10 z-50 overflow-hidden"
            >
              <div class="px-3 py-2 border-b border-gray-100">
                <input
                  v-model="search"
                  type="text"
                  placeholder="Cerca tenant…"
                  class="w-full text-xs text-gray-700 outline-none placeholder-gray-400"
                  @click.stop
                />
              </div>
              <ul class="max-h-52 overflow-y-auto py-1">
                <li v-if="filteredTenants.length === 0" class="px-3 py-2 text-xs text-gray-400">Nessun risultato</li>
                <li
                  v-for="t in filteredTenants"
                  :key="t.id"
                  class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer flex items-center justify-between"
                  :class="{ 'font-semibold text-indigo-700 bg-indigo-50': t.id === tenantContext?.tenant_id }"
                  @click="switchTenant(t.id)"
                >
                  {{ t.name }}
                  <svg v-if="t.id === tenantContext?.tenant_id" class="w-3.5 h-3.5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </li>
              </ul>
            </div>
          </Transition>
        </div>

        <a
          :href="route('superadmin.tenants.edit', tenantContext.tenant_id)"
          class="text-xs font-semibold bg-white/15 hover:bg-white/25 text-white px-3 py-1.5 rounded-full border border-white/30 transition"
        >
          ⚙ Configura
        </a>

        <button
          @click="clearContext"
          class="text-xs font-semibold bg-white/15 hover:bg-white/25 text-white px-3 py-1.5 rounded-full border border-white/30 transition"
        >
          ✕ Esci
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'

const page          = usePage<PageProps>()
const tenantContext = computed(() => page.props.tenantContext)

// ── Tenant switcher ──────────────────────────────────────────────────────────

interface TenantOption { id: number; name: string }

const pickerOpen = ref(false)
const pickerRef  = ref<HTMLElement | null>(null)
const search     = ref('')
const allTenants = ref<TenantOption[]>([])

const filteredTenants = computed(() =>
  allTenants.value.filter(t =>
    t.name.toLowerCase().includes(search.value.toLowerCase())
  )
)

async function loadTenants() {
  if (allTenants.value.length) return
  try {
    const res = await fetch(route('superadmin.tenants.list-json'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    allTenants.value = await res.json()
  } catch { /* silently fail */ }
}

function switchTenant(id: number) {
  pickerOpen.value = false
  router.post(route('superadmin.tenants.context.set', id), {}, { preserveScroll: false })
}

function clearContext() {
  router.post(route('superadmin.tenant-context.clear'))
}

// open picker: load tenants
function onPickerOpen() {
  if (pickerOpen.value) loadTenants()
}

// close on outside click
function onOutsideClick(e: MouseEvent) {
  if (pickerRef.value && !pickerRef.value.contains(e.target as Node)) {
    pickerOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onOutsideClick))
onUnmounted(() => document.removeEventListener('mousedown', onOutsideClick))
</script>
