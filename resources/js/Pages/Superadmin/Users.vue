<template>
  <SuperadminLayout title="Gestione Utenti">
    <div class="p-6 space-y-5">

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-wrap items-center gap-3">

        <div class="relative flex-1 min-w-[200px]">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="search"
            type="search"
            placeholder="Cerca per nome o email…"
            class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          />
        </div>

        <select v-model="selectedRole" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
          <option value="">Tutti i ruoli</option>
          <option value="user">User</option>
          <option value="tenant-admin">Tenant Admin</option>
          <option value="superadmin">Superadmin</option>
        </select>

        <select v-model="selectedTenant" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none min-w-[160px]">
          <option value="">Tutti i tenant</option>
          <option v-for="t in tenants" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
        </select>

        <button v-if="hasActiveFilters" @click="resetFilters" class="text-xs text-slate-500 hover:text-red-500 transition underline">
          Resetta filtri
        </button>

        <div class="ml-auto flex items-center gap-3">
          <span class="text-sm text-slate-400">{{ users.total }} utenti</span>
          <button
            @click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition shadow-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Crea Utente
          </button>
        </div>
      </div>

      <!-- Users table -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200">
              <th class="px-5 py-3 text-left font-medium text-slate-500 w-12">ID</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Utente</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Ruolo</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Tenant / Azienda</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Stato</th>
              <th class="px-5 py-3 text-left font-medium text-slate-500">Creato il</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Azioni</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="users.data.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                Nessun utente trovato con i filtri selezionati.
              </td>
            </tr>
            <tr
              v-for="user in users.data"
              :key="user.id"
              class="hover:bg-slate-50 transition-colors group"
              :class="{ 'opacity-60': !user.is_active }"
            >
              <td class="px-5 py-3.5 text-slate-400 tabular-nums">#{{ user.id }}</td>

              <td class="px-5 py-3.5">
                <div class="font-medium text-slate-800">{{ user.name }}</div>
                <div class="text-xs text-slate-400 mt-0.5">{{ user.email }}</div>
              </td>

              <td class="px-5 py-3.5">
                <RoleBadge :role="user.role" />
              </td>

              <td class="px-5 py-3.5">
                <span v-if="user.tenant" class="text-slate-700">{{ user.tenant.name }}</span>
                <span v-else class="text-slate-300 text-xs italic">Globale</span>
              </td>

              <!-- Stato + toggle -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                  <span
                    :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                  >
                    {{ user.is_active ? 'Attivo' : 'Disabilitato' }}
                  </span>
                  <Link
                    v-if="user.role !== 'superadmin'"
                    :href="route('superadmin.users.toggle-active', user.id)"
                    method="patch"
                    as="button"
                    preserve-scroll
                    :class="user.is_active ? 'text-slate-400 hover:text-red-500' : 'text-slate-400 hover:text-emerald-600'"
                    class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded"
                    :title="user.is_active ? 'Disabilita utente' : 'Riabilita utente'"
                  >
                    <svg v-if="user.is_active" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </Link>
                </div>
              </td>

              <td class="px-5 py-3.5 text-slate-400 text-xs tabular-nums">
                {{ formatDate(user.created_at) }}
              </td>

              <!-- Azioni -->
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <!-- Modifica -->
                  <button
                    @click="openEdit(user)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition border border-slate-200"
                    title="Modifica utente"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifica
                  </button>

                  <!-- Impersona -->
                  <Link
                    v-if="user.role !== 'superadmin'"
                    :href="route('superadmin.impersonate.start', user.id)"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700 hover:bg-amber-200 transition border border-amber-200"
                    :title="`Accedi come ${user.name}`"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Accedi come
                  </Link>
                  <span v-else class="text-xs text-slate-300 italic">Superadmin</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between bg-slate-50">
          <span class="text-xs text-slate-500">
            {{ users.from ?? 0 }}–{{ users.to ?? 0 }} di {{ users.total }}
          </span>
          <div class="flex gap-1">
            <Link
              v-for="link in users.links"
              :key="link.label"
              :href="link.url ?? '#'"
              :class="[
                'px-3 py-1 rounded text-xs border transition',
                link.active
                  ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                  : 'text-slate-600 border-slate-300 hover:bg-slate-100',
                !link.url ? 'opacity-40 pointer-events-none' : ''
              ]"
              preserve-state
              v-html="link.label"
            />
          </div>
        </div>
      </div>

    </div>

    <!-- ── Modale Crea / Modifica Utente ─────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
          <!-- Backdrop — nessun handler: la modale si chiude SOLO con i pulsanti -->
          <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" />

          <!-- Panel -->
          <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div v-if="showModal" class="relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl">

              <!-- Header -->
              <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
                <div>
                  <h2 class="text-base font-semibold text-slate-800">
                    {{ isEditMode ? `Modifica Utente — ${editingUser?.name}` : 'Crea Nuovo Utente' }}
                  </h2>
                  <p class="text-xs text-slate-400 mt-0.5">
                    {{ isEditMode
                      ? 'Aggiorna i dati. La password va cambiata solo se necessario.'
                      : "L'utente riceverà le credenziali per accedere al sistema." }}
                  </p>
                </div>
                <button
                  @click="closeModal"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
                  title="Chiudi"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Form -->
              <form @submit.prevent="submitModal" class="px-6 py-5 space-y-4">

                <!-- Nome -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1.5">Nome completo</label>
                  <input
                    v-model="form.name"
                    type="text"
                    autocomplete="off"
                    placeholder="Es. Mario Rossi"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                    :class="{ 'border-red-400 bg-red-50': form.errors.name }"
                  />
                  <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <!-- Email -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1.5">Indirizzo email</label>
                  <input
                    v-model="form.email"
                    type="email"
                    autocomplete="off"
                    placeholder="utente@esempio.it"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                    :class="{ 'border-red-400 bg-red-50': form.errors.email }"
                  />
                  <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                </div>

                <!-- Password -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1.5">
                    {{ isEditMode ? 'Nuova Password' : 'Password' }}
                    <span v-if="!isEditMode" class="text-red-400">*</span>
                    <span v-else class="text-slate-400 font-normal ml-1">(lascia vuoto per non modificare)</span>
                  </label>
                  <input
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="isEditMode ? 'Lascia vuoto per non modificare' : 'Min. 8 caratteri'"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                    :class="{ 'border-red-400 bg-red-50': form.errors.password }"
                  />
                  <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                </div>

                <!-- Ruolo -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1.5">Ruolo</label>
                  <select
                    v-model="form.role"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white"
                    :class="{ 'border-red-400': form.errors.role }"
                  >
                    <option value="user">User (operatore)</option>
                    <option value="tenant-admin">Tenant Admin</option>
                    <option value="superadmin">Superadmin</option>
                  </select>
                  <p v-if="form.errors.role" class="mt-1 text-xs text-red-500">{{ form.errors.role }}</p>
                </div>

                <!-- Tenant -->
                <div>
                  <label class="block text-xs font-medium text-slate-700 mb-1.5">
                    Tenant associato
                    <span v-if="form.role !== 'superadmin'" class="text-red-400">*</span>
                  </label>
                  <select
                    v-model="form.tenant_id"
                    :disabled="form.role === 'superadmin'"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
                    :class="{ 'border-red-400': form.errors.tenant_id }"
                  >
                    <option value="">
                      {{ form.role === 'superadmin' ? '— Nessun tenant (globale) —' : 'Seleziona un tenant…' }}
                    </option>
                    <option v-for="t in tenants" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                  </select>
                  <p v-if="form.errors.tenant_id" class="mt-1 text-xs text-red-500">{{ form.errors.tenant_id }}</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                  <button
                    type="button"
                    @click="closeModal"
                    class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition"
                  >
                    Annulla
                  </button>
                  <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60 transition"
                  >
                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span v-if="form.processing">{{ isEditMode ? 'Salvataggio…' : 'Creazione…' }}</span>
                    <span v-else>{{ isEditMode ? 'Salva modifiche' : 'Crea utente' }}</span>
                  </button>
                </div>

              </form>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </SuperadminLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue'
