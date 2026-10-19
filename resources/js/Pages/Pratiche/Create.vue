<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 leading-tight">Nuovo Sinistro</h2>
        <TastoIndietro :href="route('pratiche.index')">Torna ai sinistri</TastoIndietro>
      </div>
    </template>

    <div class="py-6 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
      <NuovoSinistroForm :periti="periti" :compagnie="compagnie" :chiedi-veicolo="chiediVeicolo" @annulla="router.visit(route('pratiche.index'))" />
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TastoIndietro from '@/Components/TastoIndietro.vue'
import NuovoSinistroForm from '@/Components/NuovoSinistroForm.vue'

defineProps<{ periti: { id: number; nome: string }[]; compagnie: string[]; chiediVeicolo: boolean }>()
</script>
