<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link
          :href="cliente ? route('clienti.show', cliente.id) : route('clienti.index')"
          class="text-slate-400 hover:text-slate-600"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </Link>
        <h1 class="text-base font-semibold text-slate-800">
          {{ cliente ? `Modifica ${cliente.nome}` : 'Nuovo Cliente' }}
        </h1>
      </div>
    </template>

    <div class="p-6 max-w-2xl">
      <form @submit.prevent="submit" class="space-y-5">

        <!-- Errori globali -->
        <div v-if="form.errors && Object.keys(form.errors).length" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
          </ul>
        </div>

        <!-- Dati base -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati contatto</h2>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Nome <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.nome"
              type="text"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              :class="{ 'border-red-400': form.errors.nome }"
              placeholder="Es. Mario Rossi"
            />
            <p v-if="form.errors.nome" class="text-xs text-red-500 mt-1">{{ form.errors.nome }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Telefono</label>
            <input
              v-model="form.telefono"
              type="tel"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              :class="{ 'border-red-400': form.errors.telefono }"
              placeholder="+39 333 1234567"
            />
            <p v-if="form.errors.telefono" class="text-xs text-red-500 mt-1">{{ form.errors.telefono }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              :class="{ 'border-red-400': form.errors.email }"
              placeholder="mario@esempio.it"
            />
            <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
          </div>
        </div>

        <!-- Campi personalizzati -->
        <div v-if="schema.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dati aggiuntivi</h2>

          <div v-for="field in schema" :key="field.name">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              {{ field.label }}
              <span v-if="field.required" class="text-red-500">*</span>
            </label>

            <!-- text / number -->
            <input
              v-if="field.type === 'text' || field.type === 'number'"
              v-model="form.custom_fields[field.name]"
              :type="field.type"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
            />

            <!-- date -->
            <input
              v-else-if="field.type === 'date'"
              v-model="form.custom_fields[field.name]"
              type="date"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
            />

            <!-- select -->
            <select
              v-else-if="field.type === 'select'"
              v-model="form.custom_fields[field.name]"
              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white"
            >
              <option value="">— Seleziona —</option>
              <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
            </select>

            <!-- boolean -->
            <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2">
              <input
                :id="`cf_${field.name}`"
                v-model="form.custom_fields[field.name]"
                type="checkbox"
                :true-value="'1'"
                :false-value="'0'"
                class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500"
              />
              <label :for="`cf_${field.name}`" class="text-sm text-slate-700">{{ field.label }}</label>
            </div>

          </div>
        </div>

        <!-- Azioni -->
        <div class="flex items-center justify-end gap-3">
          <Link
            :href="cliente ? route('clienti.show', cliente.id) : route('clienti.index')"
            class="text-sm font-medium text-slate-600 hover:underline"
          >
            Annulla
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition"
          >
            <span v-if="form.processing">Salvataggio…</span>
            <span v-else>{{ cliente ? 'Salva modifiche' : 'Crea cliente' }}</span>
          </button>
        </div>

      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface FieldSchema { name: string; label: string; type: string; required?: boolean; options?: string[] }
interface Cliente {
  id: number; nome: string; telefono: string | null; email: string | null
  custom_fields: Record<string, string> | null
}

const props = defineProps<{
  cliente: Cliente | null
  schema: FieldSchema[]
}>()

const initialCustomFields: Record<string, string> = {}
for (const field of props.schema) {
  initialCustomFields[field.name] = props.cliente?.custom_fields?.[field.name] ?? ''
}

const form = useForm({
  nome:          props.cliente?.nome ?? '',
  telefono:      props.cliente?.telefono ?? '',
  email:         props.cliente?.email ?? '',
  custom_fields: initialCustomFields,
})

function submit() {
  if (props.cliente) {
    form.put(route('clienti.update', props.cliente.id))
  } else {
    form.post(route('clienti.store'))
  }
}
</script>
