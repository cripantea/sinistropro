<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop — nessun handler: la modale si chiude SOLO con i pulsanti -->
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" />

        <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div>
              <h2 class="text-base font-semibold text-slate-800">
                {{ utente ? `Modifica utente — ${utente.name}` : (tenantFisso ? `Nuovo utente per ${tenantFisso.name}` : 'Crea nuovo utente') }}
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">
                {{ utente ? 'La password va cambiata solo se necessario.' : "L'utente riceverà le credenziali per accedere al sistema." }}
              </p>
            </div>
            <button type="button" @click="chiudi" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition" title="Chiudi">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <form @submit.prevent="salva" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Nome completo</label>
              <input v-model="form.name" type="text" autocomplete="off" placeholder="Es. Mario Rossi" class="campo" :class="{ 'border-red-400 bg-red-50': form.errors.name }" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Indirizzo email</label>
              <input v-model="form.email" type="email" autocomplete="off" placeholder="utente@esempio.it" class="campo" :class="{ 'border-red-400 bg-red-50': form.errors.email }" />
              <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">
                {{ utente ? 'Nuova password' : 'Password' }}
                <span v-if="!utente" class="text-red-400">*</span>
                <span v-else class="text-slate-400 font-normal ml-1">(lascia vuoto per non modificare)</span>
              </label>
              <input v-model="form.password" type="password" autocomplete="new-password" :placeholder="utente ? 'Lascia vuoto per non modificare' : 'Min. 8 caratteri'" class="campo" :class="{ 'border-red-400 bg-red-50': form.errors.password }" />
              <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Ruolo</label>
              <select v-model="form.role" class="campo bg-white" :class="{ 'border-red-400': form.errors.role }">
                <option value="user">Operatore</option>
                <option value="tenant-admin">Amministratore agenzia</option>
                <option value="external">Esterno (perito, carrozzeria)</option>
                <option v-if="!tenantFisso" value="superadmin">Superadmin</option>
              </select>
              <p v-if="form.errors.role" class="mt-1 text-xs text-red-500">{{ form.errors.role }}</p>
            </div>

            <div v-if="!tenantFisso">
              <label class="block text-xs font-medium text-slate-700 mb-1.5">
                Tenant associato
                <span v-if="form.role !== 'superadmin'" class="text-red-400">*</span>
              </label>
              <select
                v-model="form.tenant_id"
                :disabled="form.role === 'superadmin'"
                class="campo bg-white disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
                :class="{ 'border-red-400': form.errors.tenant_id }"
              >
                <option value="">{{ form.role === 'superadmin' ? '— Nessun tenant (globale) —' : 'Seleziona un tenant…' }}</option>
                <option v-for="t in tenants" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
              </select>
              <p v-if="form.errors.tenant_id" class="mt-1 text-xs text-red-500">{{ form.errors.tenant_id }}</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
              <button type="button" @click="chiudi" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition">Annulla</button>
              <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60 transition">
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                {{ form.processing ? 'Salvataggio…' : (utente ? 'Salva modifiche' : 'Crea utente') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

export interface UtenteModificabile {
  id: number
  name: string
  email: string
  role: string
  tenant_id?: number | null
  tenant?: { id: number; name: string } | null
}

const props = defineProps<{
  open: boolean
  utente: UtenteModificabile | null
  tenants?: { id: number; name: string }[]
  // Dentro la scheda di un tenant: il tenant è già deciso e non si possono creare superadmin.
  tenantFisso?: { id: number; name: string }
}>()

const emit = defineEmits<{ chiudi: [] }>()

const form = useForm({ name: '', email: '', password: '', role: 'user', tenant_id: '' })

watch(() => form.role, (role) => { if (role === 'superadmin') form.tenant_id = '' })

// All'apertura la modale si riempie con l'utente da modificare (o si svuota per uno nuovo).
watch(() => props.open, (aperta) => {
  if (!aperta) return
  form.clearErrors()
  const u = props.utente
  form.name     = u?.name ?? ''
  form.email    = u?.email ?? ''
  form.password = ''
  form.role     = u?.role ?? 'user'
  const tenantId = props.tenantFisso?.id ?? u?.tenant?.id ?? u?.tenant_id ?? null
  form.tenant_id = tenantId ? String(tenantId) : ''
})

function salva() {
  const opzioni = { preserveScroll: true, onSuccess: () => emit('chiudi') }
  if (props.utente) form.patch(route('superadmin.users.update', props.utente.id), opzioni)
  else form.post(route('superadmin.users.store'), opzioni)
}

function chiudi() {
  form.clearErrors()
  emit('chiudi')
}
</script>

<style scoped>
.campo {
  @apply w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none;
}
</style>
