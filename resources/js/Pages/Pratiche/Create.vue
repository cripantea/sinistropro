<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Nuovo Sinistro</h2>
        <TastoIndietro :href="route('pratiche.index')">Torna ai sinistri</TastoIndietro>
      </div>
    </template>

    <div class="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Prova appena attivata: primo passo guidato -->
      <div v-if="primiPassi === 'primo_sinistro'" class="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Benvenuto in SinistroPro · passo 1 di 2</p>
        <p class="mt-1 text-base font-semibold text-slate-900">Inserisci il tuo primo sinistro</p>
        <p class="mt-1 text-sm text-slate-600">
          Prendi una pratica che stai seguendo adesso: bastano cliente, compagnia e data. In due minuti la vedi nella Board,
          con lo stato e i promemoria delle scadenze. Dopo carichiamo tutti i tuoi clienti dall'Excel.
        </p>
      </div>
      <NuovoSinistroForm :periti="periti" :compagnie="compagnie" :chiedi-veicolo="chiediVeicolo" @annulla="router.visit(route('pratiche.index'))" />
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TastoIndietro from '@/Components/TastoIndietro.vue'
import NuovoSinistroForm from '@/Components/NuovoSinistroForm.vue'

const primiPassi = computed(() => usePage<PageProps>().props.flash?.primi_passi)

defineProps<{ periti: { id: number; nome: string }[]; compagnie: string[]; chiediVeicolo: boolean }>()
</script>
