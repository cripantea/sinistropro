<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Gestione Team</h2>
    </template>

    <!-- Flash -->
    <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
      <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
        {{ flash.success }}
      </div>
    </Transition>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Lista membri ──────────────────────────────── -->
        <div class="lg:col-span-2 space-y-3">
          <p class="text-sm text-gray-500 mb-1">{{ members.length }} membro{{ members.length !== 1 ? 'i' : '' }} nel team</p>

          <div
            v-for="member in members"
            :key="member.id"
            class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center gap-4"
          >
            <!-- Avatar -->
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0"
              :class="{
                'bg-indigo-600': member.role === 'tenant-admin',
                'bg-amber-500':  member.role === 'external',
                'bg-slate-400':  member.role === 'user',
              }"
            >
              {{ initials(member.name) }}
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-gray-800 truncate">{{ member.name }}</span>
                <span
                  class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                  :class="{
                    'bg-indigo-100 text-indigo-700': member.role === 'tenant-admin',
                    'bg-amber-100 text-amber-700':   member.role === 'external',
                    'bg-slate-100 text-slate-600':   member.role === 'user',
                  }"
                >
                  {{ member.role === 'tenant-admin' ? 'Admin' : member.role === 'external' ? 'Perito' : 'Collaboratore' }}
                </span>
                <span v-if="member.id === authUserId" class="text-[11px] text-gray-400">(tu)</span>
              </div>
              <p class="text-xs text-gray-500 truncate mt-0.5">{{ member.email }}</p>
            </div>

            <!-- Status + actions -->
            <div class="flex items-center gap-2 shrink-0">
              <span
                class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full"
                :class="member.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="member.is_active ? 'bg-green-500' : 'bg-red-400'"/>
                {{ member.is_active ? 'Attivo' : 'Disattivo' }}
              </span>

              <!-- Edit button -->
              <button
                @click="openEdit(member)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                title="Modifica"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
              </button>

              <!-- Toggle active button -->
              <button
                v-if="member.id !== authUserId"
                @click="toggleActive(member)"
                class="text-xs text-gray-400 hover:text-gray-700 border border-gray-200 hover:border-gray-300 px-2.5 py-1 rounded-lg transition"
              >
                {{ member.is_active ? 'Disattiva' : 'Riattiva' }}
              </button>
            </div>
          </div>

          <div v-if="members.length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-12 text-center text-gray-400 text-sm">
            Nessun membro nel team.
          </div>
        </div>

        <!-- ── Form nuovo collaboratore ─────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 h-fit">
          <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Aggiungi collaboratore
          </h3>

          <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Nome completo</label>
              <input
                v-model="createForm.name"
                type="text"
                placeholder="Mario Rossi"
                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              />
              <p v-if="createForm.errors.name" class="text-xs text-red-600 mt-1">{{ createForm.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
              <input
                v-model="createForm.email"
                type="email"
                placeholder="mario@studio.it"
                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              />
              <p v-if="createForm.errors.email" class="text-xs text-red-600 mt-1">{{ createForm.errors.email }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
              <input
                v-model="createForm.password"
                type="password"
                placeholder="Minimo 8 caratteri"
                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              />
              <p v-if="createForm.errors.password" class="text-xs text-red-600 mt-1">{{ createForm.errors.password }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Ruolo</label>
              <select
                v-model="createForm.role"
                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
              >
                <option value="user">Collaboratore</option>
                <option value="tenant-admin">Amministratore</option>
                <option value="external">Tecnico Esterno / Perito</option>
              </select>
              <p v-if="createForm.errors.role" class="text-xs text-red-600 mt-1">{{ createForm.errors.role }}</p>
            </div>

            <button
              type="submit"
              :disabled="createForm.processing"
              class="w-full bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 flex items-center justify-center gap-2"
            >
              <svg v-if="createForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Aggiungi al team
            </button>
          </form>
        </div>

      </div>
    </div>

    <!-- ── Modale modifica ──────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="editingMember"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeEdit"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-to-class="opacity-0 scale-95 translate-y-2"
        >
          <div v-if="editingMember" class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

            <!-- Modal header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <div
                  class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                  :class="{
                    'bg-indigo-600': editingMember.role === 'tenant-admin',
                    'bg-amber-500':  editingMember.role === 'external',
                    'bg-slate-400':  editingMember.role === 'user',
                  }"
                >
                  {{ initials(editingMember.name) }}
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-800">Modifica collaboratore</p>
                  <p class="text-xs text-gray-400">{{ editingMember.email }}</p>
                </div>
              </div>
              <button @click="closeEdit" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Modal body -->
            <form @submit.prevent="submitEdit" class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nome completo</label>
                <input
                  v-model="editForm.name"
                  type="text"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                />
                <p v-if="editForm.errors.name" class="text-xs text-red-600 mt-1">{{ editForm.errors.name }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                <input
                  v-model="editForm.email"
                  type="email"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                />
                <p v-if="editForm.errors.email" class="text-xs text-red-600 mt-1">{{ editForm.errors.email }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                  Nuova password
                  <span class="text-gray-400 font-normal">(lascia vuoto per non cambiarla)</span>
                </label>
                <input
                  v-model="editForm.password"
                  type="password"
                  placeholder="Minimo 8 caratteri"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                />
                <p v-if="editForm.errors.password" class="text-xs text-red-600 mt-1">{{ editForm.errors.password }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Ruolo</label>
                <select
                  v-model="editForm.role"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
                >
                  <option value="user">Collaboratore</option>
                  <option value="tenant-admin">Amministratore</option>
                  <option value="external">Tecnico Esterno / Perito</option>
                </select>
                <p v-if="editForm.errors.role" class="text-xs text-red-600 mt-1">{{ editForm.errors.role }}</p>
              </div>

              <div class="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  :disabled="editForm.processing"
                  class="flex-1 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60 flex items-center justify-center gap-2"
                >
                  <svg v-if="editForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Salva modifiche
                </button>
                <button
                  type="button"
                  @click="closeEdit"
                  class="px-4 py-2.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                >
                  Annulla
                </button>
              </div>
            </form>

          </div>
        </Transition>
      </div>
    </Transition>

  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { PageProps } from '@/types'

interface Member {
  id: number
  name: string
  email: string
  role: string
  is_active: boolean
  created_at: string
}

const props      = defineProps<{ members: Member[] }>()
const page       = usePage<PageProps>()
const flash      = computed(() => page.props.flash)
const authUserId = computed(() => page.props.auth.user.id)

// ── Create form ───────────────────────────────────────────────────────────
const createForm = useForm({
  name:     '',
  email:    '',
  password: '',
  role:     'user' as 'user' | 'tenant-admin' | 'external',
})

function submitCreate() {
  createForm.post(route('team.store'), {
    onSuccess: () => createForm.reset(),
  })
}

// ── Edit modal ────────────────────────────────────────────────────────────
const editingMember = ref<Member | null>(null)

const editForm = useForm({
  name:     '',
  email:    '',
  password: '',
  role:     'user' as 'user' | 'tenant-admin' | 'external',
})

function openEdit(member: Member) {
  editingMember.value  = member
  editForm.name        = member.name
  editForm.email       = member.email
  editForm.password    = ''
  editForm.role        = member.role as 'user' | 'tenant-admin' | 'external'
  editForm.clearErrors()
}

function closeEdit() {
  editingMember.value = null
  editForm.reset()
}

function submitEdit() {
  if (!editingMember.value) return
  editForm.patch(route('team.update', editingMember.value.id), {
    onSuccess: () => closeEdit(),
  })
}

// ── Toggle active ─────────────────────────────────────────────────────────
function toggleActive(member: Member) {
  router.patch(route('team.toggle-active', member.id), {}, { preserveScroll: true })
}

// ── Helpers ───────────────────────────────────────────────────────────────
function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
}
</script>
