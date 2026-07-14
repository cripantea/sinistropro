<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Nuova Pratica</h2>
        <Link :href="route('pratiche.index')" class="text-sm text-gray-500 hover:text-gray-700 transition">← Torna alla lista</Link>
      </div>
    </template>

    <form @submit.prevent="submit" class="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div>
          <label for="cliente_id" class="block text-sm font-medium text-gray-700 mb-1">
            Cliente <span class="text-red-500">*</span>
          </label>
          <select
            id="cliente_id"
            v-model="form.cliente_id"
            required
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          >
            <option :value="null" disabled>Seleziona un cliente</option>
            <option v-for="cliente in clienti" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
          </select>
          <p v-if="form.errors.cliente_id" class="text-xs text-red-600 mt-1">{{ form.errors.cliente_id }}</p>
        </div>

        <div>
          <label for="perito_user_id" class="block text-sm font-medium text-gray-700 mb-1">
            Perito <span class="text-red-500">*</span>
          </label>
          <select
            id="perito_user_id"
            v-model="form.perito_user_id"
            required
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          >
            <option :value="null" disabled>Seleziona un perito</option>
            <option v-for="perito in periti" :key="perito.id" :value="perito.id">{{ perito.name }}</option>
          </select>
          <p v-if="form.errors.perito_user_id" class="text-xs text-red-600 mt-1">{{ form.errors.perito_user_id }}</p>
        </div>

        <p class="text-xs text-gray-400">
          La pratica verrà creata con lo stato iniziale del tenant. Potrai completare gli altri
          dati subito dopo, nella pagina di modifica.
        </p>
      </div>

      <!-- Submit -->
      <div class="flex items-center gap-4">
        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
        >
          <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          Crea Pratica
        </button>
        <Link :href="route('pratiche.index')" class="text-sm text-gray-500 hover:underline">Annulla</Link>
      </div>

    </form>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface ClienteOption { id: number; nome: string }
interface PeritoOption { id: number; name: string }

defineProps<{ clienti: ClienteOption[]; periti: PeritoOption[] }>()

const form = useForm({
  cliente_id: null as number | null,
  perito_user_id: null as number | null,
})

function submit() {
  form.post(route('pratiche.store'))
}
</script>
