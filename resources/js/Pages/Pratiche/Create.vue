<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Nuova Pratica</h2>
        <Link :href="route('pratiche.index')" class="text-sm text-gray-500 hover:text-gray-700 transition">← Torna alla lista</Link>
      </div>
    </template>

    <form @submit.prevent="submit" class="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Stato iniziale -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Stato iniziale</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button
            v-for="status in tenant.statuses"
            :key="status.id"
            type="button"
            @click="form.current_status_id = status.id"
            :class="[
              'flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition',
              form.current_status_id === status.id
                ? 'border-indigo-500 ring-2 ring-indigo-200'
                : 'border-gray-200 hover:border-gray-300'
            ]"
          >
            <span
              class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="{ backgroundColor: status.color }"
            />
            {{ status.name }}
          </button>
          <p v-if="form.errors.current_status_id" class="col-span-full text-xs text-red-600 mt-1">
            {{ form.errors.current_status_id }}
          </p>
        </div>

        <p class="text-xs text-gray-400 mt-3">
          La data del prossimo avviso verrà impostata automaticamente a
          <strong>oggi + {{ tenant.settings?.default_notice_days ?? 30 }} giorni</strong>.
        </p>
      </div>

      <!-- Campi personalizzati dinamici -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Dati della pratica</h3>

        <div v-if="schema.length === 0" class="text-sm text-gray-400 italic">
          Nessun campo configurato per questo tenant. Contatta il tuo amministratore.
        </div>

        <div class="space-y-5">
          <div v-for="field in schema" :key="field.name">
            <label :for="field.name" class="block text-sm font-medium text-gray-700 mb-1">
              {{ field.label }} <span v-if="field.required" class="text-red-500">*</span>
            </label>

            <!-- Tipo: boolean -->
            <label v-if="field.type === 'boolean'" class="flex items-center gap-2 cursor-pointer">
              <input
                :id="field.name"
                v-model="form.custom_fields[field.name]"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span class="text-sm text-gray-600">Sì</span>
            </label>

            <!-- Tipo: date, number, text -->
            <input
              v-else
              :id="field.name"
              v-model="form.custom_fields[field.name]"
              :type="field.type"
              :required="field.required"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              :placeholder="field.label"
            />
            <p v-if="form.errors[`custom_fields.${field.name}`]" class="text-xs text-red-600 mt-1">
              {{ form.errors[`custom_fields.${field.name}`] }}
            </p>
          </div>
        </div>
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
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

interface FieldSchema { name: string; label: string; type: 'text' | 'date' | 'number' | 'boolean'; required?: boolean }
interface TenantStatus { id: number; name: string; color: string }
interface Tenant {
  id: number
  settings: { default_notice_days: number; custom_fields_schema: FieldSchema[] } | null
  statuses: TenantStatus[]
}

const props = defineProps<{ tenant: Tenant }>()

const schema = computed<FieldSchema[]>(() => props.tenant.settings?.custom_fields_schema ?? [])

// Inizializza il form con i campi vuoti basandosi sullo schema del tenant.
const initialCustomFields: Record<string, string | boolean> = {}
schema.value.forEach(f => {
  initialCustomFields[f.name] = f.type === 'boolean' ? false : ''
})

const form = useForm({
  current_status_id: null as number | null,
  custom_fields:     initialCustomFields,
})

function submit() {
  form.post(route('pratiche.store'))
}
</script>
