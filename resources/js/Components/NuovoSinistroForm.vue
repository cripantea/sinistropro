<template>
  <form @submit.prevent="submit" class="space-y-5">

    <!-- ── 1. Cliente ─────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800"><span class="text-indigo-600">1.</span> {{ !scheda && creaCliente ? 'Nuovo cliente' : 'Cliente' }} <span class="text-red-500">*</span></h3>
        <button v-if="scheda" type="button" @click="cambiaCliente" class="text-xs text-indigo-600 hover:underline">Cambia cliente</button>
      </div>

      <!-- Nuovo cliente, direttamente nella pagina -->
      <template v-if="!scheda && creaCliente">
        <ClienteNuovoForm :nome-iniziale="nomeNuovoCliente" @creato="onClienteCreato" @usa="onSelect" @annulla="creaCliente = false" />
      </template>

      <template v-else-if="!scheda">
        <div class="flex items-center gap-2">
          <div class="flex-1"><ClienteAutocomplete id="cliente" @select="onSelect" @nuovo="nuovoCliente" /></div>
          <button type="button" @click="nuovoCliente()" class="shrink-0 inline-flex items-center gap-1 text-sm font-semibold text-green-700 border border-green-300 bg-green-50 px-3 py-2 rounded-lg hover:bg-green-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuovo cliente
          </button>
        </div>
        <p v-if="loadingScheda" class="text-xs text-gray-400">Caricamento scheda…</p>
      </template>

      <!-- Scheda del cliente, compatta: i dati mancanti si completano solo se servono -->
      <div v-else class="space-y-3">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 shrink-0 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold">{{ initials(scheda.cliente.nome) }}</div>
          <div class="min-w-0 flex-1">
            <p class="text-base font-semibold text-gray-900 truncate">
              {{ scheda.cliente.nome }}
              <span v-if="scheda.cliente.codice_esterno" class="ml-1 text-xs font-normal font-mono text-gray-400">#{{ scheda.cliente.codice_esterno }}</span>
            </p>
            <p class="text-sm text-gray-600 truncate">{{ datiPresenti || 'Nessun recapito' }}</p>
          </div>
        </div>

        <button
          v-if="campiMancanti.length && !completaAperto"
          type="button" @click="completaAperto = true"
          class="text-xs font-medium text-indigo-600 hover:underline"
        >+ Completa dati ({{ campiMancanti.map(f => f.label.toLowerCase()).join(', ') }})</button>

        <div v-if="completaAperto" class="rounded-lg bg-gray-50 border border-gray-200 p-3 space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div v-for="f in campiMancanti" :key="f.key" :class="f.wide ? 'sm:col-span-2' : ''">
              <input
                v-model="dati[f.key]"
                :type="f.type ?? 'text'"
                :placeholder="f.label"
                class="block w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
              />
              <p v-if="erroriDati[f.key]" class="text-xs text-red-600 mt-0.5">{{ erroriDati[f.key] }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button" @click="salvaDati" :disabled="savingDati || !datiModificati"
              class="text-xs font-semibold text-white bg-indigo-600 px-3 py-1.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            >{{ savingDati ? 'Salvataggio…' : 'Salva nella scheda cliente' }}</button>
            <button type="button" @click="completaAperto = false" class="text-xs text-gray-500 hover:underline">Chiudi</button>
          </div>
        </div>
        <p v-if="datiSalvati" class="text-xs text-green-700">Dati del cliente salvati.</p>
      </div>
      <p v-if="form.errors.cliente_id" class="text-xs text-red-600">{{ form.errors.cliente_id }}</p>
    </div>

    <!-- ── 2. Veicolo ─────────────────────────────────────── -->
    <div v-if="scheda && mostraVeicolo" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800">
          <span class="text-indigo-600">2.</span> Veicolo
          <span v-if="chiediVeicolo" class="text-red-500">*</span>
          <span v-else class="text-gray-400 font-normal">(opzionale)</span>
        </h3>
        <button v-if="!nuovoVeicoloAperto" type="button" @click="nuovoVeicoloAperto = true" class="text-xs font-medium text-green-700 hover:underline">+ Aggiungi veicolo</button>
      </div>

      <input
        v-if="scheda.veicoli.length > 6"
        v-model="filtroVeicoli"
        type="text"
        placeholder="Filtra per targa, marca o modello…"
        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
      />

      <div v-if="scheda.veicoli.length" class="space-y-1.5 max-h-80 overflow-y-auto">
        <label
          v-for="v in veicoliFiltrati" :key="v.id"
          :class="['flex items-center gap-3 border rounded-lg px-3 py-2 cursor-pointer transition', form.veicolo_id === v.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50']"
        >
          <input type="radio" :value="v.id" v-model="form.veicolo_id" class="text-indigo-600 focus:ring-indigo-500" />
          <span v-if="v.targa" class="font-mono text-sm font-semibold text-gray-900 w-24 shrink-0">{{ v.targa }}</span>
          <span v-else class="font-mono text-xs text-gray-500 w-24 shrink-0 truncate" :title="v.telaio ?? ''">{{ v.telaio ?? 'senza targa' }}</span>
          <span class="text-sm text-gray-700 truncate">{{ [v.marca, v.modello].filter(Boolean).join(' ') || '—' }}</span>
          <span v-if="v.data_immatricolazione" class="ml-auto text-xs text-gray-400 shrink-0">{{ new Date(v.data_immatricolazione).getFullYear() }}</span>
        </label>
        <p v-if="!veicoliFiltrati.length" class="text-xs text-gray-500">Nessun veicolo corrisponde al filtro.</p>
      </div>
      <p v-else-if="!nuovoVeicoloAperto" class="text-sm text-gray-500">Questo cliente non ha ancora veicoli: aggiungine uno.</p>

      <div v-if="nuovoVeicoloAperto" class="border border-dashed border-gray-300 rounded-lg p-3 space-y-2">
        <div class="grid grid-cols-2 gap-2">
          <input v-model="nuovoVeicolo.targa" placeholder="Targa *" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm uppercase font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
          <input v-model="nuovoVeicolo.telaio" placeholder="Telaio" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm uppercase font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
          <input v-model="nuovoVeicolo.marca" placeholder="Marca" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
          <input v-model="nuovoVeicolo.modello" placeholder="Modello" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
        </div>
        <p v-if="erroreVeicolo" class="text-xs text-red-600">{{ erroreVeicolo }}</p>
        <div class="flex items-center gap-3">
          <button type="button" @click="salvaVeicolo" :disabled="savingVeicolo || !nuovoVeicolo.targa.trim()" class="text-sm font-semibold text-white bg-green-600 px-3 py-1.5 rounded-lg hover:bg-green-700 disabled:opacity-60">
            {{ savingVeicolo ? 'Salvataggio…' : 'Aggiungi veicolo' }}
          </button>
          <button type="button" @click="nuovoVeicoloAperto = false" class="text-sm text-gray-500 hover:underline">Annulla</button>
        </div>
      </div>
      <p v-if="form.errors.veicolo_id" class="text-xs text-red-600">{{ form.errors.veicolo_id }}</p>
    </div>

    <!-- ── 3. Dettagli ────────────────────────────────────── -->
    <div v-if="scheda" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-800"><span class="text-indigo-600">{{ mostraVeicolo ? 3 : 2 }}.</span> Dettagli</h3>

      <div v-if="compagnie.length > 0">
        <label for="compagnia" class="block text-sm font-medium text-gray-700 mb-1">
          Compagnia <span class="text-gray-400 font-normal">(opzionale)</span>
        </label>
        <select
          id="compagnia"
          v-model="form.compagnia"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white"
        >
          <option :value="null">Seleziona compagnia…</option>
          <option v-for="c in compagnie" :key="c" :value="c">{{ c }}</option>
        </select>
        <p v-if="form.errors.compagnia" class="text-xs text-red-600 mt-1">{{ form.errors.compagnia }}</p>
      </div>

      <div>
        <label for="perito_contatto_id" class="block text-sm font-medium text-gray-700 mb-1">
          Perito <span class="text-gray-400 font-normal">(opzionale)</span>
        </label>
        <select
          id="perito_contatto_id"
          v-model="form.perito_contatto_id"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
        >
          <option :value="null">Nessun perito assegnato</option>
          <option v-for="perito in periti" :key="perito.id" :value="perito.id">{{ perito.nome }}</option>
        </select>
        <p v-if="form.errors.perito_contatto_id" class="text-xs text-red-600 mt-1">{{ form.errors.perito_contatto_id }}</p>
      </div>

      <!-- Conducente: per auto aziendali, in leasing o a noleggio il cliente è il proprietario, ma si parla con chi la usa. -->
      <div>
        <button
          v-if="!conducenteAperto"
          type="button"
          @click="conducenteAperto = true"
          class="text-sm font-medium text-indigo-700 hover:underline"
        >+ Guidava un'altra persona <span class="font-normal text-gray-500">(auto aziendale, leasing, noleggio, familiare)</span></button>
        <div v-else class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-medium text-gray-700">Conducente / referente</p>
            <button type="button" @click="chiudiConducente" class="text-xs text-gray-500 hover:underline">Togli</button>
          </div>
          <p class="text-xs text-gray-500">Il cliente resta il proprietario dell'auto; qui segni chi la guidava o chi segue il sinistro.</p>
          <div class="grid sm:grid-cols-2 gap-3">
            <div>
              <label for="conducente_nome" class="block text-xs font-medium text-gray-600 mb-1">Cognome e nome</label>
              <input id="conducente_nome" v-model="form.conducente_nome" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white" />
              <p v-if="form.errors.conducente_nome" class="text-xs text-red-600 mt-1">{{ form.errors.conducente_nome }}</p>
            </div>
            <div>
              <label for="conducente_telefono" class="block text-xs font-medium text-gray-600 mb-1">Telefono</label>
              <input id="conducente_telefono" v-model="form.conducente_telefono" type="tel" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white" />
              <p v-if="form.errors.conducente_telefono" class="text-xs text-red-600 mt-1">{{ form.errors.conducente_telefono }}</p>
            </div>
          </div>
        </div>
      </div>

      <p class="text-xs text-gray-400">
        Il sinistro verrà creato con lo stato iniziale del tenant. Potrai completare gli altri
        dati subito dopo, nella pagina del sinistro.
      </p>
    </div>

    <!-- Submit -->
    <div class="flex items-center gap-4">
      <button
        type="submit"
        :disabled="form.processing || !scheda || (chiediVeicolo && !form.veicolo_id)"
        class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-60"
      >
        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        Crea Sinistro
      </button>
      <button type="button" @click="$emit('annulla')" class="text-sm text-gray-500 hover:underline">Annulla</button>
    </div>

  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import ClienteNuovoForm from '@/Components/ClienteNuovoForm.vue'
import ClienteAutocomplete, { type RisultatoCliente } from '@/Components/ClienteAutocomplete.vue'

interface PeritoOption { id: number; nome: string }
interface Veicolo {
  id: number; targa: string | null; telaio: string | null; marca: string | null; modello: string | null
  descrizione: string | null; data_immatricolazione: string | null; scadenza_revisione: string | null
}
type CampoCliente = 'telefono' | 'email' | 'codice_fiscale' | 'partita_iva' | 'indirizzo' | 'cap' | 'citta' | 'provincia'
interface Scheda {
  cliente: { id: number; nome: string; codice_esterno: string | null } & Record<CampoCliente, string | null>
  veicoli: Veicolo[]
}

const props = defineProps<{
  periti: PeritoOption[]
  compagnie: string[]
  chiediVeicolo: boolean
  /** Pagina su cui tornare dopo la creazione (es. 'kanban'); senza, si apre il sinistro creato. */
  da?: string
}>()
defineEmits<{ annulla: [] }>()

const CAMPI: { key: CampoCliente; label: string; type?: string; wide?: boolean }[] = [
  { key: 'telefono', label: 'Telefono', type: 'tel' },
  { key: 'email', label: 'Email', type: 'email' },
  { key: 'codice_fiscale', label: 'Codice fiscale' },
  { key: 'partita_iva', label: 'Partita IVA' },
  { key: 'indirizzo', label: 'Indirizzo', wide: true },
  { key: 'cap', label: 'CAP' },
  { key: 'citta', label: 'Città' },
]

const form = useForm({
  cliente_id: null as number | null,
  veicolo_id: null as number | null,
  compagnia: null as string | null,
  perito_contatto_id: null as number | null,
  conducente_nome: '',
  conducente_telefono: '',
})

const conducenteAperto = ref(false)
function chiudiConducente() {
  conducenteAperto.value = false
  form.conducente_nome = ''
  form.conducente_telefono = ''
}

const creaCliente = ref(false)
const scheda = ref<Scheda | null>(null)
const loadingScheda = ref(false)

// Dati del cliente: quelli già presenti si mostrano, quelli vuoti si possono compilare.
const originale = ref<Record<string, string | number | null>>({})
const dati = reactive<Record<string, string>>({})
const erroriDati = ref<Record<string, string>>({})
const savingDati = ref(false)
const datiSalvati = ref(false)
const datiModificati = computed(() => CAMPI.some(f => !originale.value[f.key] && (dati[f.key] ?? '').trim() !== ''))
// A una persona fisica (codice fiscale di 16 caratteri) non si chiede la partita IVA.
const campiMancanti = computed(() => CAMPI.filter(f => !originale.value[f.key]
  && !(f.key === 'partita_iva' && String(originale.value.codice_fiscale ?? '').length === 16)))
const completaAperto = ref(false)
const nomeNuovoCliente = ref('')
const datiPresenti = computed(() => {
  const c = originale.value
  const luogo = [c.citta, c.provincia ? `(${c.provincia})` : null].filter(Boolean).join(' ')
  return [c.telefono, c.email, c.codice_fiscale, luogo].filter(Boolean).join(' · ')
})

const filtroVeicoli = ref('')
const nuovoVeicoloAperto = ref(false)
const nuovoVeicolo = reactive({ targa: '', telaio: '', marca: '', modello: '' })
const erroreVeicolo = ref('')
const savingVeicolo = ref(false)

// Malacrida (funzione "veicoli"): il veicolo si chiede sempre. Per gli altri solo se il cliente ne ha.
const mostraVeicolo = computed(() => props.chiediVeicolo || (scheda.value?.veicoli.length ?? 0) > 0)
const veicoliFiltrati = computed(() => {
  const f = filtroVeicoli.value.trim().toLowerCase().replace(/\s+/g, '')
  const list = scheda.value?.veicoli ?? []
  // Il veicolo scelto resta sempre in cima, anche in una flotta di centinaia.
  const sorted = [...list].sort((a, b) => Number(b.id === form.veicolo_id) - Number(a.id === form.veicolo_id))
  if (!f) return sorted
  return sorted.filter(v => [v.targa, v.marca, v.modello, v.telaio].some(x => (x ?? '').toLowerCase().replace(/\s+/g, '').includes(f)))
})

async function caricaScheda(clienteId: number, veicoloId: number | null = null) {
  loadingScheda.value = true
  try {
    const { data } = await axios.get<Scheda>(route('clienti.scheda', clienteId))
    scheda.value = data
    originale.value = { ...data.cliente }
    for (const f of CAMPI) dati[f.key] = ''
    erroriDati.value = {}
    datiSalvati.value = false
    completaAperto.value = false
    form.cliente_id = data.cliente.id
    // Un solo veicolo, o il veicolo trovato cercando la targa: già selezionato.
    form.veicolo_id = veicoloId ?? (data.veicoli.length === 1 ? data.veicoli[0].id : null)
    nuovoVeicoloAperto.value = props.chiediVeicolo && data.veicoli.length === 0
  } finally {
    loadingScheda.value = false
  }
}

function onSelect(r: RisultatoCliente) {
  creaCliente.value = false
  caricaScheda(r.id, r.veicolo?.id ?? null)
}

function nuovoCliente(testo = '') {
  nomeNuovoCliente.value = testo
  creaCliente.value = true
}

function onClienteCreato(id: number) {
  creaCliente.value = false
  caricaScheda(id)
}

function cambiaCliente() {
  scheda.value = null
  form.cliente_id = null
  form.veicolo_id = null
  filtroVeicoli.value = ''
}

async function salvaDati() {
  if (!scheda.value) return
  const payload: Record<string, string> = {}
  for (const f of CAMPI) {
    const v = (dati[f.key] ?? '').trim()
    if (!originale.value[f.key] && v !== '') payload[f.key] = v
  }
  savingDati.value = true
  erroriDati.value = {}
  try {
    const { data } = await axios.patch(route('clienti.dati', scheda.value.cliente.id), payload)
    Object.assign(scheda.value.cliente, data.cliente)
    originale.value = { ...originale.value, ...data.cliente }
    for (const k of Object.keys(payload)) dati[k] = ''
    datiSalvati.value = true
    completaAperto.value = false
  } catch (err: any) {
    const errors = err.response?.data?.errors ?? {}
    erroriDati.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, (v as string[])[0]]))
  } finally {
    savingDati.value = false
  }
}

async function salvaVeicolo() {
  if (!scheda.value) return
  savingVeicolo.value = true
  erroreVeicolo.value = ''
  try {
    const { data } = await axios.post(route('clienti.veicoli.store', scheda.value.cliente.id), nuovoVeicolo)
    scheda.value.veicoli.push(data.veicolo)
    form.veicolo_id = data.veicolo.id
    Object.assign(nuovoVeicolo, { targa: '', telaio: '', marca: '', modello: '' })
    nuovoVeicoloAperto.value = false
  } catch (err: any) {
    const errors = err.response?.data?.errors
    erroreVeicolo.value = errors ? (Object.values(errors)[0] as string[])[0] : 'Errore durante il salvataggio del veicolo.'
  } finally {
    savingVeicolo.value = false
  }
}

function initials(nome: string) {
  return nome.split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase()
}

function submit() {
  form.transform(data => ({ ...data, da: props.da ?? null })).post(route('pratiche.store'), { preserveScroll: true, preserveState: 'errors' })
}
</script>
