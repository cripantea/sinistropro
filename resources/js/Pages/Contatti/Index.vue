<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Periti e Carrozzerie</h2>
        <button
          @click="openCreate"
          class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          {{ tab === 'perito' ? 'Nuovo perito' : 'Nuova carrozzeria' }}
        </button>
      </div>
    </template>

    <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 mx-4 mt-4 rounded">
      {{ flash.success }}
    </div>

    <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
      <p class="text-sm text-gray-500">
        Anagrafica di periti e carrozzerie da assegnare ai sinistri. Non serve un account: basta nome e un recapito.
      </p>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-gray-200">
        <button
          v-for="t in tabs"
          :key="t.value"
          @click="tab = t.value"
          :class="[
            'px-4 py-2 text-sm font-medium -mb-px border-b-2 transition',
            tab === t.value ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ t.label }} <span class="text-xs text-gray-400">({{ countOf(t.value) }})</span>
        </button>
      </div>

      <div v-for="c in visible" :key="c.id" class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-gray-800 truncate">{{ c.nome }}</span>
            <span v-if="!c.is_active" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-600">Disattivo</span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            <span v-if="c.telefono">📱 {{ c.telefono }}</span>
            <span v-if="c.telefono && c.email"> · </span>
            <span v-if="c.email">✉️ {{ c.email }}</span>
            <span v-if="!c.telefono && !c.email" class="italic text-gray-400">Nessun recapito</span>
          </p>
          <p v-if="c.note" class="text-xs text-gray-400 mt-1 truncate">{{ c.note }}</p>
        </div>
        <button @click="openEdit(c)" class="text-xs text-indigo-600 hover:underline">Modifica</button>
        <button @click="toggleActive(c)" class="text-xs text-gray-400 hover:text-gray-700 border border-gray-200 px-2.5 py-1 rounded-lg transition">
          {{ c.is_active ? 'Disattiva' : 'Riattiva' }}
        </button>
        <button @click="remove(c)" class="text-xs text-red-500 hover:underline">Elimina</button>
      </div>

      <div v-if="visible.length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-12 text-center text-gray-400 text-sm">
        Nessun {{ tab === 'perito' ? 'perito' : 'carrozzeria' }} in anagrafica.
      </div>
    </div>

    <!-- Modale -->
    <Teleport to="body">
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @mousedown.self="closeModal">
        <form @submit.prevent="submit" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <h3 class="text-base font-semibold text-gray-800">
            {{ editing ? 'Modifica' : 'Nuovo' }} {{ form.tipo === 'perito' ? 'perito' : 'carrozzeria' }}
          </h3>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
            <input v-model="form.nome" type="text" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="form.errors.nome" class="text-xs text-red-600 mt-1">{{ form.errors.nome }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Cellulare / telefono</label>
            <input v-model="form.telefono" type="text" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="form.errors.telefono" class="text-xs text-red-600 mt-1">{{ form.errors.telefono }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
            <input v-model="form.email" type="email" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
            <textarea v-model="form.note" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" @click="closeModal" class="text-sm text-gray-500 hover:underline">Annulla</button>
            <button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60">
              Salva
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { PageProps } from '@/types'

interface Contatto {
  id: number
  tipo: 'perito' | 'carrozzeria'
  nome: string
  telefono: string | null
  email: string | null
  note: string | null
  is_active: boolean
}

const props = defineProps<{ contatti: Contatto[] }>()
const flash = computed(() => usePage<PageProps>().props.flash)

const tabs = [
  { value: 'perito' as const, label: 'Periti' },
  { value: 'carrozzeria' as const, label: 'Carrozzerie' },
]
const tab = ref<'perito' | 'carrozzeria'>('perito')

const visible = computed(() => props.contatti.filter(c => c.tipo === tab.value))
const countOf = (tipo: string) => props.contatti.filter(c => c.tipo === tipo).length

const modalOpen = ref(false)
const editing = ref<Contatto | null>(null)

const form = useForm({
  tipo: 'perito' as 'perito' | 'carrozzeria',
  nome: '',
  telefono: '',
  email: '',
  note: '',
})

function openCreate() {
  editing.value = null
  form.reset()
  form.clearErrors()
  form.tipo = tab.value
  modalOpen.value = true
}

function openEdit(c: Contatto) {
  editing.value = c
  form.clearErrors()
  form.tipo = c.tipo
  form.nome = c.nome
  form.telefono = c.telefono ?? ''
  form.email = c.email ?? ''
  form.note = c.note ?? ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editing.value = null
}

function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal }
  if (editing.value) {
    form.put(route('contatti.update', editing.value.id), opts)
  } else {
    form.post(route('contatti.store'), opts)
  }
}

function toggleActive(c: Contatto) {
  router.put(route('contatti.update', c.id), {
    tipo: c.tipo, nome: c.nome, telefono: c.telefono, email: c.email, note: c.note,
    is_active: !c.is_active,
  }, { preserveScroll: true })
}

function remove(c: Contatto) {
  if (!confirm(`Eliminare "${c.nome}"? I sinistri già assegnati perderanno questa assegnazione.`)) return
  router.delete(route('contatti.destroy', c.id), { preserveScroll: true })
}
</script>
