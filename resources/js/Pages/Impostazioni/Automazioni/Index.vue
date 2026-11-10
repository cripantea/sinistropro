<template>
  <ImpostazioniLayout largo>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-lg font-semibold text-gray-800">Automazioni</h3>
        <p class="text-sm text-gray-500">Messaggi che partono da soli quando succede qualcosa nei sinistri.</p>
      </div>
      <Link :href="route('impostazioni.automazioni.create')" class="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700">
        + Nuova automazione
      </Link>
    </div>

    <div v-if="!opzioni.emailCollegata" class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
      Nessuna casella email collegata: le automazioni via email non possono partire.
      <Link :href="route('impostazioni.email')" class="font-semibold underline">Collega la casella</Link>
    </div>

    <!-- Elenco -->
    <div v-if="automazioni.length" class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
      <div v-for="a in automazioni" :key="a.id" class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 py-3">
        <button
          type="button"
          role="switch"
          :aria-checked="a.is_active"
          :title="a.is_active ? 'Accesa: clicca per spegnere' : 'Spenta: clicca per accendere'"
          class="relative w-10 h-6 shrink-0 rounded-full transition"
          :class="a.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
          @click="toggle(a)"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition" :class="a.is_active ? 'translate-x-4' : ''" />
        </button>

        <Link :href="route('impostazioni.automazioni.edit', a.id)" class="flex-1 min-w-0 group">
          <p class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 truncate">{{ a.name }}</p>
          <p class="text-xs text-gray-500 truncate">
            ⚡ {{ a.innesco }} → {{ canale(a.channel) }} a {{ destinatari(a) }}
          </p>
        </Link>

        <div class="text-right text-xs text-gray-500 shrink-0 w-28">
          <template v-if="a.invii_30gg">{{ a.invii_30gg }} invii in 30 gg</template>
          <template v-else>nessun invio recente</template>
        </div>

        <div class="flex items-center gap-1 shrink-0">
          <Link :href="route('impostazioni.automazioni.edit', a.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 px-2 py-1">Modifica</Link>
          <button type="button" class="text-sm text-gray-400 hover:text-red-600 px-2 py-1" title="Elimina" @click="elimina(a)">✕</button>
        </div>
      </div>
    </div>

    <!-- Modelli pronti -->
    <div>
      <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ automazioni.length ? 'Aggiungi da un modello' : 'Parti da un modello' }}</h4>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <Link
          v-for="m in modelli"
          :key="m.chiave"
          :href="route('impostazioni.automazioni.create', { modello: m.chiave })"
          class="block rounded-xl border border-gray-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition"
        >
          <p class="text-sm font-semibold text-gray-800"><span class="mr-1">{{ m.icona }}</span>{{ m.titolo }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ m.descrizione }}</p>
        </Link>
      </div>
    </div>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'
import { MODELLI, type BozzaAutomazione } from '@/Components/Impostazioni/modelliAutomazione'

interface Riga extends BozzaAutomazione { id: number; innesco: string; invii_30gg: number }
interface Opzioni { emailCollegata: boolean; dateCliente: unknown[]; utenti: { id: number; name: string }[] }

const props = defineProps<{ automazioni: Riga[]; opzioni: Opzioni }>()

const modelli = computed(() => MODELLI.filter((m) => !m.soloClienti || props.opzioni.dateCliente.length))

const canale = (c: string) => ({ email: '✉️ Email', whatsapp: '💬 WhatsApp', both: '✉️+💬 Email e WhatsApp' } as Record<string, string>)[c] ?? c

function destinatari(a: Riga): string {
  const nomi: Record<string, string> = { cliente: 'cliente', perito: 'perito', carrozzeria: 'carrozzeria', gestore: 'gestore' }
  const lista = a.recipients_to.map((r) => r.type === 'user'
    ? (props.opzioni.utenti.find((u) => u.id === r.user_id)?.name ?? 'collega')
    : nomi[r.type] ?? r.type)
  return lista.join(', ') || 'nessuno'
}

function toggle(a: Riga) {
  router.patch(route('impostazioni.automazioni.toggle', a.id), { is_active: !a.is_active }, { preserveScroll: true })
}
function elimina(a: Riga) {
  if (!window.confirm(`Eliminare l'automazione "${a.name}"?`)) return
  router.delete(route('impostazioni.automazioni.destroy', a.id), { preserveScroll: true })
}
</script>
