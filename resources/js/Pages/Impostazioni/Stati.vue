<template>
  <ImpostazioniLayout>
    <form @submit.prevent="salva">
      <Sezione
        titolo="Stati dei sinistri e avvisi"
        :descrizione="`Ogni sinistro aperto ti viene ricordato dopo un certo numero di giorni. Se lasci vuoto, vale il predefinito (${giorniPredefiniti} giorni). Cambiando stato il conto riparte con i giorni del nuovo stato.`"
      >
        <p v-if="form.errors.stati" class="mb-3 text-sm text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ form.errors.stati }}</p>

        <div class="hidden sm:grid grid-cols-[1.5rem_1fr_6.5rem_5rem_5rem_2rem] gap-3 px-3 pb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
          <span></span><span>Stato</span><span>Avviso ogni</span><span class="text-center">Chiuso</span><span class="text-center">Iniziale</span><span></span>
        </div>

        <div class="space-y-2">
          <div
            v-for="(s, i) in form.stati"
            :key="s._uid"
            class="grid grid-cols-[1.5rem_1fr_2rem] sm:grid-cols-[1.5rem_1fr_6.5rem_5rem_5rem_2rem] items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
          >
            <div class="flex flex-col items-center -my-1">
              <button type="button" class="text-gray-400 hover:text-gray-700 disabled:opacity-20 leading-none" :disabled="i === 0" @click="sposta(i, -1)" title="Sposta su">▲</button>
              <button type="button" class="text-gray-400 hover:text-gray-700 disabled:opacity-20 leading-none" :disabled="i === form.stati.length - 1" @click="sposta(i, 1)" title="Sposta giù">▼</button>
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <input v-model="s.color" type="color" class="w-8 h-8 shrink-0 rounded cursor-pointer border border-gray-300 p-0.5" title="Colore" />
              <input v-model="s.name" type="text" class="field-input py-1.5" placeholder="Nome stato" />
              <span v-if="s.sinistri" class="hidden lg:inline shrink-0 text-xs text-gray-500">{{ s.sinistri }} sinistri</span>
            </div>

            <!-- Mobile: le opzioni vanno sotto -->
            <div class="col-span-3 sm:col-span-1 sm:order-none order-last">
              <div class="flex items-center gap-1.5">
                <input
                  v-model.number="s.notice_days"
                  type="number" min="1" max="365"
                  class="field-input py-1.5 w-16 text-center"
                  :placeholder="String(giorniPredefiniti)"
                  :disabled="s.is_closed"
                />
                <span class="text-xs text-gray-500">gg</span>
              </div>
            </div>
            <label class="hidden sm:flex justify-center cursor-pointer" title="I sinistri in questo stato sono conclusi: niente avvisi">
              <input v-model="s.is_closed" type="checkbox" class="rounded border-gray-300 text-red-500 focus:ring-red-400" />
            </label>
            <label class="hidden sm:flex justify-center cursor-pointer" title="Stato dei sinistri appena aperti">
              <input :checked="s.is_initial" type="radio" name="iniziale" class="border-gray-300 text-indigo-600" @change="iniziale(i)" />
            </label>

            <button
              type="button"
              class="text-gray-400 hover:text-red-600 disabled:opacity-30 disabled:hover:text-gray-400"
              :disabled="s.sinistri > 0"
              :title="s.sinistri > 0 ? `Ha ${s.sinistri} sinistri: spostali prima di eliminarlo` : 'Elimina stato'"
              @click="elimina(i)"
            >✕</button>

            <!-- Mobile: chiuso/iniziale -->
            <div class="col-span-3 flex sm:hidden gap-4 text-xs text-gray-600 order-last">
              <label class="flex items-center gap-1.5"><input v-model="s.is_closed" type="checkbox" class="rounded border-gray-300" /> Chiuso</label>
              <label class="flex items-center gap-1.5"><input :checked="s.is_initial" type="radio" name="iniziale-m" @change="iniziale(i)" /> Iniziale</label>
            </div>

          </div>
        </div>

        <button type="button" class="mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-800" @click="aggiungi">+ Aggiungi stato</button>

        <template #azioni><BottoneSalva :caricamento="form.processing" :salvato="form.recentlySuccessful" /></template>
      </Sezione>
    </form>
  </ImpostazioniLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import ImpostazioniLayout from '@/Components/Impostazioni/Layout.vue'
import Sezione from '@/Components/Impostazioni/Sezione.vue'
import BottoneSalva from '@/Components/Impostazioni/BottoneSalva.vue'

interface Stato {
  id: number | null; name: string; color: string; is_closed: boolean; is_initial: boolean
  notice_days: number | null; sinistri: number; automazioni: number; _uid: number
}

const props = defineProps<{ stati: Omit<Stato, '_uid'>[]; giorniPredefiniti: number }>()

let uid = 0
const form = useForm({ stati: props.stati.map((s) => ({ ...s, _uid: ++uid })) as Stato[] })

function aggiungi() {
  form.stati.push({ id: null, name: '', color: '#6366F1', is_closed: false, is_initial: false, notice_days: null, sinistri: 0, automazioni: 0, _uid: ++uid })
}
function elimina(i: number) {
  const s = form.stati[i]
  if (s.automazioni && !window.confirm(`"${s.name}" ha ${s.automazioni} automazioni: verranno spente. Eliminare lo stato?`)) return
  form.stati.splice(i, 1)
}
function sposta(i: number, d: number) {
  const [s] = form.stati.splice(i, 1)
  form.stati.splice(i + d, 0, s)
}
function iniziale(i: number) {
  form.stati.forEach((s, j) => { s.is_initial = j === i })
}
const salva = () => form
  .transform((d) => ({ stati: d.stati.map(({ id, name, color, is_closed, is_initial, notice_days }) => ({ id, name, color, is_closed, is_initial, notice_days: notice_days || null })) }))
  // Dopo il salvataggio si ricarica dagli stati salvati (gli stati nuovi ricevono il loro id).
  .post(route('impostazioni.stati.update'), { preserveScroll: true, onSuccess: () => { form.stati = props.stati.map((s) => ({ ...s, _uid: ++uid })) } })
</script>
