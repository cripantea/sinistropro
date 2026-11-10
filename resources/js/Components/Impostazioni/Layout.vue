<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 leading-tight">Impostazioni</h2>
    </template>

    <div class="flex flex-col md:flex-row min-h-[calc(100vh-3.5rem)]">
      <!-- Menu delle sezioni -->
      <nav class="md:w-56 shrink-0 border-b md:border-b-0 md:border-r border-gray-200 bg-white">
        <ul class="flex md:flex-col overflow-x-auto md:overflow-visible p-2 gap-1">
          <li v-for="v in voci" :key="v.href">
            <Link
              :href="v.href"
              class="flex items-center gap-2 whitespace-nowrap px-3 py-2 rounded-lg text-sm transition"
              :class="v.attiva ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            >
              <span class="w-5 text-center">{{ v.icona }}</span>{{ v.label }}
            </Link>
          </li>
        </ul>
      </nav>

      <div class="flex-1 min-w-0">
        <div class="mx-auto px-4 sm:px-6 py-6 space-y-4" :class="largo ? 'max-w-6xl' : 'max-w-4xl'">
          <div v-if="flash?.success" class="bg-green-50 border-l-4 border-green-500 px-4 py-3 text-sm text-green-800 rounded">{{ flash.success }}</div>
          <div v-if="flash?.error" class="bg-red-50 border-l-4 border-red-500 px-4 py-3 text-sm text-red-800 rounded">{{ flash.error }}</div>
          <slot />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import type { PageProps } from '@/types'

defineProps<{ largo?: boolean }>()

const page = usePage<PageProps>()
const flash = computed(() => page.props.flash)

const voci = computed(() => {
  const url = page.url
  const v = (label: string, icona: string, nome: string, prefisso: string) => ({
    label, icona, href: route(nome), attiva: url.startsWith(prefisso),
  })
  return [
    v('Generale', '⚙️', 'impostazioni.generale', '/impostazioni/generale'),
    v('Stati e avvisi', '🔔', 'impostazioni.stati', '/impostazioni/stati'),
    v('Campi', '🧾', 'impostazioni.campi', '/impostazioni/campi'),
    v('Categorie documenti', '📁', 'impostazioni.categorie', '/impostazioni/categorie'),
    v('Automazioni', '⚡', 'impostazioni.automazioni.index', '/impostazioni/automazioni'),
    v('Casella email', '✉️', 'impostazioni.email', '/impostazioni/email'),
    v('WhatsApp', '💬', 'whatsapp.index', '/whatsapp'),
    v('Template WhatsApp', '🧩', 'impostazioni.whatsapp-template.index', '/impostazioni/whatsapp-template'),
    v('Team', '👥', 'team.index', '/team'),
  ]
})
</script>
