<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
        @click.self="$emit('close')"
      >
        <Transition
          enter-active-class="transition duration-200"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition duration-150"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="show" class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
              <p class="text-sm font-semibold text-slate-800">Nuovo cliente</p>
              <button
                type="button"
                @click="$emit('close')"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
              <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-xs text-red-700">
                {{ errorMsg }}
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Nome <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.nome"
                  type="text"
                  required
                  :disabled="submitting"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                />
                <p v-if="errors.nome" class="text-xs text-red-600 mt-1">{{ errors.nome }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Telefono</label>
                <input
                  v-model="form.telefono"
                  type="text"
                  :disabled="submitting"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                />
                <p v-if="errors.telefono" class="text-xs text-red-600 mt-1">{{ errors.telefono }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  :disabled="submitting"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none disabled:bg-slate-50"
                />
                <p v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</p>
              </div>
            </form>

            <!-- Footer -->
            <div class="flex items-center gap-3 px-6 py-4 border-t border-slate-100">
              <button
                type="button"
                :disabled="submitting || !form.nome.trim()"
                @click="submit"
                class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                {{ submitting ? 'Salvataggio...' : 'Crea cliente' }}
              </button>
              <button
                type="button"
                :disabled="submitting"
                @click="$emit('close')"
                class="px-4 py-2.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition disabled:opacity-50"
              >
                Annulla
              </button>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import axios from 'axios'

interface Cliente {
  id: number
  nome: string
  telefono: string | null
  email: string | null
}

const props = defineProps<{ show: boolean }>()
const emit = defineEmits<{ close: []; created: [cliente: Cliente] }>()

const form = reactive({ nome: '', telefono: '', email: '' })
const errors = ref<Record<string, string>>({})
const errorMsg = ref<string | null>(null)
const submitting = ref(false)

watch(() => props.show, (isOpen) => {
  if (isOpen) {
    form.nome = ''
    form.telefono = ''
    form.email = ''
    errors.value = {}
    errorMsg.value = null
    submitting.value = false
  }
})

async function submit() {
  if (!form.nome.trim()) return
  submitting.value = true
  errors.value = {}
  errorMsg.value = null

  try {
    const resp = await axios.post<{ cliente: Cliente }>(route('clienti.store'), {
      nome: form.nome,
      telefono: form.telefono || null,
      email: form.email || null,
    })

    emit('created', resp.data.cliente)
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }

    if (e.response?.status === 422 && e.response.data?.errors) {
      const fieldErrors: Record<string, string> = {}
      for (const [key, msgs] of Object.entries(e.response.data.errors)) {
        fieldErrors[key] = msgs[0]
      }
      errors.value = fieldErrors
    } else {
      errorMsg.value = e.response?.data?.message ?? 'Errore durante la creazione del cliente.'
    }
  } finally {
    submitting.value = false
  }
}
</script>
