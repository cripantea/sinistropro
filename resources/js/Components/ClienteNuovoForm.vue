<template>
  <form @submit.prevent="salva" class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">Cognome e nome / Ragione sociale <span class="text-red-500">*</span></label>
      <input ref="primo" v-model="form.nome" type="text" :class="cls('nome')" placeholder="Es. Rossi Mario oppure Rossi S.r.l." />
      <p v-if="errori.nome" class="text-xs text-red-600 mt-0.5">{{ errori.nome }}</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Telefono</label>
        <input v-model="form.telefono" type="tel" :class="cls('telefono')" placeholder="+39 333 1234567" />
        <p v-if="errori.telefono" class="text-xs text-red-600 mt-0.5">{{ errori.telefono }}</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
        <input v-model="form.email" type="email" :class="cls('email')" placeholder="mario@esempio.it" />
        <p v-if="errori.email" class="text-xs text-red-600 mt-0.5">{{ errori.email }}</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Codice fiscale</label>
        <input v-model="form.codice_fiscale" type="text" maxlength="16" :class="[cls('codice_fiscale'), 'uppercase font-mono']" />
        <p v-if="errori.codice_fiscale" class="text-xs text-red-600 mt-0.5">{{ errori.codice_fiscale }}</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Partita IVA <span class="text-gray-400 font-normal">(aziende)</span></label>
        <input v-model="form.partita_iva" type="text" maxlength="20" :class="[cls('partita_iva'), 'font-mono']" />
        <p v-if="errori.partita_iva" class="text-xs text-red-600 mt-0.5">{{ errori.partita_iva }}</p>
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">Indirizzo</label>
      <input v-model="form.indirizzo" type="text" :class="cls('indirizzo')" placeholder="Via Roma, 1" />
    </div>
    <div class="grid grid-cols-[6rem_1fr_4.5rem] gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">CAP</label>
        <input v-model="form.cap" type="text" maxlength="10" :class="cls('cap')" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Città</label>
        <input v-model="form.citta" type="text" :class="cls('citta')" />
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Prov.</label>
        <input v-model="form.provincia" type="text" maxlength="5" :class="[cls('provincia'), 'uppercase']" />
      </div>
    </div>

    <!-- Probabile doppione: il CF o il telefono appartengono già a un cliente -->
    <div v-if="doppione" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 text-sm">
      <p class="flex-1 text-amber-800">
        Esiste già <strong>{{ doppione.nome }}</strong><span v-if="doppione.dettaglio" class="text-amber-700"> ({{ doppione.dettaglio }})</span>.
      </p>
      <button type="button" @click="$emit('usa', doppione)" class="shrink-0 text-xs font-semibold text-white bg-amber-600 px-3 py-1.5 rounded-lg hover:bg-amber-700">Usa questo cliente</button>
    </div>

    <p v-if="errori._" class="text-sm text-red-600">{{ errori._ }}</p>

    <div class="flex items-center gap-3">
      <button type="submit" :disabled="saving || !form.nome.trim()" class="text-sm font-semibold text-white bg-green-600 px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">
        {{ saving ? 'Salvataggio…' : 'Crea cliente e continua' }}
      </button>
      <button type="button" @click="$emit('annulla')" class="text-sm text-gray-500 hover:underline">Annulla</button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import type { RisultatoCliente } from '@/Components/ClienteAutocomplete.vue'

const props = defineProps<{ nomeIniziale?: string }>()
const emit = defineEmits<{ creato: [id: number]; usa: [cliente: RisultatoCliente]; annulla: [] }>()

const form = reactive({
  nome: props.nomeIniziale ?? '', telefono: '', email: '', codice_fiscale: '', partita_iva: '',
  indirizzo: '', cap: '', citta: '', provincia: '',
})
const errori = ref<Record<string, string>>({})
const saving = ref(false)
const doppione = ref<RisultatoCliente | null>(null)
const primo = ref<HTMLInputElement | null>(null)

onMounted(() => primo.value?.focus())

function cls(campo: string) {
  return ['block w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none',
    errori.value[campo] ? 'border-red-400' : 'border-gray-300']
}

// Mentre si scrive CF, P.IVA o telefono si controlla se il cliente esiste già.
let timer: ReturnType<typeof setTimeout> | null = null
watch(() => [form.codice_fiscale, form.partita_iva, form.telefono], () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(cercaDoppione, 400)
})

async function cercaDoppione() {
  const cf = form.codice_fiscale.trim().toUpperCase()
  const piva = form.partita_iva.replace(/\s/g, '')
  const tel = form.telefono.replace(/\D/g, '')
  const chiavi = [cf.length >= 11 ? cf : '', piva.length >= 11 ? piva : '', tel.length >= 8 ? tel.slice(-9) : ''].filter(Boolean)
  for (const q of chiavi) {
    const { data } = await axios.get(route('clienti.cerca'), { params: { q } })
    if (data.risultati.length) {
      doppione.value = data.risultati[0]
      return
    }
  }
  doppione.value = null
}

async function salva() {
  saving.value = true
  errori.value = {}
  const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]))
  try {
    const { data } = await axios.post(route('clienti.store'), payload)
    emit('creato', data.cliente.id)
  } catch (err: any) {
    const errors = err.response?.data?.errors
    errori.value = errors
      ? Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, (v as string[])[0]]))
      : { _: err.response?.data?.message ?? 'Errore durante il salvataggio del cliente.' }
  } finally {
    saving.value = false
  }
}
</script>