import RoleBadge from '@/Components/Superadmin/RoleBadge.vue'

interface TenantMin { id: number; name: string }

interface UserRow {
  id: number
  name: string
  email: string
  role: string
  is_active: boolean
  tenant: TenantMin | null
  created_at: string
}

interface PaginationLink { url: string | null; label: string; active: boolean }

interface Paginated<T> {
  data: T[]
  from: number | null
  to: number | null
  total: number
  links: PaginationLink[]
}

const props = defineProps<{
  users: Paginated<UserRow>
  tenants: TenantMin[]
  filters: { search?: string; role?: string; tenant_id?: string }
}>()

// ── Filter state ───────────────────────────────────────────
const search         = ref(props.filters.search ?? '')
const selectedRole   = ref(props.filters.role ?? '')
const selectedTenant = ref(props.filters.tenant_id ?? '')

const hasActiveFilters = computed(() =>
  !!search.value || !!selectedRole.value || !!selectedTenant.value
)

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, (val) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => navigate(), val ? 300 : 0)
})
watch([selectedRole, selectedTenant], () => navigate())

function navigate() {
  router.get(
    route('superadmin.users'),
    {
      search:    search.value || undefined,
      role:      selectedRole.value || undefined,
      tenant_id: selectedTenant.value || undefined,
    },
    { preserveState: true, replace: true }
  )
}

function resetFilters() {
  search.value = ''
  selectedRole.value = ''
  selectedTenant.value = ''
}

// ── Modal state ────────────────────────────────────────────
const showModal   = ref(false)
const editingUser = ref<UserRow | null>(null)
const isEditMode  = computed(() => editingUser.value !== null)

const form = useForm({
  name:      '',
  email:     '',
  password:  '',
  role:      'user' as string,
  tenant_id: '',
})

// Auto-reset tenant when role switches to superadmin
watch(() => form.role, (role) => {
  if (role === 'superadmin') form.tenant_id = ''
})

function openCreate() {
  editingUser.value = null
  form.reset()
  form.clearErrors()
  showModal.value = true
}

function openEdit(user: UserRow) {
  editingUser.value = user
  form.name      = user.name
  form.email     = user.email
  form.password  = ''
  form.role      = user.role
  form.tenant_id = user.tenant ? String(user.tenant.id) : ''
  form.clearErrors()
  showModal.value = true
}

function submitModal() {
  if (isEditMode.value) {
    form.patch(route('superadmin.users.update', editingUser.value!.id), {
      onSuccess: () => {
        showModal.value = false
        editingUser.value = null
        form.reset()
      },
    })
  } else {
    form.post(route('superadmin.users.store'), {
      onSuccess: () => {
        showModal.value = false
        form.reset()
      },
    })
  }
}

function closeModal() {
  showModal.value = false
  editingUser.value = null
  form.reset()
  form.clearErrors()
}

// ── Helpers ────────────────────────────────────────────────
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('it-IT')
}
</script>
