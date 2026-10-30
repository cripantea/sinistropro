<template>
  <div class="max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-semibold text-slate-900">Utenti</h3>
        <p class="text-xs text-slate-500 mt-0.5">Chi accede a {{ tenant.name }}. Da qui li crei, li modifichi o entri al loro posto.</p>
      </div>
      <button type="button" @click="apri(null)" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nuovo utente
      </button>
    </div>

    <ul v-if="utenti.length" class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
      <li v-for="u in utenti" :key="u.id" class="flex items-center gap-4 px-4 py-3" :class="{ 'opacity-60': !u.is_active }">
        <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-bold shrink-0">
          {{ iniziali(u.name) }}
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-medium text-slate-800">{{ u.name }}</span>
            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5" :class="u.role === 'tenant-admin' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'">
              {{ RUOLI[u.role] ?? u.role }}
            </span>
            <span v-if="!u.is_active" class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-slate-100 text-slate-500">Disabilitato</span>
            <span v-else-if="u.must_change_password" class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-amber-50 text-amber-700" title="Non ha ancora scelto la sua password">Mai entrato</span>
          </div>
          <div class="text-xs text-slate-500 truncate">{{ u.email }}</div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
          <button type="button" @click="apri(u)" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">Modifica</button>
          <Link
            v-if="u.role !== 'superadmin'"
            :href="route('superadmin.users.toggle-active', u.id)"
            method="patch"
            as="button"
            preserve-scroll
            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition"
            :class="u.is_active ? 'text-slate-500 hover:text-red-600 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50'"
          >{{ u.is_active ? 'Disabilita' : 'Riabilita' }}</Link>
          <Link
            v-if="u.is_active && u.role !== 'superadmin'"
            :href="route('superadmin.impersonate.start', u.id)"
            method="post"
            as="button"
            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition"
            :title="`Accedi come ${u.name}`"
          >Accedi come</Link>
        </div>
      </li>
    </ul>

    <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-xl px-8 py-12 text-center">
      <p class="text-sm text-slate-500 font-medium">Nessun utente in questa agenzia</p>
      <p class="text-xs text-slate-400 mt-1">Clicca "Nuovo utente" per crearne uno.</p>
    </div>

    <UtenteModal :open="modaleAperta" :utente="inModifica" :tenant-fisso="{ id: tenant.id, name: tenant.name }" @chiudi="modaleAperta = false" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import UtenteModal from '@/Components/Superadmin/UtenteModal.vue'

export interface UtenteTenant {
  id: number
  name: string
  email: string
  role: string
  is_active: boolean
  must_change_password: boolean
}

defineProps<{
  tenant: { id: number; name: string }
  utenti: UtenteTenant[]
}>()

const RUOLI: Record<string, string> = { 'tenant-admin': 'Amministratore', user: 'Operatore', external: 'Esterno', superadmin: 'Superadmin' }

const modaleAperta = ref(false)
const inModifica = ref<UtenteTenant | null>(null)

function apri(u: UtenteTenant | null) {
  inModifica.value = u
  modaleAperta.value = true
}

function iniziali(nome: string) {
  return nome.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}
</script>
