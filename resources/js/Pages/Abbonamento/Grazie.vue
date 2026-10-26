<template>
  <Head title="Grazie — SinistroPro" />
  <div class="min-h-screen bg-slate-50 flex items-start sm:items-center justify-center px-4 py-10">
    <div class="w-full max-w-xl bg-white rounded-2xl shadow-sm border border-gray-200 px-6 py-7 sm:px-8">
      <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">SinistroPro</p>
      <h1 class="text-xl font-bold text-gray-900 mt-1">{{ attivo ? 'Abbonamento attivo, grazie!' : 'Pagamento ricevuto, grazie!' }}</h1>
      <p class="text-sm text-gray-600 mt-3">
        {{ attivo
          ? `SinistroPro è attivo per ${tenantName}. Accedi con le credenziali che ti abbiamo mandato.`
          : 'Stiamo completando l\'attivazione: ci vuole qualche secondo. Puoi già accedere con le tue credenziali.' }}
      </p>
      <p class="text-sm text-gray-600 mt-2">La ricevuta di Stripe arriva per email.</p>
      <div class="mt-6">
        <Link :href="route('login')" class="inline-flex items-center bg-indigo-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-indigo-700">Accedi a SinistroPro</Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

defineProps<{ tenantName: string; attivo: boolean }>()
</script>
