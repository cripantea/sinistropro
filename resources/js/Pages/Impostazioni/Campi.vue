<template>
  <ImpostazioniLayout>
    <form class="space-y-4" @submit.prevent="salva">
      <Sezione
        v-for="g in gruppi"
        :key="g.chiave"
        :titolo="g.titolo"
        :descrizione="g.descrizione"
      >
        <p v-if="form.errors[g.chiave]" class="mb-3 text-sm text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ form.errors[g.chiave] }}</p>
        <p v-if="!form[g.chiave].length" class="text-sm text-gray-400 italic mb-2">Nessun campo aggiuntivo.</p>

        <div class="space-y-2">
          <div v-for="(c, i) in form[g.chiave]" :key="c._uid" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <div class="grid sm:grid-cols-[1fr_11rem_auto_auto] gap-2 items-center">
              <input v-model="c.label" type="text" class="field-input py-1.5" placeholder="Nome del campo (es. Data visita medica)" />
              <select v-model="c.type" class="field-input py-1.5">
                <option value="text">Testo</option>
                <option value="date">Data</option>
                <option value="number">Numero</option>
                <option value="boolean">Sì / No</option>
                <option value="select">Lista di scelte</option>
              </select>
              <label class="flex items-center gap-1.5 text-xs text-gray-600 whitespace-nowrap">
                <input v-model="c.required" type="checkbox" class="rounded border-gray-300 text-indigo-600" /> Obbligatorio
              </label>
              <div class="flex items-center gap-1 justify-end">
                <button type="button" class="px-1 text-gray-400 hover:text-gray-700 disabled:opacity-20" :disabled="i === 0" @click="sposta(g.chiave, i, -1)">▲</button>
                <button type="button" class="px-1 text-gray-400 hover:text-gray-700 disabled:opacity-20" :disabled="i === form[g.chiave].length - 1" @click="sposta(g.chiave, i, 1)">▼</button>
                <button type="button" class="px-1 text-gray-400 hover:text-red-600" title="Elimina campo" @click="elimina(g.chiave, i)">✕</button>
              </div>
            </div>
            <div v-if="c.type === 'select'" class="mt-2">
              <label class="text-xs font-medium text-gray-600">Scelte (una per riga)</label>
              <textarea
                :value="c.options.join('\n')"
                rows="3"
                class="field-input mt-1 text-sm"
                placeholder="RCA&#10;Kasko&#10;Infortuni"
                @input="c.options = ($event.target as HTMLTextAreaElement).value.split('\n')"
              />
              <p v-if="form.errors[`${g.chiave}.${i}.options`]" class="text-sm text-red-600 mt-1">{{ form.errors[`${g.chiave}.${i}.options`] }}</p>
            </div>
            <p v-if="c.name" class="mt-1 text-[11px] text-gray-400">Nei messaggi: <code>{{ g.variabile(c.name) }}</code></p>
          </div>
        </div>

        <button type="button" class="mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-800" @click="aggiungi(g.chiave)">+ Aggiungi campo</button>

        <template #azioni><BottoneSalva :caricamento="form.processing" :salvato="form.recentlySuccessful" /></template>
      </Sezione>
    </form>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'
import Sezione from '@/Components/Impostazioni/Sezione.vue'
import BottoneSalva from '@/Components/Impostazioni/BottoneSalva.vue'

interface Campo { name: string; label: string; type: string; required: boolean; options: string[]; _uid: number }
type Chiave = 'campi' | 'campi_cliente'

const props = defineProps<{ campi: Omit<Campo, '_uid'>[]; campiCliente: Omit<Campo, '_uid'>[]; conClienti: boolean }>()

let uid = 0
const carica = (l: Omit<Campo, '_uid'>[]) => l.map((c) => ({ ...c, options: c.options ?? [], _uid: ++uid }))
const form = useForm({ campi: carica(props.campi) as Campo[], campi_cliente: carica(props.campiCliente) as Campo[] })

const gruppi = computed(() => [
  {
    chiave: 'campi' as Chiave,
    titolo: 'Campi dei sinistri',
    descrizione: 'Informazioni in più da compilare in ogni sinistro. I campi di tipo Data si possono usare nelle automazioni (es. promemoria il giorno prima).',
    variabile: (n: string) => `{campi_custom.${n}}`,
  },
  ...(props.conClienti ? [{
    chiave: 'campi_cliente' as Chiave,
    titolo: 'Campi dei clienti',
    descrizione: 'Informazioni in più nell\'anagrafica clienti (es. scadenza patente): le date si usano per i promemoria automatici.',
    variabile: (n: string) => `{cliente.${n}}`,
  }] : []),
])

function aggiungi(k: Chiave) {
  form[k].push({ name: '', label: '', type: 'text', required: false, options: [], _uid: ++uid })
}
function elimina(k: Chiave, i: number) {
  const c = form[k][i]
  if (c.name && !window.confirm(`Eliminare il campo "${c.label}"? I valori già inseriti non si vedranno più.`)) return
  form[k].splice(i, 1)
}
function sposta(k: Chiave, i: number, d: number) {
  const [c] = form[k].splice(i, 1)
  form[k].splice(i + d, 0, c)
}

const pulisci = (l: Campo[]) => l.map(({ name, label, type, required, options }) => ({ name: name || null, label, type, required, options: type === 'select' ? options.map((o) => o.trim()).filter(Boolean) : [] }))
const salva = () => form
  .transform((d) => ({ campi: pulisci(d.campi), campi_cliente: pulisci(d.campi_cliente) }))
  .post(route('impostazioni.campi.update'), {
    preserveScroll: true,
    onSuccess: () => { form.campi = carica(props.campi); form.campi_cliente = carica(props.campiCliente) },
  })
</script>
